<?php

/*
 * Plugin Name: AR Design DPD for WooCommerce
 * Description: Samostatný DPD modul pre WooCommerce spravovaný Arpád Horák. Fork vychádza z pôvodnej integrácie Webikon, ktorá zostáva uvedená ako coworker foundation projektu.
 * Version: 8.6.14
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
define('AR_DESIGN_DPD_VERSION', '8.6.14');
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

function ard_dpd_ensure_legacy_language_aliases(): void
{
    if (!defined('WP_LANG_DIR')) {
        return;
    }

    $locale = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
    if ('' === $locale) {
        return;
    }

    $target_dir = WP_LANG_DIR . DIRECTORY_SEPARATOR . 'plugins';
    if (!is_dir($target_dir)) {
        if (!function_exists('wp_mkdir_p') || !wp_mkdir_p($target_dir)) {
            return;
        }
    }

    $legacy_prefix = $target_dir . DIRECTORY_SEPARATOR . 'wc-dpd-' . $locale;
    $source_prefix = AR_DESIGN_DPD_PLUGIN_PATH . 'languages' . DIRECTORY_SEPARATOR . 'ar-design-dpd-' . $locale;

    foreach (['mo', 'po'] as $extension) {
        $target_file = $legacy_prefix . '.' . $extension;
        $source_file = $source_prefix . '.' . $extension;

        if (file_exists($target_file) || !file_exists($source_file)) {
            continue;
        }

        @copy($source_file, $target_file);
    }

    $l10n_php_file = $legacy_prefix . '.l10n.php';
    if (file_exists($l10n_php_file)) {
        return;
    }

    $stub = <<<'PHP'
<?php
return [
	'domain' => 'wc-dpd',
	'plural-forms' => 'nplurals=2; plural=n != 1;',
	'language' => '',
	'messages' => [],
];
PHP;

    $stub = str_replace("'language' => ''", "'language' => '" . addslashes(substr($locale, 0, 2)) . "'", $stub);
    @file_put_contents($l10n_php_file, $stub);
}

ard_dpd_ensure_legacy_language_aliases();

require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'Updater.php';
require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'RollbackManager.php';
require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'EnergySurcharge.php';
require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'EnergySurchargeMonitor.php';
require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'OrderWorkflow.php';

$composer_autoloader = AR_DESIGN_DPD_PLUGIN_PATH . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($composer_autoloader)) {
    require_once $composer_autoloader;

    Core::initTranslations();
}

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
function ard_dpd_bootstrap_woo_runtime(): void
{
    $composerAutoloader = AR_DESIGN_DPD_PLUGIN_PATH . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!file_exists($composerAutoloader)) {
        return;
    }

    ard_dpd_register_legacy_class_aliases();

    if (!\ArDesign\DPD\is_woocommerce_active()) {
        return; // WooCommerce is not active, so exit early
    }

    // Compare the installed WooCommerce version with the required version
    if (!class_exists('WooCommerce') || version_compare(WC()->version, AR_DESIGN_DPD_PLUGIN_WC_MIN_VERSION, '<')) {
        return; // WooCommerce is not active or doesn't meet the required version, so exit early
    }

    // Initialize the plugin
    \ArDesign\DPD\Core::init();
}

add_action('woocommerce_loaded', __NAMESPACE__ . '\\ard_dpd_bootstrap_woo_runtime', 20);

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
