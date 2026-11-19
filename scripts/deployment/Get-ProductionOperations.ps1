# Read-only administrator inventory. Does not execute application commands,
# inspect environment values, query the database, or restart any process.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$principal = [Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Run as administrator on the production server.' }
if (-not (Get-NetIPAddress -IPAddress '192.168.146.11' -ErrorAction SilentlyContinue)) { throw 'This is not the expected server.' }

function Get-OperationKinds([string]$Text) {
    if ($Text -match 'queue:(work|listen)|queue-listen\.bat') { 'queue' }
    if ($Text -match 'schedule:(run|work)|scheduler\.bat') { 'scheduler' }
    if ($Text -match 'supreme-cbed-poc') { 'application-path-reference' }
    if ($Text -match 'laragon') { 'laragon-reference' }
}

# Command lines are used only to classify relevant processes; never printed.
$allProcesses = @(Get-CimInstance Win32_Process)
$processes = @($allProcesses | Where-Object {
    $_.Name -match '^(php(-cgi)?|nginx|mysqld|httpd|laragon)\.exe$' -or
    ($_.Name -match '^(cmd|powershell|pwsh)\.exe$' -and
        $_.CommandLine -match 'queue-listen\.bat|scheduler\.bat|artisan\s+(queue|schedule):')
})
# Include up to six ancestor levels so service wrappers (for example NSSM)
# are mapped to their service names instead of guessing restart commands.
$processById = @{}
foreach ($process in $allProcesses) { $processById[[string]$process.ProcessId] = $process }
$relatedIds = [Collections.Generic.HashSet[uint32]]::new()
foreach ($process in $processes) {
    $null = $relatedIds.Add([uint32]$process.ProcessId)
    $child = $process
    for ($depth = 0; $depth -lt 6; $depth++) {
        $parent = $processById[[string]$child.ParentProcessId]
        if ($null -eq $parent) { break }
        # A reused PID may now refer to a newer, unrelated process.
        if ($null -eq $parent.CreationDate -or $null -eq $child.CreationDate -or
            $parent.CreationDate -gt $child.CreationDate) { break }
        if (-not $relatedIds.Add([uint32]$parent.ProcessId)) { break }
        $child = $parent
    }
}
$processes = @($allProcesses | Where-Object { $relatedIds.Contains([uint32]$_.ProcessId) })
$processRows = @(foreach ($process in $processes) {
    try {
        $owner = Invoke-CimMethod -InputObject $process -MethodName GetOwner
        $ownerText = if ($owner.ReturnValue -eq 0) { "$($owner.Domain)\$($owner.User)" } else { "Unavailable (code $($owner.ReturnValue))" }
    } catch {
        # A short-lived PHP process may exit after enumeration. Preserve the
        # other inventory results and report the failed field explicitly.
        # Exception messages are not printed because they may contain arguments.
        $ownerText = "Unavailable (owner query failed: $($_.Exception.GetType().Name))"
    }
    [ordered]@{
        name = $process.Name
        pid = $process.ProcessId
        parentPid = $process.ParentProcessId
        executable = $process.ExecutablePath
        sessionId = $process.SessionId
        owner = $ownerText
        kinds = @(Get-OperationKinds $process.CommandLine)
    }
})
$processIds = @($processes | ForEach-Object { $_.ProcessId })
$serviceRows = @(Get-CimInstance Win32_Service | Where-Object {
    $_.Name -match 'nginx|mysql|maria|apache|laragon|supreme' -or
    ($_.ProcessId -ne 0 -and $_.ProcessId -in $processIds)
} | Select-Object Name, State, StartMode, StartName, ProcessId)
$taskRows = @(foreach ($task in Get-ScheduledTask) {
    $actionText = ($task.Actions | ForEach-Object {
        foreach ($property in @('Execute', 'Arguments', 'WorkingDirectory')) {
            if ($_.PSObject.Properties.Name -contains $property) { [string]$_.$property }
        }
    }) -join ' '
    if ("$($task.TaskName) $actionText" -match 'supreme|laragon|queue-listen\.bat|scheduler\.bat|artisan\s+(queue|schedule):') {
        [ordered]@{
            name = $task.TaskName
            taskPath = $task.TaskPath
            state = [string]$task.State
            user = $task.Principal.UserId
            logonType = [string]$task.Principal.LogonType
            kinds = @(Get-OperationKinds $actionText)
        }
    }
})
# Bounded, non-recursive metadata lookup in conventional backup locations.
# Presence alone does not verify backup contents, consistency or recoverability.
$candidateRows = @(foreach ($directory in @('C:\laragon\www', 'C:\Backup', 'C:\Backups', 'D:\Backup', 'D:\Backups')) {
    if (Test-Path -LiteralPath $directory -PathType Container) {
        Get-ChildItem -LiteralPath $directory -File | Where-Object {
            $_.Name -match '(supreme|cbed|backup)' -and $_.Extension -match '^\.(zip|7z|bak|sql|gz)$'
        } | Select-Object -First 20 FullName, Length, LastWriteTimeUtc
    }
})
[ordered]@{
    capturedAtUtc = [DateTime]::UtcNow.ToString('o')
    identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    processes = $processRows
    services = $serviceRows
    scheduledTasks = $taskRows
    disks = @(Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3' | Select-Object DeviceID, Size, FreeSpace)
    backupCandidates = $candidateRows
    backupVerified = $false
    activationAllowed = $false
} | ConvertTo-Json -Depth 6
