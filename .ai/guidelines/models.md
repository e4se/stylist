# Models

- All new Eloquent models must use UUIDs as their `id` primary key unless they are mapping an existing table that already uses another primary key strategy.
- Use `Illuminate\Database\Eloquent\Concerns\HasUuids` on new models with UUID primary keys.
- New model migrations must create the primary key with `$table->uuid('id')->primary();` instead of `$table->id()`.
- Foreign keys that reference UUID-backed models must use UUID columns, such as `$table->foreignUuid('<model>_id')->constrained()`, following the project's existing cascade and nullability conventions.
- Treat model IDs as strings in PHP, TypeScript, factories, tests, validation rules, resources, and route parameters; do not cast UUID primary keys to integers.
