# Contributing to WordPress Casdoor Plugin

Thank you for your interest in contributing! This guide will help you get started.

## Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/casdoor/wordpress-casdoor-plugin.git
   cd wordpress-casdoor-plugin
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Run the tests:
   ```bash
   composer test
   ```

## Running Tests

We use PHPUnit for unit testing. To run the test suite:

```bash
# Run all tests
vendor/bin/phpunit

# Run tests with detailed output
vendor/bin/phpunit --testdox

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage
```

## Commit Message Convention

This project uses [Conventional Commits](https://www.conventionalcommits.org/) for automated versioning and changelog generation.

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- **feat**: A new feature (triggers minor version bump)
- **fix**: A bug fix (triggers patch version bump)
- **docs**: Documentation only changes
- **style**: Code style changes (formatting, missing semi colons, etc)
- **refactor**: Code refactoring without feature changes or bug fixes
- **perf**: Performance improvements
- **test**: Adding or updating tests
- **chore**: Maintenance tasks, dependency updates, etc
- **ci**: CI/CD configuration changes

### Examples

```bash
# Feature (bumps from 1.0.0 to 1.1.0)
feat(auth): add support for custom redirect URLs

# Bug fix (bumps from 1.0.0 to 1.0.1)
fix(login): resolve issue with special characters in username

# Breaking change (bumps from 1.0.0 to 2.0.0)
feat(api): redesign authentication flow

BREAKING CHANGE: The authentication flow has been completely redesigned.
Users will need to reconfigure their Casdoor settings.
```

### Breaking Changes

To indicate a breaking change, add `BREAKING CHANGE:` in the footer or add `!` after the type:

```bash
feat!: drop support for PHP 7.3
```

## Pull Request Process

1. Fork the repository and create your branch from `master` or `main`
2. Write tests for your changes
3. Ensure all tests pass: `composer test`
4. Update documentation as needed
5. Follow the commit message convention
6. Create a pull request with a clear description

## Continuous Integration

All pull requests run through our CI pipeline:

- **Tests**: Run on PHP 7.4, 8.0, 8.1, 8.2, and 8.3
- **Code Coverage**: Tracked via Codecov

## Releases

Releases are automated using semantic-release:

- Merging to `main`/`master` triggers the release workflow
- Version numbers are determined by commit messages
- Changelog is automatically generated
- GitHub releases are created automatically

## Questions?

Feel free to open an issue if you have questions or need help!
