<?php

/*
 * Plugin Name: AR Design DPD for WooCommerce
 * Description: Samostatný DPD modul pre WooCommerce spravovaný Arpád Horák. Fork vychádza z pôvodnej integrácie Webikon, ktorá zostáva uvedená ako coworker foundation projektu.
 * Version: 8.6.6
 * Author: Arpád Horák
 * Author URI: https://arpad-horak.cz
 * Update URI: https://github.com/Arpad70/woocommerce_ar-design-dpd
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ar-design-dpd
 * Domain Path: /languages
 * Requires at least: 5.3
 * Tested up to: 6.9.4
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.6.1
 */

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

/**
 * Global plugin constants
 */
//  Work out plugin folder name and store it as a constant
$plugin_dir = str_replace(basename(__FILE__), "", plugin_basename(__FILE__));
$plugin_dir = substr($plugin_dir, 0, strlen($plugin_dir) - 1);
define('AR_DESIGN_DPD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AR_DESIGN_DPD_PLUGIN_DIR', $plugin_dir);
define('AR_DESIGN_DPD_PLUGIN_INDEX', __FILE__);
define('AR_DESIGN_DPD_PLUGIN_WC_MIN_VERSION', '7.0');
define('AR_DESIGN_DPD_PLUGIN_ASSETS_URL', plugins_url(AR_DESIGN_DPD_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR));
define('AR_DESIGN_DPD_PLUGIN_TEMPLATES_PATH', AR_DESIGN_DPD_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR);
define('AR_DESIGN_DPD_VERSION', '8.6.6');
define('AR_DESIGN_DPD_BASENAME', plugin_basename(__FILE__));
define('AR_DESIGN_DPD_REPOSITORY', 'Arpad70/woocommerce_ar-design-dpd');
define('AR_DESIGN_DPD_TEXT_DOMAIN', 'ar-design-dpd');

$legacy_constants = [
    'WCDPD_PLUGIN_PATH' => AR_DESIGN_DPD_PLUGIN_PATH,
    'WCDPD_PLUGIN_DIR' => AR_DESIGN_DPD_PLUGIN_DIR,
    'WCDPD_PLUGIN_INDEX' => AR_DESIGN_DPD_PLUGIN_INDEX,
    'WCDPD_PLUGIN_WC_MIN_VERSION' => AR_DESIGN_DPD_PLUGIN_WC_MIN_VERSION,
    'WCDPD_PLUGIN_ASSETS_URL' => AR_DESIGN_DPD_PLUGIN_ASSETS_URL,
    'WCDPD_PLUGIN_TEMPLATES_PATH' => AR_DESIGN_DPD_PLUGIN_TEMPLATES_PATH,
];

foreach ($legacy_constants as $legacy_constant_name => $legacy_constant_value) {
    if (!defined($legacy_constant_name)) {
        define($legacy_constant_name, $legacy_constant_value);
    }
}

require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'Updater.php';
require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'RollbackManager.php';
require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'EnergySurcharge.php';
require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'EnergySurchargeMonitor.php';

if (class_exists(__NAMESPACE__ . '\\ArDesignDpdUpdater') && !class_exists('WcDPD\\ArDesignDpdUpdater', false)) {
    class_alias(__NAMESPACE__ . '\\ArDesignDpdUpdater', 'WcDPD\\ArDesignDpdUpdater');
}

function ard_dpd_register_legacy_class_aliases(): void
{
    $legacy_aliases = [
        Ajax::class => 'WcDPD\\Ajax',
        \ArDesign\DPD\Automation::class => 'WcDPD\\Automation',
        Assets::class => 'WcDPD\\Assets',
        Blocks::class => 'WcDPD\\Blocks',
        Client::class => 'WcDPD\\Client',
        Core::class => 'WcDPD\\Core',
        DpdExport::class => 'WcDPD\\DpdExport',
        DpdExportSettings::class => 'WcDPD\\DpdExportSettings',
        DpdClassicShippingMethod::class => 'WcDPD\\DpdClassicShippingMethod',
        DpdHomeShippingMethod::class => 'WcDPD\\DpdHomeShippingMethod',
        DpdExpress1000ShippingMethod::class => 'WcDPD\\DpdExpress1000ShippingMethod',
        DpdExpress1200ShippingMethod::class => 'WcDPD\\DpdExpress1200ShippingMethod',
        DpdGuaranteeShippingMethod::class => 'WcDPD\\DpdGuaranteeShippingMethod',
        DpdParcelShopShippingMethod::class => 'WcDPD\\DpdParcelShopShippingMethod',
        Email::class => 'WcDPD\\Email',
        Hooks::class => 'WcDPD\\Hooks',
        Notice::class => 'WcDPD\\Notice',
        Order::class => 'WcDPD\\Order',
        OrderList::class => 'WcDPD\\OrderList',
        OrderMetabox::class => 'WcDPD\\OrderMetabox',
        \ArDesign\DPD\Shipment::class => 'WcDPD\\Shipment',
        Shipping::class => 'WcDPD\\Shipping',
        Tracking::class => 'WcDPD\\Tracking',
        ArDesignDpdUpdater::class => 'WcDPD\\ArDesignDpdUpdater',
    ];

    foreach ($legacy_aliases as $modern_class => $legacy_alias) {
        if (class_exists($modern_class) && !class_exists($legacy_alias, false)) {
            class_alias($modern_class, $legacy_alias);
        }
    }
}

/**
 * Declare HPOS support
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Check if WC meets the required version
 */
add_action('admin_notices', function () {
    if (class_exists('WooCommerce') && version_compare(WC()->version, AR_DESIGN_DPD_PLUGIN_WC_MIN_VERSION, '>=')) {
        return; // WooCommerce is active and meets the required version, so no notice needed
    }

    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo sprintf(__('DPD SK for WooCommerce plugin requires WooCommerce version %s or higher to work properly. Please update WooCommerce to use this plugin.', AR_DESIGN_DPD_TEXT_DOMAIN), AR_DESIGN_DPD_PLUGIN_WC_MIN_VERSION); ?></p>
    </div>
    <?php
});

/**
 * Autoload plugin files
 */
add_action('plugins_loaded', function () {
    // Check that the composer autoloader is present
    $composer_autoloader = AR_DESIGN_DPD_PLUGIN_PATH . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!file_exists($composer_autoloader)) {
        return;
    }

    require_once $composer_autoloader;

    ard_dpd_register_legacy_class_aliases();

    if (!\ArDesign\DPD\is_woocommerce_active()) {
        return; // WooCommerce is not active, so exit early
    }

    \ArDesign\DPD\Core::initTranslations();

    // Compare the installed WooCommerce version with the required version
    if (!class_exists('WooCommerce') || version_compare(WC()->version, AR_DESIGN_DPD_PLUGIN_WC_MIN_VERSION, '<')) {
        return; // WooCommerce is not active or doesn't meet the required version, so exit early
    }

    // Initialize the plugin
    \ArDesign\DPD\Core::init();
});

$ar_design_dpd_updater = new \ArDesign\DPD\ArDesignDpdUpdater(
    AR_DESIGN_DPD_REPOSITORY,
    AR_DESIGN_DPD_BASENAME,
    AR_DESIGN_DPD_VERSION
);
$ar_design_dpd_updater->register();

$ar_design_dpd_rollback_manager = new \ArDesign\DPD\ArDesignDpdRollbackManager(
    AR_DESIGN_DPD_BASENAME,
    AR_DESIGN_DPD_PLUGIN_PATH
);
$ar_design_dpd_rollback_manager->register();
