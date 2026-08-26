# Documentation standards

## Principle

Documentation is part of the implementation, not a release-afterthought. A code change that changes architecture, runtime commands, routes, validation, persistence or operational behavior must update the relevant maintained Markdown in the same change.

## Three documentation layers

### 1. Source-code documentation

Application classes and methods under `src/` must have PHPDoc. Comments should explain responsibility, contract, invariants, side effects or non-obvious framework behavior — not narrate obvious syntax.

The generator enforces this rule. Missing PHPDoc causes `make docs` and CI to fail.

### 2. Maintained technical Markdown

Tracked Markdown under `docs/` owns architectural and operational explanation. Keep one canonical page per concept; extend it instead of adding ticket-shaped duplicates.

### 3. Generated reference and HTML

The Developer Guide, Developer Handbook, code reference and VitePress `dist` tree are generated artifacts. Never edit or commit them directly.

## Source-of-truth rules

- Symfony routes: controller route attributes.
- Persistence schema: Doctrine entity mapping + committed migrations.
- Validation: Validator attributes on the entity.
- Developer commands: `Makefile`.
- Dependency/runtime constraints: `composer.json`, CI workflow, Compose configuration.
- Behavioral expectations: PHPUnit tests.
- Architecture/intent: maintained `docs/*.md` pages.

## Required documentation changes

Update docs when a change modifies any of the following:

- route path/method/response semantics;
- entity field, validation or normalization;
- repository filtering, aggregation or ordering;
- privacy behavior;
- local environment prerequisite, port or command;
- migration/reset/seed workflow;
- CI/coverage/quality gate;
- Docker service topology;
- documentation generation itself.

## PHPDoc style

Prefer comments such as:

> Returns company aggregates in stable PHP scalar types and deterministic leaderboard order.

Avoid comments such as:

> Gets the company statistics.

Document *why and contract*, not the English translation of the method name.

## Freshness guarantee

`bin/build-docs.php` validates repository assumptions before generating reference pages. VitePress then builds the full Markdown graph. CI runs the same process on every push to `main` and every pull request, so a broken or undocumented application contract blocks the quality gate.
