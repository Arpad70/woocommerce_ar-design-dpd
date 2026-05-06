<?php

namespace WcDPD;

defined('ABSPATH') || exit;

/**
 * Core class
 */
class Core
{
    /**
     * Initialize classes
     */
    public static function init()
    {
        // Initialize classes
        Assets::init();
        Ajax::init();
        Notice::init();
        Shipping::init();
        Order::init();
        OrderMetabox::init();
        OrderList::init();
        Email::init();
        Hooks::init();
        Blocks::init();
    }

    /**
     * Init translations.
     */
    public static function initTranslations()
    {
        add_action('after_setup_theme', function () {
            load_plugin_textdomain(
                'wc-dpd',
                false,
                dirname(plugin_basename(WCDPD_PLUGIN_INDEX)) . DIRECTORY_SEPARATOR . 'languages'
            );
        });
    }
}
