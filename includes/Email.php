<?php

namespace WcDPD;

defined('ABSPATH') || exit;

/**
 * Email class
 */
class Email
{
    public static function init()
    {
        add_action('woocommerce_email_after_order_table', [__CLASS__, 'displayParcelShopShippingInfo'], 10, 1);
    }

    /**
     * Display parcelshop shipping info in the order emails
     *
     * @param object $order
     *
     * @return void
     */
    public static function displayParcelShopShippingInfo(object $order)
    {
        echo Order::getParcelShopOrderHtmlDetails($order);
    }
}
