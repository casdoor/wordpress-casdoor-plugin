<?php

defined('ABSPATH') || exit;

$casdoor_messages = [
    'casdoor_login_only'         => __('This Casdoor account does not exist in WordPress, please use another one.', 'casdoor'),
    'casdoor_sso_failed'         => __('Casdoor login failed.', 'casdoor'),
    'casdoor_invalid_state'      => __('Casdoor login failed. The login was not started in this browser or has expired.', 'casdoor'),
    'casdoor_wrong_organization' => __('Casdoor login failed. This Casdoor user does not belong to the organization of this site.', 'casdoor'),
    'casdoor_email_conflict'     => __('Casdoor login failed. The email is used by another user, verify the email in Casdoor first.', 'casdoor'),
];
$casdoor_message = Casdoor_Rewrites::message();

if (isset($casdoor_messages[$casdoor_message])) : ?>
    <div class="casdoor-message error" role="alert">
        <p>
            <?php echo esc_html($casdoor_messages[$casdoor_message]); ?>
            <a href="<?php echo esc_url(casdoor_redirect_uri()); ?>"><?php esc_html_e('Please try again', 'casdoor'); ?></a>
        </p>
    </div>
<?php endif; ?>
