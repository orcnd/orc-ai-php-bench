# Changelog

## 3.4.0 (2026-07)
- Payroll export uses the weekly report keys directly.
- New: audit export of the journal.

## 3.2.1 (2025-11)
- Reverted 3.2.0 "bill time entries from exact minutes": customer portals
  rejected every XRechnung invoice containing short entries (BR-CO-10).
  See #87.

## 3.2.0 (2025-11)
- Bill time entries from exact minutes instead of rounded hours.

## 3.1.0 (2025-06)
- ERP push: order discounts are split per row like Magento does
  (proportional share plus the previous row's rounding remainder); the ERP
  re-validates each row and rejects orders otherwise. See #71.
- Inclusive VAT: switched back to half-down rounding to match the shops'
  till reports (#64).

## 3.0.0 (2025-01)
- Invoice numbers: deleting validated invoices removed (gaps failed the
  2024 tax audit); use credit notes.
- Journal: corrections are booked as reversal + new entry (audit finding).
