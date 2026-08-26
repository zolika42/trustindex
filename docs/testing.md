# Testing and quality strategy

The test suite is behavior-oriented. It deliberately avoids padding coverage with low-value accessor tests and instead protects query semantics, boundary rules and real HTTP/form behavior.

## Test layers

### Unit

`RatingClassifierTest` isolates the numeric threshold boundaries used by the company leaderboard. These tests are fast and require no Symfony kernel or database.

### Integration

`ReviewRepositoryTest` boots Doctrine against SQLite and exercises the query layer with real persisted rows. It protects:

- review ordering;
- average/count calculations;
- deterministic leaderboard tie-breaking;
- company search;
- rating filtering;
- interaction between filters.

### Functional

`ReviewFlowTest` drives the real BrowserKit HTTP stack and Symfony Form/Twig integration. It protects:

- form rendering;
- valid submission and persistence;
- exact required success flash;
- redirect/list/detail behavior;
- invalid required fields;
- missing rating;
- overlong email;
- reviewer-email privacy;
- search and rating filtering;
- aggregated company output.

## Database isolation

Tests use a dedicated SQLite database from `.env.test`. The database-reset helper recreates the schema so tests do not rely on a developer's local data.

## Coverage policy

`make coverage` creates `var/coverage.xml` and `bin/check-coverage.php` enforces the configured line threshold.

The default gate is **90%** for meaningful application code. The current suite exceeds the gate substantially; the threshold remains a guardrail rather than a target to game.

## Coding standards

`make cs` runs php-cs-fixer in dry-run mode with Symfony and Symfony-risky rule sets. `make cs-fix` applies fixes locally.

## Framework linting

`make lint` validates:

- Symfony service container;
- YAML configuration;
- Twig templates.

## Documentation quality

`make docs-smoke` is part of the quality contract. The documentation generator checks source PHPDoc coverage and required repository contracts, VitePress validates/builds the Markdown tree, and the smoke step checks expected HTML entry points.

## Full local gate

```bash
make ci
```

This is the preferred pre-push command. It validates Composer metadata, source style, Symfony/Twig/YAML configuration, tests, documentation and coverage.

## CI order

The GitHub Actions quality job installs pinned Composer dependencies, runs all PHP quality checks, builds the documentation portal and uploads its static HTML output. The disposable Docker application smoke test runs only after the quality job succeeds.
