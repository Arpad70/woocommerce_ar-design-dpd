<?php

namespace WcDPD;

defined('ABSPATH') || exit;

/**
 * Notice class
 */
class Notice
{
    public const PREFIX = 'DPD Export: ';

    public static function init()
    {
        add_filter('admin_init', [__CLASS__, 'initSession']);
        add_filter('admin_notices', [__CLASS__, 'displayNotices']);
    }

    /**
     * Initialize session
     *
     * @return void
     */
    public static function initSession()
    {
        if (!session_id()) {
            session_start();
        }

        if (function_exists('session_status') && session_status() != constant('PHP_SESSION_ACTIVE') || session_id() != '') {
            @session_write_close();
        }
    }

    /**
     * Display admin notices
     *
     * @return void
     */
    public static function displayNotices()
    {
        $notices = isset($_SESSION['notices']) && !empty($_SESSION['notices']) ? (array) wp_kses_post_deep($_SESSION['notices']) : [];

        foreach ($notices as $notice) {
            wp_kses_post(printf(
                '<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
                isset($notice['type']) && !empty($notice['type']) ? $notice['type'] : 'success',
                isset($notice['dismissible']) && (bool) $notice['dismissible'] ? 'is-dismissible' : false,
                isset($notice['notice']) && !empty($notice['notice']) ? self::PREFIX . $notice['notice'] : '',
            ));
        }

        // Unset already flashed notices
        if (!empty($notices)) {
            unset($_SESSION['notices']);
        }
    }

    /**
     * Add error notice
     *
     * @param string $notice
     *
     * @return void
     */
    public static function error($notice)
    {
        self::add($notice, 'error');
    }

    /**
     * Add success notice
     *
     * @param string $notice
     *
     * @return void
     */
    public static function success($notice)
    {
        self::add($notice, 'success');
    }

    /**
     * Add notice
     *
     * @param string $notice
     * @param string $type
     * @param bool $dismissible
     *
     * @return void
     */
    public static function add($notice = "", $type = "warning", $dismissible = true)
    {
        $notices = isset($_SESSION['notices']) && !empty($_SESSION['notices']) ? (array) wp_kses_post_deep($_SESSION['notices']) : [];
        $dismissible_text = ($dismissible) ? "is-dismissible" : "";

        array_push(
            $notices,
            wp_kses_post_deep(
                array(
                    "notice" => $notice,
                    "type" => $type,
                    "dismissible" => $dismissible_text
                )
            )
        );

        $_SESSION['notices'] = $notices;
    }
}
