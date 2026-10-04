# wordpress-casdoor-plugin

[![Tests](https://github.com/casdoor/wordpress-casdoor-plugin/actions/workflows/test.yml/badge.svg)](https://github.com/casdoor/wordpress-casdoor-plugin/actions/workflows/test.yml)
[![Semantic Release](https://github.com/casdoor/wordpress-casdoor-plugin/actions/workflows/release.yml/badge.svg)](https://github.com/casdoor/wordpress-casdoor-plugin/actions/workflows/release.yml)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](LICENSE)

This plugin is designed and developed for use with [casdoor](https://github.com/casbin/casdoor). After activating the plugin, it will replace standard WordPress login forms with one powered by casdoor. 

## Installation
This plugin has not been published to wordpress plugin store, so you need to download this plugin, and move it to the `wp-content/plugins` directory manaully.

## Get started
First, activate this plugin as an admin, this will add a new section about casdoor to your settings page. 

Because this plugin is a client of casdoor. So you need to run a casdoor program, create a application and add `http://your-wordpress-domain/?auth=casdoor` to the `Redirect URLs` list of your casdoor application.

Then click on this new section and set up your casdoor plugin, this mainly involves the following settings.

- Activate Casdoor: If this radio box is checked, the default login form will be replaced.
- Client ID: the client id of your casdoor application.
- Client Secret: the client secret of your casdoor application.
- Backend URL: The address of the computer running your casdoor program:the backend port.
- Organization: Only the users of this organization can log in, e.g. the organization of your casdoor application. Leave it empty to allow the users of all organizations.
- Redirect to the dashboard after signing in: If this radio box is checked, after logging in, the user will be redirected to the dashboard page.
- Restrict flow to log in only: If this radio box is checked, casdoor will not insert user's information to wordpress's wp_users table.In other words, casdoor users that do not exist in the wordpress will not be able to login.
- Auto SSO for users that are not logged in: If this radio box is checked, the user will be redirected to the login page, even if the page the user visits does not require a login.
- Log out of casdoor when logging out of WordPress: If this radio box is checked, logging out of WordPress also ends the casdoor session, so the next login asks for the credentials again. Add `http://your-wordpress-domain/` to the `Redirect URLs` list of your casdoor application too, casdoor only redirects back to an allowed url after the logout.

After successfully setting up this plugin, all login requests sent to login.php will be redirected to casdoor application.

Users that only exist in WordPress (e.g. the admin created when installing WordPress) can not log in on casdoor. They can still use the WordPress login form at `http://your-wordpress-domain/wp-login.php?use_native_login=1`, which also has a button to log in with casdoor. Lost password and reset password pages of WordPress are not redirected either.

## workflow
The login starts on `http://your-wordpress-domain/?auth=casdoor`. The plugin creates a random `state`, binds it to the browser with a cookie and sends the user to casdoor. When casdoor sends the user back, the plugin checks the `state`, exchanges the code for an access token on the casdoor backend (with TLS verification), and asks casdoor for the user that owns the token (`/api/get-account`).

The casdoor user is then matched to a wordpress user in this order:

1. The wordpress user linked to this casdoor user (the `casdoor_user_id` user meta, saved on every login).
2. The wordpress user with the same login name that logged in with this casdoor user before (users of older versions of the plugin).
3. The wordpress user with the same email, only when casdoor has verified the email.

A login name alone is never enough, so a casdoor user named `admin` can not log in as the wordpress `admin`. When no user matches, a new wordpress user is created (unless `Restrict flow to log in only` is checked), with a login name like `admin-2` if the name is taken. The admins of the organization in the `Organization` setting become wordpress administrators.

## Development

### Running Tests

This plugin uses PHPUnit for unit testing. To run the tests:

1. Install dependencies:
   ```bash
   composer install
   ```

2. Run the test suite:
   ```bash
   composer test
   # or directly:
   vendor/bin/phpunit
   ```

3. Run tests with coverage:
   ```bash
   composer test:coverage
   ```

### Continuous Integration

The project uses GitHub Actions for CI/CD:

- **Tests**: Automatically runs on all pull requests and pushes to main/master branches
- **Semantic Release**: Automatically creates releases when PRs are merged to main/master

The test suite runs against multiple PHP versions (7.4, 8.0, 8.1, 8.2, 8.3) to ensure compatibility.

## TODOS
- Integrate `php-casdoor-sdk`
- Publish this plugin to wordpress
- Display warning and error messages