#!/usr/bin/env php
<?php

declare(strict_types=1);

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\Routing\Attribute\Route;

$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "[docs] vendor/autoload.php is missing. Run `make install` first.\n");
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
        fwrite(STDERR, "[docs] Required documentation source is missing: {$requiredFile}\n");
        exit(1);
    }
}

/** @return list<string> */
function parseMakeTargets(string $source): array
{
    preg_match_all('/^([a-zA-Z0-9_-]+):/m', $source, $matches);

    return array_values(array_unique(array_filter(
        $matches[1],
        static fn (string $target): bool => !str_contains($target, '%') && '.PHONY' !== $target,
    )));
}

/** @return list<string> */
function discoverApplicationClasses(string $root): array
{
    $classes = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src'));

    foreach ($iterator as $file) {
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($root.'/src/') );
        $classes[] = 'App\\'.str_replace('/', '\\', substr($relative, 0, -4));
    }

    sort($classes);

    return $classes;
}

function docSummary(string|false $docComment): string
{
    if (false === $docComment) {
        return '';
    }

    $lines = preg_split('/\R/', $docComment) ?: [];
    $summary = [];

    foreach ($lines as $line) {
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

function reflectionType(?ReflectionType $type): string
{
    return null === $type ? '' : (string) $type;
}

function parameterSignature(ReflectionParameter $parameter): string
{
    $type = reflectionType($parameter->getType());
    $signature = ('' === $type ? '' : $type.' ').($parameter->isPassedByReference() ? '&' : '').($parameter->isVariadic() ? '...' : '').'$'.$parameter->getName();

    if ($parameter->isOptional() && !$parameter->isVariadic()) {
        $signature .= ' = …';
    }

    return $signature;
}

function methodSignature(ReflectionMethod $method): string
{
    $visibility = $method->isPublic() ? 'public' : ($method->isProtected() ? 'protected' : 'private');
    $static = $method->isStatic() ? ' static' : '';
    $parameters = implode(', ', array_map(parameterSignature(...), $method->getParameters()));
    $return = reflectionType($method->getReturnType());

    return sprintf('%s%s function %s(%s)%s', $visibility, $static, $method->getName(), $parameters, '' === $return ? '' : ': '.$return);
}

/** @param list<string> $classes */
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
        fwrite(STDERR, "[docs] Source documentation contract failed:\n - ".implode("\n - ", $failures)."\n");
        exit(1);
    }

    $tracked = [];
    exec('git -C '.escapeshellarg($root).' ls-files docs 2>/dev/null', $tracked, $status);
    if (0 === $status) {
        foreach ($tracked as $path) {
            if (in_array($path, ['docs/DEVELOPER_GUIDE.md', 'docs/DEVELOPER_HANDBOOK.md'], true) || str_starts_with($path, 'docs/code-reference/') || str_starts_with($path, 'docs/dist/')) {
                fwrite(STDERR, "[docs] Generated output must not be tracked: {$path}\n");
                exit(1);
            }
        }
    }
}

/** @param list<string> $classes
 *  @return list<array{method: string, path: string, name: string, handler: string}>
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

    usort($routes, static fn (array $left, array $right): int => [$left['path'], $left['method']] <=> [$right['path'], $right['method']]);

    return $routes;
}

$makefile = file_get_contents($root.'/Makefile');
if (false === $makefile) {
    throw new RuntimeException('Cannot read Makefile.');
}
$makeTargets = parseMakeTargets($makefile);
$requiredTargets = ['install', 'setup', 'serve', 'db-reset', 'seed', 'test', 'coverage', 'cs', 'lint', 'qa', 'ci', 'docker-up', 'docker-down', 'docs', 'docs-smoke', 'docs-up', 'docs-down'];
$missingTargets = array_values(array_diff($requiredTargets, $makeTargets));
if ([] !== $missingTargets) {
    fwrite(STDERR, '[docs] Makefile targets referenced by documentation are missing: '.implode(', ', $missingTargets)."\n");
    exit(1);
}

$compose = file_get_contents($root.'/compose.yaml') ?: '';
foreach (['8080:8000', '8088:80'] as $portContract) {
    if (!str_contains($compose, $portContract)) {
        fwrite(STDERR, "[docs] compose.yaml no longer contains required documented port contract {$portContract}.\n");
        exit(1);
    }
}

$composer = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$classes = discoverApplicationClasses($root);
validateSourceDocumentation($classes, $root);
$routes = discoverRoutes($classes);

$requirements = [];
foreach (($composer['require'] ?? []) as $package => $constraint) {
    $requirements[] = "| `{$package}` | `{$constraint}` |";
}

$routeRows = array_map(
    static fn (array $route): string => "| `{$route['method']}` | `{$route['path']}` | `{$route['name']}` | `{$route['handler']}` |",
    $routes,
);

$classRows = [];
$codeSections = [];
foreach ($classes as $className) {
    $class = new ReflectionClass($className);
    $summary = docSummary($class->getDocComment());
    $relativeFile = str_replace($root.'/', '', (string) $class->getFileName());
    $classRows[] = "| `{$className}` | `{$relativeFile}` | {$summary} |";

    $methods = [];
    $classFile = realpath((string) $class->getFileName());
    foreach ($class->getMethods() as $method) {
        if (realpath((string) $method->getFileName()) !== $classFile) {
            continue;
        }
        $methods[] = "### `{$method->getName()}()`\n\n{$summaryMethod = docSummary($method->getDocComment())}\n\n```php\n".methodSignature($method)."\n```";
    }
    $codeSections[] = "## `{$className}`\n\n**Source:** `{$relativeFile}`\n\n{$summary}\n\n".implode("\n\n", $methods);
}

$phpConstraint = $composer['require']['php'] ?? 'see composer.json';
$developerGuide = <<<MD
# Developer guide: run Trustindex Reviews locally

> **Generated documentation. Do not edit this file directly.**
>
> This guide is regenerated by `php bin/build-docs.php`. Supported commands are validated against the current `Makefile`, runtime ports against `compose.yaml`, dependencies against `composer.json`, and HTTP routes are reflected from Symfony attributes.

This is the supported path from a clean development machine to a running, tested application and documentation portal.

## 1. Runtime prerequisites

- PHP **{$phpConstraint}**
- Composer 2
- PHP extensions: `ctype`, `iconv`, `pdo_sqlite`
- Node.js + npm/npx for the VitePress documentation portal
- GNU Make
- Git
- optional: Xdebug for local coverage
- optional: Docker + Docker Compose v2 for disposable app/docs services

Verify the toolchain before debugging application code:

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
```

`make setup` performs dependency installation, recreates the local SQLite database, runs migrations, seeds deterministic demo data and rebuilds the documentation portal.

Start the non-Docker application:

```bash
make serve
```

Open:

- `http://127.0.0.1:8000/` — review feed
- `http://127.0.0.1:8000/reviews/new` — review form
- `http://127.0.0.1:8000/companies` — company leaderboard

## 3. macOS setup

1. Install PHP 8.2+, Composer 2, Node.js, GNU Make and Git using your normal package manager.
2. Confirm `pdo_sqlite` is enabled with `php -m | grep -i sqlite`.
3. Clone the repository and run `make setup`.
4. Run `make serve` in one terminal.
5. Run `make docs-up` if you want the HTML documentation at `http://127.0.0.1:8088`.
6. Before pushing, run `make ci`.

Docker Desktop is only required for the disposable container workflow; the normal Symfony development path does not require Docker.

## 4. Windows / WSL2 setup

The supported Windows path is **WSL2** so the same Make/Bash/PHP commands used by CI remain valid.

1. Enable WSL2 and install an Ubuntu distribution.
2. Install PHP 8.2+, `php-sqlite3`, Composer 2, Node.js, Make and Git inside WSL.
3. Keep the repository in the WSL filesystem (for example `~/work/trustindex`) rather than under `/mnt/c` for better filesystem performance.
4. From the WSL shell run `make setup` then `make serve`.
5. Open `http://127.0.0.1:8000` from the Windows browser; WSL2 forwards localhost in normal configurations.
6. If using Docker, enable WSL integration in Docker Desktop and verify `docker compose version` from the WSL shell.

Native PowerShell can be used for Git/editor tasks, but repository Make targets and shell commands are designed for a POSIX shell and should be run from WSL2.

## 5. Database workflow

Local state lives in `var/app.db`.

Reset to a known demo state:

```bash
make db-reset
```

For a mapping change:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

Commit the migration together with the entity change. Never commit SQLite database files from `var/`.

## 6. Normal development loop

```bash
make serve
# edit code
make test
make cs
make lint
make docs-smoke
```

Use focused tests while iterating:

```bash
make test-unit
make test-functional
```

Then run the complete local gate:

```bash
make ci
```

## 7. Coverage

```bash
make coverage
```

Xdebug must be available. The default meaningful-code threshold is **90%** and is enforced by `bin/check-coverage.php`.

## 8. Docker demo

```bash
make docker-up
# app: http://127.0.0.1:8080
make docker-logs
make docker-down
```

The application image runs migrations and deterministic demo seeding on startup. CI builds the same image and smoke-tests public routes.

## 9. Documentation workflow

```bash
make docs
make docs-smoke
make docs-up
# docs: http://127.0.0.1:8088
```

`make docs` generates this guide, the developer handbook and code reference, then builds static VitePress HTML into `docs/dist/`.

For documentation authoring with live reload:

```bash
make docs-dev
```

Generated files are disposable and ignored by Git. Edit maintained Markdown under `docs/` or PHPDoc in `src/`, then regenerate.

## 10. Current HTTP routes

| Method | Path | Route name | Handler |
| --- | --- | --- | --- |
%s

## 11. Troubleshooting

### Composer classes are missing

Run `make install`. Both Symfony and the documentation reflection step require `vendor/autoload.php`.

### Review form returns a validation error

Expected invalid input returns HTTP 422. Check field-level errors first; do not treat a controlled 422 as an application crash.

### Database is in an unknown local state

Run `make db-reset` to recreate the SQLite database from migrations and deterministic seed data.

### Documentation fails because a class/method has no PHPDoc

Add documentation that states responsibility, contract, side effects or invariants. This is a deliberate CI rule keeping source and generated reference synchronized.

### VitePress cannot start

Confirm `node` and `npx` are available. The Makefile pins the VitePress version; no global install is expected.

### Docker app or docs port is occupied

The standard ports are `8080` for the disposable application and `8088` for documentation. Stop the conflicting service or run the underlying tool with a different host mapping.

## 12. Definition of done for a code change

- implementation follows the responsibility boundaries documented under `docs/architecture`;
- source PHPDoc is present and meaningful;
- relevant maintained Markdown is updated when behavior/architecture changes;
- migration exists for schema changes;
- tests protect changed behavior;
- `make ci` is green;
- `make docs-smoke` builds a valid HTML portal;
- reviewer email remains outside public output unless requirements explicitly change.
MD;
$developerGuide = sprintf($developerGuide, implode("\n", $routeRows));

$handbook = "# Generated developer handbook\n\n> **Generated documentation. Do not edit directly.** The content below is derived from the current repository by `bin/build-docs.php`.\n\n## Composer requirements\n\n| Package | Constraint |\n| --- | --- |\n".implode("\n", $requirements)."\n\n## Make targets\n\n".implode("\n", array_map(static fn (string $target): string => "- `{$target}`", $makeTargets))."\n\n## Symfony routes\n\n| Method | Path | Route name | Handler |\n| --- | --- | --- | --- |\n".implode("\n", $routeRows)."\n\n## Application classes\n\n| Class | Source | Responsibility |\n| --- | --- | --- |\n".implode("\n", $classRows)."\n\n## Documentation contract\n\n- Maintained architecture/operations knowledge lives in tracked Markdown under `docs/`.\n- Application class/method PHPDoc is mandatory and validated by the generator.\n- Generated guide/handbook/code-reference/HTML are not committed.\n- CI rebuilds the portal on every push/PR.\n";

$codeReference = "# Generated code reference\n\n> Generated from application Reflection metadata and source PHPDoc by `bin/build-docs.php`. Do not edit directly.\n\n".implode("\n\n", $codeSections)."\n";

@mkdir($root.'/docs/code-reference', 0777, true);
file_put_contents($root.'/docs/DEVELOPER_GUIDE.md', $developerGuide);
file_put_contents($root.'/docs/DEVELOPER_HANDBOOK.md', $handbook);
file_put_contents($root.'/docs/code-reference/index.md', $codeReference);

fwrite(STDOUT, sprintf("[docs] Generated guide, handbook and code reference from %d classes and %d routes.\n", count($classes), count($routes)));
