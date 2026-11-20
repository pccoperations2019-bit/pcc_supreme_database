[CmdletBinding()]
param(
    [switch]$BackupFirst,
    [Parameter(Mandatory)][string]$RepositoryPath,
    [Parameter(Mandatory)][ValidatePattern('^[0-9a-f]{40}$')][string]$Commit,
    [string]$LivePath = 'C:\laragon\www\supreme-cbed-poc',
    [string]$DeployRoot = 'C:\ProgramData\SUPREME-Deploy',
    [string]$BackupRoot = 'C:\ProgramData\SUPREME-Backups',
    [string]$PhpExecutable = 'C:\laragon\bin\php\php-8.4.27-nts-Win32-vs17-x64\php.exe',
    [string]$ComposerPhar = 'C:\ProgramData\ComposerSetup\bin\composer.phar',
    [string]$SmokeTestUrl = 'https://supremecbed.pcc.gov.ph/login',
    [switch]$AllowMigrations,
    [switch]$DryRun,
    [switch]$SimulateSmokeTestFailure
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

if (-not $BackupFirst) {
    throw 'Production deployment requires -BackupFirst.'
}

$deploymentScripts = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../deployment'))
. (Join-Path $deploymentScripts 'Release.Common.ps1')

$repo = (Resolve-Path -LiteralPath $RepositoryPath).Path
$live = (Resolve-Path -LiteralPath $LivePath).Path.TrimEnd('\', '/')
New-Item -ItemType Directory -Path $DeployRoot -Force | Out-Null
New-Item -ItemType Directory -Path $BackupRoot -Force | Out-Null
$deploy = (Resolve-Path -LiteralPath $DeployRoot).Path.TrimEnd('\', '/')
$backups = (Resolve-Path -LiteralPath $BackupRoot).Path.TrimEnd('\', '/')

if ($deploy.Equals($live, [StringComparison]::OrdinalIgnoreCase) -or
    $deploy.StartsWith($live + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -or
    $live.StartsWith($deploy + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'DeployRoot and LivePath must be separate, non-overlapping directories.'
}

Assert-NoReparsePoint $deploy
Assert-NoReparsePoint $backups
Assert-NoReparsePoint $live

$locksDir = Join-Path $deploy 'locks'
$stagingBase = Join-Path $deploy 'staging'
New-Item -ItemType Directory -Path $locksDir -Force | Out-Null
New-Item -ItemType Directory -Path $stagingBase -Force | Out-Null

$managedDirectories = @('app', 'config', 'database', 'resources', 'routes')
$managedRootFiles = @('artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json')
$bootstrapFiles = @('app.php', 'providers.php')
$protectedPublicEntries = @('storage', 'uploads', 'hot')
$workerServices = @('LaravelQueueListener', 'LaravelQueueListener2', 'StorageCleanupScheduler')

function Sync-ManagedDirectory {
    param(
        [Parameter(Mandatory)][string]$SourceDir,
        [Parameter(Mandatory)][string]$TargetDir
    )

    if (-not (Test-Path -LiteralPath $SourceDir -PathType Container)) {
        return
    }
    New-Item -ItemType Directory -Path $TargetDir -Force | Out-Null

    $sourceResolved = (Resolve-Path -LiteralPath $SourceDir).Path.TrimEnd('\', '/')
    $targetResolved = (Resolve-Path -LiteralPath $TargetDir).Path.TrimEnd('\', '/')

    $sourceMap = @{}
    foreach ($file in @(Get-ChildItem -LiteralPath $sourceResolved -File -Recurse -Force)) {
        $rel = $file.FullName.Substring($sourceResolved.Length + 1).Replace('\', '/')
        $sourceMap[$rel] = $file.FullName
    }

    foreach ($existing in @(Get-ChildItem -LiteralPath $targetResolved -File -Recurse -Force)) {
        $rel = $existing.FullName.Substring($targetResolved.Length + 1).Replace('\', '/')
        if (-not $sourceMap.ContainsKey($rel)) {
            Remove-Item -LiteralPath $existing.FullName -Force
        }
    }

    foreach ($rel in $sourceMap.Keys) {
        $destFile = Join-Path $targetResolved $rel
        $destParent = Split-Path -Parent $destFile
        if (-not (Test-Path -LiteralPath $destParent -PathType Container)) {
            New-Item -ItemType Directory -Path $destParent -Force | Out-Null
        }
        [IO.File]::Copy($sourceMap[$rel], $destFile, $true)
    }

    $dirs = @(Get-ChildItem -LiteralPath $targetResolved -Directory -Recurse -Force | Sort-Object { $_.FullName.Length } -Descending)
    foreach ($dir in $dirs) {
        if (@(Get-ChildItem -LiteralPath $dir.FullName -Force).Count -eq 0) {
            Remove-Item -LiteralPath $dir.FullName -Force
        }
    }
}

function Sync-PublicDirectory {
    param(
        [Parameter(Mandatory)][string]$SourcePublic,
        [Parameter(Mandatory)][string]$TargetPublic
    )

    if (-not (Test-Path -LiteralPath $SourcePublic -PathType Container)) {
        return
    }
    New-Item -ItemType Directory -Path $TargetPublic -Force | Out-Null

    $sourceFiles = @{}
    $sourceSubdirs = @{}
    foreach ($item in @(Get-ChildItem -LiteralPath $SourcePublic -Force)) {
        if ($protectedPublicEntries -contains $item.Name) {
            continue
        }
        if ($item.PSIsContainer) {
            $sourceSubdirs[$item.Name] = $item.FullName
        } else {
            $sourceFiles[$item.Name] = $item.FullName
        }
    }

    foreach ($existing in @(Get-ChildItem -LiteralPath $TargetPublic -Force)) {
        if ($existing.Name -in @('storage', 'uploads')) {
            continue
        }
        if ($existing.Name -eq 'hot') {
            Remove-Item -LiteralPath $existing.FullName -Force
            continue
        }
        if ($existing.PSIsContainer) {
            if (-not $sourceSubdirs.ContainsKey($existing.Name)) {
                Remove-Item -LiteralPath $existing.FullName -Recurse -Force
            }
        } elseif (-not $sourceFiles.ContainsKey($existing.Name)) {
            Remove-Item -LiteralPath $existing.FullName -Force
        }
    }

    foreach ($name in $sourceSubdirs.Keys) {
        Sync-ManagedDirectory -SourceDir $sourceSubdirs[$name] -TargetDir (Join-Path $TargetPublic $name)
    }
    foreach ($name in $sourceFiles.Keys) {
        [IO.File]::Copy($sourceFiles[$name], (Join-Path $TargetPublic $name), $true)
    }
}

function Sync-ApplicationTree {
    param(
        [Parameter(Mandatory)][string]$SourceRoot,
        [Parameter(Mandatory)][string]$TargetRoot,
        [switch]$IsRollback
    )

    foreach ($dirName in $managedDirectories) {
        Sync-ManagedDirectory -SourceDir (Join-Path $SourceRoot $dirName) -TargetDir (Join-Path $TargetRoot $dirName)
    }

    $sourceVendor = Join-Path $SourceRoot 'vendor'
    $targetVendor = Join-Path $TargetRoot 'vendor'
    if (Test-Path -LiteralPath $sourceVendor -PathType Container) {
        Sync-ManagedDirectory -SourceDir $sourceVendor -TargetDir $targetVendor
    } elseif ($IsRollback -and (Test-Path -LiteralPath $targetVendor -PathType Container)) {
        Remove-Item -LiteralPath $targetVendor -Recurse -Force
    }

    $sourceBootstrap = Join-Path $SourceRoot 'bootstrap'
    $targetBootstrap = Join-Path $TargetRoot 'bootstrap'
    New-Item -ItemType Directory -Path $targetBootstrap -Force | Out-Null
    foreach ($fileName in $bootstrapFiles) {
        $srcBootstrapFile = Join-Path $sourceBootstrap $fileName
        if (Test-Path -LiteralPath $srcBootstrapFile -PathType Leaf) {
            [IO.File]::Copy($srcBootstrapFile, (Join-Path $targetBootstrap $fileName), $true)
        }
    }

    Sync-PublicDirectory -SourcePublic (Join-Path $SourceRoot 'public') -TargetPublic (Join-Path $TargetRoot 'public')

    foreach ($rootFile in $managedRootFiles) {
        $srcFile = Join-Path $SourceRoot $rootFile
        if (Test-Path -LiteralPath $srcFile -PathType Leaf) {
            [IO.File]::Copy($srcFile, (Join-Path $TargetRoot $rootFile), $true)
        }
    }

    $srcMarker = Join-Path $SourceRoot 'DEPLOYED_COMMIT.txt'
    $dstMarker = Join-Path $TargetRoot 'DEPLOYED_COMMIT.txt'
    if (Test-Path -LiteralPath $srcMarker -PathType Leaf) {
        [IO.File]::Copy($srcMarker, $dstMarker, $true)
    } elseif ($IsRollback -and (Test-Path -LiteralPath $dstMarker -PathType Leaf)) {
        Remove-Item -LiteralPath $dstMarker -Force
    }
}

function Stop-WorkerServices {
    $stoppedServices = @()
    foreach ($serviceName in $workerServices) {
        $svc = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
        if ($null -ne $svc -and $svc.Status -ne [ServiceProcess.ServiceControllerStatus]::Stopped) {
            Write-Host "Stopping service $serviceName..."
            Stop-Service -Name $serviceName -Force -ErrorAction Stop
            $svc.WaitForStatus([ServiceProcess.ServiceControllerStatus]::Stopped, [TimeSpan]::FromSeconds(60))
            $stoppedServices += $serviceName
        }
    }
    return ,$stoppedServices
}

function Start-WorkerServices {
    param([string[]]$Services)
    foreach ($serviceName in $Services) {
        $svc = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
        if ($null -ne $svc -and $svc.Status -ne [ServiceProcess.ServiceControllerStatus]::Running) {
            Write-Host "Starting service $serviceName..."
            Start-Service -Name $serviceName -ErrorAction Stop
            $svc.WaitForStatus([ServiceProcess.ServiceControllerStatus]::Running, [TimeSpan]::FromSeconds(30))
        }
    }
}

function Invoke-ArtisanOptimization {
    param([string]$TargetRoot)
    $artisan = Join-Path $TargetRoot 'artisan'
    foreach ($cmd in @('optimize:clear')) {
        $output = & $PhpExecutable $artisan $cmd 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php artisan $cmd failed: $($output -join ' ')"
        }
    }
}

function Invoke-SmokeTest {
    param(
        [string]$Url,
        [string[]]$ExpectedServices,
        [switch]$IsDryRun,
        [switch]$ForceFail
    )

    if ($ForceFail) {
        throw 'Simulated smoke test failure.'
    }
    if ($IsDryRun) {
        Write-Host 'DryRun mode: skipping live HTTP and NSSM service checks.'
        return
    }

    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
    $lastError = $null
    $ok = $false
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 30
            if ($response.StatusCode -eq 200 -and $response.Content.Length -gt 0) {
                $ok = $true
                break
            }
            $lastError = "Unexpected HTTP status $($response.StatusCode)"
        } catch {
            $lastError = $_.Exception.Message
        }
        Start-Sleep -Seconds 3
    }
    if (-not $ok) {
        throw "Smoke test failed for ${Url}: $lastError"
    }

    foreach ($serviceName in $ExpectedServices) {
        $svc = Get-Service -Name $serviceName -ErrorAction Stop
        if ($svc.Status -ne [ServiceProcess.ServiceControllerStatus]::Running) {
            throw "Expected service $serviceName to be Running after deploy (status: $($svc.Status))."
        }
    }
}

$lockPath = Join-Path $locksDir 'deploy.lock'
$lock = [IO.File]::Open($lockPath, [IO.FileMode]::OpenOrCreate, [IO.FileAccess]::ReadWrite, [IO.FileShare]::None)
$runId = [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ') + '-' + $Commit.Substring(0, 8) + '-' + [guid]::NewGuid().ToString('N').Substring(0, 8)
$predeployDir = Join-Path $backups ("predeploy-$runId")
$snapshotDir = Join-Path $predeployDir 'app-snapshot'
$runStageRoot = Join-Path $stagingBase ("run-$runId")
$packageDir = Join-Path $runStageRoot 'package'
$stageTargetRoot = Join-Path $runStageRoot 'staged'
$stageConfigPath = Join-Path $runStageRoot 'stage-config.json'

$report = [ordered]@{
    status            = 'failed'
    commit            = $Commit
    dryRun            = [bool]$DryRun
    backupFirst       = [bool]$BackupFirst
    backupPath        = $predeployDir
    snapshotCreated   = $false
    databaseBackupRun = $false
    stagedPath        = $null
    migrationChanges  = @()
    activated         = $false
    smokeTestPassed   = $false
    rolledBack        = $false
    startedAtUtc      = [DateTime]::UtcNow.ToString('o')
    completedAtUtc    = $null
    error             = $null
}

$activationStarted = $false
$servicesToRestart = @()

try {
    New-Item -ItemType Directory -Path $predeployDir -Force | Out-Null
    New-Item -ItemType Directory -Path $snapshotDir -Force | Out-Null
    New-Item -ItemType Directory -Path $packageDir -Force | Out-Null
    New-Item -ItemType Directory -Path $stageTargetRoot -Force | Out-Null

    # 1. Pre-deploy backups (-BackupFirst)
    if (-not $DryRun) {
        Write-Host 'Phase 1a: Running pre-deploy MySQL backup...'
        $dbBackupOutput = & (Join-Path $deploymentScripts 'New-ProductionDatabaseBackup.ps1') -NoSchemaChangesConfirmed
        if ($LASTEXITCODE -ne 0) {
            throw 'Pre-deploy MySQL backup failed.'
        }
        $report.databaseBackupRun = $true
    } else {
        Write-Host 'Phase 1a: DryRun mode - skipping live MySQL backup.'
    }

    Write-Host "Phase 1b: Capturing live application snapshot at $snapshotDir..."
    Sync-ApplicationTree -SourceRoot $live -TargetRoot $snapshotDir
    $report.snapshotCreated = $true

    # 2. Package & Stage Release
    Write-Host "Phase 2a: Packaging commit $Commit..."
    $newReleaseOut = & (Join-Path $deploymentScripts 'New-Release.ps1') -RepositoryPath $repo -OutputDirectory $packageDir -Commit $Commit
    if ($LASTEXITCODE -ne 0) {
        throw "Release packaging failed: $($newReleaseOut -join ' ')"
    }
    $archivePath = Join-Path $packageDir "supreme-$Commit.zip"
    $archiveHash = Get-ReleaseHash $archivePath

    Write-Host 'Phase 2b: Staging and verifying release...'
    [ordered]@{
        schemaVersion = 1
        stagingRoot   = $stageTargetRoot
        livePath      = $live
    } | ConvertTo-Json | Set-Content -LiteralPath $stageConfigPath -Encoding UTF8

    $stageOutput = & (Join-Path $deploymentScripts 'Stage-Release.ps1') `
        -ArchivePath $archivePath -Commit $Commit -Sha256 $archiveHash -ConfigPath $stageConfigPath
    if ($LASTEXITCODE -ne 0) {
        throw 'Release staging failed.'
    }
    $stageReport = ($stageOutput -join "`n") | ConvertFrom-Json
    $stagedReleaseDir = $stageReport.stagePath
    $report.stagedPath = $stagedReleaseDir
    $report.migrationChanges = @($stageReport.migrationChanges)

    if (-not $AllowMigrations -and @($stageReport.migrationChanges).Count -gt 0) {
        throw "Migration files differ from live ($($stageReport.migrationChanges -join ', ')). Obtain DB approval and pass -AllowMigrations."
    }

    if (-not $DryRun) {
        Write-Host 'Phase 2c: Installing locked production PHP dependencies in staging...'
        if (-not (Test-Path -LiteralPath $PhpExecutable) -or -not (Test-Path -LiteralPath $ComposerPhar)) {
            throw 'Configured PHP or Composer PHAR is missing.'
        }
        $composerOut = & $PhpExecutable $ComposerPhar install --working-dir="$stagedReleaseDir" `
            --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Composer production install failed: $($composerOut -join ' ')"
        }
        $platformOut = & $PhpExecutable $ComposerPhar check-platform-reqs --working-dir="$stagedReleaseDir" --no-dev 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Composer platform requirement check failed: $($platformOut -join ' ')"
        }
    }

    # 3. Stop workers, sync staged code into live, clear/rebuild caches, start workers
    $activationStarted = $true
    if (-not $DryRun) {
        $servicesToRestart = @(Stop-WorkerServices)
    }

    Write-Host "Phase 3: Syncing verified release to $live (preserving .env and storage)..."
    Sync-ApplicationTree -SourceRoot $stagedReleaseDir -TargetRoot $live
    $markerContent = "commit=$Commit`ndeployedAtUtc=$([DateTime]::UtcNow.ToString('o'))`n"
    [IO.File]::WriteAllText((Join-Path $live 'DEPLOYED_COMMIT.txt'), $markerContent, [Text.UTF8Encoding]::new($false))
    $report.activated = $true

    if (-not $DryRun) {
        Write-Host 'Phase 3b: Refreshing Laravel caches and starting workers...'
        Invoke-ArtisanOptimization -TargetRoot $live
        Start-WorkerServices -Services $servicesToRestart
    }

    # 4. Smoke test
    Write-Host "Phase 4: Running smoke test against $SmokeTestUrl..."
    Invoke-SmokeTest -Url $SmokeTestUrl -ExpectedServices $servicesToRestart -IsDryRun:$DryRun -ForceFail:$SimulateSmokeTestFailure
    $report.smokeTestPassed = $true
    $report.status = 'deployed'
    $report.completedAtUtc = [DateTime]::UtcNow.ToString('o')

    $json = $report | ConvertTo-Json -Depth 6
    Set-Content -LiteralPath (Join-Path $predeployDir 'deploy-report.json') -Value $json -Encoding UTF8
    Write-Output $json
} catch {
    $report.error = $_.Exception.Message
    if ($activationStarted -and $report.snapshotCreated) {
        Write-Host "Deployment failed ($($report.error)); rolling back to pre-deploy snapshot..."
        try {
            if (-not $DryRun) {
                $null = Stop-WorkerServices
            }
            Sync-ApplicationTree -SourceRoot $snapshotDir -TargetRoot $live -IsRollback
            if (-not $DryRun) {
                Invoke-ArtisanOptimization -TargetRoot $live
                Start-WorkerServices -Services $servicesToRestart
            }
            $report.rolledBack = $true
            $report.status = 'rolled-back'
        } catch {
            $report.status = 'rollback-failed'
            $report.error = "$($report.error) | Rollback error: $($_.Exception.Message)"
        }
    }
    $report.completedAtUtc = [DateTime]::UtcNow.ToString('o')
    $json = $report | ConvertTo-Json -Depth 6
    if (Test-Path -LiteralPath $predeployDir -PathType Container) {
        Set-Content -LiteralPath (Join-Path $predeployDir 'deploy-report.json') -Value $json -Encoding UTF8
    }
    Write-Output $json
    throw
} finally {
    $lock.Dispose()
}
