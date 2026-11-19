# Deployment

## c. Updating an existing install

- Pull the code.
- Run `php bin/migrate.php status` to see pending migrations.
- Run `php bin/migrate.php up` to apply new migration files in numeric order.
- Use `php bin/migrate.php baseline` only for an existing database whose schema is already current and whose `schema_migrations` table is empty.
- Never edit a migration file after it has been applied.
- Migrations 023 to 025 are already included in `database/schema.sql`; new migrations start at 026.

## e. Verification after deploy

- Run `php bin/smoke.php <base-url>` against the live URL after each deploy (for example `php bin/smoke.php http://gudangpc.test`).
- The script makes GET requests only, sends no cookies or credentials, and writes nothing to the database; it exits non-zero if any check fails.
