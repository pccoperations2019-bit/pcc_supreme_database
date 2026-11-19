# Run once in elevated PowerShell on 192.168.146.11, from the reviewed setup bundle.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$principal = [Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Run as administrator on the production server.' }
$livePath = 'C:\laragon\www\supreme-cbed-poc'
$phpPath = 'C:\laragon\bin\php\php-8.4.27-nts-Win32-vs17-x64\php.exe'
$composerPhar = 'C:\ProgramData\ComposerSetup\bin\composer.phar'
$root = 'C:\ProgramData\SUPREME-Deploy'
if (-not (Get-NetIPAddress -IPAddress '192.168.146.11' -ErrorAction SilentlyContinue)) { throw 'This is not the expected server.' }
foreach ($path in @($livePath, $phpPath, $composerPhar)) {
    if (-not (Test-Path -LiteralPath $path)) { throw "Required path missing: $path" }
}
if (Test-Path -LiteralPath $root) { throw 'Deployment directory already exists. Inspect before updating.' }
$deployUser = Get-LocalUser -Name supreme_deploy -ErrorAction Stop
$deploySid = $deployUser.SID
$adminGroup = Get-LocalGroup -SID 'S-1-5-32-544'
if (Get-LocalGroupMember -Group $adminGroup | Where-Object { $_.SID -eq $deploySid }) { throw 'Deployment account must not be an administrator.' }
$controllerFiles = @('Release.Common.ps1', 'Stage-Release.ps1', 'Get-DeploymentReadiness.ps1')
foreach ($file in $controllerFiles) {
    if (-not (Test-Path -LiteralPath (Join-Path $PSScriptRoot $file))) { throw "Setup bundle missing: $file" }
}

# Protect the controller before copying files. The deployment user may stage
# payloads but cannot replace the controller or its server configuration.
New-Item -ItemType Directory -Path $root | Out-Null
$acl = [Security.AccessControl.DirectorySecurity]::new()
$acl.SetAccessRuleProtection($true, $false)
$acl.SetOwner([Security.Principal.SecurityIdentifier]::new('S-1-5-32-544'))
foreach ($sid in @('S-1-5-18', 'S-1-5-32-544')) {
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        [Security.Principal.SecurityIdentifier]::new($sid), 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow'))
}
$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
    $deploySid, 'ReadAndExecute', 'ContainerInherit,ObjectInherit', 'None', 'Allow'))
Set-Acl -LiteralPath $root -AclObject $acl
foreach ($folder in @('controller', 'incoming', 'staging')) {
    New-Item -ItemType Directory -Path (Join-Path $root $folder) | Out-Null
}
foreach ($folder in @('incoming', 'staging')) {
    $path = Join-Path $root $folder
    $folderAcl = Get-Acl -LiteralPath $path
    $folderAcl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
        $deploySid, 'Modify', 'ContainerInherit,ObjectInherit', 'None', 'Allow'))
    Set-Acl -LiteralPath $path -AclObject $folderAcl
}
foreach ($file in $controllerFiles) {
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot $file) -Destination (Join-Path "$root\controller" $file)
}
[ordered]@{
    schemaVersion = 1
    livePath = $livePath
    stagingRoot = "$root\staging"
    phpPath = $phpPath
    composerPhar = $composerPhar
} | ConvertTo-Json | Set-Content -LiteralPath "$root\controller\server.json" -Encoding UTF8
& "$root\controller\Get-DeploymentReadiness.ps1"
Write-Output 'Staging setup complete. Live files, Git remote, services and database were not changed.'
