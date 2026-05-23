<?php

namespace ArDesign\DPD;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined('ABSPATH') || exit;

/**
 * Official WooCommerce Blocks integration for DPD parcelshop checkout UI.
 */
class BlocksIntegration implements IntegrationInterface
{
    private const SCRIPT_HANDLE = 'ard_dpd_checkout_blocks_integration';
    private const STYLE_HANDLE = 'ard_dpd_checkout_blocks_integration_styles';
    private const INTEGRATION_NAME = 'ard_dpd_checkout_blocks';

    public function get_name()
    {
        return self::INTEGRATION_NAME;
    }

    public function initialize()
    {
        $script_relative_path = 'public/scripts/dpd-parcelshop-checkout-blocks-registry.js';
        $style_relative_path = 'public/styles/dpd-parcelshop-block-shipping-method.css';

        wp_register_script(
            self::SCRIPT_HANDLE,
            AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'scripts/dpd-parcelshop-checkout-blocks-registry.js',
            [],
            $this->getAssetVersion($script_relative_path),
            true
        );

        wp_register_style(
            self::STYLE_HANDLE,
            AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'styles/dpd-parcelshop-block-shipping-method.css',
            [],
            $this->getAssetVersion($style_relative_path)
        );

        wp_enqueue_style(self::STYLE_HANDLE);
    }

    public function get_script_handles()
    {
        return [self::SCRIPT_HANDLE];
    }

    public function get_editor_script_handles()
    {
        return [];
    }

    public function get_script_data()
    {
        return Blocks::getCheckoutBlockScriptData();
    }

    private function getAssetVersion(string $relative_path): string
    {
        $absolute_path = AR_DESIGN_DPD_PLUGIN_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);

        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($absolute_path)) {
            return (string) filemtime($absolute_path);
        }

        return (string) ard_dpd_get_plugin_version();
    }
}