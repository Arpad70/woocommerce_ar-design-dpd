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
        add_action('yaymail_register_emails', [__CLASS__, 'registerYayMailEmails']);
    }

    public static function registerEmailClasses($emails)
    {
        $emails[\ArDesign\DPD\ShipmentCreatedEmail::class] = new \ArDesign\DPD\ShipmentCreatedEmail();
        $emails[\ArDesign\DPD\ShipmentDeliveredEmail::class] = new \ArDesign\DPD\ShipmentDeliveredEmail();

        return $emails;
    }

    public static function registerYayMailEmails($yaymailEmails): void
    {
        if (!class_exists('\YayMail\YayMailEmails')) {
            return;
        }

        if (!$yaymailEmails instanceof \YayMail\YayMailEmails) {
            return;
        }

        $yaymailEmailClass = __NAMESPACE__ . '\\YayMailShipmentCreatedEmail';
        if (!class_exists($yaymailEmailClass)) {
            return;
        }

        $yaymailEmails->register($yaymailEmailClass::get_instance());
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
