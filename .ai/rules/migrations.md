---
paths:
  - 'database/migrations/**'
---

# Migrations

## Tests run on SQLite; verify every migration against MySQL by hand
phpunit.xml pins the suite to sqlite/:memory: deliberately, for speed. Dev and production are MySQL. Do not switch the suite to MySQL without asking — the slowdown on every run is the reason it is this way.

The trap: SQLite does not enforce column types, so a schema mistake can pass a fully green suite and fail only on MySQL. A `foreignUuid` pointing at a `bigint` is the concrete case — MySQL rejects it with errno 150, SQLite accepts it silently.

So after writing or editing a migration, run `php artisan migrate:fresh` against the real MySQL `quill` schema before trusting green tests. A passing suite is not evidence the schema is valid.
