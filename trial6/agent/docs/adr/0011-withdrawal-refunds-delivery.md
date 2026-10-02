# ADR 0011: Statutory withdrawal refunds delivery

Status: Accepted (2026-08-12, legal + finance)
Supersedes: ADR 0008

## Context

EU Directive 2011/83/EU, Art. 13: when a consumer withdraws within the
withdrawal period, the trader reimburses all payments received, including
the standard delivery cost. Our refusal to refund delivery generated
chargebacks and a regulator complaint.

## Decision

When a cancellation request cancels the last remaining merchandise unit of
an order (the order becomes fully cancelled) and that request is received
within the withdrawal period, the order's delivery charge (all `shipping`
lines) is refunded together with that request.

* Withdrawal period: until the end of the 14th calendar day after the day
  the order was placed, in the shop's timezone (Europe/Berlin). Example: an
  order placed on 3 March (Berlin time) can be withdrawn until 17 March
  23:59:59 Berlin time.
* Delivery is part of the customer's entitlement from that request on: it
  is subject to the same credit netting as merchandise
  (`max(0, ... + delivery)`), and it is refunded at most once.
* Requests completing a cancellation after the period never refund
  delivery, and partially cancelled orders never refund delivery.
