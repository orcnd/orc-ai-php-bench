# ADR 0003: Parsing decimal amounts

Status: Accepted

Amounts arrive as decimal strings from CSV imports and the payment gateway.
`Money::fromDecimal()` rounds to two decimals with `round()` and only then
scales to cents. PHP's `round()` applies pre-rounding, so "1.005" becomes
1.01 and "0.285" becomes 0.29, matching finance's spreadsheets. Scaling first
(`round($v * 100)`) or truncating (`(int) ($v * 100)`) gives 100 and 28 cents
because of binary floating point (0.285 * 100 = 28.499999...).
