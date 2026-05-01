# Artisan Commands

## stringhive:audit

Diff hive keys against static translation calls in the codebase.

```bash
php artisan stringhive:audit [hive] [options]
```

### Arguments

| Argument | Description |
|----------|-------------|
| `hive` | Hive slug. Overrides `stringhive.hive` in config / `STRINGHIVE_HIVE` env var. |

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--format=<format>` | `table` | Output format: `table`, `json`, or `github` |
| `--scan-path=<path>` | project root | Root directory to scan for translation calls |
| `--fail-on-missing` | off | Exit 1 if any keys are used in code but absent from the hive |
| `--fail-on-orphaned` | off | Exit 1 if any hive keys are not referenced in code |
| `--fail-on-unapproved` | off | Exit 1 if any locale has unapproved translations |
| `--locale=<codes>` | all locales | Comma-separated locale codes to scope the check (e.g. `fr,de,es`). Only applies with `--fail-on-unapproved`. |
| `--min-approved=<pct>` | `100` | Minimum approved % to pass. Only applies with `--fail-on-unapproved`. |

### Examples

```bash
# Basic audit — show missing and orphaned keys
php artisan stringhive:audit my-app

# Fail CI if any keys are missing from the hive
php artisan stringhive:audit my-app --fail-on-missing

# Fail CI if any locale has unapproved translations
php artisan stringhive:audit my-app --fail-on-unapproved

# Check approval only for specific locales
php artisan stringhive:audit my-app --fail-on-unapproved --locale=fr,de,es

# Require at least 95% approval (instead of 100%)
php artisan stringhive:audit my-app --fail-on-unapproved --min-approved=95

# JSON output for programmatic use
php artisan stringhive:audit my-app --format=json

# GitHub Actions annotations
php artisan stringhive:audit my-app --format=github
```

### Approval check behavior

When `--fail-on-unapproved` is set, the command calls `GET /api/hives/{slug}` and reads the `approved_percent` for each locale. If any locale is below `--min-approved` (default 100), the command exits 1 and prints a table of the failing locales with their percentages. Use `--locale` to restrict the check to a specific subset of locales.
