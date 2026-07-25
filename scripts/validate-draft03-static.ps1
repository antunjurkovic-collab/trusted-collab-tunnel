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
$receipt = Read-File 'includes/Receipt.php'
$stats = Read-File 'includes/Stats.php'
$changes = Read-File 'includes/Changes.php'
$auth = Read-File 'includes/Auth.php'
$adminCache = Read-File 'includes/AdminCache.php'
$readme = Read-File 'README.md'

Add-Check 'version_alpha' ($main -match '3\.0\.0-alpha\.1')
Add-Check 'root_link_profile' ($main -match 'profile="tct-1"')
Add-Check 'narrow_route_matching' ($main -match 'preg_match\(' -and $main -notmatch 'strpos\(\$uri')
Add-Check 'canonical_encoder_exists' ($hashing -match 'function tct_canonical_json_encode')
Add-Check 'hash_uses_canonical_encoder' ($hashing.Contains("hash('sha256', tct_canonical_json_encode(`$payload))"))
Add-Check 'filter_hash_recomputed' ($hashing -match 'ignore caller-provided hashes' -and $hashing -match 'tct_compute_hash_from_json\(\$payload\)')
Add-Check 'endpoint_body_uses_canonical_encoder' ($endpoint -match '\$body = tct_canonical_json_encode\(\$payload\);')
Add-Check 'endpoint_content_digest' ($endpoint -match 'Content-Digest: sha-256=')
Add-Check 'endpoint_no_body_hash_field' ($endpoint -notmatch "'hash'\s*=>")
Add-Check 'sitemap_lastModified' ($sitemap -match "'lastModified'" -and $sitemap -notmatch "'modified'\s*=>")
Add-Check 'sitemap_canonical_json' ($sitemap -match '\$json = tct_canonical_json_encode\(\$out\);')
Add-Check 'sitemap_excludes_posts_page_archive' ($sitemap -match "get_option\('page_for_posts'\)" -and $sitemap -match '\$post_not_in\[\] = \$posts_page_id;')
Add-Check 'sitemap_excludes_explicit_front_page_duplicate' ($sitemap -match "get_option\('page_on_front'\)" -and $sitemap -match '\$post_not_in\[\] = \$front_page_id;')
Add-Check 'admin_clear_cache_clears_active_sitemap_transients' ($adminCache -match "delete_transient\('tct_sitemap_json_v3'\)" -and $adminCache -match "delete_transient\('tct_sitemap_etag_v3'\)")
Add-Check 'stats_writes_opt_in' ($stats -match "get_option\('tct_stats_enabled', 0\)")
Add-Check 'changes_writes_opt_in' ($changes -match "get_option\('tct_changes_enabled', 0\)")
Add-Check 'receipt_contract_sanitized' ($receipt -match 'function tct_receipt_contract_id' -and $receipt -match '\^\[A-Za-z0-9\._:-\]\{1,128\}\$')
Add-Check 'api_keys_constant_time_compare' ($auth -match 'hash_equals')
Add-Check 'readme_draft03_alpha' ($readme -match 'draft-03 alignment workspace' -and $readme -match 'not.*production-grade')

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
