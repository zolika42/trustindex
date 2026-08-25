# Trustindex Reviews — Medior PHP Developer Test

A small but production-minded **Symfony 7.4 / PHP 8.2+** application for publishing and browsing company reviews.

The assignment intentionally asks for more than “it works”: clean structure, Symfony conventions, Doctrine ORM, forms/validation, aggregation logic and automated tests all matter. This repository therefore keeps the domain deliberately small while giving the delivery pipeline the same care as the application code.

## Highlights

- Public review list on `/`
- Symfony Form based review submission on `/reviews/new`
- Required validation:
  - every field is required
  - rating is an integer from 1 to 5
  - author email must be valid
- Exact success flash message: `Köszönjük a véleményed!`
- Review detail page on `/reviews/{id}`
- Company statistics on `/companies`
  - review count
  - average rating
  - descending average-rating ordering
  - deterministic tie-breakers
- Bonus company-name search
- Extra rating filter on the review feed
- Extra transparent rating signal (`Excellent`, `Very good`, etc.) on the company leaderboard
- Reviewer email is intentionally **not rendered publicly**
- Doctrine attribute mapping + committed migration
- SQLite by default for zero-friction evaluation
- Demo seed command
- PHPUnit unit, integration and functional tests
- Meaningful-code coverage gate (90%)
- php-cs-fixer with Symfony rules
- GitHub Actions CI
- Disposable Docker demo server smoke-tested in CI
- Makefile as the single developer entry point

## Requirements

- PHP 8.2+
- Composer 2
- PHP extensions: `ctype`, `iconv`, `pdo_sqlite`
- Optional:
  - Xdebug for local coverage
  - Docker + Docker Compose for the disposable demo environment

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

`make setup` installs dependencies, recreates the SQLite database, runs migrations and adds deterministic demo data.

## Docker demo server

The repository also contains a completely disposable, seeded test environment:

```bash
make docker-up
```

Then open http://127.0.0.1:8080/.

Stop it with:

```bash
make docker-down
```

This same container is built and smoke-tested by GitHub Actions after the PHP quality gate passes.

## Useful commands

```bash
make help             # list all commands
make install          # composer install
make setup            # install + database + seed
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
make qa               # cs + lint + PHPUnit
make ci               # local equivalent of the main quality gate

make docker-up
make docker-down
make docker-logs
```

The assignment explicitly requires the tests to work with:

```bash
php bin/phpunit
```

That command is supported directly.

## Database and migrations

The local application uses SQLite to keep evaluation fast and dependency-free.

Create the database and run the committed migration manually:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

The migration in `migrations/` represents the schema produced from the attribute-mapped `Review` entity. For future schema changes:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Architecture

The application follows standard Symfony responsibilities:

```text
src/
├── Command/
│   └── SeedDemoCommand.php
├── Controller/
│   ├── CompanyController.php
│   └── ReviewController.php
├── Entity/
│   └── Review.php
├── Form/
│   └── ReviewType.php
├── Repository/
│   └── ReviewRepository.php
└── Service/
    └── RatingClassifier.php
```

### Repository responsibilities

`ReviewRepository` owns query behavior rather than leaking QueryBuilder logic into controllers:

- review feed ordering
- company-name search
- rating filtering
- overall review statistics
- per-company `COUNT` + `AVG` aggregation
- deterministic leaderboard ordering

### Controller responsibilities

Controllers are intentionally thin:

- parse request filters
- coordinate form handling
- persist valid entities
- enrich already-calculated company statistics with a presentation label
- render responses

### Privacy choice

`author_email` is required and validated because the assignment asks for it, but it is never rendered on public review or company pages. A public review platform should not leak reviewer contact data as a side-effect of meeting a persistence requirement.

## Testing strategy

The suite avoids low-value getter/setter tests and targets behavior instead.

### Unit

`RatingClassifierTest`

Tests the boundaries where customer-facing rating labels change.

### Integration

`ReviewRepositoryTest`

Tests the important SQL/DQL behavior against a real SQLite database:

- average calculation
- review count
- descending average-rating order
- deterministic tie-break ordering
- company search + rating filter interaction

### Functional

`ReviewFlowTest`

Exercises the real HTTP/Form/Twig stack:

- valid review submission
- required flash message
- redirect and public listing
- review detail page
- invalid form rejection
- email privacy
- company search
- rating filter
- aggregated company statistics

### Coverage

The coverage gate deliberately targets code where tests provide engineering value:

- controllers
- forms
- repositories
- services

Entity accessors, the demo seed command and framework bootstrap are excluded from the coverage target rather than padded with meaningless tests.

```bash
make coverage
```

Default threshold: **90% line coverage**. Override locally if needed:

```bash
make coverage COVERAGE_MIN=95
```

## CI pipeline

`.github/workflows/ci.yml` runs on pushes to `main` and on pull requests.

Quality job:

1. PHP 8.2 environment
2. Composer metadata validation
3. dependency install
4. Symfony coding-standard check
5. container/YAML/Twig lint
6. clean database migration
7. Doctrine schema validation
8. PHPUnit
9. 90% meaningful-code coverage gate

Demo-server job:

1. build the Docker image
2. start a temporary seeded server
3. smoke-test `/`, `/companies` and `/reviews/new`
4. print container logs automatically on failure

No external test server credentials are required.

## Implementation notes / trade-offs

- **SQLite** is used by default because the assignment does not require a specific RDBMS and it makes reviewer setup almost instant.
- The data model stays exactly focused on the requested `Review` entity instead of introducing speculative company/user tables.
- Company statistics are derived from reviews in SQL/DQL; no duplicated aggregate state is stored.
- Search remains simple (`LIKE`) because the exercise dataset does not justify a search engine.
- Rating filtering is implemented as a small useful extra without changing the required data model.
- The project avoids unnecessary packages and frontend build tooling.
- Custom CSS keeps the UI polished while leaving the PHP/Symfony work easy to inspect.

## Approximate work log

This was developed as an AI-assisted pair-programming exercise; the following is an approximate active-effort breakdown rather than a claim of stopwatch precision.

| Area | Approx. effort |
|---|---:|
| Requirements analysis and project structure | 0:30 |
| Doctrine entity, migration and repository queries | 0:55 |
| Controllers, form handling and validation | 0:45 |
| Twig UI, responsive styling and UX pass | 0:55 |
| Unit/integration/functional tests | 0:55 |
| Makefile, Docker demo environment and CI | 0:45 |
| README, cleanup and review | 0:35 |
| **Total** | **5:20** |

## AI usage

AI assistance was used as a development tool for scaffolding, review, test-case ideation and documentation. The assignment explicitly allows AI assistance; the implementation is intentionally kept small and explainable so every architectural and code-level decision can be discussed live.

## What I would add in a real production iteration

Not required for this exercise, but natural next steps would be:

- authentication and verified reviewer identity
- moderation/reporting workflow
- pagination for large review volumes
- rate limiting / anti-spam protection
- database-specific full-text search when dataset size justifies it
- observability and error tracking
- deployment environment secrets and managed persistent database

---

Built for the Trustindex Medior PHP Developer test task.
