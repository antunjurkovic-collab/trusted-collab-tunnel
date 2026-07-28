$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$checks = New-Object System.Collections.Generic.List[object]

function Add-Check {
    param([string]$Name, [bool]$Ok, [string]$Detail = '')
    $script:checks.Add([pscustomobject]@{ name = $Name; ok = $Ok; detail = $Detail }) | Out-Null
}

function Read-File([string]$Relative) {
    Get-Content (Join-Path $root $Relative) -Raw
}

$main = Read-File 'trusted-collab-tunnel.php'
$endpoint = Read-File 'includes/Endpoint.php'
$sitemap = Read-File 'includes/Sitemap.php'
$hashing = Read-File 'includes/Hashing.php'
$cache = Read-File 'includes/Cache.php'
$headLinks = Read-File 'includes/HeadLinks.php'
$auth = Read-File 'includes/Auth.php'
$receipt = Read-File 'includes/Receipt.php'
$stats = Read-File 'includes/Stats.php'
$changes = Read-File 'includes/Changes.php'
$protocol = Read-File 'src/Draft03/Protocol.php'
$sitemapDocument = Read-File 'src/Draft03/MSitemapDocument.php'
$jcs = Read-File 'src/Draft03/JcsEncoder.php'
$readme = Read-File 'README.md'
$baseline = Read-File 'docs/DRAFT03_PUBLISHED_BASELINE.md'
$doctor = Read-File 'src/Compatibility/Doctor/DeploymentDoctor.php'
$doctorPreflight = Read-File 'src/Compatibility/Doctor/BoundedJsonPreflight.php'
$doctorTransport = Read-File 'includes/Compatibility/WordPressHttpTransport.php'
$doctorSerializer = Read-File 'includes/Compatibility/WordPressReportSerializer.php'
$doctorAdmin = Read-File 'includes/Compatibility/CompatibilityAdminController.php'
$doctorStore = Read-File 'includes/Compatibility/DoctorReportStore.php'
$liveValidator = Read-File 'scripts/validate-live.ps1'
$packageBuilder = Read-File 'scripts/build-package.ps1'

$mUrlProfile = 'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-url-profile'
$sitemapProfile = 'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-sitemap-profile'

Add-Check 'version_alpha6' (
    $main -match 'Version:\s+3\.0\.0-alpha\.6' -and
    $main -match "define\('TCT_VERSION', '3\.0\.0-alpha\.6'\)"
)
Add-Check 'php_81_floor' ($main -match 'Requires PHP:\s+8\.1')
Add-Check 'runtime_autoloader' ($main -match "TCT\\\\Draft03\\\\" -and $main -match 'src/Draft03/')
Add-Check 'published_baseline_source_digest_pinned' (
    $baseline -match 'D106C6B10FAD897B434834D74682BF093E66A0A5AEA1DBF116691E301EF692FD'
)
Add-Check 'exact_murl_profile' ($protocol.Contains($mUrlProfile))
Add-Check 'exact_sitemap_profile' ($protocol.Contains($sitemapProfile))
Add-Check 'root_index_has_no_obsolete_profile_attribute' (
    $main -match 'rel="index"; type="application/json"' -and
    $main -notmatch 'rel="index"; type="application/json"; profile=' -and
    $main -match "add_action\('template_redirect', 'tct_add_root_link_header', -1\)"
)
Add-Check 'c_url_http_alternate_link' (
    $headLinks -match 'tct_add_c_url_alternate_header' -and
    $headLinks -match 'rel="alternate"; type="application/json"' -and
    $headLinks -match "add_action\('template_redirect', 'tct_add_c_url_alternate_header', -1\)"
)
Add-Check 'murl_bidirectional_links' (
    $endpoint -match 'rel="canonical"' -and
    $endpoint -match 'rel="profile"'
)
Add-Check 'murl_safe_methods_only' ($endpoint -match 'isSafeReadMethod' -and $endpoint -match 'Allow: GET, HEAD')
Add-Check 'identity_accept_encoding_gate' (
    $endpoint -match 'AcceptEncoding::identityIsAllowed' -and
    $sitemap -match 'AcceptEncoding::identityIsAllowed'
)
Add-Check 'single_exposure_gate' (
    $endpoint -match 'tct_post_is_exposable' -and
    $sitemap -match 'tct_post_is_exposable' -and
    $headLinks -match 'tct_post_is_exposable'
)
Add-Check 'murl_certified_final_document' (
    $hashing -match 'MUrlDocument::fromArray' -and
    $hashing -match 'IdentityRepresentation::fromValue'
)
Add-Check 'murl_exact_cached_identity' (
    $endpoint -match 'tct_get_cached_identity' -and
    $endpoint -match 'tct_build_murl_identity'
)
Add-Check 'murl_response_metadata' (
    $endpoint -match 'Content-Type:.*Protocol::CONTENT_TYPE' -and
    $endpoint -match 'Content-Digest:' -and
    $endpoint -match 'Content-Length:' -and
    $endpoint -match 'no-transform'
)
Add-Check 'murl_conditional_request_parser' ($endpoint -match 'ConditionalRequest::ifNoneMatchMatches')
Add-Check 'wordpress_if_none_match_unslashed' (
    $endpoint -match 'tct_if_none_match_request_value' -and
    $endpoint -match 'wp_unslash' -and
    $sitemap -match 'tct_if_none_match_request_value'
)
Add-Check 'plain_permalink_murl_route' (
    $hashing -match "add_query_arg\('tct_m_url'" -and
    $endpoint -match "get_query_var\('tct_m_url'\)" -and
    $main -match "update_option_permalink_structure" -and
    $main -match "\[\]\s*=\s*'tct_m_url'"
)
Add-Check 'home_path_prefix_removed_once_and_bounded' (
    $endpoint -match 'tct_request_path_relative_to_home' -and
    $endpoint -match 'TCT_MAX_REQUEST_PATH_BYTES' -and
    $endpoint -match 'str_starts_with\(\$request_path, \$home_prefix\)' -and
    $main -match 'tct_request_path_relative_to_home'
)
Add-Check 'protocol_diagnostics_not_displayed' (
    $main -match 'tct_is_protocol_response_request' -and
    $main -match "ini_set\('display_errors', '0'\)"
)
Add-Check 'sitemap_certified_profile_v2' (
    $sitemap -match 'MSitemapDocument::fromArray' -and
    $sitemap -match 'M_SITEMAP_VERSION' -and
    $sitemap -match 'M_SITEMAP_PROFILE'
)
Add-Check 'sitemap_hints_from_identity' (
    $sitemap -match '\$identity->catalogEtag\(\)' -and
    $sitemap -notmatch '_tct_etag'
)
Add-Check 'sitemap_rejects_index_member' (
    $sitemapDocument -match "array_key_exists\('sitemaps'" -and
    $sitemapDocument -match 'must not contain'
)
Add-Check 'sitemap_filter_cannot_rewrite_certified_core' (
    $sitemap -match '\$certified_core' -and
    $sitemap -match "'version', 'profile', 'items'" -and
    $sitemap -match 'must not modify version, profile, or items'
)
Add-Check 'sitemap_bounded_query' (
    $sitemap -match 'tct_sitemap_max_items' -and
    $sitemap -match '\$maximum_items \+ 1' -and
    $sitemap -notmatch "posts_per_page'\s*=>\s*-1"
)
Add-Check 'sitemap_own_etag_and_digest' (
    $sitemap -match 'tct_send_sitemap_identity_headers' -and
    $sitemap -match 'Content-Digest:' -and
    $sitemap -match 'rel="profile"'
)
Add-Check 'cache_body_validator_pair' (
    $cache.Contains("'body' => `$identity->body") -and
    $cache.Contains("'etag' => `$identity->etag") -and
    $cache -match 'hash_equals'
)
Add-Check 'cache_generation_versioned' (
    $cache -match 'tct_v03_cache_epoch' -and
    $cache -match 'Protocol::CACHE_NAMESPACE'
)
Add-Check 'recursive_block_extraction' (
    $hashing -match 'innerContent' -and
    $hashing -match 'innerBlocks' -and
    $hashing -match 'tct_extract_block_text_parts' -and
    $hashing.Contains('$depth + 1')
)
Add-Check 'jcs_bounded_before_output' (
    $jcs -match 'MAX_JSON_DEPTH' -and
    $jcs -match 'MAX_JSON_NODES' -and
    $jcs -match 'MAX_IDENTITY_BYTES'
)
Add-Check 'hashed_key_configuration' (
    $auth -match "get_option\('tct_api_key_hashes'" -and
    $auth.Contains("hash('sha256', `$candidate)") -and
    $auth -notmatch "get_option\('tct_api_keys'"
)
Add-Check 'auth_applies_to_murl_and_catalog' (
    $endpoint -match 'tct_auth_required' -and
    $sitemap -match 'tct_auth_required'
)
Add-Check 'receipt_secret_runtime_only' (
    $receipt -match "getenv\('TCT_RECEIPT_HMAC_KEY'\)" -and
    $receipt -notmatch "get_option\('tct_receipt_hmac_key'"
)
Add-Check 'stats_writes_opt_in' ($stats -match "get_option\('tct_stats_enabled', 0\)")
Add-Check 'changes_writes_opt_in' ($changes -match "get_option\('tct_changes_enabled', 0\)")
Add-Check 'readme_published_nonstable' (
    $readme -match '3\.0\.0-alpha\.6' -and
    $readme -match 'published' -and
    $readme -match 'non-stable|not.*production'
)
Add-Check 'doctor_additive_runtime_wiring' (
    $main -match "TCT\\\\Compatibility\\\\Doctor\\\\" -and
    $main -match 'src/Compatibility/Doctor/' -and
    $main -match 'includes/Compatibility/bootstrap.php'
)
Add-Check 'doctor_safe_raw_wordpress_transport' (
    $doctorTransport -match 'wp_safe_remote_request' -and
    $doctorTransport -match "'redirection'\s*=>\s*0" -and
    $doctorTransport -match "'decompress'\s*=>\s*false" -and
    $doctorTransport -match "'limit_response_size'" -and
    $doctorTransport -match "'cookies'\s*=>\s*\[\]"
)
Add-Check 'doctor_bounded_before_json_allocation' (
    $doctor -match 'BoundedJsonPreflight' -and
    $doctorPreflight -match 'MAX_JSON_DEPTH' -and
    $doctorPreflight -match 'MAX_JSON_NODES' -and
    $doctor -match 'JSON_THROW_ON_ERROR'
)
Add-Check 'doctor_authoritative_bounded_report' (
    $doctorSerializer -match 'wp_json_encode' -and
    $doctorSerializer -match 'maxDiagnosticTextBytes' -and
    $doctorSerializer -match 'maxDiagnosticBytes'
)
Add-Check 'doctor_admin_explicit_owner_scoped' (
    $doctorAdmin -match 'admin_post_tct_run_deployment_doctor' -and
    $doctorAdmin -match 'manage_options' -and
    $doctorAdmin -match 'check_admin_referer' -and
    $doctorStore -match '\$userId' -and
    $doctorStore -match 'random_bytes'
)
Add-Check 'doctor_no_adapter_or_purge_mutation' (
    $doctorAdmin -notmatch 'update_option|delete_option|tct_bump_cache_epoch|litespeed_purge|rocket_clean' -and
    $doctorTransport -notmatch 'update_option|delete_option|tct_bump_cache_epoch|litespeed_purge|rocket_clean'
)
Add-Check 'external_validator_raw_bounded' (
    $liveValidator -match 'AutomaticDecompression\s*=\s*\[Net\.DecompressionMethods\]::None' -and
    $liveValidator -match 'AllowAutoRedirect\s*=\s*\$false' -and
    $liveValidator -match 'ResponseHeadersRead' -and
    $liveValidator -match 'Read-BoundedBody' -and
    $liveValidator -match 'Expand-GzipBounded' -and
    $liveValidator -match 'unexpected_content_coding'
)
Add-Check 'external_validator_preserves_base_path' (
    $liveValidator -match 'base_url\s*=\s*\$baseUri\.AbsoluteUri\.TrimEnd'
)
Add-Check 'alpha6_package_includes_doctor_runtime' (
    $packageBuilder -match '\^includes/Compatibility/' -and
    $packageBuilder -match '\^src/Compatibility/Doctor/' -and
    $packageBuilder -match 'scripts/validate-live\.ps1' -and
    $packageBuilder -match 'requiredPackageFiles' -and
    $packageBuilder -match 'd106c6b10fad897b434834d74682bf093e66a0a5aea1dbf116691e301ef692fd'
)

$failed = @($checks | Where-Object { -not $_.ok })
foreach ($check in $checks) {
    $status = if ($check.ok) { 'PASS' } else { 'FAIL' }
    if ($check.detail) { "$status $($check.name) - $($check.detail)" } else { "$status $($check.name)" }
}

''
[pscustomobject]@{
    ok = ($failed.Count -eq 0)
    checks = $checks.Count
    failed = $failed.Count
    failed_names = @($failed | ForEach-Object { $_.name })
} | ConvertTo-Json -Depth 4

if ($failed.Count -gt 0) { exit 1 }
