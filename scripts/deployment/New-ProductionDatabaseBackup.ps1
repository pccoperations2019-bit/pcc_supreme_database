# Run in elevated RDP PowerShell. Exports only; never imports, migrates or restarts.
# Coordinate a period without schema/DDL changes while this command is running.
[CmdletBinding()]
param([switch]$NoSchemaChangesConfirmed)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
if (-not $NoSchemaChangesConfirmed) { throw 'Confirm no schema changes during this backup, then pass -NoSchemaChangesConfirmed.' }
$principal = [Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Run as administrator on the production server.' }
if (-not (Get-NetIPAddress -IPAddress '192.168.146.11' -ErrorAction SilentlyContinue)) { throw 'This is not the expected server.' }
$php = 'C:\laragon\bin\php\php-8.4.27-nts-Win32-vs17-x64\php.exe'
$dump = 'C:\laragon\bin\mysql\mysql-8.0.40-winx64\bin\mysqldump.exe'
$root = 'C:\ProgramData\SUPREME-Backups'
. 'C:\ProgramData\SUPREME-Deploy\controller\Release.Common.ps1'
foreach ($path in @('C:\ProgramData', $php, $dump, $PSScriptRoot)) { Assert-NoReparsePoint $path }
$phpVersion = & $php -r 'echo PHP_VERSION;'
if ($LASTEXITCODE -ne 0 -or $phpVersion -cne '8.4.27') { throw 'Unexpected PHP runtime.' }
$dumpVersion = & $dump --no-defaults --version
if ($LASTEXITCODE -ne 0 -or $dumpVersion -notmatch 'Ver 8\.0\.40 for Win64') { throw 'Unexpected mysqldump runtime.' }
if ((Get-PSDrive -Name C).Free -lt 5GB) { throw 'Insufficient free space; inspect before creating a backup.' }

function Assert-PrivateBackupDirectory([string]$Path) {
    Assert-NoReparsePoint $Path
    $acl = Get-Acl -LiteralPath $Path
    $owner = $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value
    if ($owner -notin @('S-1-5-18', 'S-1-5-32-544') -or -not $acl.AreAccessRulesProtected) { throw 'Backup directory ownership/inheritance is not protected.' }
    $rules = @($acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier]))
    if ($rules.Count -ne 2) { throw 'Unexpected backup directory permissions.' }
    foreach ($sid in @('S-1-5-18', 'S-1-5-32-544')) {
        $matches = @($rules | Where-Object {
            $_.IdentityReference.Value -eq $sid -and $_.AccessControlType -eq 'Allow' -and
            $_.FileSystemRights -eq 'FullControl' -and $_.InheritanceFlags -eq 'ContainerInherit,ObjectInherit' -and $_.PropagationFlags -eq 'None'
        })
        if ($matches.Count -ne 1) { throw 'Expected SYSTEM/Administrators-only permissions.' }
    }
}

$privateAcl = [Security.AccessControl.DirectorySecurity]::new()
$privateAcl.SetAccessRuleProtection($true, $false)
$privateAcl.SetOwner([Security.Principal.SecurityIdentifier]::new('S-1-5-32-544'))
foreach ($sid in @('S-1-5-18', 'S-1-5-32-544')) {
    $privateAcl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        [Security.Principal.SecurityIdentifier]::new($sid), 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow'))
}
if (-not (Test-Path -LiteralPath $root)) {
    # Windows PowerShell 5.1/.NET Framework creates the directory with its ACL.
    [IO.Directory]::CreateDirectory($root, $privateAcl) | Out-Null
}
Assert-PrivateBackupDirectory $root
$run = Join-Path $root ('database-' + [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ') + '-' + [guid]::NewGuid().ToString('N'))
if (Test-Path -LiteralPath $run) { throw 'Backup directory unexpectedly exists.' }
[IO.Directory]::CreateDirectory($run, $privateAcl) | Out-Null
Assert-PrivateBackupDirectory $run
foreach ($name in @('ProductionDatabase.Common.php', 'Export-ProductionDatabase.php')) {
    $source = Join-Path $PSScriptRoot $name
    Assert-NoReparsePoint $source
    $expected = Get-ReleaseHash $source
    $target = Join-Path $run $name
    Copy-Item -LiteralPath $source -Destination $target
    if ((Get-ReleaseHash $target) -cne $expected) { throw 'Copied backup helper hash mismatch.' }
    & $php -l $target
    if ($LASTEXITCODE -ne 0) { throw 'Backup helper syntax validation failed.' }
}
Write-Output "Backup directory: $run"
Write-Output 'Exporting pcc; wait for the completion report. Keep schema changes paused.'
try {
    & $php (Join-Path $run 'Export-ProductionDatabase.php')
    if ($LASTEXITCODE -ne 0) { throw 'Database export failed. Keep partial files protected; inspect locally before retrying.' }
} finally {
    # Exact path only; also covers interrupted PHP/error paths. Never recurse.
    $temporaryCredentials = Join-Path $run 'mysql-client.cnf'
    if (Test-Path -LiteralPath $temporaryCredentials) { Remove-Item -LiteralPath $temporaryCredentials -Force }
}
Assert-PrivateBackupDirectory $run
$report = Get-Content -LiteralPath (Join-Path $run 'backup-report.json') -Raw | ConvertFrom-Json
$dumpPath = Join-Path $run 'pcc.sql'
if ($report.status -cne 'export-completed-restore-not-tested' -or (Get-ReleaseHash $dumpPath) -cne $report.sha256) { throw 'Backup report/hash verification failed.' }
Write-Output 'Database export/hash check completed. An isolated restore test and application backup are still required. Activation remains disabled.'
