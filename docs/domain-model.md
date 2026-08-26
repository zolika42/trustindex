# Domain model

## `Review`

`Review` is the only persisted domain entity. This is intentional: the assignment requires reviews and company-level statistics, but it does not require company administration or a company lifecycle. Introducing a `Company` table would create synchronization and identity questions without adding value to the exercise.

## Persisted fields

| Field | Storage | Validation / invariant | Public? |
| --- | --- | --- | --- |
| `id` | auto-generated integer | Doctrine identity | yes, in detail URL |
| `companyName` | `VARCHAR(255)` | required, max 255, trimmed | yes |
| `rating` | integer | required, integer, 1–5 | yes |
| `reviewText` | text | required, trimmed | yes |
| `authorEmail` | `VARCHAR(255)` | required, valid email, max 255, trimmed/lowercased | **no** |
| `createdAt` | immutable datetime | initialized automatically | yes |
| `updatedAt` | immutable datetime | initialized automatically, refreshed on update | internal |

## Transitional invalid state during form binding

Symfony forms may submit an empty value as `null` before validation runs. Entity setters therefore accept nullable input for form-bound fields and normalize it into a stable scalar representation:

- empty strings become `''`;
- missing rating becomes `0`;
- email is trimmed and lowercased.

This does **not** weaken the domain validation. The normalized placeholder state is deliberately invalid and is rejected by Validator before persistence. The distinction prevents PHP `TypeError` exceptions from turning ordinary validation errors into HTTP 500 responses.

## Timestamp lifecycle

Construction initializes `createdAt` and `updatedAt` to the same immutable timestamp. Doctrine's `PreUpdate` lifecycle callback refreshes `updatedAt` before an update is written.

Immutable date objects are used so callers cannot accidentally mutate timestamps through a shared object reference.

## Company identity

Company identity is currently the normalized submitted company-name string. There is no slug or company master record. Consequently, differently spelled names are different aggregation buckets. This is acceptable for the assignment scope and is explicitly preferable to inventing fuzzy matching or hidden normalization rules.

## Rating semantics

Stored ratings are integer stars from 1 through 5. Company averages may therefore be fractional. `RatingClassifier` maps averages into bands:

- `>= 4.5`: Excellent
- `>= 3.5`: Very good
- `>= 2.5`: Good
- `>= 1.5`: Mixed
- `< 1.5`: Needs improvement

The classifier is not persisted; it is derived every time company statistics are rendered.

## Database evolution

The Doctrine mapping is the model source of truth; migrations are the deployment history. For a field/index change:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
```

Never make schema changes by manually editing `var/app.db`.
