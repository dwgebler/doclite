# Contributing to DocLite

Thanks for your interest in DocLite. Bug reports, PRs, and discussion are all welcome.

## Local development

DocLite targets PHP 8.1+ with `pdo_sqlite`. A docker dev image is bundled
so you don't need to install PHP locally:

```bash
.docker/run.sh composer install
.docker/run.sh vendor/bin/phpunit
.docker/run.sh vendor/bin/phpcs
.docker/run.sh vendor/bin/psalm
```

To run against a specific PHP version (8.1, 8.2, 8.3, 8.4):

```bash
PHP_VERSION=8.4 .docker/run.sh vendor/bin/phpunit
```

If you already have a suitable PHP locally, the same commands work
without the wrapper.

## Branches

- Target `main` for bug fixes and small features.
- Long-running work goes on a `feature/<topic>` branch.
- Each PR should include passing tests (or a clear justification when
  not applicable).

## Tests

- Every bug fix needs a regression test under `tests/unit` or
  `tests/functional`.
- Functional tests hit a real SQLite database — please don't mock PDO
  in functional tests.
- New features should land with tests in both layers when both apply.

## Style

- PSR-12 (enforced by `vendor/bin/phpcs`).
- Pass `vendor/bin/psalm` at the configured baseline level.

## Releases

Maintainers tag releases as `vX.Y.Z` on `main` after CI passes.
