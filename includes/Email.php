<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

/**
 * Email class
 */
class Email
{
    public static function init()
    {
        add_action('woocommerce_email_after_order_table', [__CLASS__, 'displayParcelShopShippingInfo'], 10, 1);
        add_filter('woocommerce_email_classes', [__CLASS__, 'registerEmailClasses']);
    }

    public static function registerEmailClasses($emails)
    {
        $emails[\ArDesign\DPD\ShipmentCreatedEmail::class] = new \ArDesign\DPD\ShipmentCreatedEmail();
        $emails[\ArDesign\DPD\ShipmentDeliveredEmail::class] = new \ArDesign\DPD\ShipmentDeliveredEmail();

        return $emails;
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
