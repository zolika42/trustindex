# Request flows

## `GET /` — review feed

1. `ReviewController::index()` reads `q` and `rating` from the query string.
2. Search text is trimmed.
3. Rating is accepted only if it is one of `1..5`; every other value becomes no rating filter.
4. `ReviewRepository::findForHomepage()` retrieves the feed.
5. `ReviewRepository::getOverallStatistics()` retrieves count/average for the same search scope.
6. Twig renders the feed and filter state.

The controller does not construct DQL and Twig does not access Doctrine.

## `GET|POST /reviews/new` — create review

### GET

1. Controller creates an empty `Review`.
2. Symfony builds `ReviewType`.
3. The form is rendered with HTTP 200.

### Valid POST

1. Form maps input to `Review` setters.
2. Setter normalization executes.
3. Validator applies entity constraints.
4. EntityManager persists and flushes the entity.
5. Exact success flash `Köszönjük a véleményed!` is stored.
6. Controller redirects to `/`.

### Invalid POST

1. Form mapping still completes safely even for empty values.
2. Validator records violations.
3. No persistence occurs.
4. The same form is rendered with HTTP **422 Unprocessable Entity**.

Returning 422 distinguishes a syntactically valid HTTP request whose submitted domain data is invalid.

## `GET /reviews/{id}` — review detail

Symfony's entity value resolver loads `Review` from the numeric `{id}` route parameter. A missing entity is handled by Symfony as a 404; the controller only renders the resolved review.

## `GET /companies` — company leaderboard

1. Controller reads and trims optional `q`.
2. Repository runs grouped company statistics.
3. Repository normalizes DB aggregate scalar types to `int`/`float` and rounds averages to two decimals.
4. Controller enriches each row with a `RatingClassifier` presentation band.
5. Twig renders the deterministic leaderboard.

## Search semantics

Company search is case-insensitive substring matching using `LOWER(companyName) LIKE LOWER(:companySearch)`. The parameter is bound, not interpolated, so user input does not become executable DQL/SQL.

## Error and privacy semantics

- invalid form data is a controlled 422, not a 500;
- invalid/nonexistent review IDs do not enter custom error branches unnecessarily;
- reviewer email is persisted but never rendered on public pages;
- there is no API exposing raw entity serialization.
