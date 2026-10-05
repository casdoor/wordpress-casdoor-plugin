<?php

// Deleting the plugin removes its settings and the links between WordPress and Casdoor users.
defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('casdoor_options');
delete_metadata('user', 0, 'casdoor_user_id', '', true);
delete_metadata('user', 0, 'casdoor_access_token', '', true);
