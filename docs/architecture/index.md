# Architecture

## Design goal

The application is deliberately small, but its boundaries are production-minded: HTTP coordination belongs in controllers, validation and input mapping belong to Symfony Form/Validator, persistence belongs to Doctrine, query behavior belongs to the repository, and presentation belongs to Twig.

The project avoids introducing speculative abstractions that are not required by the assignment. There is one persisted aggregate, `Review`; company statistics are a query projection over reviews rather than a separately synchronized `Company` table.

## Runtime request path

```text
Browser
  |
  v
public/index.php
  |
  v
Symfony Kernel / Router
  |
  +--> ReviewController -------------------------+
  |       |                                      |
  |       +--> ReviewType --> Validator          |
  |       |                    |                 |
  |       +--> ReviewRepository|                 |
  |       |                    v                 |
  |       +--> EntityManager -> Review -> SQLite |
  |                                              |
  +--> CompanyController -> ReviewRepository ----+
                         -> RatingClassifier
  |
  v
Twig templates -> HTML response
```

## Responsibility map

### `src/Controller`

Controllers are orchestration boundaries. They parse request filters, coordinate repositories/forms, persist valid entities and choose HTTP responses. Business/query rules are kept out of controllers where practical.

### `src/Entity`

`Review` owns persisted state, normalization performed by setters, validation metadata and timestamp lifecycle behavior. Form binding is allowed to place the entity temporarily into an invalid state; Symfony Validator decides whether that state may be persisted.

### `src/Form`

`ReviewType` defines the public write contract. It intentionally does not duplicate entity validation rules. The form controls widget semantics and user-facing help; Validator attributes remain the server-side truth.

### `src/Repository`

`ReviewRepository` is both the read model and query boundary. It owns feed ordering, optional filters, company aggregation and overall statistics. Aggregates are cast into stable PHP types before leaving the repository.

### `src/Service`

`RatingClassifier` is a pure presentation-domain service that converts a numeric company average into a label/CSS contract. Its threshold boundaries are unit-tested independently from HTTP and Doctrine.

### `templates` and `public/styles`

Twig is intentionally passive. It renders already-prepared data and never queries Doctrine. Reviewer email is not rendered on any public page.

## Data architecture

SQLite is the default datastore because it makes evaluation and local development zero-friction. The schema is still managed through Doctrine migrations, which keeps schema evolution explicit and reviewable.

The current model uses indexes on `company_name` and `rating` because both participate in public filtering/aggregation paths.

## Read paths

### Review feed

`ReviewRepository::findForHomepage()` returns newest reviews first, with ID as a deterministic secondary sort. Optional company search and rating filtering are applied in the repository.

### Company leaderboard

`ReviewRepository::getCompanyStatistics()` performs `COUNT`, `AVG` and `MAX` in SQL/DQL, grouped by company. Ordering is deterministic:

1. average rating descending;
2. review count descending;
3. company name ascending.

### Overall statistics

`ReviewRepository::getOverallStatistics()` returns total review count plus optional average for the current company-search scope.

## Write path

The only public write path is `/reviews/new`.

1. Symfony creates a `Review` and binds `ReviewType`.
2. Entity setters normalize string/email input.
3. Validator evaluates required/range/email/length rules.
4. Invalid submissions render with HTTP 422.
5. Valid submissions are persisted and flushed.
6. A success flash with the required exact Hungarian text is created.
7. The browser is redirected to the review feed.

## Privacy boundary

`authorEmail` exists to satisfy the input/persistence requirement but is not part of the public read model. Controllers never pass a separate email projection to templates, and tests assert that submitted email addresses do not leak into public HTML.

## Extension rules

When extending the application:

- put new query logic in a repository/query service rather than Twig or controllers;
- add database changes as migrations, never by editing a local SQLite file;
- keep presentation-only labels out of persisted state;
- add functional coverage for user-visible behavior;
- document new runtime concepts in `docs/` in the same commit;
- add PHPDoc to new source classes/methods because the documentation generator enforces this contract.
