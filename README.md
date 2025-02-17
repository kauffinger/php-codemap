[![Latest Version on Packagist](https://img.shields.io/packagist/v/kauffinger/php-codemap.svg?style=flat-square)](https://packagist.org/packages/kauffinger/php-codemap)
[![Linting](https://img.shields.io/github/actions/workflow/status/kauffinger/php-codemap/formats.yml?branch=main&label=linting&style=flat-square)](https://github.com/kauffinger/php-codemap/actions/workflows/checks.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/kauffinger/php-codemap/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/kauffinger/php-codemap/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/kauffinger/php-codemap.svg?style=flat-square)](https://packagist.org/packages/kauffinger/php-codemap)

# PHP Codemap

PHP Codemap is a Composer package that generates a textual codemap of your PHP code. It scans your PHP files and produces a map that lists classes, methods, and public properties.

## Features

- **Recursive Scanning:** Scan directories or individual PHP files.
- **AST Parsing:** Utilizes [nikic/php-parser](https://github.com/nikic/PHP-Parser) to parse PHP code.
- **Codemap Generation:** Outputs a detailed codemap including classes, methods, and public properties.
- **Easy to Use:** Command-line tool that writes the codemap to `codemap.txt` by default.

## Installation

You can install the package via Composer:

```bash
composer require kauffinger/php-codemap
```

## Usage

Run the codemap generator using the command-line:

```bash
./vendor/bin/codemap [path/to/scan]
```

- **Default Behavior:** If no path is provided, it defaults to scanning the `src` directory.
- **Output:** The codemap is generated in a file named `codemap.txt` in the project root.

## Running Tests

This package uses [Pest PHP](https://pestphp.com/) for testing. To run the tests, execute:

```bash
composer test
```

## Code Quality Tools

- **Laravel Pint:** For code formatting.
- **PHPStan:** For static analysis.
- **Rector:** For automated refactoring.

## License

This project is licensed under the MIT License. See [LICENSE.md](LICENSE.md) for details.

## Contributing

Contributions are welcome! Please open an issue or submit a pull request with your improvements.
