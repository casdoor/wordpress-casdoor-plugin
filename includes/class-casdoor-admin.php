<?php

defined('ABSPATH') || exit;

/**
 * The settings page, Settings > Casdoor SSO.
 */
class Casdoor_Admin
{
    const PAGE = 'casdoor_settings';

    public static function init()
    {
        add_action('admin_init', [__CLASS__, 'admin_init']);
        add_action('admin_menu', [__CLASS__, 'add_page']);
        add_filter('plugin_action_links_' . plugin_basename(CASDOOR_PLUGIN_DIR . 'casdoor.php'), [__CLASS__, 'action_links']);
    }

    public static function admin_init()
    {
        register_setting('casdoor_options', CASDOOR_OPTIONS, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'validate'],
        ]);
    }

    public static function add_page()
    {
        add_options_page(
            __('Casdoor SSO', 'casdoor'),
            __('Casdoor SSO', 'casdoor'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'options_do_page']
        );
    }

    /**
     * A "Settings" link on the plugins page.
     *
     * @param array $links
     *
     * @return array
     */
    public static function action_links(array $links): array
    {
        $url = admin_url('options-general.php?page=' . self::PAGE);
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'casdoor') . '</a>');
        return $links;
    }

    private static function text_field(string $key, string $label, string $description = '', string $type = 'text')
    {
        ?>
        <tr>
            <th scope="row"><label for="casdoor-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input type="<?php echo esc_attr($type); ?>" class="regular-text" id="casdoor-<?php echo esc_attr($key); ?>"
                       name="<?php echo esc_attr(CASDOOR_OPTIONS . '[' . $key . ']'); ?>"
                       value="<?php echo esc_attr((string) casdoor_get_option($key)); ?>" autocomplete="off"/>
                <?php if ($description !== '') : ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private static function checkbox_field(string $key, string $label, string $description = '')
    {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr(CASDOOR_OPTIONS . '[' . $key . ']'); ?>"
                           value="1" <?php checked(1, (int) casdoor_get_option($key)); ?>/>
                    <?php echo esc_html($description); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    public static function options_do_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Casdoor SSO', 'casdoor'); ?></h1>
            <p>
                <?php
                printf(
                    /* translators: %s: link to the Casdoor website */
                    esc_html__('Log in to WordPress with %s, the open-source identity and access management (IAM) and single sign-on (SSO) platform.', 'casdoor'),
                    '<a href="https://casdoor.ai" target="_blank" rel="noopener">Casdoor</a>'
                );
                ?>
            </p>

            <h2><?php esc_html_e('Step 1: Set up Casdoor', 'casdoor'); ?></h2>
            <ol>
                <li><?php esc_html_e('In Casdoor, create an application (or use an existing one).', 'casdoor'); ?></li>
                <li>
                    <?php esc_html_e('Add this URL to the "Redirect URLs" of the application:', 'casdoor'); ?>
                    <code><?php echo esc_html(casdoor_redirect_uri()); ?></code>
                </li>
                <li>
                    <?php esc_html_e('To log out of Casdoor together with WordPress, add this URL too:', 'casdoor'); ?>
                    <code><?php echo esc_html(home_url('/')); ?></code>
                </li>
                <li><?php esc_html_e('Copy the client ID and client secret of the application into step 2.', 'casdoor'); ?></li>
            </ol>
            <p>
                <?php esc_html_e('A login link anywhere on the site: link to the URL above, or use the [casdoor_login_button] shortcode.', 'casdoor'); ?>
            </p>

            <h2><?php esc_html_e('Step 2: Configuration', 'casdoor'); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields('casdoor_options'); ?>
                <table class="form-table" role="presentation">
                    <?php
                    self::checkbox_field('active', __('Activate', 'casdoor'), __('Replace the WordPress login page with Casdoor.', 'casdoor'));
                    self::text_field('backend', __('Casdoor URL', 'casdoor'), __('The URL of your Casdoor server, e.g. https://door.casdoor.com', 'casdoor'), 'url');
                    self::text_field('client_id', __('Client ID', 'casdoor'));
                    self::text_field('client_secret', __('Client secret', 'casdoor'), '', 'password');
                    self::text_field('organization', __('Organization', 'casdoor'), __('Only the users of this organization can log in, leave it empty to allow the users of all organizations.', 'casdoor'));
                    self::checkbox_field('redirect_to_dashboard', __('After login', 'casdoor'), __('Go to the dashboard after logging in.', 'casdoor'));
                    self::checkbox_field('login_only', __('Existing users only', 'casdoor'), __('Do not create WordPress users, only users that already exist can log in.', 'casdoor'));
                    self::checkbox_field('auto_sso', __('Auto login', 'casdoor'), __('Send visitors that are not logged in to Casdoor on every page.', 'casdoor'));
                    self::checkbox_field('logout_from_casdoor', __('Logout', 'casdoor'), __('Log out of Casdoor too when logging out of WordPress, so the next login asks for the credentials again.', 'casdoor'));
                    ?>
                </table>
                <?php submit_button(); ?>
            </form>
            <p>
                <?php esc_html_e('Users that only exist in WordPress can still use the WordPress login form at:', 'casdoor'); ?>
                <code><?php echo esc_html(add_query_arg('use_native_login', '1', wp_login_url())); ?></code>
            </p>
        </div>
        <?php
    }

    /**
     * Settings Validation
     *
     * @param mixed $input option array
     *
     * @return array
     */
    public static function validate($input): array
    {
        $input  = is_array($input) ? $input : [];
        $output = [];
        foreach (['active', 'redirect_to_dashboard', 'login_only', 'auto_sso', 'logout_from_casdoor'] as $key) {
            $output[$key] = empty($input[$key]) ? 0 : 1;
        }
        foreach (['client_id', 'client_secret', 'organization'] as $key) {
            $output[$key] = isset($input[$key]) ? sanitize_text_field($input[$key]) : '';
        }
        $output['backend'] = isset($input['backend']) ? untrailingslashit(esc_url_raw(trim($input['backend']))) : '';

        return $output;
    }
}
