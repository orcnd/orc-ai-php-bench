param([string]$Root = (Split-Path -Parent $PSScriptRoot))

$ErrorActionPreference = 'Stop'
$php = (Get-Command php).Source
$result = [ordered]@{}

# The production runner executes this same suite in a PHP 7.4 container.
# Locally, PHPStan's hidden phpVersion configuration provides a deterministic
# compatibility gate; the container result replaces this field in CI.
& $php "$Root/evaluator/hidden_tests.php" | Out-Host
$result.hidden_tests_passed = ($LASTEXITCODE -eq 0)

$phpstan = Join-Path $Root 'vendor/bin/phpstan'
if (Test-Path $phpstan) {
    & $php "$phpstan" analyse -c "$Root/evaluator/phpstan.neon" --error-format=json | Set-Content "$Root/evaluator/phpstan-result.json"
    $result.phpstan_passed = ($LASTEXITCODE -eq 0)
} else {
    $result.phpstan_passed = $false
    $result.phpstan_note = 'phpstan unavailable; run composer install in evaluator image'
}
$result | ConvertTo-Json
