<?php

namespace WcDPD;

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

        if (wc_dpd_is_hpos_enabled()) {
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
        $bulk_actions[self::BULK_EXPORT_ORDERS_KEY] = __('DPD Bulk Export', 'wc-dpd');
        $bulk_actions[self::BULK_DOWNLOAD_LABELS_KEY] = __('DPD Bulk Download Labels', 'wc-dpd');

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
     * @param [array] $columns
     *
     * @return array
     */
    public static function addOrdersGridDPDExportColumn($columns)
    {
        if (!is_array($columns)) {
            return $columns;
        }

        $new_columns = [];

        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;

            if ('order_status' === $column_name) {
                $new_columns[DpdExportSettings::SETTINGS_ID_KEY] = __('Export to DPD', 'wc-dpd');
            }
        }

        return $new_columns;
    }

    /**
     * Populate order DPD export status column value
     *
     * @param array $column
     * @param \WC_Order $order
     *
     * @return void
     */
    public static function addOrderByDPDExportColumn($column, $order_or_order_id = null)
    {
        if (DpdExportSettings::SETTINGS_ID_KEY !== $column) {
            return;
        }

        if (!$order_or_order_id instanceof \WC_Order) {
            $order = new \WC_Order($order_or_order_id);
        } else {
            $order = $order_or_order_id;
        }

        if (!$order instanceof \WC_Order) {
            return;
        }

        $dpd_export_result = $order->get_meta(Order::EXPORT_STATUS_META_KEY, true);

        if (!$dpd_export_result) {
            echo '<p><a class="button" href="' . esc_url(add_query_arg(self::EXPORT_ORDER_KEY, $order->get_id())) . '">' . __('Export', 'wc-dpd') . '</a></p>';

            return;
        }

        if ($dpd_export_result === Order::EXPORT_SUCCESS_STATUS) {
            $dpd_label_url = $order->get_meta(Order::EXPORT_LABEL_URL_META_KEY, true);
            $dpd_package_number = wp_kses_post($order->get_meta(Order::EXPORT_PACKAGE_NUMBER_META_KEY, true));

            if ($dpd_label_url) {
                echo '<p><a class="button" href="' . esc_url($dpd_label_url) . '">' . __('Download label', 'wc-dpd') . '</a></p>';
            }

            if ($dpd_package_number) {
                echo '<p style="font-size: 12px; margin-top: 5px;">' . __('Package number', 'wc-dpd') . ':<br><strong>' . $dpd_package_number . '</strong></p>';
            }
        }

        return;
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
            Notice::error(sprintf(__('Wrong order ID %d', 'wc-dpd'), $order_id));

            return;
        }

        Order::export($order_id);

        wp_safe_redirect(remove_query_arg(self::EXPORT_ORDER_KEY));
        exit;
    }
}
