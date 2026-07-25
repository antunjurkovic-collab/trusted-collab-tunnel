param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl,
    [string]$SitemapPath = '/llm-sitemap.json',
    [int]$SampleMurls = 10,
    [string]$ApiKey = '',
    [switch]$CheckExtensions
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
$checks = New-Object System.Collections.Generic.List[object]
$mUrlProfile = 'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-url-profile'
$sitemapProfile = 'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-sitemap-profile'
$utf8 = [Text.UTF8Encoding]::new($false, $true)
Add-Type -AssemblyName System.Net.Http
$httpHandler = [Net.Http.HttpClientHandler]::new()
$httpHandler.AutomaticDecompression = [Net.DecompressionMethods]::None
$httpClient = [Net.Http.HttpClient]::new($httpHandler)
$httpClient.Timeout = [TimeSpan]::FromSeconds(30)

function Add-Check {
    param([string]$Name, [bool]$Ok, [string]$Detail = '')
    $script:checks.Add([pscustomobject]@{ name = $Name; ok = $Ok; detail = $Detail }) | Out-Null
}

function Header-Value {
    param($Headers, [string]$Name)
    $value = $Headers[$Name]
    if ($null -eq $value) { return '' }
    return [string]$value
}

function Invoke-TctRequest {
    param(
        [string]$Url,
        [string]$Method = 'GET',
        [hashtable]$AdditionalHeaders = @{}
    )

    $headers = @{ 'Accept-Encoding' = 'identity' }
    if ($ApiKey -ne '') { $headers['X-API-Key'] = $ApiKey }
    foreach ($entry in $AdditionalHeaders.GetEnumerator()) {
        $headers[$entry.Key] = $entry.Value
    }

    $request = [Net.Http.HttpRequestMessage]::new(
        [Net.Http.HttpMethod]::new($Method),
        $Url
    )
    try {
        foreach ($entry in $headers.GetEnumerator()) {
            if (-not $request.Headers.TryAddWithoutValidation($entry.Key, $entry.Value)) {
                throw "Unable to add request header $($entry.Key)."
            }
        }

        $response = $script:httpClient.SendAsync(
            $request,
            [Net.Http.HttpCompletionOption]::ResponseHeadersRead
        ).GetAwaiter().GetResult()
        try {
            [byte[]]$bodyBytes = $response.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
            $responseHeaders = @{}
            foreach ($entry in $response.Headers) {
                $responseHeaders[$entry.Key] = [string]::Join(',', @($entry.Value))
            }
            foreach ($entry in $response.Content.Headers) {
                $responseHeaders[$entry.Key] = [string]::Join(',', @($entry.Value))
            }

            $body = ''
            if ($bodyBytes.Length -gt 0) {
                $body = $script:utf8.GetString($bodyBytes)
            }

            return [pscustomobject]@{
                status = [int]$response.StatusCode
                headers = $responseHeaders
                body = $body
                bodyBytes = $bodyBytes
            }
        } finally {
            $response.Dispose()
        }
    } finally {
        $request.Dispose()
    }
}

function Get-IdentityMetadata {
    param([byte[]]$Bytes)

    $sha = [Security.Cryptography.SHA256]::Create()
    try { $digest = $sha.ComputeHash($Bytes) } finally { $sha.Dispose() }
    $hex = -join ($digest | ForEach-Object { $_.ToString('x2') })
    return [pscustomobject]@{
        etag = "`"sha256-$hex`""
        contentDigest = 'sha-256=:' + [Convert]::ToBase64String($digest) + ':'
        bytes = $Bytes.Length
    }
}

function Test-IdentityResponse {
    param(
        [string]$Prefix,
        $Response,
        [string]$ExpectedProfile,
        [string]$RequiredProfileLink
    )

    $contentType = Header-Value $Response.headers 'Content-Type'
    $etag = Header-Value $Response.headers 'ETag'
    $digest = Header-Value $Response.headers 'Content-Digest'
    $link = Header-Value $Response.headers 'Link'
    $metadata = Get-IdentityMetadata $Response.bodyBytes
    $json = $null
    try { $json = $Response.body | ConvertFrom-Json } catch {}

    Add-Check "$Prefix status_200" ($Response.status -eq 200) "status=$($Response.status)"
    Add-Check "$Prefix content_type_exact" ($contentType -eq 'application/json') $contentType
    Add-Check "$Prefix json_parse" ($null -ne $json)
    Add-Check "$Prefix exact_profile" (
        $null -ne $json -and $json.profile -eq $ExpectedProfile
    ) "profile=$($json.profile)"
    Add-Check "$Prefix etag_exact_body_hash" ($etag -eq $metadata.etag) "actual=$etag expected=$($metadata.etag)"
    Add-Check "$Prefix digest_exact_body_hash" ($digest -eq $metadata.contentDigest) "actual=$digest expected=$($metadata.contentDigest)"
    Add-Check "$Prefix content_length" (
        (Header-Value $Response.headers 'Content-Length') -eq [string]$metadata.bytes
    )
    Add-Check "$Prefix profile_link" ($link -match [regex]::Escape($RequiredProfileLink)) $link
    Add-Check "$Prefix varies_accept_encoding" (
        (Header-Value $Response.headers 'Vary') -match '(?i)(^|,\s*)Accept-Encoding($|,)'
    )
    Add-Check "$Prefix no_transform" (
        (Header-Value $Response.headers 'Cache-Control') -match '(?i)(^|,|\s)no-transform($|,|\s)'
    )

    return [pscustomobject]@{ json = $json; etag = $etag; metadata = $metadata }
}

$root = Invoke-TctRequest "$BaseUrl/"
$rootLink = Header-Value $root.headers 'Link'
Add-Check 'root_status_200' ($root.status -eq 200) "status=$($root.status)"
Add-Check 'root_generic_index_link' (
    $rootLink -match 'rel="index"' -and
    $rootLink -match 'type="application/json"' -and
    $rootLink -notmatch 'profile='
) $rootLink

$sitemapUrl = "$BaseUrl/$($SitemapPath.TrimStart('/'))"
$sitemapResponse = Invoke-TctRequest $sitemapUrl 'GET' @{ 'Cache-Control' = 'no-cache' }
$sitemapResult = Test-IdentityResponse 'sitemap' $sitemapResponse $sitemapProfile $sitemapProfile
$items = @()
if ($sitemapResult.json) {
    $items = @($sitemapResult.json.items)
    Add-Check 'sitemap_version_2' ($sitemapResult.json.version -eq 2)
    $duplicateCurls = @($items | Group-Object cUrl | Where-Object { $_.Count -gt 1 })
    $duplicateMurls = @($items | Group-Object mUrl | Where-Object { $_.Count -gt 1 })
    Add-Check 'sitemap_unique_curls' ($duplicateCurls.Count -eq 0) "duplicates=$($duplicateCurls.Count)"
    Add-Check 'sitemap_unique_murls' ($duplicateMurls.Count -eq 0) "duplicates=$($duplicateMurls.Count)"
    $invalidHints = @($items | Where-Object {
        $_.etag -and [string]$_.etag -notmatch '^sha256-[0-9a-f]{64}$'
    })
    Add-Check 'sitemap_hint_shape' ($invalidHints.Count -eq 0) "invalid=$($invalidHints.Count)"
}

$sitemapConditional = Invoke-TctRequest $sitemapUrl 'GET' @{
    'If-None-Match' = $sitemapResult.etag
}
Add-Check 'sitemap_if_none_match_304' ($sitemapConditional.status -eq 304) "status=$($sitemapConditional.status)"
Add-Check 'sitemap_304_current_etag' (
    (Header-Value $sitemapConditional.headers 'ETag') -eq $sitemapResult.etag
)

foreach ($item in @($items | Select-Object -First $SampleMurls)) {
    $mUrl = [string]$item.mUrl
    $prefix = "murl:$mUrl"
    $response = Invoke-TctRequest $mUrl
    $result = Test-IdentityResponse $prefix $response $mUrlProfile $mUrlProfile
    $link = Header-Value $response.headers 'Link'
    Add-Check "$prefix canonical_link" ($link -match 'rel="canonical"') $link
    Add-Check "$prefix catalog_hint_matches" (
        $result.etag.Trim('"') -eq [string]$item.etag
    ) "header=$($result.etag) hint=$($item.etag)"

    $conditional = Invoke-TctRequest $mUrl 'GET' @{ 'If-None-Match' = $result.etag }
    Add-Check "$prefix if_none_match_304" ($conditional.status -eq 304) "status=$($conditional.status)"
    Add-Check "$prefix 304_current_etag" (
        (Header-Value $conditional.headers 'ETag') -eq $result.etag
    )

    $head = Invoke-TctRequest $mUrl 'HEAD'
    Add-Check "$prefix head_200" ($head.status -eq 200) "status=$($head.status)"
    Add-Check "$prefix head_same_etag" (
        (Header-Value $head.headers 'ETag') -eq $result.etag
    )
}

$methodProbe = Invoke-TctRequest $sitemapUrl 'POST'
Add-Check 'catalog_post_405' ($methodProbe.status -eq 405) "status=$($methodProbe.status)"
$encodingProbe = Invoke-TctRequest $sitemapUrl 'GET' @{ 'Accept-Encoding' = 'identity;q=0' }
Add-Check 'catalog_identity_forbidden_406' ($encodingProbe.status -eq 406) "status=$($encodingProbe.status)"

if ($CheckExtensions) {
    foreach ($path in @('/llms.txt', '/llm-policy.json', '/llm-stats.json', '/llm-changes.json')) {
        $response = Invoke-TctRequest "$BaseUrl$path"
        Add-Check "extension:$path reachable" ($response.status -eq 200) "status=$($response.status)"
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

$httpClient.Dispose()
$httpHandler.Dispose()
if ($failed.Count -gt 0) { exit 1 }
