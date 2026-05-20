<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

/**
 * OrderList class
 */
class OrderList
{
    public const EXPORT_ORDER_KEY = 'dpd_order_export';
    public const BULK_EXPORT_ORDERS_KEY = 'dpd_bulk_orders_export';
    public const BULK_DOWNLOAD_LABELS_KEY = 'dpd_bulk_download_labels';

    public static function init()
    {

        add_action('admin_init', [__CLASS__, 'maybeExportSingleOrder']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAdminScripts']);
        add_action('wp_ajax_ard_dpd_mark_label_printed', [__CLASS__, 'ajaxMarkLabelPrinted']);

        if (ard_dpd_is_hpos_enabled()) {
            add_filter('manage_woocommerce_page_wc-orders_columns', [__CLASS__, 'addOrdersGridDPDExportColumn']);
            add_action('manage_woocommerce_page_wc-orders_custom_column', [__CLASS__, 'addOrderByDPDExportColumn'], 10, 2);
            add_action('bulk_actions-woocommerce_page_wc-orders', [__CLASS__, 'addBulkActions'], 10, 1);
            add_action('handle_bulk_actions-woocommerce_page_wc-orders', [__CLASS__, 'handleBulkActions'], 10, 3);
        } else {
            add_filter('manage_edit-shop_order_columns', [__CLASS__, 'addOrdersGridDPDExportColumn']);
            add_action('manage_shop_order_posts_custom_column', [__CLASS__, 'addOrderByDPDExportColumn'], 10, 2);
            add_action('bulk_actions-edit-shop_order', [__CLASS__, 'addBulkActions'], 10, 1);
            add_action('handle_bulk_actions-edit-shop_order', [__CLASS__, 'handleBulkActions'], 10, 3);
        }
    }

    /**
     * Add bulk export custom action to the orders list
     *
     * @param array $bulk_actions
     *
     * @return array
     */
    public static function addBulkActions($bulk_actions)
    {
        $bulk_actions[self::BULK_EXPORT_ORDERS_KEY] = __('DPD Bulk Export', 'ar-design-dpd');
        $bulk_actions[self::BULK_DOWNLOAD_LABELS_KEY] = __('DPD Bulk Download Labels', 'ar-design-dpd');

        return $bulk_actions;
    }

    /**
     * Bulk orders export action handler
     *
     * @param string $redirect_to
     * @param string $action
     * @param array $order_ids
     *
     * @return string
     */
    public static function handleBulkActions($redirect_to, string $action, array $order_ids)
    {
        if ($action == self::BULK_EXPORT_ORDERS_KEY) {
            foreach ($order_ids as $order_id) {
                Order::export($order_id);
            }
        }

        if ($action == self::BULK_DOWNLOAD_LABELS_KEY) {
            Order::bulkDownloadLabels($order_ids);
        }

        return $redirect_to;
    }

    /**
     * Add DPD export status column to orders listing grid table
     *
     * @param array<string, mixed> $columns
     *
     * @return array<string, mixed>
     */
    public static function addOrdersGridDPDExportColumn(array $columns): array
    {
        $new_columns = [];

        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;

            if ('order_status' === $column_name) {
                $new_columns[DpdExportSettings::SETTINGS_ID_KEY] = __('Export to DPD', 'ar-design-dpd');
            }
        }

        return $new_columns;
    }

    /**
     * Populate order DPD export status column value
     *
     * @param string $column
     * @param \WC_Order|int|null $order_or_order_id
     *
     * @return void
     */
    public static function addOrderByDPDExportColumn(string $column, \WC_Order|int|null $order_or_order_id = null): void
    {
        if (DpdExportSettings::SETTINGS_ID_KEY !== $column) {
            return;
        }

        if (!$order_or_order_id instanceof \WC_Order) {
            $order = wc_get_order($order_or_order_id);
        } else {
            $order = $order_or_order_id;
        }

        if (!$order instanceof \WC_Order) {
            return;
        }

        $dpd_export_result = $order->get_meta(Order::EXPORT_STATUS_META_KEY, true);

        if (!$dpd_export_result) {
            echo '<p><a class="button" href="' . esc_url(add_query_arg(self::EXPORT_ORDER_KEY, $order->get_id())) . '">' . __('Export', 'ar-design-dpd') . '</a></p>';

            return;
        }

        if ($dpd_export_result === Order::EXPORT_SUCCESS_STATUS) {
            $dpd_package_number = wp_kses_post($order->get_meta(Order::EXPORT_PACKAGE_NUMBER_META_KEY, true));

            $labelButtonHtml = self::getLabelDownloadButtonHtml($order);
            if ($labelButtonHtml !== '') {
                echo '<p>' . $labelButtonHtml . '</p>';
            }

            if ($dpd_package_number) {
                echo '<p style="font-size: 12px; margin-top: 5px;">' . __('Package number', 'ar-design-dpd') . ':<br><strong>' . $dpd_package_number . '</strong></p>';
            }

            $currentLabel = (string) $order->get_meta('dpd_shipment_tracking_label', true);
            $currentStatus = (string) $order->get_meta('dpd_shipment_tracking_status', true);
            $currentStatusCode = (string) $order->get_meta('dpd_shipment_tracking_status_code', true);
            $shipmentStatus = (string) $order->get_meta(Shipment::STATUS_META_KEY, true);

            if ($currentLabel !== '' || $currentStatus !== '' || $currentStatusCode !== '' || $shipmentStatus !== '') {
                echo '<p style="font-size: 12px; margin-top: 5px; line-height: 1.35;">';

                if ($currentStatusCode !== '') {
                    echo '<strong>' . esc_html__('Code:', 'ar-design-dpd') . '</strong> <code>' . esc_html($currentStatusCode) . '</code><br>';
                }

                if ($currentLabel !== '') {
                    echo '<strong>' . esc_html__('Status:', 'ar-design-dpd') . '</strong> ' . esc_html($currentLabel);
                } elseif ($currentStatus !== '') {
                    echo '<strong>' . esc_html__('Status:', 'ar-design-dpd') . '</strong> <code>' . esc_html($currentStatus) . '</code>';
                }

                if ($shipmentStatus !== '') {
                    echo '<br><strong>' . esc_html__('Shipment:', 'ar-design-dpd') . '</strong> <code>' . esc_html($shipmentStatus) . '</code>';
                }

                echo '</p>';
            }
        }
    }

    /**
     * Export single order if conditions are met
     */
    public static function maybeExportSingleOrder()
    {
        if (!isset($_GET[self::EXPORT_ORDER_KEY])) {
            return;
        }

        $order_id = !empty($_GET[self::EXPORT_ORDER_KEY]) ? (int) $_GET[self::EXPORT_ORDER_KEY] : null;

        if (!$order_id) {
            Notice::error(sprintf(
                /* translators: %d: invalid WooCommerce order ID. */
                __('Wrong order ID %d', 'ar-design-dpd'),
                $order_id
            ));

            return;
        }

        Order::export($order_id);

        wp_safe_redirect(remove_query_arg(self::EXPORT_ORDER_KEY));
        exit;
    }

    public static function enqueueAdminScripts(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, ['shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders'], true)) {
            return;
        }

        wp_enqueue_script(
            'ard-dpd-admin-workflow',
            AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'scripts/dpd-admin-workflow.js',
            ['jquery'],
            ard_dpd_get_plugin_version(),
            true
        );

        wp_localize_script('ard-dpd-admin-workflow', 'ardDpdAdminWorkflow', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ard_dpd_mark_label_printed'),
            'confirmText' => __('Potvrďte prosím, že štítok bol naozaj vytlačený a objednávka sa má prepnúť na stav Na odoslanie.', 'ar-design-dpd'),
            'successText' => __('Objednávka bola prepnutá na stav Na odoslanie.', 'ar-design-dpd'),
            'errorText' => __('Stav objednávky sa nepodarilo zmeniť.', 'ar-design-dpd'),
        ]);
    }

    public static function ajaxMarkLabelPrinted(): void
    {
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => __('Nemáte oprávnenie meniť stav objednávky.', 'ar-design-dpd')], 403);
        }

        check_ajax_referer('ard_dpd_mark_label_printed', 'nonce');

        $orderId = isset($_POST['orderId']) ? absint(wp_unslash($_POST['orderId'])) : 0;
        $order = $orderId > 0 ? wc_get_order($orderId) : null;

        if (!$order instanceof \WC_Order) {
            wp_send_json_error(['message' => __('Objednávku sa nepodarilo načítať.', 'ar-design-dpd')], 404);
        }

        OrderWorkflow::markLabelPrinted($order);
        $order->save();

        wp_send_json_success(['message' => __('Objednávka bola prepnutá na stav Na odoslanie.', 'ar-design-dpd')]);
    }

    public static function getLabelDownloadButtonHtml(\WC_Order $order, ?string $label = null): string
    {
        $dpdLabelUrl = (string) $order->get_meta(Order::EXPORT_LABEL_URL_META_KEY, true);
        if ($dpdLabelUrl === '') {
            return '';
        }

        $label = $label ?: __('Download label', 'ar-design-dpd');

        return sprintf(
            '<a class="button ard-dpd-download-label" href="%1$s" target="_blank" rel="noopener" data-order-id="%2$d">%3$s</a>',
            esc_url($dpdLabelUrl),
            (int) $order->get_id(),
            esc_html($label)
        );
    }
}
