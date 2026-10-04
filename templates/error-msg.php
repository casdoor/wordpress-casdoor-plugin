<?php

// ABSPATH prevent public user to directly access your .php files through URL.
defined('ABSPATH') or die('No script kiddies please!');

$message = $wp_query->get('message');
$alert_message = '';
if ($message == 'casdoor_login_only') {
    $alert_message = 'This casdoor account doesn\'t exists in wordpress, please use another.';
} elseif ($message == 'casdoor_sso_failed') {
    $alert_message = 'Casdoor Single Sign On Failed. User mismatch or clash with existing data and SSO can not complete.';
} elseif ($message == 'casdoor_id_not_allowed') {
    $alert_message = 'For security reasons, this user can not use Single Sign On.';
} elseif ($message == 'casdoor_invalid_state') {
    $alert_message = 'Casdoor Single Sign On Failed. The login was not started in this browser or has expired.';
} elseif ($message == 'casdoor_wrong_organization') {
    $alert_message = 'Casdoor Single Sign On Failed. This casdoor user does not belong to the organization of this site.';
} elseif ($message == 'casdoor_email_conflict') {
    $alert_message = 'Casdoor Single Sign On Failed. The email is used by another user, verify the email in casdoor first.';
}

if (!empty($alert_message)) : ?>
    <div class="error">
        <p class="alertbar"><?= $alert_message . ' <a href="' . site_url('?auth=casdoor') . '">Please try again</a>'?></p>
    </div>
<?php endif; ?>
