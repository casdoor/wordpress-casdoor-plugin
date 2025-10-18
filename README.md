# wordpress-casdoor-plugin
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
- Organization: This is a setting for `casdoor-php-sdk`, you can ignore it now.
- Redirect to the dashboard after signing in: If this radio box is checked, after logging in, the user will be redirected to the dashboard page.
- Restrict flow to log in only: If this radio box is checked, casdoor will not insert user's information to wordpress's wp_users table.In other words, casdoor users that do not exist in the wordpress will not be able to login.
- Auto SSO for users that are not logged in: If this radio box is checked, the user will be redirected to the login page, even if the page the user visits does not require a login.

After successfully setting up this plugin, all login requests sent to login.php will be redirected to casdoor application.

### Fallback to Native WordPress Login
If you have users who only exist in WordPress (not in Casdoor), they can access the native WordPress login by:
1. Visiting the Casdoor login page and clicking the "Use WordPress Login" link at the bottom
2. Directly accessing `wp-login.php?use_native_login=1`

This provides a fallback mechanism for WordPress-only users while keeping Casdoor SSO as the primary login method.

## workflow
After the username/email, password you entered is verified by casdoor, there may be two situations. Casdoor will try to find the corresponding user, if the user exists in wordpress, casdoor will login as this user, otherwise it will insert the user's information to the wp_users table of wordpress, then login as this user.

## TODOS
- Integrate `php-casdoor-sdk`
- Publish this plugin to wordpress
- Display warning and error messages