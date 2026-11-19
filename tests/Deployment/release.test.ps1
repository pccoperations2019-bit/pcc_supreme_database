$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$scripts = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../scripts/deployment'))
. "$scripts/Release.Common.ps1"
$testRoot = Join-Path ([IO.Path]::GetTempPath()) ('supreme-release-test-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $testRoot | Out-Null
$passed = 0
function Check {
    param([bool]$Condition, [string]$Message)
    if (-not $Condition) { throw "FAILED: $Message" }
    $script:passed++
    Write-Output "PASS: $Message"
}
function Must-Fail {
    param([scriptblock]$Action, [string]$Message)
    $failed = $false
    try { & $Action | Out-Null } catch { $failed = $true }
    Check $failed $Message
}
function Write-Fixture {
    param([string]$Root, [string]$Path, [string]$Content)
    $target = Join-Path $Root $Path
    New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
    [IO.File]::WriteAllText($target, $Content, [Text.UTF8Encoding]::new($false))
}
function Add-ZipEntry {
    param([string]$ZipPath, [string]$Name, [string]$Content)
    $zip = [IO.Compression.ZipFile]::Open($ZipPath, [IO.Compression.ZipArchiveMode]::Update)
    try {
        $entry = $zip.CreateEntry($Name)
        $writer = [IO.StreamWriter]::new($entry.Open())
        try { $writer.Write($Content) } finally { $writer.Dispose() }
    } finally { $zip.Dispose() }
}
function Forge-ZipEntryLength {
    param([string]$ZipPath, [string]$Name)
    $bytes = [IO.File]::ReadAllBytes($ZipPath)
    for ($offset = 0; $offset -lt $bytes.Length - 46; $offset++) {
        if ([BitConverter]::ToUInt32($bytes, $offset) -ne 0x02014b50) { continue }
        $nameLength = [BitConverter]::ToUInt16($bytes, $offset + 28)
        if ($offset + 46 + $nameLength -gt $bytes.Length) { continue }
        $entryName = [Text.Encoding]::UTF8.GetString($bytes, $offset + 46, $nameLength)
        if ($entryName -ceq $Name) {
            [Array]::Copy([BitConverter]::GetBytes([uint32]1), 0, $bytes, $offset + 24, 4)
            [IO.File]::WriteAllBytes($ZipPath, $bytes)
            return
        }
    }
    throw 'Fixture ZIP central-directory entry not found.'
}
try {
    $repo = Join-Path $testRoot 'repo'
    New-Item -ItemType Directory -Path $repo | Out-Null
    $fixtureFiles = @{
        'artisan' = '<?php'
        'composer.json' = '{}'
        'composer.lock' = '{}'
        'package.json' = '{}'
        'package-lock.json' = '{}'
        'bootstrap/app.php' = '<?php'
        'bootstrap/providers.php' = '<?php return [];'
        'public/index.php' = '<?php'
        'public/.htaccess' = '# required hidden file'
        'resources/js/app.js' = 'console.log("fixture");'
        'app/Example.php' = '<?php'
        'database/migrations/2026_01_01_example.php' = '<?php'
    }
    foreach ($path in $fixtureFiles.Keys) { Write-Fixture $repo $path $fixtureFiles[$path] }
    & git -C $repo init --quiet
    if ($LASTEXITCODE -ne 0) { throw 'Fixture git init failed.' }
    & git -C $repo add .
    & git -C $repo -c user.name=DeploymentTests -c user.email=tests@example.invalid -c commit.gpgsign=false commit --quiet -m fixture
    if ($LASTEXITCODE -ne 0) { throw 'Fixture commit failed.' }
    $commit = & git -C $repo rev-parse HEAD
    Write-Fixture $repo 'public/build/manifest.json' '{"resources/js/app.js":{"file":"assets/app.js"}}'
    Write-Fixture $repo 'public/build/assets/app.js' 'console.log("built");'
    Write-Fixture $repo '.env' 'FAKE_SECRET=must-not-be-packaged'
    Write-Fixture $repo 'storage/app/private/user.txt' 'must-not-be-packaged'
    Write-Fixture $repo 'public/uploads/user.txt' 'must-not-be-packaged'
    Write-Fixture $repo 'vendor/example.php' '<?php'
    Write-Fixture $repo 'database/dump.sql' 'must-not-be-packaged'
    $output = Join-Path $testRoot 'output'
    & "$scripts/New-Release.ps1" -RepositoryPath $repo -OutputDirectory $output -Commit $commit
    $archive = Join-Path $output "supreme-$commit.zip"
    $hash = Get-ReleaseHash $archive
    $destination = Join-Path $testRoot 'expanded'
    $manifest = Expand-VerifiedRelease $archive $destination $commit $hash
    Check ($manifest.commit -ceq $commit) 'manifest identifies the requested commit'
    Check ((Get-Content -LiteralPath "$destination/public/build/assets/app.js" -Raw) -eq 'console.log("built");') 'built assets survive packaging and verification'
    Check (Test-Path -LiteralPath "$destination/public/.htaccess") 'required hidden public file is included'
    Check (@($manifest.files | Where-Object { $_.path -match '^(.env|vendor|storage|public/uploads|database/dump)' }).Count -eq 0) 'environment, uploads, dependencies and database dump are excluded'
    Must-Fail { Expand-VerifiedRelease $archive $destination $commit $hash } 'existing releases cannot be overwritten'
    Must-Fail { Expand-VerifiedRelease $archive "$testRoot/wrong-digest" $commit ('0' * 64) } 'wrong archive digest is rejected'
    Must-Fail { Expand-VerifiedRelease $archive "$testRoot/wrong-commit" ('0' * 40) $hash } 'wrong commit is rejected'

    foreach ($badPath in @('../outside.php', 'public/../../outside.php', 'public/a:stream.php',
        'public/CON.txt', 'public/NUL.php', 'public/file. /a.php', 'public\\bad.php',
        'app/.env.production', 'app/private.key', 'app/file.bak.php', 'public/storage/user.php')) {
        Must-Fail { Assert-ReleasePath $badPath } "reject unsafe path $badPath"
    }
    foreach ($badEntry in @('../escaped.php', '.env', 'app/extra.php', 'PUBLIC/INDEX.PHP')) {
        $badZip = Join-Path $testRoot ([guid]::NewGuid().ToString('N') + '.zip')
        Copy-Item -LiteralPath $archive -Destination $badZip
        Add-ZipEntry $badZip $badEntry 'bad'
        Must-Fail { Expand-VerifiedRelease $badZip "$testRoot/rejected" $commit (Get-ReleaseHash $badZip) } "reject malicious/unlisted/duplicate ZIP entry $badEntry"
        Check (-not (Test-Path "$testRoot/rejected")) 'archive validation happens before extraction'
    }
    $badZip = Join-Path $testRoot 'tampered.zip'
    Copy-Item $archive $badZip
    $zip = [IO.Compression.ZipFile]::Open($badZip, [IO.Compression.ZipArchiveMode]::Update)
    try { $zip.GetEntry('app/Example.php').Delete() } finally { $zip.Dispose() }
    Add-ZipEntry $badZip 'app/Example.php' 'tampered'
    Must-Fail { Expand-VerifiedRelease $badZip "$testRoot/tampered" $commit (Get-ReleaseHash $badZip) } 'per-file manifest catches tampering even with updated ZIP digest'
    foreach ($forgedEntry in @('release-manifest.json', 'resources/js/app.js')) {
        $forgedZip = Join-Path $testRoot ([guid]::NewGuid().ToString('N') + '.zip')
        Copy-Item $archive $forgedZip
        Forge-ZipEntryLength $forgedZip $forgedEntry
        $forgedDestination = Join-Path $testRoot ([guid]::NewGuid().ToString('N'))
        Must-Fail { Expand-VerifiedRelease $forgedZip $forgedDestination $commit (Get-ReleaseHash $forgedZip) } "forged size is rejected while decompressing $forgedEntry"
    }

    $live = Join-Path $testRoot 'live'
    Write-Fixture $live 'database/migrations/2026_01_01_example.php' '<?php'
    Write-Fixture $live '.env' 'FAKE_SECRET=preserve'
    Write-Fixture $live 'storage/app/user.txt' 'preserve upload'
    $envHash = Get-ReleaseHash "$live/.env"
    $uploadHash = Get-ReleaseHash "$live/storage/app/user.txt"
    $stageRoot = Join-Path $testRoot 'staging'
    New-Item -ItemType Directory -Path $stageRoot | Out-Null
    $configPath = Join-Path $testRoot 'server.json'
    @{ schemaVersion = 1; stagingRoot = $stageRoot; livePath = $live } | ConvertTo-Json | Set-Content $configPath
    $report = & "$scripts/Stage-Release.ps1" -ArchivePath $archive -Commit $commit -Sha256 $hash -ConfigPath $configPath | ConvertFrom-Json
    Check (-not $report.activationAllowed -and $report.status -eq 'verified-source-only') 'staging never activates a release'
    Check (@($report.migrationChanges).Count -eq 0) 'identical migrations are reported unchanged'
    Check ((Get-ReleaseHash "$live/.env") -eq $envHash -and (Get-ReleaseHash "$live/storage/app/user.txt") -eq $uploadHash) 'staging preserves live environment and uploads'
    $lock = [IO.File]::Open("$stageRoot/stage.lock", 'OpenOrCreate', 'ReadWrite', 'None')
    try {
        Must-Fail { & "$scripts/Stage-Release.ps1" -ArchivePath $archive -Commit $commit -Sha256 $hash -ConfigPath $configPath } 'concurrent staging is blocked'
    } finally { $lock.Dispose() }
    $secondStage = Join-Path $testRoot 'second-stage'
    New-Item -ItemType Directory -Path $secondStage | Out-Null
    Write-Fixture $live 'database/migrations/2026_01_01_example.php' '<?php // changed'
    Write-Fixture $live 'database/migrations/extra.php' '<?php'
    @{ schemaVersion = 1; stagingRoot = $secondStage; livePath = $live } | ConvertTo-Json | Set-Content $configPath
    $report = & "$scripts/Stage-Release.ps1" -ArchivePath $archive -Commit $commit -Sha256 $hash -ConfigPath $configPath | ConvertFrom-Json
    Check (@($report.migrationChanges).Count -eq 2) 'changed and production-only migrations require review'
    @{ schemaVersion = 1; stagingRoot = $live; livePath = $live } | ConvertTo-Json | Set-Content $configPath
    Must-Fail { & "$scripts/Stage-Release.ps1" -ArchivePath $archive -Commit $commit -Sha256 $hash -ConfigPath $configPath } 'live directory cannot be used for staging'

    # Test self-hosted deploy.ps1 (-BackupFirst, sync, protection of .env/storage, migration gate, and auto-rollback)
    $deployScript = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../scripts/deploy/deploy.ps1'))
    $deployLive = Join-Path $testRoot 'deploy-live'
    $deployRoot = Join-Path $testRoot 'deploy-root'
    $backupRoot = Join-Path $testRoot 'backup-root'
    Write-Fixture $deployLive 'app/OldFile.php' '<?php // stale file'
    Write-Fixture $deployLive 'database/migrations/2026_01_01_example.php' '<?php'
    Write-Fixture $deployLive '.env' 'APP_KEY=preserve-live-key'
    Write-Fixture $deployLive 'storage/app/user.txt' 'preserve live upload'
    Write-Fixture $deployLive 'public/storage/avatar.txt' 'preserve symlinked upload'
    Write-Fixture $deployLive 'public/hot' 'http://localhost:5173'
    Write-Fixture $deployLive 'bootstrap/cache/config.php' '<?php return [];'
    $deployEnvHash = Get-ReleaseHash "$deployLive/.env"
    $deployUploadHash = Get-ReleaseHash "$deployLive/storage/app/user.txt"
    $deployPublicUploadHash = Get-ReleaseHash "$deployLive/public/storage/avatar.txt"
    $deployCacheHash = Get-ReleaseHash "$deployLive/bootstrap/cache/config.php"

    Must-Fail { & $deployScript -RepositoryPath $repo -Commit $commit -LivePath $deployLive -DeployRoot $deployRoot -BackupRoot $backupRoot -DryRun } 'deploy requires -BackupFirst'
    $deployReport = & $deployScript -BackupFirst -RepositoryPath $repo -Commit $commit -LivePath $deployLive -DeployRoot $deployRoot -BackupRoot $backupRoot -DryRun | ConvertFrom-Json
    Check ($deployReport.status -eq 'deployed' -and $deployReport.snapshotCreated -and $deployReport.activated -and $deployReport.smokeTestPassed) 'dry-run deploy succeeds with snapshot backup'
    Check (Test-Path -LiteralPath (Join-Path $deployReport.backupPath 'app-snapshot/app/OldFile.php')) 'pre-deploy snapshot preserves previous code'
    Check (-not (Test-Path -LiteralPath "$deployLive/app/OldFile.php") -and (Test-Path -LiteralPath "$deployLive/app/Example.php")) 'deploy syncs new code and removes stale managed files'
    Check ((Get-Content -LiteralPath "$deployLive/public/build/assets/app.js" -Raw) -eq 'console.log("built");') 'deploy copies built frontend assets'
    Check (-not (Test-Path -LiteralPath "$deployLive/public/hot")) 'deploy removes stale public/hot dev marker'
    Check ((Get-Content -LiteralPath "$deployLive/DEPLOYED_COMMIT.txt" -Raw) -match "commit=$commit") 'deploy writes DEPLOYED_COMMIT.txt marker'
    Check ((Get-ReleaseHash "$deployLive/.env") -eq $deployEnvHash -and (Get-ReleaseHash "$deployLive/storage/app/user.txt") -eq $deployUploadHash -and (Get-ReleaseHash "$deployLive/public/storage/avatar.txt") -eq $deployPublicUploadHash -and (Get-ReleaseHash "$deployLive/bootstrap/cache/config.php") -eq $deployCacheHash) 'deploy preserves .env, storage uploads, and bootstrap cache'

    Write-Fixture $repo 'app/Example.php' '<?php // v2'
    Write-Fixture $repo 'database/migrations/2026_02_01_new.php' '<?php'
    & git -C $repo add 'app/Example.php' 'database/migrations/2026_02_01_new.php'
    & git -C $repo -c user.name=DeploymentTests -c user.email=tests@example.invalid -c commit.gpgsign=false commit --quiet -m v2
    if ($LASTEXITCODE -ne 0) { throw 'Fixture v2 commit failed.' }
    $commitV2 = & git -C $repo rev-parse HEAD
    Must-Fail { & $deployScript -BackupFirst -RepositoryPath $repo -Commit $commitV2 -LivePath $deployLive -DeployRoot $deployRoot -BackupRoot $backupRoot -DryRun } 'deploy blocks unapproved migration changes'
    $v2ReportDir = Get-ChildItem -LiteralPath $backupRoot -Directory -Filter "predeploy-*-$($commitV2.Substring(0, 8))-*" | Select-Object -First 1
    $v2Report = Get-Content -LiteralPath (Join-Path $v2ReportDir.FullName 'deploy-report.json') -Raw | ConvertFrom-Json
    Check (-not $v2Report.activated -and @($v2Report.migrationChanges).Count -eq 1 -and $v2Report.error -match 'Migration files differ') 'migration gate rejects deploy before modifying live files'

    Write-Fixture $repo 'app/Example.php' '<?php // v3 rollback target'
    & git -C $repo rm --quiet 'database/migrations/2026_02_01_new.php'
    & git -C $repo add 'app/Example.php'
    & git -C $repo -c user.name=DeploymentTests -c user.email=tests@example.invalid -c commit.gpgsign=false commit --quiet -m v3
    if ($LASTEXITCODE -ne 0) { throw 'Fixture v3 commit failed.' }
    $commitV3 = & git -C $repo rev-parse HEAD
    Must-Fail { & $deployScript -BackupFirst -RepositoryPath $repo -Commit $commitV3 -LivePath $deployLive -DeployRoot $deployRoot -BackupRoot $backupRoot -DryRun -SimulateSmokeTestFailure } 'smoke test failure fails deploy script'
    $v3ReportDir = Get-ChildItem -LiteralPath $backupRoot -Directory -Filter "predeploy-*-$($commitV3.Substring(0, 8))-*" | Select-Object -First 1
    $rollbackReport = Get-Content -LiteralPath (Join-Path $v3ReportDir.FullName 'deploy-report.json') -Raw | ConvertFrom-Json
    Check ($rollbackReport.status -eq 'rolled-back' -and $rollbackReport.rolledBack -and $rollbackReport.error -match 'Simulated smoke test failure') 'failed smoke test triggers automatic rollback'
    Check ((Get-Content -LiteralPath "$deployLive/app/Example.php" -Raw) -eq '<?php') 'rollback restores previous PHP code'
    Check ((Get-Content -LiteralPath "$deployLive/DEPLOYED_COMMIT.txt" -Raw) -match "commit=$commit") 'rollback restores previous DEPLOYED_COMMIT.txt'
    Check ((Get-ReleaseHash "$deployLive/.env") -eq $deployEnvHash -and (Get-ReleaseHash "$deployLive/storage/app/user.txt") -eq $deployUploadHash -and (Get-ReleaseHash "$deployLive/public/storage/avatar.txt") -eq $deployPublicUploadHash) 'rollback preserves .env and uploads'

    Write-Fixture $repo 'app/Example.php' '<?php // dirty'
    Must-Fail { & "$scripts/New-Release.ps1" -RepositoryPath $repo -OutputDirectory "$testRoot/dirty" -Commit $commitV3 } 'modified source cannot be labelled as a tested commit'
    Write-Output "All $passed deployment checks passed."
} finally {
    # Delete only this uniquely created disposable test tree, never a computed live path.
    $resolvedTestRoot = [IO.Path]::GetFullPath($testRoot)
    $tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
    if ($resolvedTestRoot.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -and
        (Split-Path -Leaf $resolvedTestRoot) -match '^supreme-release-test-[0-9a-f]{32}$') {
        Remove-Item -LiteralPath $resolvedTestRoot -Recurse -Force
    } else { throw 'Refusing cleanup outside the disposable test directory.' }
}
# The dirty-source negative test intentionally leaves git diff's native exit
# code at 1. Report success only after all assertions and cleanup complete.
exit 0
