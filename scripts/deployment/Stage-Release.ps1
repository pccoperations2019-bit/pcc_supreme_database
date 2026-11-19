[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ArchivePath,
    [Parameter(Mandatory)][ValidatePattern('^[0-9a-f]{40}$')][string]$Commit,
    [Parameter(Mandatory)][ValidatePattern('^[0-9a-f]{64}$')][string]$Sha256,
    [string]$ConfigPath = 'C:\ProgramData\SUPREME-Deploy\controller\server.json'
)
$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/Release.Common.ps1"
$config = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json
if ($config.schemaVersion -ne 1) { throw 'Unknown server configuration version.' }
$stageRoot = (Resolve-Path -LiteralPath $config.stagingRoot).Path
$liveRoot = (Resolve-Path -LiteralPath $config.livePath).Path.TrimEnd('\', '/')
if ($stageRoot.Equals($liveRoot, [StringComparison]::OrdinalIgnoreCase) -or
    $stageRoot.StartsWith($liveRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -or
    $liveRoot.StartsWith($stageRoot.TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Staging and live paths must be separate, non-overlapping directories.'
}
Assert-NoReparsePoint $stageRoot
$destination = Join-Path $stageRoot $Commit
$lock = [IO.File]::Open((Join-Path $stageRoot 'stage.lock'), [IO.FileMode]::OpenOrCreate,
    [IO.FileAccess]::ReadWrite, [IO.FileShare]::None)
try {
    $manifest = Expand-VerifiedRelease $ArchivePath $destination $Commit $Sha256
    # Compare migration files without opening a database or running application code.
    $releaseMigrations = @{}
    foreach ($file in $manifest.files) {
        if ($file.path.StartsWith('database/migrations/')) { $releaseMigrations[$file.path] = $file.sha256 }
    }
    $liveMigrations = @{}
    foreach ($file in Get-ChildItem -LiteralPath (Join-Path $liveRoot 'database/migrations') -File -Recurse) {
        Assert-NoReparsePoint $file.FullName
        $relative = $file.FullName.Substring($liveRoot.Length + 1).Replace('\', '/')
        $liveMigrations[$relative] = Get-ReleaseHash $file.FullName
    }
    $migrationChanges = @(@($releaseMigrations.Keys) + @($liveMigrations.Keys) | Sort-Object -Unique | Where-Object {
        -not $releaseMigrations.ContainsKey($_) -or -not $liveMigrations.ContainsKey($_) -or
        $releaseMigrations[$_] -cne $liveMigrations[$_]
    })
    $report = [ordered]@{
        commit = $Commit
        archiveSha256 = $Sha256
        stagePath = $destination
        status = 'verified-source-only'
        migrationChanges = $migrationChanges
        activationAllowed = $false
        outstanding = @('Production activation is not implemented by this staging command.',
            'Install and validate locked Windows PHP and PDF/browser dependencies in staging.',
            'Verify backups, worker/scheduler control and recovery before activation.')
    }
    $report | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $destination 'stage-report.json') -Encoding UTF8
    $report | ConvertTo-Json -Depth 5
} finally { $lock.Dispose() }
