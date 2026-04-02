# Contributing

## Development Setup

### Requirements

- PHP 8.2+
- Node.js 18+
- Composer
- A TYPO3 13.4 or 14.x installation for testing

### Initial Setup

```bash
composer install
composer build:assets
```

## Frontend Assets

Frontend libraries (jQuery, flatpickr, Parsley, TinyMCE) are managed via npm. The built files are committed to `Resources/Public/` so that users of the extension do not need Node.js.

### Available commands

| Command | Description |
|---|---|
| `composer build:assets` | Install exact versions from `package-lock.json` and build assets |
| `composer update:assets` | Update all libraries to their latest versions and rebuild assets |

### Manually updating a library

```bash
# Update a specific package
npm install tinymce@latest
npm run build

git add Resources/Public/ package.json package-lock.json
git commit -m "[TASK] update tinymce to x.y.z"
```

### Dependabot

This repository uses [GitHub Dependabot](https://docs.github.com/en/code-security/dependabot) to automatically detect outdated npm dependencies. Dependabot opens a pull request each month when newer versions are available.

**Important:** Dependabot only updates `package.json` and `package-lock.json`. The built files in `Resources/Public/` are not updated automatically. After reviewing a Dependabot PR, check it out locally and run:

```bash
composer build:assets
git add Resources/Public/
git commit -m "[TASK] rebuild assets after dependency update"
git push
```

Then merge the pull request.

## Static Analysis

```bash
composer ci:static
```

This runs composer normalize, PHP CS Fixer, PHP Lint and PHPStan.

### Individual checks

```bash
composer ci:php:cs-fixer   # coding style check
composer ci:php:lint       # syntax check
composer ci:php:stan       # PHPStan
```

### Fix coding style

```bash
composer fix:php:cs
```

## Unit Tests

```bash
composer ci:php:unit
```

Tests are located in `Tests/Unit/` and cover the custom business logic of `FileUploadService` and `ModifyAllowedMimeTypesEvent`. They run without a TYPO3 bootstrap and require no database connection.

## Functional Tests

```bash
typo3DatabaseDriver=pdo_sqlite composer ci:php:functional
```

Tests are located in `Tests/Functional/` and verify controller behaviour against a real TYPO3 instance using SQLite. They cover:

- **Authorization** – `editAction` is only accessible to the news owner; unauthenticated and non-owner requests are rejected
- **File handling** – delete flag removes the FAL reference; uploading a replacement deletes the old file and creates a new reference; file reference metadata (title, description, showinpreview) is persisted correctly
- **Slug generation** – `createAction` sets `path_segment` from the title and guarantees uniqueness for duplicate titles
- **Hidden records** – owners can edit hidden news when `allowNotEnabledNews = 1` is set; without the setting, hidden records are not loadable

## Rector

Rector is not part of the CI pipeline and should only be run manually when dropping support for a TYPO3 version:

```bash
composer fix:php:rector
```
