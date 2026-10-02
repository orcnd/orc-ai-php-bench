# Compatibility entry point; active scoring lives in the cross-platform runner.
# Example: -workspace C:/runs/candidate --phpstan C:/bench/vendor/bin/phpstan
python (Join-Path $PSScriptRoot '../trial2/runner.py') score @args
exit $LASTEXITCODE
