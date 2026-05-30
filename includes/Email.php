<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

/**
 * Email class
 */
class Email
{
    private const SHIPMENT_CREATED_EMAIL_ID = 'ard_shipping_shipment_created';
    private const SHIPMENT_DELIVERED_EMAIL_ID = 'ard_dpd_shipment_delivered';

    public static function init()
    {
        add_action('woocommerce_email_after_order_table', [__CLASS__, 'displayParcelShopShippingInfo'], 10, 1);
        add_filter('woocommerce_email_classes', [__CLASS__, 'registerEmailClasses']);
        add_action('yaymail_register_emails', [__CLASS__, 'registerYayMailEmails']);
        add_action('admin_init', [__CLASS__, 'maybeActivateShipmentCreatedYayMailTemplate']);
    }

    public static function registerEmailClasses(array $emails): array
    {
        $emails[\ArDesign\DPD\ShipmentCreatedEmail::class] = new \ArDesign\DPD\ShipmentCreatedEmail();
        $emails[\ArDesign\DPD\ShipmentDeliveredEmail::class] = new \ArDesign\DPD\ShipmentDeliveredEmail();

        return $emails;
    }

    public static function registerYayMailEmails(mixed $yaymailEmails): void
    {
        if (!class_exists('\YayMail\YayMailEmails')) {
            return;
        }

        if (!$yaymailEmails instanceof \YayMail\YayMailEmails) {
            return;
        }

        foreach ([
            __NAMESPACE__ . '\\YayMailShipmentCreatedEmail',
            __NAMESPACE__ . '\\YayMailShipmentDeliveredEmail',
        ] as $yaymailEmailClass) {
            if (!class_exists($yaymailEmailClass)) {
                continue;
            }

            $yaymailEmails->register($yaymailEmailClass::get_instance());
        }
    }

    public static function maybeActivateShipmentCreatedYayMailTemplate(): void
    {
        if (!class_exists('\YayMail\YayMailTemplate')) {
            return;
        }

        self::maybeActivateYayMailTemplate(self::SHIPMENT_CREATED_EMAIL_ID, __('Shipment handed over', 'ar-design-dpd'));
        self::maybeActivateYayMailTemplate(self::SHIPMENT_DELIVERED_EMAIL_ID, __('Shipment delivered follow-up', 'ar-design-dpd'));
    }

    private static function maybeActivateYayMailTemplate(string $templateEmailId, string $defaultTitle): void
    {
        $template = new \YayMail\YayMailTemplate($templateEmailId);

        if (!$template->is_exists()) {
            return;
        }

        $templateId = (int) $template->get_id();
        if ($templateId <= 0) {
            return;
        }

        if ('active' !== (string) $template->get_status()) {
            update_post_meta($templateId, '_yaymail_status', 'active');
        }

        $templatePost = get_post($templateId);
        if (!$templatePost instanceof \WP_Post) {
            return;
        }

        $postUpdate = [
            'ID' => $templateId,
        ];

        if ('publish' !== $templatePost->post_status) {
            $postUpdate['post_status'] = 'publish';
        }

        if ('' === trim((string) $templatePost->post_title)) {
            $postUpdate['post_title'] = $defaultTitle;
        }

        if (count($postUpdate) > 1) {
            wp_update_post($postUpdate);
        }
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
