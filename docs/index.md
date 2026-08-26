# Trustindex Reviews documentation

This is the canonical technical documentation for the Trustindex Medior PHP developer test application. It describes the code that is **currently present in the repository**, the supported developer workflow and the quality gates that protect it.

The documentation follows the same source-of-truth model used in the ColumbiaGames CCM v2 project: maintained Markdown explains intent and architecture, while repository-derived pages are generated from executable project metadata and source-code comments before the HTML portal is built.

## New developer? Start here

Run `make docs` and open the generated portal, or start the local documentation service with `make docs-up` and browse to `http://127.0.0.1:8088`.

The generated **Developer Guide** is the primary onboarding document. It is intentionally generated rather than hand-maintained so commands, runtime requirements, routes and repository structure cannot silently drift away from the implementation.

## Current system shape

| Layer | Implementation | Source of truth |
| --- | --- | --- |
| HTTP | Symfony 7.4 controllers + attribute routes | `src/Controller` |
| Forms | Symfony Form + Validator | `src/Form`, `src/Entity` |
| Domain persistence | Doctrine ORM | `src/Entity/Review.php` |
| Query model | Doctrine repository queries | `src/Repository/ReviewRepository.php` |
| Presentation | Twig + static CSS | `templates`, `public/styles` |
| Database | SQLite by default + Doctrine migrations | `migrations`, Doctrine config |
| Quality | PHPUnit, php-cs-fixer, Symfony linters, coverage | `tests`, `Makefile`, CI |
| Documentation | VitePress + repository-derived generator | `docs`, `bin/build-docs.php` |

## Functional areas

- review submission through a real Symfony form;
- validation of required company, rating, review text and author email;
- normalized persisted values and automatic timestamps;
- public review feed with company search and rating filter;
- review detail page;
- company aggregation with count, average rating and deterministic sorting;
- human-readable rating bands;
- reviewer-email privacy by design;
- deterministic local seed data;
- unit, integration and full HTTP/Form/Twig functional tests;
- disposable Docker demo runtime;
- automatically generated developer and code-reference documentation.

## Source-of-truth hierarchy

1. **Executable PHP, configuration and migrations** define implemented behavior.
2. **Tests** define protected behavioral expectations and regression boundaries.
3. **Maintained Markdown under `docs/`** explains architecture, decisions and operations.
4. **Generated Developer Guide, handbook, code reference and `docs/dist/`** are disposable build output derived from the first three sources.

If maintained documentation contradicts executable behavior, fix both in the same change. Do not create a second temporary documentation tree.

## Reading order

1. **Developer Guide** — clone-to-running-system instructions, daily workflow and debugging.
2. [Architecture](./architecture/) — runtime boundaries and responsibility map.
3. [Domain model](./domain-model.md) — `Review` invariants, normalization and persistence.
4. [Request flows](./request-flows.md) — HTTP request lifecycles for list, create, detail and company aggregation.
5. [Testing strategy](./testing.md) — what is protected and why.
6. [Operations](./operations.md) — local database, Docker, CI and troubleshooting.
7. [Documentation runtime](./documentation-runtime.md) — how automatic freshness is enforced.
8. [Reference](./reference/) — generated handbook and source-comment reference.

## Build the HTML documentation

```bash
make docs
make docs-smoke
```

The static VitePress site is written to `docs/dist/`. It is generated output and is intentionally ignored by Git.

To serve it in the dedicated documentation container:

```bash
make docs-up
# http://127.0.0.1:8088
```

The CI pipeline performs the same build and uploads the complete HTML portal as a workflow artifact.
