# Contributing

* PHP 7.4 (production), code must also run warning-free on PHP 8.4.
* Soft deletes use the `App\Catalog\SoftDeletes` trait (`deletedAt`); never add
  `is_deleted`-style flags.
* Keep public APIs. Add tests under `tests/cases/`.
* Read `docs/` before changing behaviour.
