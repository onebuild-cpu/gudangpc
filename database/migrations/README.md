# Migrations

Migration files use the name `NNN_name.sql`: three digits, then a lowercase name. New migrations start at 026.

Migrations 001 to 025 are not kept in the repository. `database/schema.sql` is the baseline install schema and includes everything up to migration 025.

Workflow:

1. Write the new migration file in this directory.
2. Run `php bin/migrate.php status`.
3. Run `php bin/migrate.php up`.

An applied migration file is never edited. Write a new migration instead.
