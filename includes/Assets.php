<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

/**
 * Assets class
 */
class Assets
{
    public static function init()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueScripts']);
    }

    /**
     * Enqueue plugin styles and scripts
     *
     * @return void
     */
    public static function enqueueScripts()
    {
        if (!is_cart_or_checkout_page()) {
            return;
        }

        $is_map_widget_enabled = is_map_widget_enabled();

        // Enqueue styles
        wp_enqueue_style('ard_dpd_parcelshop_shipping_method_content_styles', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'styles/dpd-parcelshop-shipping-method-content.css', [], ard_dpd_get_plugin_version(), 'all');

        if ($is_map_widget_enabled) {
            wp_enqueue_style('ard_dpd_parcelshop_map_widget_styles', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'styles/dpd-parcelshop-map-widget.css', [], ard_dpd_get_plugin_version(), 'all');
            wp_enqueue_style('ard_dpd_parcelshop_popup_styles', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'styles/dpd-parcelshop-popup.css', [], ard_dpd_get_plugin_version(), 'all');
        } else {
            wp_enqueue_style('ard_dpd_parcelshop_popup_styles', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'styles/dpd-parcelshop-popup.css', [], ard_dpd_get_plugin_version(), 'all');
        }

        // Enqueue scripts
        if ($is_map_widget_enabled) {
            wp_enqueue_script('ard_dpd_parcelshop_map_scripts', 'https://pus-maps.dpd.sk/lib/library.js', [], ard_dpd_get_plugin_version(), true);
            wp_enqueue_script('ard_dpd_parcelshop_map_widget_scripts', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'scripts/dpd-parcelshop-map-widget-fixed.js', [], ard_dpd_get_plugin_version(), true);
            wp_enqueue_script('ard_dpd_parcelshop_popup_scripts', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'scripts/dpd-parcelshop-popup.js', [], ard_dpd_get_plugin_version(), true);
            wp_localize_script('ard_dpd_parcelshop_map_widget_scripts', 'ard_dpd_parcelshop_map_widget_settings', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'no_pickup_types_error_message' => __('No pickup point types are allowed. Please check the shipping settings.', 'ar-design-dpd'),
                'widget_error_message' => __('DPD pickup point selection could not be opened. Please try again or choose a different payment/shipping combination.', 'ar-design-dpd'),
                'invalid_api_key_error_message' => __('The configured DPD Map API Key is invalid. Switching to the manual parcelshop search.', 'ar-design-dpd'),
            ]);
            wp_localize_script('ard_dpd_parcelshop_popup_scripts', 'ard_dpd_parcelshop_popup_settings', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'required_fields_error_message' => __('Please fill all the fields above!', 'ar-design-dpd'),
                'select_parcelshop_error_message' => __('Please select one of the available parcelshops!', 'ar-design-dpd')
            ]);
        } else {
            wp_enqueue_script('ard_dpd_parcelshop_popup_scripts', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'scripts/dpd-parcelshop-popup.js', [], ard_dpd_get_plugin_version(), true);
            wp_localize_script('ard_dpd_parcelshop_popup_scripts', 'ard_dpd_parcelshop_popup_settings', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'required_fields_error_message' => __('Please fill all the fields above!', 'ar-design-dpd'),
                'select_parcelshop_error_message' => __('Please select one of the available parcelshops!', 'ar-design-dpd')
            ]);
        }

    }
}
