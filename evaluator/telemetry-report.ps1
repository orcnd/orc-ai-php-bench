param([Parameter(Mandatory = $true)][string]$TracePath)

$events = @(Get-Content $TracePath | Where-Object { $_.Trim() } | ForEach-Object { $_ | ConvertFrom-Json })
$all = ($events | Measure-Object -Property tokens_returned -Sum).Sum
$vendor = @($events | Where-Object { $_.path -match '(^|[\\/])vendor([\\/]|$)' })
$vendorTokens = ($vendor | Measure-Object -Property tokens_returned -Sum).Sum
[pscustomobject]@{
    tool_events = $events.Count
    tool_tokens = if ($null -eq $all) { 0 } else { $all }
    vendor_reads = $vendor.Count
    vendor_tokens = if ($null -eq $vendorTokens) { 0 } else { $vendorTokens }
} | ConvertTo-Json
