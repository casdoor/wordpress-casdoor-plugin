=== Casdoor – SSO, OAuth 2.0 & OIDC Login ===
Contributors: casdoor
Tags: sso, oauth, openid connect, single sign-on, login
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: Apache-2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0

Log in to WordPress with Casdoor, the open-source identity and access management (IAM) and single sign-on (SSO) platform.

== Description ==

[Casdoor](https://casdoor.ai) is an open-source identity and access management (IAM) and single sign-on (SSO) platform. With this plugin your users log in to WordPress with their Casdoor account, over OAuth 2.0 and OpenID Connect, the same way they log in to the other applications of your organization.

Behind Casdoor they can use passwords, MFA, passkeys, social logins (Google, GitHub, WeChat, Microsoft and many more), LDAP, SAML or any other provider Casdoor supports, without another plugin in WordPress.

**Features**

* Replaces the WordPress login page with Casdoor, or adds a "Log in with Casdoor" button to it.
* Creates the WordPress user on the first login, or only lets existing users in.
* Links WordPress users to Casdoor users by the Casdoor user ID or a verified email, never by the user name alone.
* Only lets in the users of one Casdoor organization, if you want.
* Logs out of Casdoor together with WordPress.
* Optional automatic login for visitors that are not logged in.
* The `[casdoor_login_button]` shortcode for a login link anywhere.
* Protects the login with the OAuth `state`, verifies TLS and asks Casdoor for the user, so a login can not be forged.

Users that only exist in WordPress, such as the first admin, can still use the WordPress login form at `/wp-login.php?use_native_login=1`.

**Casdoor**

* Website: [casdoor.ai](https://casdoor.ai)
* Source code of Casdoor: [github.com/casdoor/casdoor](https://github.com/casdoor/casdoor)
* Source code of this plugin: [github.com/casdoor/wordpress-casdoor-plugin](https://github.com/casdoor/wordpress-casdoor-plugin)

== External services ==

This plugin connects to the Casdoor server that you set in Settings > Casdoor SSO. It is your own Casdoor (self-hosted or a Casdoor cloud instance), the plugin does not connect to any other service.

* When a user logs in, the browser is sent to the login page of your Casdoor server.
* After the login, the plugin sends the authorization code with the client ID and client secret of your Casdoor application to the server, and gets the access token and the user's account (name, display name, email, organization) from it.
* When a user logs out and "Log out of Casdoor too" is enabled, the browser is sent to the logout page of your Casdoor server with the user's token.

Casdoor is open-source software under the Apache-2.0 license. The terms and privacy policy are those of whoever runs your Casdoor server; for Casdoor cloud they are at [casdoor.com/terms](https://casdoor.com/terms) and [casdoor.com/privacy](https://casdoor.com/privacy).

== Installation ==

1. Install and activate the plugin.
2. In Casdoor, create an application (or use an existing one) and add `https://your-site/?auth=casdoor` to its "Redirect URLs". To log out of Casdoor together with WordPress, add `https://your-site/` too.
3. In WordPress, go to Settings > Casdoor SSO, enter the URL of your Casdoor server and the client ID and client secret of the application, and check "Activate".

== Frequently Asked Questions ==

= Do I need a Casdoor server? =

Yes. Run your own with Docker (see the [Casdoor docs](https://casdoor.ai/docs/basic/server-installation)) or use Casdoor cloud.

= I am locked out after activating the plugin. =

Use the WordPress login form at `/wp-login.php?use_native_login=1`.

= Which WordPress user does a Casdoor user log in as? =

The WordPress user that is linked to the Casdoor user, the one that logged in with this Casdoor user before, or the one with the same email when Casdoor has verified the email. Otherwise a new WordPress user is created, unless "Existing users only" is checked. The admins of the organization in the "Organization" setting become WordPress administrators.

= Can I change where users go after logging in? =

Yes, with the `casdoor_user_redirect_url` filter. The `casdoor_user_login` and `casdoor_user_created` actions run after a login and after a new user is created.

== Changelog ==

See the [releases on GitHub](https://github.com/casdoor/wordpress-casdoor-plugin/releases).
