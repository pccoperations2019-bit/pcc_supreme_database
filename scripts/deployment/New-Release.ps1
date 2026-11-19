[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$RepositoryPath,
    [Parameter(Mandatory)][string]$OutputDirectory,
    [Parameter(Mandatory)][ValidatePattern('^[0-9a-f]{40}$')][string]$Commit
)
$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/Release.Common.ps1"
$repo = (Resolve-Path -LiteralPath $RepositoryPath).Path
$headCommit = & git -C $repo rev-parse HEAD
if ($LASTEXITCODE -ne 0 -or $headCommit -cne $Commit) { throw 'Checkout does not match the requested commit.' }
# Refuse to label modified application source as a tested commit.
& git -C $repo diff --quiet HEAD -- app bootstrap config database public resources routes artisan composer.json composer.lock package.json package-lock.json
if ($LASTEXITCODE -ne 0) { throw 'Tracked application source differs from the requested commit.' }
$tracked = @(& git -c core.quotepath=false -C $repo ls-files)
if ($LASTEXITCODE -ne 0) { throw 'Cannot list tracked source files.' }
$paths = @($tracked | Where-Object {
    $_ -match '^(app|config|database/(migrations|factories|seeders)|resources|routes|public)/' -or
    $_ -in @('artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
        'bootstrap/app.php', 'bootstrap/providers.php')
})
$build = Join-Path $repo 'public/build'
Assert-NoReparsePoint $build
$paths += @(Get-ChildItem -LiteralPath $build -File -Recurse -Force | ForEach-Object {
    $_.FullName.Substring($repo.Length + 1).Replace('\', '/')
})
$paths = @($paths | Sort-Object -Unique)
$files = foreach ($path in $paths) {
    Assert-ReleasePath $path
    $fullPath = Join-Path $repo $path
    Assert-NoReparsePoint $fullPath
    [ordered]@{ path = $path; sha256 = Get-ReleaseHash $fullPath }
}
$manifest = [ordered]@{
    schemaVersion = 1
    repository = 'pccoperations2019-bit/pcc_supreme_database'
    commit = $Commit
    createdAtUtc = [DateTime]::UtcNow.ToString('o')
    files = @($files)
}
$manifestText = $manifest | ConvertTo-Json -Depth 5
$null = Read-ReleaseManifest $manifestText $Commit
New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
$output = (Resolve-Path -LiteralPath $OutputDirectory).Path
Assert-NoReparsePoint $output
$zipPath = Join-Path $output "supreme-$Commit.zip"
# Create mode fails if an archive already exists.
$archive = [IO.Compression.ZipFile]::Open($zipPath, [IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $files) {
        $null = [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive,
            (Join-Path $repo $file.path), $file.path, [IO.Compression.CompressionLevel]::Optimal)
    }
    $entry = $archive.CreateEntry('release-manifest.json')
    $writer = [IO.StreamWriter]::new($entry.Open(), [Text.UTF8Encoding]::new($false))
    try { $writer.Write($manifestText) } finally { $writer.Dispose() }
} finally { $archive.Dispose() }
$hash = Get-ReleaseHash $zipPath
[IO.File]::WriteAllText("$zipPath.sha256", "$hash  supreme-$Commit.zip`n", [Text.UTF8Encoding]::new($false))
Write-Output "Release: $zipPath"
Write-Output "SHA256: $hash"
