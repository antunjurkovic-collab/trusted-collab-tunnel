param(
    [string]$BaseUrl = 'https://llmpages.org',
    [int]$SampleMurls = 10,
    [switch]$CheckExtensions
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
$checks = New-Object System.Collections.Generic.List[object]

function Add-Check {
    param([string]$Name, [bool]$Ok, [string]$Detail = '')
    $script:checks.Add([pscustomobject]@{ name = $Name; ok = $Ok; detail = $Detail }) | Out-Null
}

function Invoke-Get {
    param([string]$Url, [hashtable]$Headers = @{})
    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri $Url -Headers $Headers -TimeoutSec 30 -ErrorAction Stop
        return [pscustomobject]@{ status = [int]$response.StatusCode; headers = $response.Headers; body = $response.Content }
    } catch {
        $resp = $_.Exception.Response
        if (-not $resp) { throw }
        $reader = New-Object IO.StreamReader($resp.GetResponseStream())
        return [pscustomobject]@{ status = [int]$resp.StatusCode; headers = $resp.Headers; body = $reader.ReadToEnd() }
    }
}

function Get-CurlStatus {
    param([string]$Url, [string]$Etag)
    $args = @('-s', '-D', '-', '-o', 'NUL')
    if ($Etag) { $args += @('-H', "If-None-Match: $Etag") }
    $args += $Url
    $lines = & curl.exe @args
    $statusLine = @($lines | Where-Object { $_ -match '^HTTP/' } | Select-Object -Last 1)[0]
    if ($statusLine -match '^HTTP/\S+\s+(\d+)') { return [int]$matches[1] }
    return 0
}

function Header-Value {
    param($Headers, [string]$Name)
    $value = $Headers[$Name]
    if ($null -eq $value) { return '' }
    return [string]$value
}

$root = Invoke-Get "$BaseUrl/"
$rootLink = Header-Value $root.headers 'Link'
Add-Check 'root_status_200' ($root.status -eq 200) "status=$($root.status)"
Add-Check 'root_link_profile_tct_1' ($rootLink -match 'profile="tct-1"') $rootLink

$sitemapUrl = "$BaseUrl/llm-sitemap.json?validate_live=$([Guid]::NewGuid().ToString('N'))"
$sitemapResp = Invoke-Get $sitemapUrl @{ 'Cache-Control' = 'no-cache'; 'Pragma' = 'no-cache' }
$sitemap = $null
try { $sitemap = $sitemapResp.body | ConvertFrom-Json } catch {}
Add-Check 'sitemap_status_200' ($sitemapResp.status -eq 200) "status=$($sitemapResp.status)"
Add-Check 'sitemap_json_parse' ($null -ne $sitemap)
Add-Check 'sitemap_has_etag' ([bool](Header-Value $sitemapResp.headers 'ETag'))
Add-Check 'sitemap_has_content_digest' ([bool](Header-Value $sitemapResp.headers 'Content-Digest'))

$items = @()
if ($sitemap) {
    $items = @($sitemap.items)
    Add-Check 'sitemap_version_2' ($sitemap.version -eq 2) "version=$($sitemap.version)"
    Add-Check 'sitemap_profile_tct_1' ($sitemap.profile -eq 'tct-1') "profile=$($sitemap.profile)"
    Add-Check 'sitemap_has_items' ($items.Count -gt 0) "count=$($items.Count)"

    $blog = @($items | Where-Object { [string]$_.mUrl -match '/blog/llm/?$' })
    Add-Check 'sitemap_excludes_blog_archive_murl' ($blog.Count -eq 0) "count=$($blog.Count)"

    $dups = @($items | Group-Object mUrl | Where-Object { $_.Count -gt 1 })
    Add-Check 'sitemap_has_no_duplicate_murls' ($dups.Count -eq 0) "duplicate_count=$($dups.Count)"

    $shapeBad = @($items | Where-Object {
        -not $_.cUrl -or -not $_.mUrl -or -not $_.etag -or -not $_.lastModified -or $_.PSObject.Properties.Name -contains 'hash' -or $_.PSObject.Properties.Name -contains 'modified'
    })
    Add-Check 'sitemap_item_shape_draft03' ($shapeBad.Count -eq 0) "bad_count=$($shapeBad.Count)"
}

$sample = @($items | Select-Object -First $SampleMurls)
foreach ($item in $sample) {
    $mUrl = [string]$item.mUrl
    $mResp = Invoke-Get $mUrl
    $mJson = $null
    try { $mJson = $mResp.body | ConvertFrom-Json } catch {}
    $headerEtag = Header-Value $mResp.headers 'ETag'
    $bareHeaderEtag = $headerEtag.Trim('"')
    $prefix = "murl:$mUrl"

    Add-Check "$prefix status_200" ($mResp.status -eq 200) "status=$($mResp.status)"
    Add-Check "$prefix json_parse" ($null -ne $mJson)
    Add-Check "$prefix has_etag" ([bool]$headerEtag)
    Add-Check "$prefix etag_matches_sitemap" ($bareHeaderEtag -eq [string]$item.etag) "header=$headerEtag sitemap=$($item.etag)"
    Add-Check "$prefix has_content_digest" ([bool](Header-Value $mResp.headers 'Content-Digest'))
    if ($mJson) {
        $props = @($mJson.PSObject.Properties.Name)
        Add-Check "$prefix body_profile_tct_1" ($mJson.profile -eq 'tct-1') "profile=$($mJson.profile)"
        Add-Check "$prefix body_no_hash_or_modified" (-not ($props -contains 'hash') -and -not ($props -contains 'modified'))
    }
    $revalidateStatus = Get-CurlStatus $mUrl $headerEtag
    Add-Check "$prefix if_none_match_304" ($revalidateStatus -eq 304) "status=$revalidateStatus"
}

if ($CheckExtensions) {
    foreach ($path in @('/llms.txt', '/llm-policy.json', '/llm-stats.json', '/llm-changes.json')) {
        $r = Invoke-Get "$BaseUrl$path"
        Add-Check "extension:$path status_200" ($r.status -eq 200) "status=$($r.status) bytes=$($r.body.Length)"
    }
}

$failed = @($checks | Where-Object { -not $_.ok })
foreach ($check in $checks) {
    $status = if ($check.ok) { 'PASS' } else { 'FAIL' }
    if ($check.detail) { "$status $($check.name) - $($check.detail)" } else { "$status $($check.name)" }
}

''
[pscustomobject]@{
    ok = ($failed.Count -eq 0)
    base_url = $BaseUrl
    checks = $checks.Count
    failed = $failed.Count
    failed_names = @($failed | ForEach-Object { $_.name })
} | ConvertTo-Json -Depth 5

if ($failed.Count -gt 0) { exit 1 }
