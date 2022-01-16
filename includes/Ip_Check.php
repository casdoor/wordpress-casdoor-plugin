<?php

// ABSPATH prevent public user to directly access your .php files through URL.
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Class Ip_Check
 */
class Ip_Check
{
    public static function init()
    {
        if (!casdoor_get_option('ip_range_check') || current_user_can('manage_options')) {
            return;
        } else {
            new self();
        }
    }

    private function __construct()
    {
        add_filter('casdoor_get_option', [$this, 'check_activate'], 10, 2);
    }

    public function check_activate($val, $key)
    {
        if ($key != 'active') {
            return $val;
        }

        $is_active = $this->validate_ip() ? 1 : 0;
        return $is_active;
    }

    private function validate_ip()
    {
        $ranges = $this->get_ranges();
        $ip = $_SERVER['REMOTE_ADDR'];

        foreach ($ranges as $range) {
            $in_range = $this->in_range($ip, $range);
            if ($in_range) {
                return true;
            }
        }
        return false;
    }

    private function in_range($ip, $range)
    {
        $from = ip2long($range['from']);
        $to = ip2long($range['to']);
        $ip = ip2long($ip);

        return $ip >= $from && $ip <= $to;
    }

    private function get_ranges()
    {
        $data = casdoor_get_option('ip_ranges');
        $data = str_replace("\r\n", "\n", $data);

        $raw = explode("\n", $data);

        $ranges = [];
        foreach ($raw as $r) {
            $d = explode('-', $r);

            if (count($d) < 2) {
                continue;
            }

            $ranges[] = [
                'from' => trim($d[0]),
                'to'   => trim($d[1])
            ];
        }

        return $ranges;
    }
}
