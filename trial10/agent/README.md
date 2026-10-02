# Agency billing

Time tracking, invoicing, newsletters and bookkeeping for a Berlin-based agency group
(customers in Germany, France and the UK). Invoices go out as XRechnung
e-invoices; orders from the web shop are pushed to customers' ERPs; the
journal is exported for tax audits.

`var/` holds production artefacts collected by support and finance
(portal responses, till exports, ERP responses, payroll files, audit
reports). They are not used by the code or the tests.

## Glossary

* **ADRESS**: Agency Data Retention & Export Storage Service, the group's
  WORM document archive (S3 Object Lock bucket `adress-prod-eu1`). Every
  invoice PDF and every consent document is stored there; the DMS and the
  auditors look documents up by their ADRESS reference (`adress_ref`).
* **DMS**: document management system, consumes `Export\InvoiceFeed`.
