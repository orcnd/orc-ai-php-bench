# Compatibility policy

* Public method contracts (documented in docblocks) are relied on by
  third-party plugins. Do not change what a public method returns or throws;
  fix behaviour in the caller instead.
* New validation rules must not reject schemas or data that were accepted
  before: introduce them as warnings, and as errors only when the caller
  opts into strict mode.
* Upgraders (rule/expression rewriters) must never change an input they do
  not fully understand; leave it unchanged so a human can migrate it.
