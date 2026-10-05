<?php

defined('ABSPATH') || exit;

// The option that keeps the settings of the plugin.
if (!defined('CASDOOR_OPTIONS')) {
    define('CASDOOR_OPTIONS', 'casdoor_options');
}

// The user meta that keeps the casdoor access token of a user, it is needed to log the
// user out of casdoor when the user logs out of wordpress.
if (!defined('CASDOOR_TOKEN_META_KEY')) {
    define('CASDOOR_TOKEN_META_KEY', 'casdoor_access_token');
}

/**
 * The settings of a new installation.
 *
 * @return array
 */
function casdoor_default_options(): array
{
    return [
        'active'                => 0,
        'client_id'             => '',
        'client_secret'         => '',
        'backend'               => '',
        'organization'          => '',
        'redirect_to_dashboard' => 0,
        'login_only'            => 0,
        'auto_sso'              => 0,
        'logout_from_casdoor'   => 0,
    ];
}

function casdoor_get_options_internal(): array
{
    $options = get_option(CASDOOR_OPTIONS, []);
    if (!is_array($options)) {
        $options = [];
    }
    return array_merge(casdoor_default_options(), $options);
}

/**
 * get option value
 *
 * @param string $option_name
 *
 * @return void|string
 */
function casdoor_get_option(string $option_name)
{
    $options = casdoor_get_options_internal();
    if (!empty($options[$option_name])) {
        return $options[$option_name];
    }
}

function casdoor_set_options(string $key, $value)
{
    $options = casdoor_get_options_internal();
    $options[$key] = $value;
    update_option(CASDOOR_OPTIONS, $options);
}

// The user meta that links a wordpress user to its casdoor user (the id of the casdoor user).
if (!defined('CASDOOR_USER_META_KEY')) {
    define('CASDOOR_USER_META_KEY', 'casdoor_user_id');
}

// The cookie that binds the state of a login to the browser that started it.
if (!defined('CASDOOR_STATE_COOKIE')) {
    define('CASDOOR_STATE_COOKIE', 'casdoor_state');
}

/**
 * The url casdoor sends the user back to with the authorization code. It must be in the
 * `Redirect URLs` list of the casdoor application.
 *
 * @return string
 */
function casdoor_redirect_uri(): string
{
    return site_url('?auth=casdoor');
}

/**
 * Get the url of the casdoor endpoint with the given path.
 *
 * @param string $path
 *
 * @return string
 */
function casdoor_backend_url(string $path): string
{
    return rtrim((string) casdoor_get_option('backend'), '/') . $path;
}

/**
 * Get the login url of casdoor
 *
 * The client secret never goes into this url, the url is visible to the browser.
 *
 * @param string $state the state created by casdoor_create_state()
 *
 * @return string
 */
function casdoor_get_login_url(string $state = ''): string
{
    $params = [
        'client_id'     => casdoor_get_option('client_id'),
        'response_type' => 'code',
        'redirect_uri'  => casdoor_redirect_uri(),
        'scope'         => 'read',
        'state'         => $state,
    ];
    return casdoor_backend_url('/login/oauth/authorize?' . http_build_query($params));
}

/**
 * Write the state cookie, an empty value removes it.
 *
 * @param string $value
 * @param int    $expires
 *
 * @return void
 */
function casdoor_set_state_cookie(string $value, int $expires)
{
    setcookie(CASDOOR_STATE_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
        'domain'   => defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Start a login: create a random state, remember where to send the user after the login and
 * bind the state to this browser with a cookie, so a code that was obtained in another browser
 * (login CSRF) is refused by casdoor_consume_state().
 *
 * @param string $redirect where to send the user after the login
 *
 * @return string the state to send to casdoor
 */
function casdoor_create_state(string $redirect): string
{
    $state = wp_generate_password(32, false, false);
    set_transient('casdoor_state_' . $state, $redirect, 10 * MINUTE_IN_SECONDS);
    casdoor_set_state_cookie($state, time() + 10 * MINUTE_IN_SECONDS);
    return $state;
}

/**
 * Check the state that casdoor sent back and use it up.
 *
 * @param string $state the state from the callback
 *
 * @return string|null where to send the user after the login, null if the state is invalid
 */
function casdoor_consume_state(string $state)
{
    $cookie = isset($_COOKIE[CASDOOR_STATE_COOKIE]) && is_string($_COOKIE[CASDOOR_STATE_COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[CASDOOR_STATE_COOKIE])) : '';
    casdoor_set_state_cookie('', time() - HOUR_IN_SECONDS);

    if ($state === '' || $cookie === '' || !hash_equals($cookie, $state) || !preg_match('/^[a-zA-Z0-9]+$/', $state)) {
        return null;
    }

    $redirect = get_transient('casdoor_state_' . $state);
    delete_transient('casdoor_state_' . $state);
    if ($redirect === false) {
        return null;
    }
    return (string) $redirect;
}

/**
 * Exchange the authorization code for an access token.
 *
 * @param string $code
 *
 * @return string|WP_Error
 */
function casdoor_exchange_code(string $code)
{
    $response = wp_remote_post(casdoor_backend_url('/api/login/oauth/access_token'), [
        'timeout' => 15,
        'body'    => [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => casdoor_get_option('client_id'),
            'client_secret' => casdoor_get_option('client_secret'),
            'redirect_uri'  => casdoor_redirect_uri(),
        ],
    ]);
    if (is_wp_error($response)) {
        return $response;
    }

    $tokens = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($tokens) || empty($tokens['access_token']) || !is_string($tokens['access_token'])) {
        $error = is_array($tokens) && !empty($tokens['error_description']) ? $tokens['error_description'] : 'no access token returned by casdoor';
        return new WP_Error('casdoor_token', $error);
    }
    return $tokens['access_token'];
}

/**
 * Get the casdoor user that owns the access token.
 *
 * Casdoor looks the token up in its own database, so the user does not depend on decoding
 * the token here.
 *
 * @param string $access_token
 *
 * @return object|WP_Error the casdoor user, e.g. owner, name, id, email, emailVerified, isAdmin
 */
function casdoor_get_account(string $access_token)
{
    $response = wp_remote_get(casdoor_backend_url('/api/get-account'), [
        'timeout' => 15,
        'headers' => ['Authorization' => 'Bearer ' . $access_token],
    ]);
    if (is_wp_error($response)) {
        return $response;
    }

    $body = json_decode(wp_remote_retrieve_body($response));
    if (!is_object($body) || ($body->status ?? '') !== 'ok' || !isset($body->data) || !is_object($body->data)
        || empty($body->data->owner) || empty($body->data->name)) {
        $error = is_object($body) && !empty($body->msg) ? $body->msg : 'failed to get the casdoor user';
        return new WP_Error('casdoor_account', $error);
    }
    return $body->data;
}

/**
 * The stable id of a casdoor user, the user id or `owner/name` for old casdoor versions.
 *
 * @param object $account
 *
 * @return string
 */
function casdoor_account_id($account): string
{
    if (!empty($account->id)) {
        return (string) $account->id;
    }
    return $account->owner . '/' . $account->name;
}

/**
 * Get `owner/name` of the user an access token was issued to, without checking the signature.
 * It is only used on tokens that the plugin saved itself.
 *
 * @param string $token
 *
 * @return string
 */
function casdoor_token_subject(string $token): string
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return '';
    }
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!is_array($payload) || empty($payload['owner']) || empty($payload['name'])) {
        return '';
    }
    return $payload['owner'] . '/' . $payload['name'];
}

/**
 * Find the wordpress user of a casdoor user.
 *
 * A wordpress user is used when it is linked to the casdoor user, when it logged in with this
 * casdoor user before the plugin linked users, or when it has the email the casdoor user has
 * verified. The login name alone is never enough, anyone can register a casdoor user named
 * `admin`.
 *
 * @param object $account
 *
 * @return WP_User|null
 */
function casdoor_find_user($account)
{
    // phpcs:disable WordPress.DB.SlowDBQuery -- one lookup per login, by the link to the casdoor user.
    $users = get_users([
        'meta_key'   => CASDOOR_USER_META_KEY,
        'meta_value' => casdoor_account_id($account),
        'number'     => 1,
    ]);
    // phpcs:enable WordPress.DB.SlowDBQuery
    if (!empty($users)) {
        return $users[0];
    }

    $user = get_user_by('login', $account->name);
    if ($user) {
        $token = get_user_meta($user->ID, CASDOOR_TOKEN_META_KEY, true);
        if (is_string($token) && $token !== '' && casdoor_token_subject($token) === $account->owner . '/' . $account->name) {
            return $user;
        }
    }

    if (!empty($account->email) && !empty($account->emailVerified)) {
        $user = get_user_by('email', $account->email);
        if ($user) {
            return $user;
        }
    }

    return null;
}

/**
 * Get a login name for a new wordpress user that is not taken yet.
 *
 * @param string $name the casdoor user name
 *
 * @return string
 */
function casdoor_unique_login(string $name): string
{
    $base = sanitize_user($name, true);
    if ($base === '') {
        $base = 'casdoor-user';
    }

    $login = $base;
    for ($i = 2; username_exists($login); $i++) {
        $login = $base . '-' . $i;
    }
    return $login;
}

/**
 * Create the wordpress user of a casdoor user.
 *
 * @param object $account
 *
 * @return WP_User|WP_Error
 */
function casdoor_create_user($account)
{
    $email = !empty($account->email) ? (string) $account->email : '';
    // The email belongs to another wordpress user but casdoor has not verified it, so the
    // users can not be linked.
    if ($email !== '' && email_exists($email)) {
        return new WP_Error('casdoor_email_conflict', 'the email is used by another user');
    }

    $user_data = [
        'user_login'   => casdoor_unique_login((string) $account->name),
        'user_email'   => $email,
        'user_pass'    => wp_generate_password(24, true, true),
        'display_name' => !empty($account->displayName) ? $account->displayName : $account->name,
    ];
    // Only the admins of the organization that is allowed to log in become administrators.
    if (!empty($account->isAdmin) && casdoor_get_option('organization') === $account->owner) {
        $user_data['role'] = 'administrator';
    }

    $user_id = wp_insert_user($user_data);
    if (is_wp_error($user_id)) {
        return $user_id;
    }
    return get_user_by('id', $user_id);
}

/**
 * Send the user to the home page with one of the messages of templates/error-msg.php.
 *
 * @param string $message
 *
 * @return void
 */
function casdoor_login_failed(string $message)
{
    wp_safe_redirect(add_query_arg('casdoor_message', $message, home_url('/')));
    exit;
}

/**
 * Get the logout url of casdoor.
 *
 * Casdoor implements the OIDC RP-Initiated Logout on `/api/logout`, it ends the casdoor
 * session and sends the user back to `post_logout_redirect_uri` afterwards.
 * An empty string is returned when the logout can not be done, e.g. when the user logged
 * in with the wordpress login form and there is no casdoor session at all.
 *
 * @param int    $user_id  the wordpress user that is logging out
 * @param string $redirect where casdoor should send the user back to
 *
 * @return string
 */
function casdoor_get_logout_url(int $user_id, string $redirect = ''): string
{
    $backend = casdoor_get_option('backend');
    if (empty($backend) || empty($user_id)) {
        return '';
    }

    // Casdoor needs the access token to know which session has to be ended, it was saved
    // when the user logged in.
    $access_token = get_user_meta($user_id, CASDOOR_TOKEN_META_KEY, true);
    if (empty($access_token)) {
        return '';
    }

    if (empty($redirect)) {
        $redirect = home_url('/');
    }
    // The redirect must be in the `Redirect URLs` list of the casdoor application,
    // otherwise casdoor refuses to redirect back.
    $redirect = apply_filters('casdoor_post_logout_redirect_url', $redirect);

    $params = http_build_query([
        'id_token_hint'            => $access_token,
        'post_logout_redirect_uri' => $redirect,
        'client_id'                => casdoor_get_option('client_id')
    ]);

    return rtrim($backend, '/') . '/api/logout?' . $params;
}

/**
 * Whether a request to wp-login.php should be redirected to the login page of casdoor.
 *
 * Only the plain login page is replaced by casdoor. The login form posts to wp-login.php and
 * the other actions (logout, lost password, reset password, ...) are left to wordpress, and
 * `use_native_login=1` shows the wordpress login form. Otherwise users that only exist in
 * wordpress are sent to casdoor every time and can never log in.
 *
 * @param string $method the request method
 * @param array  $query  the query parameters of the request
 *
 * @return bool
 */
function casdoor_should_redirect_login(string $method, array $query): bool
{
    if (strtoupper($method) !== 'GET') {
        return false;
    }
    if (!empty($query['use_native_login'])) {
        return false;
    }

    $action = isset($query['action']) && is_string($query['action']) ? $query['action'] : '';
    return $action === '' || $action === 'login';
}

/**
 * Keep the login links on the wordpress login form, used as the `login_url` filter while
 * wp-login.php shows the wordpress form.
 *
 * @param string $login_url
 *
 * @return string
 */
function casdoor_native_login_url(string $login_url): string
{
    return add_query_arg('use_native_login', '1', $login_url);
}

/**
 * Add login button for casdoor on the login form, it is added in Casdoor_Plugin::custom_login()
 * when the wordpress login form is shown.
 *
 * @link https://developer.wordpress.org/reference/hooks/login_form/
 */
function casdoor_login_form_button()
{
    printf(
        '<p style="margin-bottom:1em;"><a class="button button-primary button-large" style="width:100%%;text-align:center;" href="%s">%s</a></p>',
        esc_url(casdoor_redirect_uri()),
        esc_html__('Log in with Casdoor', 'casdoor')
    );
}

/**
 * The [casdoor_login_button] shortcode, a link that starts the login.
 *
 * @param array|string $atts
 *
 * @return string
 */
function casdoor_login_button_shortcode($atts): string
{
    $a = shortcode_atts([
        'title'  => __('Log in with Casdoor', 'casdoor'),
        'class'  => 'sso-button',
        'target' => '_self',
        'text'   => __('Log in with Casdoor', 'casdoor'),
    ], $atts);

    return sprintf(
        '<a class="%s" href="%s" title="%s" target="%s">%s</a>',
        esc_attr($a['class']),
        esc_url(casdoor_redirect_uri()),
        esc_attr($a['title']),
        esc_attr($a['target']),
        esc_html($a['text'])
    );
}

function casdoor_register_shortcodes()
{
    add_shortcode('casdoor_login_button', 'casdoor_login_button_shortcode');
    // The old name of the shortcode, kept for the pages that use it.
    if (!shortcode_exists('sso_button')) {
        add_shortcode('sso_button', 'casdoor_login_button_shortcode');
    }
}

/**
 * Get user login redirect.
 * Just in case the user wants to redirect the user to a new url.
 *
 * @return string
 */
function casdoor_get_user_redirect_url(): string
{
    $user_redirect = casdoor_get_option('redirect_to_dashboard') == 1 ? get_dashboard_url() : site_url();
    return apply_filters('casdoor_user_redirect_url', $user_redirect);
}
