param(
    [string]$TracePath = "",
    [string]$RuntimePhp = ""
)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$php = (Get-Command php).Source
$breakdown = [ordered]@{}
$raw = & $php "$root/evaluator/adversarial.php"
if ($LASTEXITCODE -ne 0) { throw 'Evaluator did not produce valid results.' }
$suite = ($raw -join [Environment]::NewLine) | ConvertFrom-Json
$weights = [ordered]@{ business = 40; rounding = 20; regression = 15 }
foreach ($group in $weights.Keys) {
    $cases = @($suite.groups.$group)
    $passed = @($cases | Where-Object { $_.passed }).Count
    $breakdown[$group] = [ordered]@{
        points = [math]::Round($weights[$group] * $passed / $cases.Count, 2)
        maximum = $weights[$group]
        passed = $passed
        total = $cases.Count
        failures = @($cases | Where-Object { !$_.passed })
    }
}

# Never label a host PHP 8.x lint as PHP 7.4 compatibility.
$runtimePoints = 0
$runtimeStatus = 'not_measured'
if ($RuntimePhp) {
    $version = & $RuntimePhp -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;'
    if ($LASTEXITCODE -ne 0 -or $version -ne '7.4') { throw 'RuntimePhp must point to PHP 7.4.' }
    $lintPassed = $true
    Get-ChildItem "$root/app" -Recurse -Filter '*.php' | ForEach-Object {
        & $RuntimePhp -l $_.FullName | Out-Host
        if ($LASTEXITCODE -ne 0) { $lintPassed = $false }
    }
    $runtimeStatus = 'failed'
    if ($lintPassed) {
        & $RuntimePhp "$root/tests/run.php" | Out-Host
        if ($LASTEXITCODE -eq 0) { $runtimePoints = 10; $runtimeStatus = 'passed' }
    }
}
$breakdown.runtime = @{ points = $runtimePoints; maximum = 10; status = $runtimeStatus }

$staticPoints = 0
$staticStatus = 'not_measured'
$stan = "$root/vendor/bin/phpstan"
if (Test-Path $stan) {
    $staticRaw = & $php $stan analyse -c "$root/evaluator/phpstan.neon" --error-format=json --no-progress
    $staticExit = $LASTEXITCODE
    if ($staticExit -gt 1) { throw 'PHPStan failed to run.' }
    $analysis = ($staticRaw -join [Environment]::NewLine) | ConvertFrom-Json
    $baseline = Get-Content "$root/evaluator/phpstan-baseline.json" -Raw | ConvertFrom-Json
    $newErrors = [math]::Max(0, $analysis.totals.file_errors + $analysis.totals.errors - $baseline.known_error_count)
    $staticPoints = [math]::Max(0, 10 - 2 * $newErrors)
    $staticStatus = 'measured'
}
$breakdown.phpstan = @{ points = $staticPoints; maximum = 10; status = $staticStatus }

$contextPoints = 0
$contextStatus = 'not_measured'
if ($TracePath -and (Test-Path $TracePath)) {
    $events = @(Get-Content $TracePath | Where-Object { $_.Trim() } | ForEach-Object { $_ | ConvertFrom-Json })
    # Empty traces never prove efficiency.
    if ($events.Count -gt 0) {
        $totalTokens = ($events | Measure-Object tokens_returned -Sum).Sum
        $vendorTokens = ($events | Where-Object { $_.path -match '(^|[\\/])vendor([\\/]|$)' } | Measure-Object tokens_returned -Sum).Sum
        if ($totalTokens -gt 0) {
            $contextPoints = [math]::Round(5 * (1 - $vendorTokens / $totalTokens), 2)
            $contextStatus = 'measured_tool_tokens_only'
        }
    }
}
$breakdown.context = @{ points = $contextPoints; maximum = 5; status = $contextStatus }
$score = 0
foreach ($entry in $breakdown.Values) { $score += $entry.points }
[ordered]@{
    score = "$score/100"
    score_numeric = $score
    complete_measurement = ($runtimeStatus -ne 'not_measured' -and $staticStatus -ne 'not_measured' -and $contextStatus -ne 'not_measured')
    interpretation = 'Current artifact score; not an independent model benchmark run.'
    breakdown = $breakdown
} | ConvertTo-Json -Depth 8
