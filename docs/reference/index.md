# Reference

This section points to repository-derived reference material. The files are generated during `make docs` and are intentionally not committed.

## Developer Guide

`docs/DEVELOPER_GUIDE.md`

Step-by-step onboarding and daily development procedure generated from the current runtime/developer contract.

## Developer Handbook

`docs/DEVELOPER_HANDBOOK.md`

High-level repository inventory including routes, dependencies, Make targets and application-class responsibilities.

## Code reference

`docs/code-reference/index.md`

Reflection-based class/method reference sourced from the PHPDoc embedded in `src/`.

## Why generated?

Reference material is most likely to become stale when it duplicates facts that already exist in executable code. Generating it means route additions, source classes and supported commands appear from the repository itself rather than relying on somebody remembering to update a table by hand.

Architectural decisions and explanations remain maintained Markdown because intent cannot be safely reconstructed from syntax alone.
