param(
    [Parameter(Mandatory = $true)][string]$CaseId,
    [string]$TracePath = ""
)

$root = Split-Path -Parent $PSScriptRoot
$caseRoot = Join-Path $root "cases/$CaseId"
$cases = Get-Content (Join-Path $root 'evaluator/cases.json') | ConvertFrom-Json
$case = $cases | Where-Object { $_.id -eq $CaseId }
if ($null -eq $case) { throw "Unknown case: $CaseId" }
if (!(Test-Path $caseRoot)) { throw "Missing case workspace: $caseRoot" }

# CI mounts evaluator/hidden only after the agent has completed its turn.
# Docker is the syntax authority; PHPStan is the deterministic type/API gate.
$image = "php:$($case.php)-cli"
docker run --rm -v "${caseRoot}:/work" -w /work $image php -l app/Controllers/UserController.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

docker run --rm -v "${caseRoot}:/work" -w /work $image php evaluator/hidden/run.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Output "Runtime gate passed for $CaseId (PHP $($case.php)); run PHPStan with phpVersion $($case.phpstanVersion) and baseline delta before final scoring."
