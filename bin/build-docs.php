#!/usr/bin/env php
<?php

declare(strict_types=1);

use Symfony\Component\Routing\Attribute\Route;

$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(\STDERR, "[docs] vendor/autoload.php is missing. Run `make install` first.\n");
    exit(1);
}

require $autoload;

$requiredFiles = [
    'composer.json',
    'Makefile',
    'compose.yaml',
    '.github/workflows/ci.yml',
    'docs/index.md',
    'docs/architecture/index.md',
    'docs/domain-model.md',
    'docs/request-flows.md',
    'docs/testing.md',
    'docs/operations.md',
    'docs/documentation-standards.md',
    'docs/documentation-runtime.md',
    'docs/reference/index.md',
];

foreach ($requiredFiles as $requiredFile) {
    if (!is_file($root.'/'.$requiredFile)) {
        fwrite(\STDERR, "[docs] Required documentation source is missing: {$requiredFile}\n");
        exit(1);
    }
}

/**
 * Extracts concrete Make targets so generated onboarding never advertises deleted commands.
 *
 * @return list<string>
 */
function parseMakeTargets(string $source): array
{
    preg_match_all('/^([a-zA-Z0-9_-]+):/m', $source, $matches);

    return array_values(array_unique(array_filter(
        $matches[1],
        static fn (string $target): bool => !str_contains($target, '%') && '.PHONY' !== $target,
    )));
}

/**
 * Converts the PSR-4 `src/` tree into application class names without maintaining a second inventory.
 *
 * @return list<string>
 */
function discoverApplicationClasses(string $root): array
{
    $classes = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src'));

    foreach ($iterator as $file) {
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($root.'/src/'));
        $classes[] = 'App\\'.str_replace('/', '\\', substr($relative, 0, -4));
    }

    sort($classes);

    return $classes;
}

/**
 * Reduces a PHPDoc block to its first prose paragraph for compact generated tables/reference pages.
 */
function docSummary(string|false $docComment): string
{
    if (false === $docComment) {
        return '';
    }

    $summary = [];
    foreach (preg_split('/\R/', $docComment) ?: [] as $line) {
        $line = trim(preg_replace('/^\s*\/\*\*|\*\/\s*$|^\s*\*\s?/', '', $line) ?? '');

        if ('' === $line && [] !== $summary) {
            break;
        }
        if ('' === $line || str_starts_with($line, '@')) {
            continue;
        }

        $summary[] = $line;
    }

    return implode(' ', $summary);
}

/**
 * Builds a readable Reflection method signature for the generated source reference.
 */
function methodSignature(ReflectionMethod $method): string
{
    $visibility = $method->isPublic() ? 'public' : ($method->isProtected() ? 'protected' : 'private');
    $static = $method->isStatic() ? ' static' : '';
    $parameters = [];

    foreach ($method->getParameters() as $parameter) {
        $type = null === $parameter->getType() ? '' : (string) $parameter->getType().' ';
        $parameters[] = $type
            .($parameter->isPassedByReference() ? '&' : '')
            .($parameter->isVariadic() ? '...' : '')
            .'$'.$parameter->getName()
            .($parameter->isOptional() && !$parameter->isVariadic() ? ' = …' : '');
    }

    $returnType = null === $method->getReturnType() ? '' : ': '.$method->getReturnType();

    return sprintf(
        '%s%s function %s(%s)%s',
        $visibility,
        $static,
        $method->getName(),
        implode(', ', $parameters),
        $returnType,
    );
}

/**
 * Enforces the self-documenting-source policy and prevents generated output from becoming tracked state.
 *
 * @param list<string> $classes
 */
function validateSourceDocumentation(array $classes, string $root): void
{
    $failures = [];

    foreach ($classes as $className) {
        if (!class_exists($className)) {
            $failures[] = "{$className}: class cannot be autoloaded";
            continue;
        }

        $class = new ReflectionClass($className);
        if (false === $class->getDocComment()) {
            $failures[] = "{$className}: missing class PHPDoc";
        }

        $classFile = realpath((string) $class->getFileName());
        foreach ($class->getMethods() as $method) {
            if ($method->isAbstract() || realpath((string) $method->getFileName()) !== $classFile) {
                continue;
            }
            if (false === $method->getDocComment()) {
                $failures[] = "{$className}::{$method->getName()}(): missing method PHPDoc";
            }
        }
    }

    if ([] !== $failures) {
        fwrite(\STDERR, "[docs] Source documentation contract failed:\n - ".implode("\n - ", $failures)."\n");
        exit(1);
    }

    $tracked = [];
    exec('git -C '.escapeshellarg($root).' ls-files docs 2>/dev/null', $tracked, $status);
    if (0 !== $status) {
        return;
    }

    foreach ($tracked as $path) {
        $generated = in_array($path, ['docs/DEVELOPER_GUIDE.md', 'docs/DEVELOPER_HANDBOOK.md'], true)
            || str_starts_with($path, 'docs/code-reference/')
            || str_starts_with($path, 'docs/dist/');

        if ($generated) {
            fwrite(\STDERR, "[docs] Generated output must not be tracked: {$path}\n");
            exit(1);
        }
    }
}

/**
 * Reflects Symfony route attributes so the generated guide always lists the current HTTP surface.
 *
 * @param list<string> $classes
 *
 * @return list<array{method: string, path: string, name: string, handler: string}>
 */
function discoverRoutes(array $classes): array
{
    $routes = [];

    foreach ($classes as $className) {
        $class = new ReflectionClass($className);
        foreach ($class->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $routeAttribute) {
                /** @var Route $route */
                $route = $routeAttribute->newInstance();
                $methods = $route->getMethods();
                $routes[] = [
                    'method' => [] === $methods ? 'ANY' : implode('|', $methods),
                    'path' => (string) $route->getPath(),
                    'name' => (string) $route->getName(),
                    'handler' => $class->getShortName().'::'.$method->getName(),
                ];
            }
        }
    }

    usort(
        $routes,
        static fn (array $left, array $right): int => [$left['path'], $left['method']] <=> [$right['path'], $right['method']],
    );

    return $routes;
}

$makefile = file_get_contents($root.'/Makefile');
if (false === $makefile) {
    throw new RuntimeException('Cannot read Makefile.');
}

$makeTargets = parseMakeTargets($makefile);
$requiredTargets = [
    'install', 'setup', 'serve', 'db-reset', 'seed', 'test', 'coverage', 'cs', 'lint',
    'qa', 'ci', 'docker-up', 'docker-down', 'docs', 'docs-smoke', 'docs-up', 'docs-down',
];
$missingTargets = array_values(array_diff($requiredTargets, $makeTargets));
if ([] !== $missingTargets) {
    fwrite(\STDERR, '[docs] Makefile targets referenced by documentation are missing: '.implode(', ', $missingTargets)."\n");
    exit(1);
}

$compose = file_get_contents($root.'/compose.yaml') ?: '';
foreach (['8080:8000', '8088:80'] as $portContract) {
    if (!str_contains($compose, $portContract)) {
        fwrite(\STDERR, "[docs] compose.yaml no longer contains documented port contract {$portContract}.\n");
        exit(1);
    }
}

/** @var array{require?: array<string, string>} $composer */
$composer = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
$classes = discoverApplicationClasses($root);
validateSourceDocumentation($classes, $root);
$routes = discoverRoutes($classes);

$requirementRows = [];
foreach (($composer['require'] ?? []) as $package => $constraint) {
    $requirementRows[] = "| `{$package}` | `{$constraint}` |";
}

$routeRows = array_map(
    static fn (array $route): string => "| `{$route['method']}` | `{$route['path']}` | `{$route['name']}` | `{$route['handler']}` |",
    $routes,
);
$routeTable = implode("\n", $routeRows);

$classRows = [];
$codeSections = [];
foreach ($classes as $className) {
    $class = new ReflectionClass($className);
    $summary = docSummary($class->getDocComment());
    $relativeFile = str_replace($root.'/', '', (string) $class->getFileName());
    $classRows[] = "| `{$className}` | `{$relativeFile}` | {$summary} |";

    $methodSections = [];
    $classFile = realpath((string) $class->getFileName());
    foreach ($class->getMethods() as $method) {
        if (realpath((string) $method->getFileName()) !== $classFile) {
            continue;
        }

        $methodSummary = docSummary($method->getDocComment());
        $signature = methodSignature($method);
        $methodSections[] = "### `{$method->getName()}()`\n\n{$methodSummary}\n\n```php\n{$signature}\n```";
    }

    $codeSections[] = "## `{$className}`\n\n**Source:** `{$relativeFile}`\n\n{$summary}\n\n".implode("\n\n", $methodSections);
}

$phpConstraint = $composer['require']['php'] ?? 'see composer.json';
$developerGuide = <<<MD
# Developer guide: run Trustindex Reviews locally

> **Generated documentation. Do not edit this file directly.**
>
> `php bin/build-docs.php` regenerates this guide from the current repository. Make targets, Compose ports, Composer requirements and Symfony routes are validated/reflected instead of being maintained as a second manual inventory.

This is the supported path from a clean development machine to a running, tested application and documentation portal.

## 1. Runtime prerequisites

- PHP **{$phpConstraint}**
- Composer 2
- PHP extensions: `ctype`, `iconv`, `pdo_sqlite`
- Node.js + npm/npx for VitePress
- GNU Make and Git
- optional: Xdebug for local coverage
- optional: Docker + Docker Compose v2

```bash
php --version
composer --version
node --version
npx --version
make --version
git --version
docker --version
docker compose version
```

## 2. Clone and bootstrap

```bash
git clone https://github.com/zolika42/trustindex.git
cd trustindex
make setup
make serve
```

`make setup` installs Composer dependencies, recreates the local SQLite database, runs migrations, seeds deterministic demo data and rebuilds the documentation portal.

Local pages:

- `http://127.0.0.1:8000/` — review feed
- `http://127.0.0.1:8000/reviews/new` — review form
- `http://127.0.0.1:8000/companies` — company leaderboard

## 3. macOS

1. Install PHP 8.2+, Composer 2, Node.js, GNU Make and Git.
2. Confirm SQLite support with `php -m | grep -i sqlite`.
3. Clone and run `make setup`.
4. Run `make serve` for the application.
5. Run `make docs-up` for the HTML documentation at `http://127.0.0.1:8088`.
6. Run `make ci` before pushing.

Docker Desktop is optional unless you want the disposable container workflow.

## 4. Windows / WSL2

WSL2 is the supported Windows developer shell so local commands match CI.

1. Enable WSL2 and install Ubuntu.
2. Install PHP 8.2+, `php-sqlite3`, Composer 2, Node.js, Make and Git inside WSL.
3. Keep the repository in the WSL filesystem, for example `~/work/trustindex`.
4. Run `make setup` and `make serve` from WSL.
5. Open `http://127.0.0.1:8000` from the Windows browser.
6. For Docker, enable Docker Desktop WSL integration and verify `docker compose version` inside WSL.

PowerShell may be used for Git/editor tasks, but Make targets are designed for a POSIX shell.

## 5. Database workflow

Local state lives in `var/app.db`.

```bash
make db-reset
```

For mapping changes:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

Commit migrations with entity changes. Never commit SQLite files from `var/`.

## 6. Daily development loop

```bash
make test
make cs
make lint
make docs-smoke
```

Focused suites:

```bash
make test-unit
make test-functional
```

Complete pre-push gate:

```bash
make ci
```

## 7. Coverage

```bash
make coverage
```

Xdebug must be available. `bin/check-coverage.php` enforces the default **90% meaningful-code line threshold**.

## 8. Docker demo

```bash
make docker-up
# app:  http://127.0.0.1:8080
# docs: http://127.0.0.1:8088
make docker-logs
make docker-down
```

The application container runs migrations and deterministic demo seeding at startup. The docs service serves generated static HTML read-only through Nginx.

## 9. Documentation workflow

```bash
make docs
make docs-smoke
make docs-dev
make docs-up
make docs-down
```

`make docs` generates this guide, the developer handbook and code reference, then builds static VitePress HTML into `docs/dist/`. Generated files are ignored by Git.

## 10. Current Symfony routes

| Method | Path | Route name | Handler |
| --- | --- | --- | --- |
{$routeTable}

## 11. Troubleshooting

### `vendor/autoload.php` missing

Run `make install`. Symfony and the Reflection-based docs generator both require installed dependencies.

### Form submission returns 422

HTTP 422 is the intentional response for submitted invalid review data. Inspect field errors before treating it as an application failure.

### Local database state is unknown

Run `make db-reset` to reconstruct SQLite from committed migrations and deterministic demo seed data.

### Docs build reports missing PHPDoc

Document the class/method responsibility, contract, side effects or invariants. The guard is intentional and should not be bypassed.

### VitePress cannot start

Verify `node` and `npx`. The Makefile pins VitePress; no global installation is required.

### Port conflict

Standard ports are `8000` (local PHP server), `8080` (Docker app) and `8088` (documentation).

## 12. Definition of done

- implementation respects documented responsibility boundaries;
- application classes/methods have meaningful PHPDoc;
- maintained Markdown changes with architecture/runtime behavior;
- schema changes have migrations;
- behavior changes have tests;
- `make ci` is green;
- `make docs-smoke` builds valid HTML;
- reviewer email remains outside public output unless requirements explicitly change.
MD;

$handbook = "# Generated developer handbook\n\n> **Generated documentation. Do not edit directly.** Repository facts below come from `bin/build-docs.php`.\n\n## Composer requirements\n\n| Package | Constraint |\n| --- | --- |\n".implode("\n", $requirementRows)."\n\n## Make targets\n\n".implode("\n", array_map(static fn (string $target): string => "- `{$target}`", $makeTargets))."\n\n## Symfony routes\n\n| Method | Path | Route name | Handler |\n| --- | --- | --- | --- |\n{$routeTable}\n\n## Application classes\n\n| Class | Source | Responsibility |\n| --- | --- | --- |\n".implode("\n", $classRows)."\n\n## Documentation contract\n\n- Maintained intent/architecture lives in tracked Markdown under `docs/`.\n- Application PHPDoc is mandatory and generator-validated.\n- Generated guide/handbook/code-reference/HTML are disposable.\n- CI rebuilds the documentation portal on every push/PR.\n";

$codeReference = "# Generated code reference\n\n> Generated from application Reflection metadata and source PHPDoc. Do not edit directly.\n\n".implode("\n\n", $codeSections)."\n";

@mkdir($root.'/docs/code-reference', 0777, true);
file_put_contents($root.'/docs/DEVELOPER_GUIDE.md', $developerGuide);
file_put_contents($root.'/docs/DEVELOPER_HANDBOOK.md', $handbook);
file_put_contents($root.'/docs/code-reference/index.md', $codeReference);

fwrite(
    \STDOUT,
    sprintf(
        "[docs] Generated guide, handbook and code reference from %d classes and %d routes.\n",
        count($classes),
        count($routes),
    ),
);
