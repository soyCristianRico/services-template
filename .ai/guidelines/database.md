# Database

MariaDB in local, MySQL in production — same engine family, so a migration that
runs locally runs on the deploy. What still bites is the set of limits and
behaviours MySQL enforces and that are easy to write past. Check these before
writing schema.

The one place the two genuinely diverge is **JSON columns**: MariaDB's `JSON` is
`LONGTEXT` with a CHECK constraint, MySQL's is a native type. Anything leaning on
JSON functions is unverified until it runs on the real thing.

## Index key length

InnoDB caps a key at **3072 bytes**, and under `utf8mb4` every character costs
4 bytes — so a plain `string()` column is `VARCHAR(255)` = **1020 bytes** inside
an index. Three of them in one composite index already leaves almost no room.

- **Size string columns to what they hold.** A column backed by an enum needs
  `string('modality', 32)`, never the default 255. This is the fix, not index
  prefixes.
- **Add up the bytes before adding a composite index**: 4 × length for
  char/text columns, 8 for ints and datetimes.
- A `unique()` is an index too, and so is every `index()`.

## `timestamp` ends in 2038

A `timestamp` column cannot hold a date past **2038-01-19**; MySQL rejects it
with *Incorrect datetime value*. Fine for `publish_at` and friends, but never
put a far-future sentinel date in a factory or a test — use
`now()->addMonths(3)`. Reach for `datetime` if a column genuinely needs to go
further.

## DDL is not transactional

A migration that fails halfway leaves the earlier statements applied and the
migration unrecorded, so the next deploy re-runs it from the top and dies on the
first statement that already happened.

Any migration doing several DDL steps has to be **re-runnable**: guard with
`Schema::hasColumn()`, and check `Schema::getIndexes($table)` before dropping or
creating an index.

## `->change()` drops what you do not restate

Modifying a column replaces its whole definition. Any attribute left out —
default, nullable, length — is gone. Restate the full definition every time.

## Indexes block column changes

Renaming or dropping a column that belongs to an index needs the index removed
first, and recreated afterwards.

## The test suite runs on MySQL too

`phpunit.xml` deliberately pins **no** connection, so the database comes from
`.env.testing` — which points at MySQL, on its own `{project}_test` database
because `RefreshDatabase` wipes it on every run. `.env.testing` is tracked in
git (only `.env`, `.env.backup` and `.env.production` are ignored), so a fresh
clone has working tests without configuring anything.

**Laravel REPLACES `.env` with `.env.testing`; it does not merge them.** So it
has to be a complete env file, `APP_KEY` included, or every test dies with
`MissingAppKeyException`. Build it from `.env.example` — which is also why it
holds no real credentials and can live in git.

The point is coverage, not tidiness: the suite has to run on the same engine
family as production or it cannot catch any of the limits above. `new-site.sh`
creates both databases and writes both env files, so a new site starts out this
way.

## A cascade deletes rows, not files

`cascadeOnDelete` is enforced by the database, and the database knows nothing
about Eloquent. Deleting a parent removes the children with one statement and
**no model event fires for them** — no `deleting`, no observer, and none of the
cleanup those hooks exist to do.

That is invisible while a child holds only columns. It bites the moment a child
holds a **file**: a media-library collection, or a path to something on a private
disk. The row disappears, the file stays, and nothing left in the database names
it. Nobody notices, because the only symptom is a disk that grows.

**When the child owns files, delete the children through Eloquent from the
parent's `deleting` hook.** Leave the foreign key as it is — it stays as the
backstop for anything that reaches the table another way.

```php
protected static function booted(): void
{
    static::deleting(function (Category $category): void {
        $category->services->each->delete();
    });
}
```

The same reasoning applies one level down: a model that stores its own file on a
disk deletes it in its own `deleting` hook, so it cleans up however it is
deleted — from the admin, from a command, from a parent, or from tinker. Put the
cleanup on the model rather than at the call site; a rule that lives in one
button is a rule that holds until the second button.

The cheapest way to know whether this applies: **does deleting this row leave
bytes on a disk?** If yes, it needs a hook and a test that asserts the file is
gone, not just the row.

## Migrations

- One concern per migration, and always a working `down()`.
- When a scale or a meaning changes, migrate the stored values in the same
  migration as the column (e.g. a 0-5 score moving to 0-10 doubles its rows),
  and check the precision still fits: `decimal(2, 1)` cannot store `10.0`.
- Verify by running the migration, rolling it back and running it again. The
  rollback is where a missing `down()` or a forgotten index shows up, and the
  re-run is where a non-re-runnable migration shows up.
