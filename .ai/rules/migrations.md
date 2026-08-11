---
paths:
  - 'database/migrations/**'
---

# Migrations

## Tests run on SQLite; verify every migration against MySQL by hand
phpunit.xml pins the suite to sqlite/:memory: deliberately. Dev and production are MySQL. Do not switch the suite to MySQL without asking.

Why: tests run in parallel, so a MySQL suite creates one test schema per process (`quill_testing_1`, `_2`, …) and expects you to drop them afterwards. The local MySQL server is shared with several other projects and already carries thousands of tables — see the leftover `notarydash_testing*` schemas. That noise makes the server hard to navigate, and it is a worse problem than the SQLite/MySQL fidelity gap below. Speed is a secondary benefit, not the reason.

The trap: SQLite does not enforce column types, so a schema mistake can pass a fully green suite and fail only on MySQL. A `foreignUuid` pointing at a `bigint` is the concrete case — MySQL rejects it with errno 150, SQLite accepts it silently.

So after writing or editing a migration, run `php artisan migrate:fresh` against the real MySQL `quill` schema before trusting green tests. A passing suite is not evidence the schema is valid.
