# ERP integration

Orders are exported to the customers' ERP (Magento-compatible import).
The import recomputes the per-row distribution of order-level discounts
with the Magento algorithm (proportional share plus the previous row's
rounding carry) and rejects documents whose row discounts differ by a
single cent. Three identical rows sharing 119.75 therefore get
39.92 / 39.91 / 39.92, never 39.92 / 39.92 / 39.91.
