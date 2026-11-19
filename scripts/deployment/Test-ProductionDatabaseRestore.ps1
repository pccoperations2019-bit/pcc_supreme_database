# Administrator-only rehearsal against a NEW local MySQL instance, never port 3306.
[CmdletBinding()]
param([switch]$RunIsolatedRestore)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
if (-not $RunIsolatedRestore) { throw 'Review the isolated restore procedure, then pass -RunIsolatedRestore to execute it.' }
if ($PSVersionTable.PSVersion.Major -ne 5) { throw 'Use Windows PowerShell 5.1 for protected directory creation.' }
$principal = [Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Run in elevated RDP PowerShell on the SUPREME server.' }
if (-not (Get-NetIPAddress -IPAddress '192.168.146.11' -ErrorAction SilentlyContinue)) { throw 'Unexpected server.' }
$php = 'C:\laragon\bin\php\php-8.4.27-nts-Win32-vs17-x64\php.exe'
$root = 'C:\ProgramData\SUPREME-Backups'
$backup = "$root\database-20261119T063634Z-d27d18a1102c4a978e5d3ae5158d9eff\pcc.sql"
$mysqlBin = 'C:\laragon\bin\mysql\mysql-8.0.40-winx64\bin'
. 'C:\ProgramData\SUPREME-Deploy\controller\Release.Common.ps1'
foreach ($path in @($php, $root, $backup, $PSScriptRoot)) { Assert-NoReparsePoint $path }
foreach ($program in @('mysqld.exe', 'mysql.exe')) {
    $executable = Join-Path $mysqlBin $program
    Assert-NoReparsePoint $executable
    $version = & $executable --no-defaults --version
    if ($LASTEXITCODE -ne 0 -or $version -notmatch 'Ver 8\.0\.40 for Win64') { throw 'Unexpected MySQL binary version.' }
}
if ((Get-ReleaseHash $backup) -cne '94466dbf798dfb9f405a81254b33e8ba43f0482c6b36599beba9dc297bd20126') { throw 'Backup hash does not match the completed export.' }
if ((Get-PSDrive -Name C).Free -lt 5GB) { throw 'At least 5 GB free disk is required for this rehearsal.' }
# Win32_OperatingSystem reports this value in KiB.
if ((Get-CimInstance Win32_OperatingSystem).FreePhysicalMemory -lt (2 * 1024 * 1024)) { throw 'At least 2 GB available RAM is required; defer the rehearsal.' }
$listeners = [Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
if ($listeners | Where-Object Port -eq 13306) { throw 'Test port 13306 is already occupied. No server was started.' }

# Refuse unexpected existing root ACLs. Do not widen deployment-account access.
$acl = Get-Acl -LiteralPath $root
if (-not $acl.AreAccessRulesProtected -or $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value -notin @('S-1-5-18', 'S-1-5-32-544')) { throw 'Backup root owner/inheritance is not protected.' }
$rules = @($acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier]))
if ($rules.Count -ne 2) { throw 'Unexpected backup root access rules.' }
foreach ($sid in @('S-1-5-18', 'S-1-5-32-544')) {
    $matchingRules = @($rules | Where-Object {
        $_.IdentityReference.Value -eq $sid -and $_.AccessControlType -eq 'Allow' -and $_.FileSystemRights -eq 'FullControl' -and
        $_.InheritanceFlags -eq 'ContainerInherit,ObjectInherit' -and $_.PropagationFlags -eq 'None'
    })
    if ($matchingRules.Count -ne 1) { throw 'Expected SYSTEM/Administrators-only access.' }
}
$run = Join-Path $root ('restore-test-' + [guid]::NewGuid().ToString('N'))
if (Test-Path -LiteralPath $run) { throw 'Restore test folder unexpectedly exists.' }
[IO.Directory]::CreateDirectory($run, $acl) | Out-Null
Assert-NoReparsePoint $run
foreach ($name in @('RestoreDatabase.Common.php', 'Run-IsolatedDatabaseRestore.php')) {
    $source = Join-Path $PSScriptRoot $name
    Assert-NoReparsePoint $source
    $hash = Get-ReleaseHash $source
    $target = Join-Path $run $name
    Copy-Item -LiteralPath $source -Destination $target
    if ((Get-ReleaseHash $target) -cne $hash) { throw 'Restore helper copy hash mismatch.' }
    & $php -l $target
    if ($LASTEXITCODE) { throw 'Restore helper syntax check failed.' }
}
Write-Output "Protected restore-test directory: $run"
Write-Output 'Starting temporary MySQL on 127.0.0.1:13306; initialization/import add disk and CPU load.'
Write-Output 'Keep this session open. No production service, firewall, Git remote or live database is changed.'
& $php (Join-Path $run 'Run-IsolatedDatabaseRestore.php')
if ($LASTEXITCODE) { throw "Restore rehearsal did not complete. Inspect the safe report in $run; do not post SQL or raw logs." }
Write-Output 'Isolated database restore and shutdown completed. Full application recovery and deployment activation remain pending.'
