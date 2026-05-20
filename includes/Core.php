<?php

namespace ArDesign\DPD;

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
        EnergySurchargeMonitor::init();
        DpdExportSettings::init();
        EnergySurcharge::init();
        Assets::init();
        Ajax::init();
        \ArDesign\DPD\Automation::init();
        \ArDesign\DPD\OrderWorkflow::init();
        Notice::init();
        Shipping::init();
        Order::init();
        \ArDesign\DPD\Shipment::init();
        \ArDesign\DPD\Tracking::init();
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
                AR_DESIGN_DPD_TEXT_DOMAIN,
                false,
                dirname(plugin_basename(AR_DESIGN_DPD_PLUGIN_INDEX)) . DIRECTORY_SEPARATOR . 'languages'
            );
        });
    }
}
