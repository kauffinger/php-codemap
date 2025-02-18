[![Latest Version on Packagist](https://img.shields.io/packagist/v/kauffinger/php-codemap.svg?style=flat-square)](https://packagist.org/packages/kauffinger/php-codemap)
[![Linting](https://img.shields.io/github/actions/workflow/status/kauffinger/php-codemap/checks.yml?branch=main&label=linting&style=flat-square)](https://github.com/kauffinger/php-codemap/actions/workflows/checks.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/kauffinger/php-codemap/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/kauffinger/php-codemap/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/kauffinger/php-codemap.svg?style=flat-square)](https://packagist.org/packages/kauffinger/php-codemap)

# PHP Codemap

## Table of Contents

- [Introduction](#introduction)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Advanced Usage](#advanced-usage)
- [Testing](#testing)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [FAQ](#faq)
- [License](#license)

## Introduction

PHP Codemap is a versatile Composer package designed to simplify PHP codebase exploration. It scans your PHP files and generates a textual "codemap"—a structured overview detailing classes, methods, and public properties.

### Why Use PHP Codemap?

- **Use with LLMs**: Compress your codebase into a manageable amount of tokens.
- **Use with RepoPrompt**: Do the above, but for using RepoPrompt.

### Key Features

- **Recursive Scanning**: Analyze entire directories or specific files effortlessly.
- **AST-Based Parsing**: Uses advanced Abstract Syntax Tree parsing for precision.
- **Comprehensive Output**: Lists classes, methods, and public properties in a readable format.
- **Customizable**: Configure scan paths and PHP versions to suit your project.
- **CLI Simplicity**: Run easily from the command line with flexible options.

## Installation

### Requirements

- **PHP**: 8.3.0 or higher
- **Composer**: Required for installation

### Installing via Composer

Install PHP Codemap by adding it to your project with Composer:

```bash
composer require kauffinger/php-codemap
```

This command fetches the package and its dependencies, making the `codemap` command available in your `vendor/bin` directory.

## Configuration

PHP Codemap supports customization through a `codemap.php` file in your project root. This optional file lets you define scan paths and the PHP version for parsing.

### Example `codemap.php`

```php
<?php

declare(strict_types=1);

use Kauffinger\Codemap\Config\CodemapConfig;
use Kauffinger\Codemap\Enum\PhpVersion;

return CodemapConfig::configure()
    ->withScanPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpVersion(PhpVersion::PHP_8_3);
```

- **Scan Paths**: Specify directories or files to scan (defaults to `src` if unspecified).
- **PHP Version**: Set the parsing version (defaults to the version in `composer.json` if not provided).

If no `codemap.php` exists, PHP Codemap generates a default configuration based on your `composer.json` PHP requirement and scans the `src` directory.

## Usage

Interact with PHP Codemap via the command-line tool located at `./vendor/bin/codemap`.

### Basic Usage

Generate a codemap using default settings:

```bash
./vendor/bin/codemap
```

This scans the configured paths (or `src` by default) and writes the output to `codemap.txt` in your project root.

### Specifying Paths to Scan

Override configured paths by passing them as arguments:

```bash
./vendor/bin/codemap app/Http tests/Unit
```

Multiple paths can be provided, and they’ll be scanned recursively.

### Output Options

Customize where the codemap is written:

- **To a Specific File**:

```bash
./vendor/bin/codemap --output=docs/codemap.txt
```

- **To Standard Output** (stdout):

```bash
./vendor/bin/codemap --output=-
```

### Using Different PHP Versions

Specify a PHP version for parsing if your codebase differs from the default:

```bash
./vendor/bin/codemap --php-version=8.1
```

Supported versions include 8.0 through 8.4, aligning with the `PhpVersion` enum.

## Advanced Usage

### Customizing Scan Paths

For complex projects, tailor scan paths in `codemap.php`:

```php
return CodemapConfig::configure()
    ->withScanPaths([
        __DIR__.'/app/Models',
        __DIR__.'/app/Services',
        __DIR__.'/modules',
    ]);
```

This focuses scanning on specific areas, improving performance and relevance.

### Handling Large Codebases

For expansive projects, limit scanning to essential directories to reduce processing time. While exclusion patterns aren’t supported yet, precise path specification in the configuration achieves similar results.

## Testing

PHP Codemap uses [Pest PHP](https://pestphp.com/) for its test suite. Run tests with:

```bash
composer test
```

This executes the full suite, targeting 100% coverage to maintain reliability. Tests verify command execution, parsing accuracy, and formatting.

## Contributing

We welcome contributions to enhance PHP Codemap! Follow these steps:

1. **Fork the Repository**: Clone it to your GitHub account.
2. **Make Changes**: Implement features or fixes in your fork.
3. **Adhere to Standards**: Use [Laravel Pint](https://github.com/laravel/pint) for formatting and pass [PHPStan](https://phpstan.org/) analysis.
4. **Test Your Changes**: Run `composer test` to ensure all tests pass; add new tests as needed.
5. **Submit a Pull Request**: Open a PR with a detailed description of your changes.

## FAQ

**Q: How do I exclude directories from scanning?**

A: Exclusion patterns aren’t supported yet. Instead, list only the directories you want to scan in `codemap.php`.

**Q: Can I output the codemap in formats like JSON or HTML?**

A: Currently, only text output is available. Future releases may add more formats—watch the changelog or contribute!

**Q: What if my codebase uses an older PHP version?**

A: Use the `--php-version` option (e.g., `--php-version=8.0`) or set it in `codemap.php` to match your code.

For more questions, open an issue on the [GitHub repository](https://github.com/kauffinger/php-codemap).

## License

PHP Codemap is licensed under the MIT License. See [LICENSE.md](LICENSE.md) for details.