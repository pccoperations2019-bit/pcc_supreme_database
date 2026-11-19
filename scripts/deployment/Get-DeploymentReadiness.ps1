[CmdletBinding()]
param([string]$ConfigPath = 'C:\ProgramData\SUPREME-Deploy\controller\server.json')
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
Set-StrictMode -Version Latest
$config = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json
if ($config.schemaVersion -ne 1) { throw 'Unknown server configuration version.' }
foreach ($path in @($config.livePath, $config.stagingRoot, $config.phpPath, $config.composerPhar)) {
    if (-not (Test-Path -LiteralPath $path)) { throw "Configured path is missing: $path" }
}
$phpVersion = & $config.phpPath -r 'echo PHP_VERSION;'
if ($LASTEXITCODE -ne 0 -or $phpVersion -notmatch '^8\.4\.') { throw 'Expected a working PHP 8.4 executable.' }
$extensions = @(& $config.phpPath -m)
if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect PHP extensions.' }
$required = @('bcmath', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'xml', 'zip')
$missing = @($required | Where-Object { $_ -notin $extensions })
$nodeCommand = Get-Command node.exe -ErrorAction SilentlyContinue
$npmCommand = Get-Command npm.cmd -ErrorAction SilentlyContinue
$configuredNode = $null
$nodeVersion = $null
$npmVersion = $null
if ($config.PSObject.Properties.Name -contains 'nodePath') {
    if (-not (Test-Path -LiteralPath $config.nodePath) -or -not (Test-Path -LiteralPath $config.npmPath)) {
        throw 'Configured Node/npm runtime is missing.'
    }
    $configuredNode = $config.nodePath
    $nodeVersion = & $config.nodePath --version
    if ($LASTEXITCODE -ne 0 -or $nodeVersion -notmatch '^v22\.') { throw 'Expected a working configured Node 22 runtime.' }
    $npmVersion = & $config.npmPath --version
    if ($LASTEXITCODE -ne 0) { throw 'Configured npm runtime failed.' }
}
[ordered]@{
    identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    phpPath = $config.phpPath
    phpVersion = $phpVersion
    missingPhpExtensions = $missing
    composerPharPresent = $true
    environmentFilePresent = Test-Path -LiteralPath (Join-Path $config.livePath '.env')
    nodeAvailableOnPath = $null -ne $nodeCommand
    npmAvailableOnPath = $null -ne $npmCommand
    configuredNodePath = $configuredNode
    configuredNodeVersion = $nodeVersion
    configuredNpmVersion = $npmVersion
    stagingRoot = $config.stagingRoot
    livePath = $config.livePath
    activationAllowed = $false
} | ConvertTo-Json -Depth 3
if ($missing.Count -gt 0) { throw 'Required PHP extensions are missing.' }
