<?php

namespace ArDesign\DPD;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined('ABSPATH') || exit;

/**
 * Log to the file
 *
 * @param string $message
 * @param mixed $variable
 *
 * @return void
 */

function ard_dpd_log($message, $variable = null)
{
    $message = trim($message);
    $message = "DEBUG: $message";

    $log_handler = new \WC_Log_Handler_File();
    $file = $log_handler->get_log_file_path('ar_design_dpd');

    $date = get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'Y-m-d H:i:s O');

    if (null !== $variable) {
        $message .= ': ' . ard_dpd_dump($variable);
    }

    // implementation note:
    // we do not use WooCommerce log handler intentionally, because it does not
    // use file locking, thus there is possibility that log gets damaged
    file_put_contents($file, "[$date] $message\n", LOCK_EX | FILE_APPEND);
}

/**
 * Dump variable
 *
 * @param mixed $variable
 *
 * @return mixed
 */
function ard_dpd_dump($variable)
{
    $type = gettype($variable);
    switch ($type) {
        case 'boolean':
            return $variable ? 'true' : 'false';
        case 'integer':
            return strval($variable);
        case 'double':
            return sprintf('%.1f', $variable);
        case 'string':
            $variable = str_replace([ '\\', "'" ], [ '\\\\', "\\'" ], $variable);
            return "'" . preg_replace_callback('/[\x00-\x1f\x7f-\xff]/', function ($m) {
                return "\\x" . bin2hex($m[0]);
            }, $variable) . "'";
        case 'array':
            $items = [];
            foreach ($variable as $key => $value) {
                $items[] = ard_dpd_dump($key) . ': ' . ard_dpd_dump($value);
            }
            return $items ? '[ ' . implode(', ', $items) . ' ]' : '[]';
        case 'object':
            $items = [];
            foreach (get_object_vars($variable) as $key => $value) {
                $items[] = ard_dpd_dump($key) . ': ' . ard_dpd_dump($value);
            }
            return get_class($variable) . ' ' . ($items ? '{ ' . implode(', ', $items) . ' }' : '{}');
        case 'resource':
        case 'resource (closed)':
            return $type;
        case 'NULL':
            return 'null';
    }
    return "??? $type ???";
}

/**
 * Get current plugin version
 *
 * @return string
 */
function ard_dpd_get_plugin_version()
{
    $plugin_data = get_plugin_data(AR_DESIGN_DPD_PLUGIN_INDEX);
    $plugin_version = !empty($plugin_data['Version']) ? (string) $plugin_data['Version'] : '1.0.0';

    return $plugin_version;
}

/**
 * Include php template and pass variables there
 *
 * @param string $file_path
 * @param array $variables
 * @param boolean $print
 *
 * @return string
 */
function ard_dpd_include_template($file_path, $variables = [])
{
    $templates_root = AR_DESIGN_DPD_PLUGIN_TEMPLATES_PATH . DIRECTORY_SEPARATOR;
    $output = '';

    if (file_exists($templates_root . $file_path)) {
        // Extract the variables to a local namespace
        extract($variables);

        // Start output buffering
        ob_start();

        // Include the template file
        include $templates_root . $file_path;

        // End buffering and return its contents
        $output = ob_get_clean();

        if ($output === false || $output === null) {
            $output = '';
        }
    }

    return $output;
}

/**
 * Check if HPOS mode is enabled in WooCommerce settings.
 *
 * @return bool true if HPOS mode is enabled, false otherwise.
 */
function ard_dpd_is_hpos_enabled()
{
    return (bool) class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled();
}

function ard_dpd_is_woocommerce_active()
{
    return is_woocommerce_active();
}

function ard_dpd_is_map_widget_enabled()
{
    return is_map_widget_enabled();
}

function ard_dpd_is_woocommerce_blocks_enabled()
{
    return is_woocommerce_blocks_enabled();
}

function ard_dpd_apply_filters(string $legacy_hook, string $new_hook, $value, ...$args)
{
    $filtered_value = apply_filters($legacy_hook, $value, ...$args);

    if ($new_hook === $legacy_hook) {
        return $filtered_value;
    }

    return apply_filters($new_hook, $filtered_value, ...$args);
}

function ard_dpd_do_action(string $legacy_hook, string $new_hook, ...$args): void
{
    do_action($legacy_hook, ...$args);

    if ($new_hook !== $legacy_hook) {
        do_action($new_hook, ...$args);
    }
}

/**
 * Check if woocommerce is active
 *
 * @return bool
 */
function is_woocommerce_active()
{
    // Check if WooCommerce is active on the network
    if (is_multisite()) {
        $network_active_plugins = get_site_option('active_sitewide_plugins');
        if (isset($network_active_plugins['woocommerce/woocommerce.php'])) {
            return true;
        }
    }

    // Check if WooCommerce is active on the current site
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        return true;
    }

    return false;
}

/**
 * Check if map widget is enabled
 *
 * @return boolean
 */
function is_map_widget_enabled()
{
    return (bool) DpdExportSettings::isMapWidgetEnabled();
}

/**
 * Check if cart or checkout page is loaded
 *
 * @return boolean
 */
function is_cart_or_checkout_page()
{
    $is_cart_or_checkout = (bool) (is_cart() || is_checkout());

    /**
     * Filter to customize cart or checkout page detection
     *
     * @param bool $is_cart_or_checkout Current detection result
     * @return bool Modified detection result
     */
    return ard_dpd_apply_filters('wc_dpd_is_cart_or_checkout_page', 'ard_dpd_is_cart_or_checkout_page', $is_cart_or_checkout);
}

/**
 * Check if WooCommerce Blocks is enabled
 *
 * @return boolean
 */
function is_woocommerce_blocks_enabled()
{
    // First check if WC_Blocks_Utils class exists
    if (!class_exists('\WC_Blocks_Utils')) {
        return false;
    }

    // Get page IDs
    $cart_page_id = wc_get_page_id('cart');
    $checkout_page_id = wc_get_page_id('checkout');

    // Check if the pages exist and are valid
    if ($cart_page_id <= 0 && $checkout_page_id <= 0) {
        return false;
    }

    // Check for blocks in cart page
    $is_cart_block = false;
    if ($cart_page_id > 0) {
        $cart_post = get_post($cart_page_id);
        if ($cart_post && has_blocks($cart_post->post_content) &&
            strpos($cart_post->post_content, 'woocommerce/cart') !== false) {
            $is_cart_block = true;
        }
    }

    // Check for blocks in checkout page
    $is_checkout_block = false;
    if ($checkout_page_id > 0) {
        $checkout_post = get_post($checkout_page_id);
        if ($checkout_post && has_blocks($checkout_post->post_content) &&
            strpos($checkout_post->post_content, 'woocommerce/checkout') !== false) {
            $is_checkout_block = true;
        }
    }

    // Only check for shortcodes if we're in a proper WP query context
    $is_not_using_shortcodes = true;

    if (did_action('wp') && (is_page($cart_page_id) || is_page($checkout_page_id))) {
        global $post;
        if ($post && isset($post->post_content)) {
            // Check for traditional shortcodes
            if (has_shortcode($post->post_content, 'woocommerce_cart') ||
                has_shortcode($post->post_content, 'woocommerce_checkout')) {
                $is_not_using_shortcodes = false;
            }
        }
    }

    // Return true only if blocks are used and shortcodes are not
    return $is_cart_block || $is_checkout_block;
}

namespace WcDPD;

defined('ABSPATH') || exit;

function wc_dpd_log($message, $variable = null)
{
    return \ArDesign\DPD\ard_dpd_log($message, $variable);
}

function wc_dpd_dump($variable)
{
    return \ArDesign\DPD\ard_dpd_dump($variable);
}

function wc_dpd_get_plugin_version()
{
    return \ArDesign\DPD\ard_dpd_get_plugin_version();
}

function include_template($file_path, $variables = [])
{
    return \ArDesign\DPD\ard_dpd_include_template($file_path, $variables);
}

function wc_dpd_is_hpos_enabled()
{
    return \ArDesign\DPD\ard_dpd_is_hpos_enabled();
}

function is_woocommerce_active()
{
    return \ArDesign\DPD\ard_dpd_is_woocommerce_active();
}

function is_map_widget_enabled()
{
    return \ArDesign\DPD\ard_dpd_is_map_widget_enabled();
}

function is_cart_or_checkout_page()
{
    return \ArDesign\DPD\is_cart_or_checkout_page();
}

function is_woocommerce_blocks_enabled()
{
    return \ArDesign\DPD\ard_dpd_is_woocommerce_blocks_enabled();
}
