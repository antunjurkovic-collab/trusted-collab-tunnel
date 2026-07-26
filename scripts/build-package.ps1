param(
    [string]$Version = '3.0.0-alpha.3'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'dist'
$draftSha256 = 'f1b2a1c9c50293c0df5936f58babb5f4fafa895510a4228ca73c71698d2a6159'

Push-Location $root
try {
    $dirty = @(git status --porcelain=v1 --untracked-files=all)
    if ($LASTEXITCODE -ne 0) { throw 'Unable to inspect Git status.' }
    if ($dirty.Count -ne 0) {
        throw 'Package builds require a clean, committed source checkpoint.'
    }

    $commit = (git rev-parse HEAD).Trim()
    if ($LASTEXITCODE -ne 0 -or $commit -notmatch '^[0-9a-f]{40}$') {
        throw 'Unable to resolve the source commit.'
    }

    $tracked = @(git ls-files)
    if ($LASTEXITCODE -ne 0) { throw 'Unable to enumerate tracked source files.' }
    $packageFiles = @($tracked | Where-Object {
        $_ -eq 'trusted-collab-tunnel.php' -or
        $_ -match '^includes/[^/]+\.php$' -or
        $_ -match '^src/Draft03/[^/]+\.php$' -or
        $_ -in @('README.md', 'readme.txt', 'CHANGELOG.md', 'SECURITY.md', 'PATENTS.md', 'LICENSE')
    } | Sort-Object)

    if ($packageFiles.Count -lt 10) {
        throw 'Unexpectedly small runtime package file set.'
    }

    function Get-GitBlobBytes {
        param([string]$Relative)

        $blob = (git rev-parse "$commit`:$Relative").Trim()
        if ($LASTEXITCODE -ne 0 -or $blob -notmatch '^[0-9a-f]{40,64}$') {
            throw "Unable to resolve committed blob: $Relative"
        }

        $start = [Diagnostics.ProcessStartInfo]::new()
        $start.FileName = 'git'
        $start.Arguments = "cat-file blob $blob"
        $start.WorkingDirectory = $root
        $start.UseShellExecute = $false
        $start.RedirectStandardOutput = $true
        $start.RedirectStandardError = $true
        $start.CreateNoWindow = $true
        $process = [Diagnostics.Process]::new()
        $process.StartInfo = $start
        if (-not $process.Start()) {
            throw "Unable to read committed blob: $Relative"
        }
        $memory = [IO.MemoryStream]::new()
        try {
            $process.StandardOutput.BaseStream.CopyTo($memory)
            $errorText = $process.StandardError.ReadToEnd()
            $process.WaitForExit()
            if ($process.ExitCode -ne 0) {
                throw "Unable to read committed blob $Relative`: $errorText"
            }
            return ,$memory.ToArray()
        } finally {
            $memory.Dispose()
            $process.Dispose()
        }
    }

    function Get-ByteSha256 {
        param([byte[]]$Bytes)

        $sha = [Security.Cryptography.SHA256]::Create()
        try {
            return -join ($sha.ComputeHash($Bytes) | ForEach-Object { $_.ToString('x2') })
        } finally {
            $sha.Dispose()
        }
    }

    $packageBytes = @{}
    $fileEvidence = @()
    foreach ($relative in $packageFiles) {
        [byte[]]$bytes = Get-GitBlobBytes $relative
        $packageBytes[$relative] = $bytes
        $fileEvidence += [ordered]@{
            path = $relative
            sha256 = Get-ByteSha256 $bytes
            bytes = $bytes.Length
        }
    }

    $main = [Text.Encoding]::UTF8.GetString($packageBytes['trusted-collab-tunnel.php'])
    if (
        $main -notmatch "Version:\s+$([regex]::Escape($Version))" -or
        $main -notmatch "define\('TCT_VERSION', '$([regex]::Escape($Version))'\)"
    ) {
        throw "Plugin header and TCT_VERSION must both equal $Version."
    }

    $manifest = [ordered]@{
        schema = 'tct-wordpress-package-manifest-v1'
        pluginVersion = $Version
        sourceCommit = $commit
        draftRevision = 'draft-jurkovikj-collab-tunnel-03'
        draftSourceSha256 = $draftSha256
        files = $fileEvidence
    }
    $manifestText = (($manifest | ConvertTo-Json -Depth 5) -replace "`r`n", "`n") + "`n"
    $manifestBytes = [Text.Encoding]::UTF8.GetBytes($manifestText)

    New-Item -ItemType Directory -Path $dist -Force | Out-Null
    $zipPath = Join-Path $dist "trusted-collab-tunnel-$Version-internal.zip"
    $proofPath = Join-Path $dist "trusted-collab-tunnel-$Version-internal.proof.zip"
    foreach ($path in @($zipPath, $proofPath)) {
        if (Test-Path -LiteralPath $path) {
            Remove-Item -LiteralPath $path -Force
        }
    }

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $fixedTime = [DateTimeOffset]::new(1980, 1, 1, 0, 0, 0, [TimeSpan]::Zero)

    function New-DeterministicZip {
        param([string]$Path)

        $stream = [IO.File]::Open($Path, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write)
        try {
            $archive = [IO.Compression.ZipArchive]::new(
                $stream,
                [IO.Compression.ZipArchiveMode]::Create,
                $false
            )
            try {
                foreach ($relative in $packageFiles) {
                    $entryName = 'trusted-collab-tunnel/' + $relative
                    $entry = $archive.CreateEntry(
                        $entryName,
                        [IO.Compression.CompressionLevel]::Optimal
                    )
                    $entry.LastWriteTime = $fixedTime
                    $entryStream = $entry.Open()
                    try {
                        $bytes = $packageBytes[$relative]
                        $entryStream.Write($bytes, 0, $bytes.Length)
                    } finally {
                        $entryStream.Dispose()
                    }
                }

                $manifestEntry = $archive.CreateEntry(
                    'trusted-collab-tunnel/package-manifest.json',
                    [IO.Compression.CompressionLevel]::Optimal
                )
                $manifestEntry.LastWriteTime = $fixedTime
                $manifestStream = $manifestEntry.Open()
                try {
                    $manifestStream.Write($manifestBytes, 0, $manifestBytes.Length)
                } finally {
                    $manifestStream.Dispose()
                }
            } finally {
                $archive.Dispose()
            }
        } finally {
            $stream.Dispose()
        }
    }

    New-DeterministicZip $zipPath
    New-DeterministicZip $proofPath
    $zipHash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    $proofHash = (Get-FileHash -LiteralPath $proofPath -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($zipHash -ne $proofHash) {
        throw 'Two clean builds from the same commit were not byte-identical.'
    }
    Remove-Item -LiteralPath $proofPath -Force

    $hashPath = "$zipPath.sha256"
    [IO.File]::WriteAllText(
        $hashPath,
        "$zipHash  $([IO.Path]::GetFileName($zipPath))`n",
        [Text.UTF8Encoding]::new($false)
    )

    [pscustomobject]@{
        ok = $true
        version = $Version
        source_commit = $commit
        draft_source_sha256 = $draftSha256
        zip = $zipPath
        zip_sha256 = $zipHash
        files = $packageFiles.Count + 1
        two_builds_byte_identical = $true
    } | ConvertTo-Json -Depth 3
} finally {
    Pop-Location
}
