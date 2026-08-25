# Documentation runtime

Trustindex has one documentation portal: **VitePress**. Maintained Markdown under `docs/` is the explanatory source; repository-derived pages are generated before VitePress builds `docs/dist/`.

The structure intentionally mirrors the ColumbiaGames CCM v2 documentation model while staying proportional to this smaller Symfony application.

## Build pipeline

`make docs` runs:

1. `php bin/build-docs.php`
   - validates required repository files and Make targets;
   - validates that application classes/methods are documented;
   - reflects current Symfony routes;
   - reads Composer/runtime requirements;
   - generates the Developer Guide;
   - generates the Developer Handbook;
   - generates the source-comment code reference;
2. pinned `vitepress` builds the complete static HTML portal into `docs/dist/`.

There is no second custom Markdown renderer and no hand-maintained HTML copy.

## Generated Developer Guide

`docs/DEVELOPER_GUIDE.md` is generated output. It derives its runtime contract from the current repository and includes:

- PHP/Composer requirements;
- clone/setup instructions;
- macOS procedure;
- Windows/WSL2 procedure;
- local URLs and ports;
- database/migration/seed workflow;
- test/coverage/quality commands;
- Docker workflow;
- documentation workflow;
- common troubleshooting paths.

## Generated Developer Handbook

`docs/DEVELOPER_HANDBOOK.md` is a repository inventory generated from current source:

- Composer requirements;
- Make targets;
- current Symfony routes;
- application classes and documented responsibilities;
- public/protected method signatures;
- source locations.

## Generated code reference

`docs/code-reference/index.md` is generated from Reflection and source PHPDoc. It is intentionally disposable and should not be maintained by hand.

## Automatic freshness

Freshness is enforced in several places:

- `make setup` rebuilds docs after setting up the application;
- `make qa` includes the docs build/smoke gate;
- `make ci` therefore includes docs automatically;
- GitHub Actions builds the HTML portal on every push/PR and uploads it as an artifact;
- the generator fails if required Make targets disappear;
- the generator fails when an application class or method lacks PHPDoc;
- VitePress fails the build for invalid internal documentation links.

## Local commands

```bash
make docs          # generate reference + build static HTML
make docs-dev      # VitePress development server
make docs-smoke    # generate/build + verify HTML entry points
make docs-up       # serve docs/dist through Nginx on :8088
make docs-down
make docs-logs
```

## Generated output policy

The following are ignored by Git and must never be edited directly:

- `docs/dist/`
- `docs/DEVELOPER_GUIDE.md`
- `docs/DEVELOPER_HANDBOOK.md`
- `docs/code-reference/`

Rebuild them from current repository state with `make docs`.
