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
2. `make docs-deps` installs the exact VitePress/Vue runtime versions declared by the Makefile into local, ignored `node_modules/`;
3. the project-local VitePress binary builds the complete static HTML portal into `docs/dist/`.

There is no second custom Markdown renderer and no hand-maintained HTML copy. `node_modules/` and every generated documentation output are disposable build state and are never committed.

## HTML serving and clean URLs

VitePress is configured with `cleanUrls: true`, so navigation links intentionally use paths such as `/DEVELOPER_GUIDE`, `/testing` and `/operations` even though the static build contains files such as `DEVELOPER_GUIDE.html` and `testing.html`.

The Compose `docs` service therefore does **not** use the stock Nginx routing behavior. It mounts `docker/nginx-docs.conf`, whose `try_files` rule resolves clean URLs to generated `.html` files and preserves directory-style pages such as `/architecture/` and `/code-reference/`.

The same Nginx configuration exposes `/healthz` for Docker/CI readiness checks. Readiness is deliberately independent of page copy so changing a documentation title cannot make the service appear unhealthy.

The default host port is `8088`, but the Makefile and Compose contract share a configurable `DOCS_PORT`. A developer can avoid a local collision without editing YAML:

```bash
make docs-up DOCS_PORT=8089
```

The override is propagated to `make docs-dev` and `make docker-up` as well. CI intentionally uses the default `8088` contract.

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
- GitHub Actions builds the HTML portal on every push/PR;
- CI starts the real Compose Nginx `docs` service and verifies its `/healthz` endpoint;
- CI smoke-tests representative clean URLs and directory-style documentation URLs over HTTP on the default port `8088`;
- CI uploads the completed static portal as the `trustindex-documentation-html` artifact;
- the generator fails if required Make targets disappear;
- the generator fails when an application class or method lacks PHPDoc;
- VitePress fails the build for invalid internal documentation links.

## Local commands

```bash
make docs-deps                    # install pinned local VitePress/Vue runtime
make docs                         # generate reference + build static HTML
make docs-dev                     # VitePress dev server on default :8088
make docs-dev DOCS_PORT=8089      # same server on an alternate port
make docs-smoke                   # generate/build + verify HTML entry points
make docs-up                      # serve docs/dist through Nginx on default :8088
make docs-up DOCS_PORT=8089       # serve through Nginx on an alternate host port
make docs-down
make docs-logs
```

Normal developers do not need to call `make docs-deps` explicitly because `make docs` and `make docs-dev` depend on it.

## Generated output policy

The following are ignored by Git and must never be edited directly:

- `node_modules/`
- `docs/dist/`
- `docs/DEVELOPER_GUIDE.md`
- `docs/DEVELOPER_HANDBOOK.md`
- `docs/code-reference/`

Rebuild them from current repository state with `make docs`.
