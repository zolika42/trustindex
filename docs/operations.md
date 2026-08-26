# Operations and troubleshooting

## Local runtime

The default non-Docker workflow is intentionally simple:

```bash
make setup
make serve
```

Application: `http://127.0.0.1:8000`

`make setup` installs Composer dependencies, rebuilds the local SQLite database, runs migrations, seeds deterministic demo rows and rebuilds documentation.

## Database reset

```bash
make db-reset
```

This removes only the local `var/app.db`, reruns committed migrations and seeds demo data. It does not touch the test database or any external service.

## Seed behavior

`app:seed-demo` is idempotent at dataset level: if any review already exists, it skips seeding. This prevents repeated local starts from duplicating the demo set.

## Docker application

```bash
make docker-up
make docker-logs
make docker-down
```

The app container exposes `http://127.0.0.1:8080` and stores its SQLite demo database in the named `demo_var` volume.

## Documentation portal

```bash
make docs
make docs-up
make docs-smoke
make docs-logs
make docs-down
```

The static site is served at `http://127.0.0.1:8088` by default through a dedicated Nginx service. `docs/dist` is mounted read-only.

If `8088` is already used by another local process/container, choose another host port without editing repository files:

```bash
make docs-up DOCS_PORT=8089
```

The same override works with `make docs-dev` and `make docker-up`.

## Common failures

### `vendor/autoload.php` missing

Run:

```bash
make install
```

The documentation generator also needs installed Composer dependencies because it reflects the actual application classes.

### SQLite driver missing

Check:

```bash
php -m | grep -i sqlite
```

Install/enable `pdo_sqlite` for the PHP runtime you are using.

### Coverage driver missing

`make coverage` requires Xdebug or PCOV in the **same PHP runtime** that executes `php bin/phpunit`.

Check:

```bash
php --version
php -m | grep -Ei 'xdebug|pcov'
```

On current macOS/Homebrew PHP installations the preferred Xdebug installer is PIE:

```bash
brew install pie
pie install xdebug/xdebug
php -v
```

Then rerun:

```bash
make coverage
```

CI already installs Xdebug explicitly, so this is a local workstation prerequisite rather than an application defect.

### Migration/schema mismatch

Run:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

If mapping changed intentionally, create and commit a migration rather than forcing the schema.

### `config/reference.php` appears as untracked

Symfony 7.4 auto-generates `config/reference.php` from the installed bundles to improve IDE completion and static analysis for PHP configuration. It is application source metadata, not disposable cache output.

Commit it when Symfony generates or updates it:

```bash
git add config/reference.php
git commit -m "chore: add Symfony configuration reference"
```

### VitePress / Node failure

Check:

```bash
node --version
npx --version
```

The repository pins the VitePress CLI version in the Makefile. No globally installed VitePress is required.

### Documentation build says PHPDoc is missing

This is intentional. Add a concise class/method PHPDoc that explains responsibility, contract or non-obvious behavior. Do not suppress the documentation guard for new application code.

### Port already in use

Default ports are:

- application PHP server: `8000`
- Docker demo: `8080`
- documentation: `8088`

On macOS, identify what owns the documentation port with:

```bash
lsof -nP -iTCP:8088 -sTCP:LISTEN
docker ps --filter publish=8088
```

If that service should remain running, use another documentation port instead of killing it:

```bash
make docs-up DOCS_PORT=8089
```

## CI troubleshooting order

When CI fails, investigate the **first failing quality step**, not later skipped jobs. The usual order is:

1. Composer metadata/install;
2. coding standards;
3. Symfony/Twig/YAML lint;
4. clean migration/schema validation;
5. PHPUnit;
6. coverage;
7. documentation generator/build/smoke;
8. disposable Docker application smoke test.
