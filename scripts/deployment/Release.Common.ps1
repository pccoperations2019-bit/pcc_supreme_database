Set-StrictMode -Version Latest
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

function Assert-ReleasePath {
    param([Parameter(Mandatory)][string]$Path)
    # One portable spelling per Windows path: no traversal, ADS, device names,
    # hidden environment files, runtime data, executable setup scripts or links.
    if ($Path -match '[\\:<>"|?*\x00-\x1f]' -or $Path.StartsWith('/')) {
        throw "Unsafe release path: $Path"
    }
    foreach ($part in $Path.Split('/')) {
        if (-not $part -or $part -in @('.', '..') -or $part -match '[. ]$' -or
            $part -match '^(?i:CON|PRN|AUX|NUL|COM[0-9]|LPT[0-9])(?:\.|$)' -or
            $part -match '^(?i:\.env.*|\.git|node_modules|vendor|storage|uploads|cache|backups?)$') {
            throw "Forbidden release path: $Path"
        }
    }
    $rootFiles = @('artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json')
    if ($Path -in $rootFiles) { return }
    if ($Path -notmatch '^(app|config|database/(migrations|factories|seeders)|resources|routes|public)/' -and
        $Path -notin @('bootstrap/app.php', 'bootstrap/providers.php')) {
        throw "Path is outside release scope: $Path"
    }
    if ($Path -eq 'public/.htaccess') { return }
    if ($Path -notmatch '\.(php|vue|js|ts|css|json|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2|ttf|eot|txt|md)$') {
        throw "Unexpected release file type: $Path"
    }
    if ($Path -match '(?i)\.(bak|backup|old|sql|pem|key|pfx)(\.|$)') {
        throw "Backup or credential file rejected: $Path"
    }
}

function Assert-NoReparsePoint {
    param([Parameter(Mandatory)][string]$Path)
    $item = Get-Item -LiteralPath $Path -Force -ErrorAction Stop
    while ($null -ne $item) {
        if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw "Symbolic links and junctions are not allowed here: $($item.FullName)"
        }
        if ($item -is [IO.FileInfo]) { $item = $item.Directory } else { $item = $item.Parent }
    }
}

function Get-ReleaseHash {
    param([Parameter(Mandatory)][string]$Path)
    (Get-FileHash -LiteralPath $Path -Algorithm SHA256 -ErrorAction Stop).Hash.ToLowerInvariant()
}

function Read-ReleaseManifest {
    param([Parameter(Mandatory)][string]$Text, [Parameter(Mandatory)][string]$ExpectedCommit)
    if ($ExpectedCommit -cnotmatch '^[0-9a-f]{40}$') { throw 'ExpectedCommit must be a full lowercase commit SHA.' }
    $manifest = $Text | ConvertFrom-Json -ErrorAction Stop
    if ($manifest.schemaVersion -ne 1 -or
        $manifest.repository -cne 'pccoperations2019-bit/pcc_supreme_database' -or
        $manifest.commit -cne $ExpectedCommit -or @($manifest.files).Count -eq 0 -or
        @($manifest.files).Count -gt 20000) {
        throw 'Release identity or manifest format is invalid.'
    }
    $seen = @{}
    foreach ($file in $manifest.files) {
        Assert-ReleasePath $file.path
        if ($seen.ContainsKey($file.path) -or $file.sha256 -cnotmatch '^[0-9a-f]{64}$') {
            throw 'Duplicate release path or invalid file hash.'
        }
        $seen[$file.path] = $true
    }
    foreach ($required in @('artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
        'bootstrap/app.php', 'bootstrap/providers.php', 'public/index.php', 'public/build/manifest.json')) {
        if (-not $seen.ContainsKey($required)) { throw "Required release file missing: $required" }
    }
    return $manifest
}

function Copy-ReleaseEntry {
    param(
        [Parameter(Mandatory)][IO.Compression.ZipArchiveEntry]$Entry,
        [Parameter(Mandatory)][IO.Stream]$OutputStream,
        [Parameter(Mandatory)][long]$Limit
    )
    # Central-directory Length is attacker-controlled. Bound actual decompressed
    # bytes before writing, and require that they match the declared length.
    $inputStream = $Entry.Open()
    $buffer = New-Object byte[] 81920
    [long]$copied = 0
    try {
        while (($count = $inputStream.Read($buffer, 0, $buffer.Length)) -gt 0) {
            $copied += $count
            if ($copied -gt $Limit -or $copied -gt $Entry.Length) { throw 'Decompressed entry exceeds its size limit or declared length.' }
            $OutputStream.Write($buffer, 0, $count)
        }
        if ($copied -ne $Entry.Length) { throw 'Decompressed entry length does not match its declaration.' }
    } finally { $inputStream.Dispose() }
}

function Write-ReleaseEntry {
    param([IO.Compression.ZipArchiveEntry]$Entry, [string]$Target)
    $outputStream = [IO.File]::Open($Target, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
    try { Copy-ReleaseEntry $Entry $outputStream 100MB } finally { $outputStream.Dispose() }
}

function Expand-VerifiedRelease {
    param(
        [Parameter(Mandatory)][string]$ArchivePath,
        [Parameter(Mandatory)][string]$Destination,
        [Parameter(Mandatory)][string]$ExpectedCommit,
        [Parameter(Mandatory)][string]$ExpectedSha256
    )
    if ($ExpectedSha256 -cnotmatch '^[0-9a-f]{64}$' -or (Get-ReleaseHash $ArchivePath) -cne $ExpectedSha256) {
        throw 'Archive SHA256 verification failed.'
    }
    if (Test-Path -LiteralPath $Destination) { throw 'Destination already exists; releases are never overwritten.' }
    $parent = Split-Path -Parent ([IO.Path]::GetFullPath($Destination))
    Assert-NoReparsePoint $parent
    $archive = [IO.Compression.ZipFile]::OpenRead($ArchivePath)
    try {
        if ($archive.Entries.Count -gt 20001) { throw 'Archive has too many entries.' }
        $entries = @{}
        [long]$total = 0
        foreach ($entry in $archive.Entries) {
            if ($entry.FullName -cne 'release-manifest.json') { Assert-ReleasePath $entry.FullName }
            if ($entries.ContainsKey($entry.FullName)) { throw 'Duplicate archive path.' }
            # Reject Unix symbolic links and other non-regular file types.
            $kind = ($entry.ExternalAttributes -shr 16) -band 0xF000
            if ($kind -notin @(0, 0x8000)) { throw 'Archive contains a non-regular file.' }
            if ($entry.Length -gt 100MB) { throw 'Archive entry exceeds the size limit.' }
            $total += $entry.Length
            if ($total -gt 1GB) { throw 'Expanded archive exceeds the size limit.' }
            $entries[$entry.FullName] = $entry
        }
        if (-not $entries.ContainsKey('release-manifest.json')) { throw 'Release manifest missing.' }
        if ($entries['release-manifest.json'].Length -gt 8MB) { throw 'Manifest is too large.' }
        $manifestStream = [IO.MemoryStream]::new()
        try {
            Copy-ReleaseEntry $entries['release-manifest.json'] $manifestStream 8MB
            $manifestText = [Text.Encoding]::UTF8.GetString($manifestStream.ToArray())
            $manifest = Read-ReleaseManifest $manifestText $ExpectedCommit
        } finally { $manifestStream.Dispose() }
        if ($entries.Count -ne @($manifest.files).Count + 1) { throw 'Archive contains unlisted or missing files.' }
        foreach ($file in $manifest.files) {
            if (-not $entries.ContainsKey($file.path)) { throw "Missing archive entry: $($file.path)" }
        }

        # The target is a new isolated directory. No executable content is run.
        New-Item -ItemType Directory -Path $Destination -ErrorAction Stop | Out-Null
        foreach ($file in $manifest.files) {
            $target = Join-Path $Destination $file.path
            New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
            Write-ReleaseEntry $entries[$file.path] $target
            if ((Get-ReleaseHash $target) -cne $file.sha256) { throw "Extracted file hash mismatch: $($file.path)" }
        }
        Write-ReleaseEntry $entries['release-manifest.json'] (Join-Path $Destination 'release-manifest.json')
        return $manifest
    } finally { $archive.Dispose() }
}
