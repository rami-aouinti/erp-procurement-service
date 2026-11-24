# PHP quality tooling

The quality tools are isolated from the application dependencies so installing or updating them does not change the Symfony/Oracle runtime lockfile.

## Install

From the repository root:

```bash
cd backend
composer update --working-dir=tooling --no-interaction --prefer-dist
```

## Checks

Run from `backend/`:

```bash
composer --working-dir=tooling phpcs
composer --working-dir=tooling ecs
composer --working-dir=tooling phpmd
composer --working-dir=tooling phpstan
composer --working-dir=tooling rector
composer --working-dir=tooling insights
```

## Automatic fixes

Review the diff after each fixer; do not run all fixers blindly on the same change:

```bash
composer --working-dir=tooling phpcbf
composer --working-dir=tooling ecs:fix
composer --working-dir=tooling rector:fix
```

- **PHPCS** checks PSR-12; **PHPCBF** applies the corresponding safe formatting fixes.
- **ECS** checks the same PSR-12 baseline and **ecs:fix** applies it.
- **PHPMD** reports maintainability/design issues using the checked-in ruleset.
- **PHPStan** performs static analysis at level 5.
- **Rector** checks PHP 8.4 upgrade/refactoring opportunities; the default command is dry-run.
- **PHP Insights** reports code quality, complexity, architecture and style.

The first run may reveal existing findings. Fix findings in small batches and keep behavior covered by the PHPUnit suite. Rector and formatting fixers can modify code, so inspect and test every generated diff.
