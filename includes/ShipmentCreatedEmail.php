<?php

namespace ArDesign\DPD;

use WC_Email;
use WC_Order;

defined('ABSPATH') || exit;

class ShipmentCreatedEmail extends WC_Email
{
    protected array $shipmentData = [];

    public function __construct()
    {
        $this->id = 'ard_shipping_shipment_created';
        $this->customer_email = true;
        $this->title = __('Shipment handed over', 'ar-design-dpd');
        $this->description = __('Sent to the customer when the shipment is created and a tracking number becomes available.', 'ar-design-dpd');
        $this->template_html = 'emails/customer-shipment-created.php';
        $this->template_plain = 'emails/plain/customer-shipment-created.php';
        $this->template_base = trailingslashit(AR_DESIGN_DPD_PLUGIN_PATH) . 'templates/';
        $this->placeholders = [
            '{order_number}' => '',
            '{carrier}' => '',
            '{tracking_number}' => '',
        ];

        add_action('ard_shipping_shipment_created', [$this, 'trigger'], 10, 3);

        parent::__construct();
    }

    public function get_default_subject()
    {
        return __('Your order #{order_number} is on its way', 'ar-design-dpd');
    }

    public function get_default_heading()
    {
        return __('We have handed your shipment to the carrier', 'ar-design-dpd');
    }

    public function trigger(int $order_id, array $shipmentData = [], ?WC_Order $order = null): void
    {
        $this->object = $order instanceof WC_Order ? $order : wc_get_order($order_id);

        if (!$this->object instanceof WC_Order) {
            return;
        }

        $this->shipmentData = is_array($shipmentData) ? $shipmentData : Shipment::getShipmentData($this->object);

        if (!empty($this->shipmentData['handover_email_sent_at']) || $this->object->get_meta(Shipment::HANDOVER_EMAIL_SENT_AT_META_KEY, true)) {
            return;
        }

        if (empty($this->shipmentData['tracking_number'])) {
            return;
        }

        $this->recipient = $this->object->get_billing_email();
        $this->placeholders['{order_number}'] = $this->object->get_order_number();
        $this->placeholders['{carrier}'] = strtoupper((string) ($this->shipmentData['carrier'] ?? Shipment::CARRIER));
        $this->placeholders['{tracking_number}'] = (string) ($this->shipmentData['tracking_number'] ?? '');

        if (!$this->is_enabled() || !$this->get_recipient()) {
            return;
        }

        $this->setup_locale();

        $sent = $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );

        $this->restore_locale();

        if ($sent) {
            \ArDesign\DPD\Shipment::storeTimestampMeta(
                $this->object,
                \ArDesign\DPD\Shipment::HANDOVER_EMAIL_SENT_AT_META_KEY,
                \ArDesign\DPD\Shipment::HANDOVER_EMAIL_SENT_AT_GMT_META_KEY
            );
            $this->object->save_meta_data();
            $this->object->add_order_note(__('Automatic shipment handover email with tracking information was sent to the customer.', 'ar-design-dpd'));
        }
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'ar-design-dpd'),
                'type' => 'checkbox',
                'label' => __('Enable this email notification', 'ar-design-dpd'),
                'default' => 'yes',
            ],
            'subject' => [
                'title' => __('Subject', 'ar-design-dpd'),
                'type' => 'text',
                'description' => sprintf(
                    /* translators: %s: HTML list of supported email placeholders. */
                    __('Available placeholders: %s', 'ar-design-dpd'),
                    '<code>{order_number}</code>, <code>{carrier}</code>, <code>{tracking_number}</code>'
                ),
                'default' => $this->get_default_subject(),
                'desc_tip' => true,
            ],
            'heading' => [
                'title' => __('Email heading', 'ar-design-dpd'),
                'type' => 'text',
                'default' => $this->get_default_heading(),
                'desc_tip' => true,
            ],
            'intro_text' => [
                'title' => __('Intro text', 'ar-design-dpd'),
                'type' => 'textarea',
                'default' => $this->get_default_intro_text(),
                'desc_tip' => true,
            ],
            'tracking_button_text' => [
                'title' => __('Tracking button text', 'ar-design-dpd'),
                'type' => 'text',
                'default' => __('Track shipment', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            'additional_content' => [
                'title' => __('Additional content', 'ar-design-dpd'),
                'description' => __('Text to appear below the main email content.', 'ar-design-dpd'),
                'css' => 'width:400px; height: 75px;',
                'placeholder' => __('Thanks for shopping with us.', 'ar-design-dpd'),
                'type' => 'textarea',
                'default' => $this->get_default_additional_content(),
                'desc_tip' => true,
            ],
            'email_type' => [
                'title' => __('Email type', 'ar-design-dpd'),
                'type' => 'select',
                'description' => __('Choose which format of email to send.', 'ar-design-dpd'),
                'default' => 'html',
                'class' => 'email_type wc-enhanced-select',
                'options' => $this->get_email_type_options(),
                'desc_tip' => true,
            ],
        ];
    }

    public function get_content_html()
    {
        return wc_get_template_html($this->template_html, [
            'order' => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text' => false,
            'email' => $this,
            'shipment_data' => $this->shipmentData,
            'intro_text' => $this->get_option('intro_text', $this->get_default_intro_text()),
            'tracking_button_text' => $this->get_option('tracking_button_text', __('Track shipment', 'ar-design-dpd')),
        ], '', $this->template_base);
    }

    public function get_content_plain()
    {
        return wc_get_template_html($this->template_plain, [
            'order' => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text' => true,
            'email' => $this,
            'shipment_data' => $this->shipmentData,
            'intro_text' => wp_strip_all_tags($this->get_option('intro_text', $this->get_default_intro_text())),
            'tracking_button_text' => $this->get_option('tracking_button_text', __('Track shipment', 'ar-design-dpd')),
        ], '', $this->template_base);
    }

    protected function get_default_intro_text(): string
    {
        return __('Vaša zásielka bola vytvorená v našom prepravnom systéme a sledovacie číslo je už k dispozícii. Pohyb zásielky môžete sledovať pomocou odkazu nižšie.', 'ar-design-dpd');
    }
}