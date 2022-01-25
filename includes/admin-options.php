<?php

// ABSPATH prevent public user to directly access your .php files through URL.
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Class casdoor_admin
 */
class casdoor_admin
{
    const OPTIONS_NAME = 'casdoor_options';

    public static function init()
    {
        // add_action adds a callback function to an action hook.
        // admin_init fires as an admin screen or script is being initialized.
        add_action('admin_init', [new self, 'admin_init']);
        // admin_menu fires before the administration menu loads in the admin.
        // This action is used to add extra submenus and menu options to the admin panel’s menu structure. It runs after the basic admin panel menu structure is in place.
        add_action('admin_menu', [new self, 'add_page']);
    }

    /**
     * [admin_init description]
     *
     * @return [type] [description]
     */
    public function admin_init()
    {
        // A callback function that sanitizes the option's value
        register_setting('casdoor_options', self::OPTIONS_NAME, [$this, 'validate']);
    }

    /**
     * Add casdoor submenu page to the settings main menu
     */
    public function add_page()
    {
        add_options_page('Casdoor SSO', 'Casdoor SSO', 'manage_options', 'casdoor_settings', [$this, 'options_do_page']);
    }

    /**
     * Loads the plugin styles and scripts into scope
     *
     * @return void
     */
    public function admin_head()
    {
        // Registers the script if $src provided (does NOT overwrite), and enqueues it.
        wp_enqueue_script('jquery-ui-accordion');
        // Registers the style if source provided (does NOT overwrite) and enqueues.
        wp_enqueue_style('casdoor_admin');
        wp_enqueue_script('casdoor_admin');
    }

    /**
     * [options_do_page description]
     *
     * @return [type] [description]
     */
    public function options_do_page()
    {
        // loads the plugin styles and scripts into scope
        $this->admin_head();
        ?>
        <div class="wrap">
            <h2>Casdoor Plugin Configuration</h2>
            <p>This plugin is meant to be used with <a href="https://casdoor.org/">casdoor</a>.</p>
            <p>
                When activated, this plugin will redirect all login requests to your casdoor page.
                <br/>
                <strong>NOTE:</strong> If you want to add a
                custom link anywhere in your theme simply link to
                <strong><?= site_url('?auth=casdoor'); ?></strong>
                if the user is not logged in.
            </p>
            <div id="accordion">
                <h4>Step 1: Setup</h4>
                <div>
                    <strong>Setting up Casdoor</strong>
                    <ol>
                        <li>Install and Run casdoor (<a
                                    href="https://github.com/casbin/casdoor" target="_blank">GitHub</a>)
                        </li>
                        <li>Create a new application and add following uri to callback URLs:
                            <strong class="code"><?= site_url('?auth=casdoor'); ?></strong></li>
                        <li>Copy the Client ID and Client Secret in Step 2 below.</li>
                    </ol>
                </div>
                <h4 id="sso-configuration">Step 2: Configuration</h4>
                <div>
                    <form method="post" action="options.php">
                        <?php settings_fields('casdoor_options'); ?>
                        <table class="form-table">
                        <tr valign="top">
                                <th scope="row">Activate Casdoor</th>
                                <td>
                                    <input type="checkbox"
                                        name="<?= self::OPTIONS_NAME ?>[active]"
                                        value="1" <?= casdoor_get_option('active') == 1 ? 'checked="checked"' : ''; ?> />
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Client ID</th>
                                <td>
                                    <input type="text" name="<?= self::OPTIONS_NAME ?>[client_id]" min="10"
                                           value="<?= casdoor_get_option('client_id') ?>"/>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Client Secret</th>
                                <td>
                                    <input type="text" name="<?= self::OPTIONS_NAME ?>[client_secret]" min="10"
                                           value="<?= casdoor_get_option('client_secret'); ?>"/>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Backend URL</th>
                                <td>
                                    <input type="text" name="<?= self::OPTIONS_NAME ?>[backend]" min="10"
                                           value="<?= casdoor_get_option('backend'); ?>"/>
                                    <p class="description">Example: https://your-casdoor-backend.com</p>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Organization</th>
                                <td>
                                    <input type="text" name="<?= self::OPTIONS_NAME ?>[organization]" 
                                           value="<?= casdoor_get_option('organization'); ?>"/>
                                    <p class="description">Example/Default: built-in</p>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Redirect to the dashboard after signing in</th>
                                <td>
                                    <input type="checkbox"
                                           name="<?= self::OPTIONS_NAME ?>[redirect_to_dashboard]"
                                           value="1" <?= casdoor_get_option('redirect_to_dashboard') == 1 ? 'checked="checked"' : ''; ?> />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Restrict flow to log in only</th>
                                <td>
                                    <input type="checkbox"
                                           name="<?= self::OPTIONS_NAME ?>[login_only]"
                                           value="1" <?= casdoor_get_option('login_only') == 1 ? 'checked="checked"' : ''; ?> />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Auto SSO for users that are not logged in</th>
                                <td>
                                    <input type="checkbox"
                                           name="<?= self::OPTIONS_NAME ?>[auto_sso]"
                                           value="1" <?= casdoor_get_option('auto_sso') == 1 ? 'checked="checked"' : ''; ?> />
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
                        </p>
                </div>

                </form>
            </div>
        </div>
        <div style="clear:both;"></div>
        </div>
        <?php
    }

    /**
     * Settings Validation
     *
     * @param array $input option array
     *
     * @return array
     */
    public function validate(array $input): array
    {
        $input['redirect_to_dashboard'] = isset($input['redirect_to_dashboard']) ? $input['redirect_to_dashboard'] : 0;
        $input['login_only']            = isset($input['login_only']) ? $input['login_only'] : 0;
        $input['organization']          = isset($input['organization']) ? $input['organization'] : 'built-in';

        return $input;
    }
}

casdoor_admin::init();
