# Contributing

Thank you for considering contributing to the Uchara PHP SDK!

## Development Setup

```bash
git clone https://github.com/uchara/uchara-php.git
cd uchara-php
composer install
```

## Running Tests

```bash
composer test
```

## Static Analysis

```bash
composer analyse
```

## Coding Standards

- PSR-12 coding style
- PSR-4 autoloading
- All public methods must have PHPDoc comments
- PHP 8.1+ features are encouraged (readonly properties, enums, named arguments)

## Pull Request Process

1. Fork the repository
2. Create a feature branch
3. Write tests for any new functionality
4. Ensure all tests pass (`composer test`)
5. Run static analysis (`composer analyse`)
6. Submit a pull request against the `main` branch

## Reporting Bugs

Please open an issue on GitHub with:
- PHP version
- SDK version
- Steps to reproduce
- Expected vs actual behavior
