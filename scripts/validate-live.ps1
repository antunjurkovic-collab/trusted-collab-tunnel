param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl,
    [string]$SitemapPath = '/llm-sitemap.json',
    [ValidateRange(0, 3)]
    [int]$SampleMurls = 3,
    [string]$ApiKey = '',
    [switch]$CheckExtensions
)

$ErrorActionPreference = 'Stop'
$checks = New-Object System.Collections.Generic.List[object]
$mUrlProfile = 'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-url-profile'
$sitemapProfile = 'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-sitemap-profile'
$utf8 = [Text.UTF8Encoding]::new($false, $true)
$maxRequests = 20
$maxRedirects = 3
$maxResponseBytes = 16MB
$maxTotalResponseBytes = 32MB
$maxRequestSeconds = 10
$maxTotalSeconds = 60
$maxDiagnosticBytes = 2MB
$requestCount = 0
$redirectCount = 0
$totalResponseBytes = 0L
$stopwatch = [Diagnostics.Stopwatch]::StartNew()

try {
    $baseUri = [Uri]::new($BaseUrl.TrimEnd('/') + '/', [UriKind]::Absolute)
} catch {
    throw 'BaseUrl must be an absolute HTTP(S) URL.'
}
if ($baseUri.Scheme -notin @('http', 'https') -or -not [string]::IsNullOrEmpty($baseUri.UserInfo)) {
    throw 'BaseUrl must be an absolute HTTP(S) URL without user information.'
}
if (
    $SitemapPath -notmatch '^/' -or
    $SitemapPath -match '[?#\x00-\x20]' -or
    $SitemapPath.Length -gt 2048
) {
    throw 'SitemapPath must be a bounded absolute path.'
}

Add-Type -AssemblyName System.Net.Http
$httpHandler = [Net.Http.HttpClientHandler]::new()
$httpHandler.AutomaticDecompression = [Net.DecompressionMethods]::None
$httpHandler.AllowAutoRedirect = $false
$httpClient = [Net.Http.HttpClient]::new($httpHandler)
$httpClient.Timeout = [Threading.Timeout]::InfiniteTimeSpan

function Limit-Text {
    param([string]$Value, [int]$MaximumBytes = 4096)

    if ($null -eq $Value) { return '' }
    if ([Text.Encoding]::UTF8.GetByteCount($Value) -le $MaximumBytes) { return $Value }

    $marker = '...[truncated]'
    $builder = [Text.StringBuilder]::new()
    foreach ($character in $Value.ToCharArray()) {
        if (
            [Text.Encoding]::UTF8.GetByteCount($builder.ToString() + $character + $marker) -gt
            $MaximumBytes
        ) {
            break
        }
        [void]$builder.Append($character)
    }

    return $builder.ToString() + $marker
}

function Add-Check {
    param([string]$Name, [bool]$Ok, [string]$Detail = '', [string]$FailureCode = '')

    $script:checks.Add([pscustomobject]@{
        name = Limit-Text $Name 512
        ok = $Ok
        detail = Limit-Text $Detail
        failure_code = if ($Ok) { '' } else { Limit-Text $FailureCode 64 }
    }) | Out-Null
}

function Header-Value {
    param($Headers, [string]$Name)

    $value = $Headers[$Name]
    if ($null -eq $value) { return '' }
    return [string]$value
}

function Test-SameOrigin {
    param([Uri]$Uri)

    return (
        $Uri.Scheme -eq $script:baseUri.Scheme -and
        $Uri.IdnHost -eq $script:baseUri.IdnHost -and
        $Uri.Port -eq $script:baseUri.Port -and
        [string]::IsNullOrEmpty($Uri.UserInfo)
    )
}

function New-TransportFailure {
    param([Uri]$Uri, [string]$Code)

    return [pscustomobject]@{
        status = 0
        headers = @{}
        bodyBytes = [byte[]]::new(0)
        requestUri = $Uri.AbsoluteUri
        transportError = $Code
    }
}

function Read-BoundedBody {
    param($Response, [Threading.CancellationToken]$CancellationToken)

    if (
        $null -ne $Response.Content.Headers.ContentLength -and
        $Response.Content.Headers.ContentLength -gt $script:maxResponseBytes
    ) {
        return [pscustomobject]@{
            bytes = [byte[]]::new(0)
            error = 'response_bytes'
        }
    }

    $stream = $Response.Content.ReadAsStreamAsync().GetAwaiter().GetResult()
    $memory = [IO.MemoryStream]::new()
    $buffer = [byte[]]::new(81920)
    try {
        while ($true) {
            $read = $stream.ReadAsync(
                $buffer,
                0,
                $buffer.Length,
                $CancellationToken
            ).GetAwaiter().GetResult()
            if ($read -eq 0) { break }

            if ($memory.Length + $read -gt $script:maxResponseBytes) {
                return [pscustomobject]@{
                    bytes = [byte[]]::new(0)
                    error = 'response_bytes'
                }
            }
            if ($script:totalResponseBytes + $read -gt $script:maxTotalResponseBytes) {
                return [pscustomobject]@{
                    bytes = [byte[]]::new(0)
                    error = 'total_response_bytes'
                }
            }

            $memory.Write($buffer, 0, $read)
            $script:totalResponseBytes += $read
        }

        return [pscustomobject]@{ bytes = $memory.ToArray(); error = '' }
    } finally {
        $memory.Dispose()
        $stream.Dispose()
    }
}

function Invoke-TctRequest {
    param(
        [string]$Url,
        [string]$Method = 'GET',
        [hashtable]$AdditionalHeaders = @{}
    )

    try {
        $currentUri = [Uri]::new($Url, [UriKind]::Absolute)
    } catch {
        return New-TransportFailure $script:baseUri 'unsafe_url'
    }
    $currentMethod = $Method
    $redirects = 0

    while ($true) {
        if (-not (Test-SameOrigin $currentUri)) {
            return New-TransportFailure $currentUri 'cross_origin_redirect'
        }
        if ($script:requestCount -ge $script:maxRequests) {
            return New-TransportFailure $currentUri 'request_limit'
        }

        $remainingSeconds = $script:maxTotalSeconds - $script:stopwatch.Elapsed.TotalSeconds
        if ($remainingSeconds -le 0) {
            return New-TransportFailure $currentUri 'total_time'
        }

        $headers = @{ 'Accept-Encoding' = 'identity' }
        if ($ApiKey -ne '') { $headers['X-API-Key'] = $ApiKey }
        foreach ($entry in $AdditionalHeaders.GetEnumerator()) {
            $headers[$entry.Key] = $entry.Value
        }

        $request = [Net.Http.HttpRequestMessage]::new(
            [Net.Http.HttpMethod]::new($currentMethod),
            $currentUri
        )
        $cancellation = [Threading.CancellationTokenSource]::new(
            [TimeSpan]::FromSeconds([Math]::Min($script:maxRequestSeconds, $remainingSeconds))
        )
        try {
            foreach ($entry in $headers.GetEnumerator()) {
                if (
                    [string]$entry.Value -match '[\r\n\x00]' -or
                    -not $request.Headers.TryAddWithoutValidation($entry.Key, [string]$entry.Value)
                ) {
                    return New-TransportFailure $currentUri 'invalid_header'
                }
            }

            ++$script:requestCount
            try {
                $response = $script:httpClient.SendAsync(
                    $request,
                    [Net.Http.HttpCompletionOption]::ResponseHeadersRead,
                    $cancellation.Token
                ).GetAwaiter().GetResult()
            } catch [OperationCanceledException] {
                $code = if (
                    $script:stopwatch.Elapsed.TotalSeconds -ge $script:maxTotalSeconds
                ) { 'total_time' } else { 'transport_timeout' }
                return New-TransportFailure $currentUri $code
            } catch {
                return New-TransportFailure $currentUri 'transport_error'
            }

            try {
                $responseHeaders = @{}
                foreach ($entry in $response.Headers) {
                    $responseHeaders[$entry.Key] = Limit-Text (
                        [string]::Join(',', @($entry.Value))
                    ) 16384
                }
                foreach ($entry in $response.Content.Headers) {
                    $responseHeaders[$entry.Key] = Limit-Text (
                        [string]::Join(',', @($entry.Value))
                    ) 16384
                }

                $status = [int]$response.StatusCode
                if ($status -in @(301, 302, 303, 307, 308)) {
                    $location = Header-Value $responseHeaders 'Location'
                    if ($location -eq '') {
                        return [pscustomobject]@{
                            status = $status
                            headers = $responseHeaders
                            bodyBytes = [byte[]]::new(0)
                            requestUri = $currentUri.AbsoluteUri
                            transportError = ''
                        }
                    }
                    if ($redirects -ge $script:maxRedirects) {
                        return New-TransportFailure $currentUri 'redirect_limit'
                    }
                    try {
                        $nextUri = [Uri]::new($currentUri, $location)
                    } catch {
                        return New-TransportFailure $currentUri 'unsafe_url'
                    }
                    if (-not (Test-SameOrigin $nextUri)) {
                        return New-TransportFailure $nextUri 'cross_origin_redirect'
                    }

                    ++$redirects
                    ++$script:redirectCount
                    if (
                        $status -eq 303 -or
                        ($status -in @(301, 302) -and $currentMethod -notin @('GET', 'HEAD'))
                    ) {
                        $currentMethod = 'GET'
                    }
                    $currentUri = $nextUri
                    continue
                }

                try {
                    $bodyResult = Read-BoundedBody $response $cancellation.Token
                } catch [OperationCanceledException] {
                    $code = if (
                        $script:stopwatch.Elapsed.TotalSeconds -ge $script:maxTotalSeconds
                    ) { 'total_time' } else { 'transport_timeout' }
                    return New-TransportFailure $currentUri $code
                } catch {
                    return New-TransportFailure $currentUri 'transport_error'
                }
                if ($bodyResult.error -ne '') {
                    return New-TransportFailure $currentUri $bodyResult.error
                }

                return [pscustomobject]@{
                    status = $status
                    headers = $responseHeaders
                    bodyBytes = [byte[]]$bodyResult.bytes
                    requestUri = $currentUri.AbsoluteUri
                    transportError = ''
                }
            } finally {
                $response.Dispose()
            }
        } finally {
            $cancellation.Dispose()
            $request.Dispose()
        }
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

function Expand-GzipBounded {
    param([byte[]]$Bytes)

    $input = [IO.MemoryStream]::new($Bytes, $false)
    $output = [IO.MemoryStream]::new()
    try {
        try {
            $gzip = [IO.Compression.GZipStream]::new(
                $input,
                [IO.Compression.CompressionMode]::Decompress
            )
            try {
                $buffer = [byte[]]::new(81920)
                while ($true) {
                    $read = $gzip.Read($buffer, 0, $buffer.Length)
                    if ($read -eq 0) { break }
                    if ($output.Length + $read -gt $script:maxResponseBytes) {
                        return [pscustomobject]@{
                            bytes = [byte[]]::new(0)
                            error = 'gzip_decode_failed_or_limit'
                        }
                    }
                    $output.Write($buffer, 0, $read)
                }
                return [pscustomobject]@{ bytes = $output.ToArray(); error = '' }
            } finally {
                $gzip.Dispose()
            }
        } catch {
            return [pscustomobject]@{
                bytes = [byte[]]::new(0)
                error = 'gzip_decode_failed_or_limit'
            }
        }
    } finally {
        $output.Dispose()
        $input.Dispose()
    }
}

function Test-IdentityResponse {
    param(
        [string]$Prefix,
        $Response,
        [string]$ExpectedProfile,
        [string]$RequiredProfileLink
    )

    if ($Response.transportError -ne '') {
        Add-Check "$Prefix transport" $false $Response.transportError $Response.transportError
        return [pscustomobject]@{ json = $null; etag = ''; metadata = $null }
    }

    $contentType = Header-Value $Response.headers 'Content-Type'
    $etag = Header-Value $Response.headers 'ETag'
    $digest = Header-Value $Response.headers 'Content-Digest'
    $link = Header-Value $Response.headers 'Link'
    $contentEncoding = (Header-Value $Response.headers 'Content-Encoding').Trim().ToLowerInvariant()
    $metadata = Get-IdentityMetadata $Response.bodyBytes
    $json = $null
    $textError = ''

    if ($contentEncoding -eq '') {
        try {
            $body = $script:utf8.GetString($Response.bodyBytes)
            $json = $body | ConvertFrom-Json
        } catch {
            $textError = 'invalid_utf8_or_json'
        }
    } elseif ($contentEncoding -eq 'gzip') {
        $expanded = Expand-GzipBounded $Response.bodyBytes
        $textError = if ($expanded.error -eq '') {
            'unexpected_content_coding:gzip'
        } else {
            $expanded.error
        }
    } else {
        $textError = 'unsupported_content_coding'
    }

    Add-Check "$Prefix status_200" ($Response.status -eq 200) "status=$($Response.status)" 'http_status'
    Add-Check "$Prefix content_type_exact" ($contentType -eq 'application/json') $contentType 'invalid_header'
    Add-Check "$Prefix json_parse" ($null -ne $json) $textError $textError
    Add-Check "$Prefix exact_profile" (
        $null -ne $json -and $json.profile -eq $ExpectedProfile
    ) $(if ($null -eq $json) { $textError } else { "profile=$($json.profile)" }) 'invalid_schema'
    Add-Check "$Prefix etag_exact_body_hash" (
        $etag -eq $metadata.etag
    ) "actual=$etag expected=$($metadata.etag)" 'etag_mismatch'
    Add-Check "$Prefix digest_exact_body_hash" (
        $digest -eq $metadata.contentDigest
    ) "actual=$digest expected=$($metadata.contentDigest)" 'digest_mismatch'
    Add-Check "$Prefix content_length" (
        (Header-Value $Response.headers 'Content-Length') -eq [string]$metadata.bytes
    ) "actual=$(Header-Value $Response.headers 'Content-Length') expected=$($metadata.bytes)" 'length_mismatch'
    Add-Check "$Prefix identity_not_content_encoded" (
        $contentEncoding -eq ''
    ) $contentEncoding 'unexpected_content_coding'
    Add-Check "$Prefix profile_link" (
        $link -match [regex]::Escape($RequiredProfileLink)
    ) $link 'invalid_header'
    Add-Check "$Prefix varies_accept_encoding" (
        (Header-Value $Response.headers 'Vary') -match '(?i)(^|,\s*)Accept-Encoding($|,)'
    ) (Header-Value $Response.headers 'Vary') 'invalid_header'
    Add-Check "$Prefix no_transform" (
        (Header-Value $Response.headers 'Cache-Control') -match '(?i)(^|,|\s)no-transform($|,|\s)'
    ) (Header-Value $Response.headers 'Cache-Control') 'invalid_header'

    return [pscustomobject]@{ json = $json; etag = $etag; metadata = $metadata }
}

try {
    $root = Invoke-TctRequest $baseUri.AbsoluteUri
    Add-Check 'root_transport' ($root.transportError -eq '') $root.transportError $root.transportError
    $rootLink = Header-Value $root.headers 'Link'
    Add-Check 'root_status_200' ($root.status -eq 200) "status=$($root.status)" 'http_status'
    Add-Check 'root_generic_index_link' (
        $rootLink -match 'rel="index"' -and
        $rootLink -match 'type="application/json"' -and
        $rootLink -notmatch 'profile='
    ) $rootLink 'invalid_header'

    $sitemapUri = [Uri]::new(
        $baseUri.AbsoluteUri.TrimEnd('/') + '/' + $SitemapPath.TrimStart('/'),
        [UriKind]::Absolute
    )
    $sitemapUrl = $sitemapUri.AbsoluteUri
    $sitemapResponse = Invoke-TctRequest $sitemapUrl 'GET' @{ 'Cache-Control' = 'no-cache' }
    $sitemapResult = Test-IdentityResponse 'sitemap' $sitemapResponse $sitemapProfile $sitemapProfile
    $items = @()
    if ($sitemapResult.json) {
        $items = @($sitemapResult.json.items)
        Add-Check 'sitemap_version_2' ($sitemapResult.json.version -eq 2) '' 'invalid_schema'
        $duplicateCurls = @($items | Group-Object cUrl | Where-Object { $_.Count -gt 1 })
        $duplicateMurls = @($items | Group-Object mUrl | Where-Object { $_.Count -gt 1 })
        Add-Check 'sitemap_unique_curls' ($duplicateCurls.Count -eq 0) "duplicates=$($duplicateCurls.Count)" 'invalid_schema'
        Add-Check 'sitemap_unique_murls' ($duplicateMurls.Count -eq 0) "duplicates=$($duplicateMurls.Count)" 'invalid_schema'
        $invalidHints = @($items | Where-Object {
            $_.etag -and [string]$_.etag -notmatch '^sha256-[0-9a-f]{64}$'
        })
        Add-Check 'sitemap_hint_shape' ($invalidHints.Count -eq 0) "invalid=$($invalidHints.Count)" 'invalid_schema'
    }

    if ($sitemapResult.etag -ne '') {
        $sitemapConditional = Invoke-TctRequest $sitemapUrl 'GET' @{
            'If-None-Match' = $sitemapResult.etag
        }
        Add-Check 'sitemap_if_none_match_304' (
            $sitemapConditional.transportError -eq '' -and
            $sitemapConditional.status -eq 304
        ) "error=$($sitemapConditional.transportError); status=$($sitemapConditional.status)" 'conditional_mismatch'
        Add-Check 'sitemap_304_current_etag' (
            (Header-Value $sitemapConditional.headers 'ETag') -eq $sitemapResult.etag
        ) (Header-Value $sitemapConditional.headers 'ETag') 'etag_mismatch'
    } else {
        Add-Check 'sitemap_if_none_match_304' $false 'identity ETag unavailable' 'not_tested'
    }

    $sitemapCompressionProbe = Invoke-TctRequest $sitemapUrl 'GET' @{
        'Accept-Encoding' = 'gzip'
        'Cache-Control' = 'no-cache'
    }
    $sitemapCompressionResult = Test-IdentityResponse `
        'sitemap:gzip-advertised' `
        $sitemapCompressionProbe `
        $sitemapProfile `
        $sitemapProfile
    Add-Check 'sitemap_gzip_advertisement_same_identity_etag' (
        $sitemapCompressionResult.etag -ne '' -and
        $sitemapCompressionResult.etag -eq $sitemapResult.etag
    ) "gzip=$($sitemapCompressionResult.etag); identity=$($sitemapResult.etag)" 'etag_mismatch'

    $compressionMurlChecked = $false
    foreach ($item in @($items | Select-Object -First $SampleMurls)) {
        $mUrl = [string]$item.mUrl
        $prefix = "murl:$mUrl"
        $response = Invoke-TctRequest $mUrl
        $result = Test-IdentityResponse $prefix $response $mUrlProfile $mUrlProfile
        $link = Header-Value $response.headers 'Link'
        Add-Check "$prefix canonical_link" ($link -match 'rel="canonical"') $link 'invalid_header'
        Add-Check "$prefix catalog_hint_matches" (
            $result.etag -ne '' -and
            $result.etag.Trim('"') -eq [string]$item.etag
        ) "header=$($result.etag); hint=$($item.etag)" 'etag_mismatch'

        if ($result.etag -ne '') {
            $conditional = Invoke-TctRequest $mUrl 'GET' @{ 'If-None-Match' = $result.etag }
            Add-Check "$prefix if_none_match_304" (
                $conditional.transportError -eq '' -and $conditional.status -eq 304
            ) "error=$($conditional.transportError); status=$($conditional.status)" 'conditional_mismatch'
            Add-Check "$prefix 304_current_etag" (
                (Header-Value $conditional.headers 'ETag') -eq $result.etag
            ) (Header-Value $conditional.headers 'ETag') 'etag_mismatch'
        }

        $head = Invoke-TctRequest $mUrl 'HEAD'
        Add-Check "$prefix head_200" (
            $head.transportError -eq '' -and $head.status -eq 200
        ) "error=$($head.transportError); status=$($head.status)" 'head_mismatch'
        Add-Check "$prefix head_same_etag" (
            (Header-Value $head.headers 'ETag') -eq $result.etag
        ) (Header-Value $head.headers 'ETag') 'head_mismatch'

        if (-not $compressionMurlChecked) {
            $compressionResponse = Invoke-TctRequest $mUrl 'GET' @{
                'Accept-Encoding' = 'gzip'
                'Cache-Control' = 'no-cache'
            }
            $compressionResult = Test-IdentityResponse `
                "$prefix`:gzip-advertised" `
                $compressionResponse `
                $mUrlProfile `
                $mUrlProfile
            Add-Check "$prefix gzip_advertisement_same_identity_etag" (
                $compressionResult.etag -ne '' -and
                $compressionResult.etag -eq $result.etag
            ) "gzip=$($compressionResult.etag); identity=$($result.etag)" 'etag_mismatch'
            $compressionMurlChecked = $true
        }
    }

    $methodProbe = Invoke-TctRequest $sitemapUrl 'POST'
    Add-Check 'catalog_post_405' (
        $methodProbe.transportError -eq '' -and $methodProbe.status -eq 405
    ) "error=$($methodProbe.transportError); status=$($methodProbe.status)" 'http_status'
    $encodingProbe = Invoke-TctRequest $sitemapUrl 'GET' @{
        'Accept-Encoding' = 'identity;q=0'
    }
    Add-Check 'catalog_identity_forbidden_406' (
        $encodingProbe.transportError -eq '' -and $encodingProbe.status -eq 406
    ) "error=$($encodingProbe.transportError); status=$($encodingProbe.status)" 'http_status'

    if ($CheckExtensions) {
        foreach ($path in @('/llms.txt', '/llm-policy.json', '/llm-stats.json', '/llm-changes.json')) {
            $extensionUri = [Uri]::new(
                $baseUri.AbsoluteUri.TrimEnd('/') + '/' + $path.TrimStart('/'),
                [UriKind]::Absolute
            )
            $response = Invoke-TctRequest $extensionUri.AbsoluteUri
            Add-Check "extension:$path reachable" (
                $response.transportError -eq '' -and $response.status -eq 200
            ) "error=$($response.transportError); status=$($response.status)" 'http_status'
        }
    }
} finally {
    $stopwatch.Stop()
    $httpClient.Dispose()
    $httpHandler.Dispose()
}

$failed = @($checks | Where-Object { -not $_.ok })
foreach ($check in $checks) {
    $status = if ($check.ok) { 'PASS' } else { 'FAIL' }
    $suffix = ''
    if ($check.failure_code) { $suffix += " [$($check.failure_code)]" }
    if ($check.detail) { $suffix += " - $($check.detail)" }
    "$status $($check.name)$suffix"
}

''
$summary = [pscustomobject]@{
    schema = 'tct-external-validator-report-v1'
    ok = ($failed.Count -eq 0)
    base_url = $baseUri.AbsoluteUri.TrimEnd('/')
    checks = $checks.Count
    failed = $failed.Count
    failed_names = @($failed | ForEach-Object { $_.name })
    counters = @{
        requests = $requestCount
        redirects = $redirectCount
        response_bytes = $totalResponseBytes
        sampled_murls = [Math]::Min($SampleMurls, $items.Count)
        elapsed_seconds = [Math]::Round($stopwatch.Elapsed.TotalSeconds, 3)
    }
}
$summaryJson = $summary | ConvertTo-Json -Depth 6 -Compress
if ([Text.Encoding]::UTF8.GetByteCount($summaryJson) -gt $maxDiagnosticBytes) {
    throw 'diagnostic_bytes'
}
$summaryJson

if ($failed.Count -gt 0) { exit 1 }
