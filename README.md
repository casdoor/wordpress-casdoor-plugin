# Casdoor – SSO, OAuth 2.0 & OIDC Login

[![Tests](https://github.com/casdoor/wordpress-casdoor-plugin/actions/workflows/test.yml/badge.svg)](https://github.com/casdoor/wordpress-casdoor-plugin/actions/workflows/test.yml)
[![Release](https://github.com/casdoor/wordpress-casdoor-plugin/actions/workflows/release.yml/badge.svg)](https://github.com/casdoor/wordpress-casdoor-plugin/actions/workflows/release.yml)
[![GitHub release](https://img.shields.io/github/v/release/casdoor/wordpress-casdoor-plugin.svg)](https://github.com/casdoor/wordpress-casdoor-plugin/releases/latest)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](LICENSE)

The WordPress plugin for [Casdoor](https://github.com/casdoor/casdoor): users log in to WordPress with their Casdoor account over OAuth 2.0 and OpenID Connect. The plugin slug is `casdoor`.

## Installation

Download `casdoor.zip` from the [latest release](https://github.com/casdoor/wordpress-casdoor-plugin/releases/latest) and upload it in WordPress under Plugins > Add New > Upload Plugin. The plugin is being submitted to the WordPress.org plugin directory as `casdoor`.

## Get started
First, activate this plugin as an admin, this will add a new section about casdoor to your settings page. 

Because this plugin is a client of casdoor. So you need to run a casdoor program, create a application and add `http://your-wordpress-domain/?auth=casdoor` to the `Redirect URLs` list of your casdoor application.

Then open Settings > Casdoor SSO and set up the plugin, this mainly involves the following settings.

- Activate: replace the WordPress login page with Casdoor.
- Casdoor URL: the URL of your Casdoor server, e.g. `https://door.casdoor.com`.
- Client ID and Client secret: of your Casdoor application.
- Organization: only the users of this organization can log in, e.g. the organization of your Casdoor application. Leave it empty to allow the users of all organizations.
- After login: go to the dashboard after logging in.
- Existing users only: do not create WordPress users, only the users that already exist can log in.
- Auto login: send the visitors that are not logged in to Casdoor on every page.
- Logout: logging out of WordPress also ends the Casdoor session, so the next login asks for the credentials again. Add `http://your-wordpress-domain/` to the `Redirect URLs` of your Casdoor application too, Casdoor only redirects back to an allowed URL after the logout.

The `[casdoor_login_button]` shortcode (`[sso_button]` still works) shows a login link anywhere.

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

- **Tests** (`test.yml`) run on all pull requests and pushes to master, against PHP 7.4 to 8.3.
- **Release** (`release.yml`): a `feat:` or `fix:` commit on master makes semantic-release tag a new version.
- **Deploy** (`deploy.yml`): the tag writes its version into `casdoor.php` and `readme.txt` (never committed back), builds `casdoor.zip` without the files in `.distignore`, attaches it to the GitHub release and, once the `SVN_USERNAME` and `SVN_PASSWORD` secrets of the WordPress.org account are set, deploys it to the WordPress.org plugin directory.

`readme.txt` is the page of the plugin on WordPress.org, keep its `Tested up to` current.
The icon, banner and screenshots of that page are in `.wordpress-org/`, they are uploaded to the `assets` folder of the SVN repository by the deploy and are not in the plugin zip.
