# Copy the existing, verified Node installation into the protected workspace.
# No download, machine PATH change or application restart is performed.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$principal = [Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Run as administrator on the production server.' }
if (-not (Get-NetIPAddress -IPAddress '192.168.146.11' -ErrorAction SilentlyContinue)) { throw 'This is not the expected server.' }
$root = 'C:\ProgramData\SUPREME-Deploy'
$source = 'C:\Users\Administrator\AppData\Local\nvm\v22.23.0'
$target = "$root\runtime\node-v22.23.0"
$configPath = "$root\controller\server.json"
. "$root\controller\Release.Common.ps1"
Assert-NoReparsePoint $root
Assert-NoReparsePoint $source
if (Test-Path -LiteralPath $target) { throw 'Pinned Node directory already exists. Inspect before updating.' }
$config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
if ($config.schemaVersion -ne 1) { throw 'Unknown server configuration version.' }
$readinessScript = Join-Path $PSScriptRoot 'Get-DeploymentReadiness.ps1'
if (-not (Test-Path -LiteralPath $readinessScript)) { throw 'Updated readiness script is missing from this setup bundle.' }
foreach ($file in @('node.exe', 'npm.cmd', 'npx.cmd', 'node_modules/npm/bin/npm-cli.js')) {
    Assert-NoReparsePoint (Join-Path $source $file)
}
$sourceVersion = & "$source\node.exe" --version
if ($LASTEXITCODE -ne 0 -or $sourceVersion -cne 'v22.23.0') { throw 'Unexpected source Node version.' }

# Collect only npm's shipped module files; do not follow symlinks or copy a
# user profile, npm cache, global modules, credentials or user .npmrc files.
$moduleRoot = "$source\node_modules\npm"
$pending = [Collections.Generic.Queue[string]]::new()
$pending.Enqueue($moduleRoot)
$moduleFiles = [Collections.Generic.List[string]]::new()
while ($pending.Count -gt 0) {
    $folder = $pending.Dequeue()
    foreach ($item in Get-ChildItem -LiteralPath $folder -Force) {
        if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Unexpected link inside the npm installation.' }
        if ($item.PSIsContainer) { $pending.Enqueue($item.FullName) } else { $moduleFiles.Add($item.FullName) }
    }
}
if (Test-Path -LiteralPath "$root\runtime") { Assert-NoReparsePoint "$root\runtime" }
New-Item -ItemType Directory -Path $target -Force | Out-Null
foreach ($file in @('node.exe', 'npm.cmd', 'npx.cmd')) {
    Copy-Item -LiteralPath (Join-Path $source $file) -Destination (Join-Path $target $file)
}
foreach ($file in $moduleFiles) {
    $relative = $file.Substring($source.Length + 1)
    $destination = Join-Path $target $relative
    New-Item -ItemType Directory -Path (Split-Path -Parent $destination) -Force | Out-Null
    Copy-Item -LiteralPath $file -Destination $destination
}
if ((Get-ReleaseHash "$source\node.exe") -cne (Get-ReleaseHash "$target\node.exe")) { throw 'Copied Node executable hash mismatch.' }
$version = & "$target\node.exe" --version
if ($LASTEXITCODE -ne 0 -or $version -cne $sourceVersion) { throw 'Copied Node runtime validation failed.' }
& "$target\npm.cmd" --version
if ($LASTEXITCODE -ne 0) { throw 'Copied npm runtime validation failed.' }
$backupPath = "$configPath.before-node-$([guid]::NewGuid().ToString('N'))"
Copy-Item -LiteralPath $configPath -Destination $backupPath
$config | Add-Member -NotePropertyName nodePath -NotePropertyValue "$target\node.exe" -Force
$config | Add-Member -NotePropertyName npmPath -NotePropertyValue "$target\npm.cmd" -Force
$config | ConvertTo-Json | Set-Content -LiteralPath $configPath -Encoding UTF8
Copy-Item -LiteralPath $readinessScript -Destination "$root\controller\Get-DeploymentReadiness.ps1" -Force
Write-Output "Server configuration backup: $backupPath"
& "$root\controller\Get-DeploymentReadiness.ps1"
Write-Output 'Pinned deployment Node runtime installed. Machine PATH, live files and application processes were not changed.'
