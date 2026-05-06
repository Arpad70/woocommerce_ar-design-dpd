<?php

namespace WcDPD;

defined('ABSPATH') || exit;

/**
 * Hooks class
 */
class Hooks
{
    public static function init()
    {
        add_filter('wc_dpd_client_error_message', [__CLASS__, 'clearClientErrorMessageCharacters'], 10, 1);
    }

    public static function clearClientErrorMessageCharacters($message)
    {
        $message = wp_strip_all_tags($message);
        $message = str_replace(['System.Object[]', '\n'], '', $message);

        return $message;
    }
}
