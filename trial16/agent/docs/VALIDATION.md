# Validation

Property rules run first. A DTO's own `validate(): ?string` (cross-field
rules) runs only when every property rule passed: cross-field code assumes
its inputs are valid (dates parsed, amounts present). Its error is reported
under the key "".
