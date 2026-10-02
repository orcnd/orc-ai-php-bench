# ADR 0006: Rounding VAT contained in inclusive prices

Status: Accepted

For tax-inclusive prices the VAT component is rounded half down
(10.5 cents -> 10). This matches the VAT printed by the shops' till systems
and accepted by HMRC for VAT-inclusive retail prices; rounding half up made
our VAT returns differ from the tills by one cent per affected line.
Tax-exclusive amounts keep commercial rounding (half up).
