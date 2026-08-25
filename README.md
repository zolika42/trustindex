# Trustindex Reviews — Medior PHP Developer Test

A small but production-minded **Symfony 7.4 / PHP 8.2+** application for publishing and browsing company reviews.

The assignment intentionally asks for more than “it works”: clean structure, Symfony conventions, Doctrine ORM, forms/validation, aggregation logic and automated tests all matter. This repository therefore keeps the domain deliberately small while giving the delivery pipeline, documentation and developer experience the same care as the application code.

## Highlights

- Public review list on `/`
- Symfony Form based review submission on `/reviews/new`
- Required validation for all fields, integer rating 1–5 and valid email
- Exact success flash message: `Köszönjük a véleményed!`
- Review detail page on `/reviews/{id}`
- Company statistics on `/companies` with deterministic average/count/name ordering
- Bonus company-name search and rating filter
- Reviewer email intentionally **never rendered publicly**
- Doctrine attribute mapping + committed migration
- SQLite by default for zero-friction evaluation
- PHPUnit unit, integration and functional tests
- Meaningful-code coverage gate (90%)
- php-cs-fixer + Symfony/Twig/YAML linting
- Disposable Docker demo server smoke-tested in CI
- **Self-documenting PHP source enforced by the documentation generator**
- **VitePress documentation portal with generated Developer Guide, handbook and code reference**
- **Documentation HTML built and uploaded by CI on every push/PR**
- Makefile as the single developer entry point

## Requirements

- PHP 8.2+
- Composer 2
- PHP extensions: `ctype`, `iconv`, `pdo_sqlite`
- Node.js + npm/npx for documentation generation
- GNU Make
- Optional: Xdebug for local coverage
- Optional: Docker + Docker Compose for the disposable app/docs environment

## Quick start

```bash
git clone https://github.com/zolika42/trustindex.git
cd trustindex

make setup
make serve
```

Open:

- http://127.0.0.1:8000/
- http://127.0.0.1:8000/companies
- http://127.0.0.1:8000/reviews/new

`make setup` installs dependencies, recreates the SQLite database, runs migrations, adds deterministic demo data **and rebuilds the documentation portal**.

## Documentation layer

The project uses the same documentation philosophy as the ColumbiaGames CCM v2 codebase: maintained architectural knowledge stays in Markdown, while facts that already exist in executable code are generated from the repository.

Canonical documentation sources:

- `docs/index.md` — documentation hub
- `docs/architecture/` — boundaries and responsibility map
- `docs/domain-model.md` — persisted model and invariants
- `docs/request-flows.md` — HTTP/form/query lifecycles
- `docs/testing.md` — testing and quality strategy
- `docs/operations.md` — local/Docker/CI troubleshooting
- `docs/documentation-standards.md` — mandatory documentation rules
- `docs/documentation-runtime.md` — automatic freshness pipeline
- PHPDoc under `src/` — code-level contracts consumed by generated reference

Generated on every docs build:

- `docs/DEVELOPER_GUIDE.md`
- `docs/DEVELOPER_HANDBOOK.md`
- `docs/code-reference/index.md`
- complete static HTML in `docs/dist/`

Generated files are ignored by Git and must never be edited manually.

```bash
make docs          # generate reference + build static HTML
make docs-smoke    # full documentation quality gate
make docs-dev      # live VitePress authoring server on :8088
make docs-up       # serve docs/dist through Nginx on :8088
make docs-down
make docs-logs
```

CI performs `make docs-smoke` and uploads `docs/dist` as the `trustindex-documentation-html` workflow artifact. Missing source PHPDoc or a broken documentation contract therefore fails CI rather than silently producing stale docs.

## Docker demo server

```bash
make docker-up
```

Then open:

- application: http://127.0.0.1:8080/
- documentation: http://127.0.0.1:8088/

Stop both with:

```bash
make docker-down
```

The application image is built and smoke-tested by GitHub Actions after the quality/documentation gate passes.

## Useful commands

```bash
make help             # list all commands
make install          # composer install
make setup            # install + database + seed + documentation
make serve            # local app on :8000
make db-reset         # recreate DB, migrate, seed
make seed             # add demo data if DB is empty

make test             # complete PHPUnit suite
make test-unit        # unit tests only
make test-functional  # integration + functional tests
make coverage         # Clover report + 90% meaningful-code gate

make cs               # dry-run Symfony coding standards
make cs-fix           # automatically fix formatting
make lint             # DI container + YAML + Twig lint
make qa               # cs + lint + PHPUnit + docs gate
make ci               # local equivalent of the main quality gate

make docs
make docs-smoke
make docs-dev
make docs-up
make docs-down
make docs-logs

make docker-up
make docker-down
make docker-logs
```

The assignment explicitly requires tests to work with `php bin/phpunit`; that command is supported directly.

## Database and migrations

The local application uses SQLite to keep evaluation fast and dependency-free. The migration in `migrations/` represents the schema produced from the attribute-mapped `Review` entity.

```bash
mkdir -p var
php bin/console doctrine:migrations:migrate --no-interaction
```

For future schema changes:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
```

## Architecture

```text
Browser
  -> Symfony Router
     -> thin Controllers
        -> Form + Validator -> Review entity -> Doctrine/SQLite
        -> ReviewRepository -> aggregate/filter queries
        -> RatingClassifier -> presentation band
     -> Twig -> HTML
```

Detailed architecture is maintained in `docs/architecture/index.md`; request-by-request behavior is documented in `docs/request-flows.md`.

### Repository responsibilities

`ReviewRepository` owns feed ordering, company-name search, rating filtering, overall statistics, per-company aggregation and deterministic leaderboard ordering. QueryBuilder logic does not leak into controllers or Twig.

### Controller responsibilities

Controllers parse request filters, coordinate form handling/repositories, persist valid entities and render responses. Invalid submitted forms return controlled HTTP 422 responses.

### Privacy choice

`author_email` is required and validated because the assignment asks for it, but it is never rendered on public review or company pages. Functional tests protect this boundary.

## Testing strategy

The suite targets behavior rather than padding coverage with trivial accessor tests.

- **Unit:** rating-classification boundaries.
- **Integration:** real SQLite repository aggregation/filter/order semantics.
- **Functional:** real HTTP/Form/Twig submission, validation, redirect, flash, list/detail, privacy and filters.

```bash
make test
make coverage
```

Default meaningful-code threshold: **90% line coverage**.

## CI pipeline

`.github/workflows/ci.yml` runs on pushes to `main` and pull requests.

Quality job:

1. PHP 8.2 + Composer environment
2. Node environment for documentation
3. strict Composer validation and lock-file install
4. Symfony coding-standard check
5. container/YAML/Twig lint
6. clean database migration + schema validation
7. PHPUnit
8. 90% meaningful-code coverage gate
9. generated documentation contract validation
10. VitePress static HTML build + smoke test
11. HTML documentation artifact upload

Demo-server job then builds the Docker application image, starts a temporary seeded server and smoke-tests `/`, `/companies` and `/reviews/new`.

## Implementation notes / trade-offs

- SQLite keeps evaluator setup immediate while migrations preserve disciplined schema evolution.
- Company statistics are derived in SQL/DQL; duplicated aggregate state is not stored.
- Search remains simple parameterized `LIKE` because the exercise dataset does not justify a search engine.
- The rating filter is a small extra without expanding the requested data model.
- Custom CSS keeps the UI polished while PHP/Symfony remains easy to inspect.
- Documentation facts that can be derived from code are generated rather than duplicated manually.
- Architectural intent remains maintained Markdown because intent cannot be reconstructed reliably from syntax.

## AI usage

AI assistance was used as a development tool for scaffolding, review, test-case ideation and documentation. The assignment explicitly allows AI assistance; the implementation is intentionally kept small and explainable so every architectural and code-level decision can be discussed live.

## What I would add in a real production iteration

- authentication and verified reviewer identity
- moderation/reporting workflow
- pagination for large review volumes
- rate limiting / anti-spam protection
- database-specific full-text search when justified
- observability and error tracking
- deployment secrets and managed persistent database

---

Built for the Trustindex Medior PHP Developer test task.
