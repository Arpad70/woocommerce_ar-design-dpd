<?php

namespace ArDesign\DPD;

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
        self::ensureSessionStarted();
    }

    /**
     * Display admin notices
     *
     * @return void
     */
    public static function displayNotices()
    {
        self::ensureSessionStarted();

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

        if (function_exists('session_write_close') && self::isSessionActive()) {
            @session_write_close();
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
        self::ensureSessionStarted();

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

        if (function_exists('session_write_close') && self::isSessionActive()) {
            @session_write_close();
        }
    }

    private static function ensureSessionStarted(): void
    {
        if (headers_sent()) {
            return;
        }

        if (self::isSessionActive()) {
            return;
        }

        @session_start();
    }

    private static function isSessionActive(): bool
    {
        if (function_exists('session_status')) {
            return session_status() === PHP_SESSION_ACTIVE;
        }

        return session_id() !== '';
    }
}
