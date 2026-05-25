<?php

namespace ArDesign\DPD;

use YayMail\Abstracts\BaseEmail;
use YayMail\Elements\ElementsLoader;
use YayMail\Utils\SingletonTrait;

defined('ABSPATH') || exit;

class YayMailShipmentCreatedEmail extends BaseEmail
{
    use SingletonTrait;

    private const SAMPLE_TRACKING_URL = 'https://tracking.dpd.com/tracking.do?parcelNumber=12345678901234';
    private const SAMPLE_TRACKING_NUMBER = '12345678901234';

    protected function __construct()
    {
        $email = $this->findRootEmail();
        if (!$email instanceof ShipmentCreatedEmail) {
            return;
        }

        $this->id = $email->id;
        $this->title = $email->get_title();
        $this->root_email = $email;
        $this->recipient = function_exists('yaymail_get_email_recipient_zone') ? yaymail_get_email_recipient_zone($email) : '';
        $this->source = [
            'plugin_id' => 'ar-design-dpd',
            'plugin_name' => 'AR Design DPD',
        ];

        $this->render_priority = apply_filters('yaymail_email_render_priority', $this->render_priority, $this->id);

        add_filter('wc_get_template', [$this, 'get_template_file'], $this->render_priority ?? 10, 3);
        add_action('yaymail_' . $this->id . '_register_shortcodes', [$this, 'registerEmailShortcodes']);

        $this->maybe_disable_block_email_editor();
    }

    public function get_default_elements()
    {
        return ElementsLoader::load_elements([
            [
                'type' => 'Logo',
            ],
            [
                'type' => 'Heading',
                'attributes' => [
                    'rich_text' => __('We have handed your shipment to the carrier', 'ar-design-dpd'),
                ],
            ],
            [
                'type' => 'Text',
                'attributes' => [
                    'rich_text' => '<p>'
                        . sprintf(
                            /* translators: %s: customer first name shortcode. */
                            esc_html__('Hi %s,', 'ar-design-dpd'),
                            '[yaymail_customer_first_name]'
                        )
                        . '</p><p>[ard_dpd_shipment_intro_text]</p><p><strong>'
                        . esc_html__('Carrier', 'ar-design-dpd')
                        . ':</strong> [ard_dpd_tracking_carrier]<br><strong>'
                        . esc_html__('Tracking number', 'ar-design-dpd')
                        . ':</strong> [ard_dpd_tracking_number]</p><p>[ard_dpd_tracking_link]</p><p><small>[ard_dpd_tracking_url]</small></p>',
                    'padding' => [
                        'top' => '15',
                        'right' => '50',
                        'bottom' => '15',
                        'left' => '50',
                    ],
                ],
            ],
            [
                'type' => 'OrderDetails',
            ],
            [
                'type' => 'BillingShippingAddress',
            ],
            [
                'type' => 'Text',
                'attributes' => [
                    'rich_text' => '<p>[yaymail_additional_content]</p>',
                    'padding' => [
                        'top' => '0',
                        'right' => '50',
                        'bottom' => '38',
                        'left' => '50',
                    ],
                ],
            ],
            [
                'type' => 'Footer',
            ],
        ]);
    }

    public function get_template_path()
    {
        return yaymail_get_template('templates/yaymail/customer-shipment-created.php', '', AR_DESIGN_DPD_PLUGIN_PATH);
    }

    public function registerEmailShortcodes($email): void
    {
        if (!is_object($email) || !method_exists($email, 'register_shortcodes')) {
            return;
        }

        $email->register_shortcodes([
            [
                'name' => 'ard_dpd_shipment_intro_text',
                'description' => __('Shipment intro text', 'ar-design-dpd'),
                'group' => 'shippings',
                'callback' => [$this, 'shortcodeIntroText'],
            ],
            [
                'name' => 'ard_dpd_tracking_carrier',
                'description' => __('Shipment carrier', 'ar-design-dpd'),
                'group' => 'shippings',
                'callback' => [$this, 'shortcodeCarrier'],
            ],
            [
                'name' => 'ard_dpd_tracking_number',
                'description' => __('Tracking number', 'ar-design-dpd'),
                'group' => 'shippings',
                'callback' => [$this, 'shortcodeTrackingNumber'],
            ],
            [
                'name' => 'ard_dpd_tracking_url',
                'description' => __('Tracking URL', 'ar-design-dpd'),
                'group' => 'shippings',
                'callback' => [$this, 'shortcodeTrackingUrl'],
            ],
            [
                'name' => 'ard_dpd_tracking_link',
                'description' => __('Tracking link', 'ar-design-dpd'),
                'attributes' => [
                    'text_link' => __('Track shipment', 'ar-design-dpd'),
                ],
                'group' => 'shippings',
                'callback' => [$this, 'shortcodeTrackingLink'],
            ],
        ]);
    }

    public function shortcodeIntroText($data): string
    {
        if ($this->isSampleRender($data)) {
            return esc_html($this->getDefaultIntroText());
        }

        $email = $this->getRenderEmail($data);
        if ($email instanceof ShipmentCreatedEmail) {
            return wp_kses_post(wpautop($email->get_option('intro_text', $this->getDefaultIntroText())));
        }

        return esc_html($this->getDefaultIntroText());
    }

    public function shortcodeCarrier($data): string
    {
        if ($this->isSampleRender($data)) {
            return 'DPD';
        }

        $shipmentData = $this->getShipmentDataFromRender($data);
        $carrier = (string) ($shipmentData['carrier'] ?? Shipment::CARRIER);

        return esc_html(strtoupper($carrier));
    }

    public function shortcodeTrackingNumber($data): string
    {
        if ($this->isSampleRender($data)) {
            return self::SAMPLE_TRACKING_NUMBER;
        }

        $shipmentData = $this->getShipmentDataFromRender($data);
        return esc_html((string) ($shipmentData['tracking_number'] ?? ''));
    }

    public function shortcodeTrackingUrl($data): string
    {
        if ($this->isSampleRender($data)) {
            return self::SAMPLE_TRACKING_URL;
        }

        $shipmentData = $this->getShipmentDataFromRender($data);
        return esc_html((string) ($shipmentData['tracking_url'] ?? ''));
    }

    public function shortcodeTrackingLink($data, $shortcodeAtts = []): string
    {
        $url = $this->isSampleRender($data)
            ? self::SAMPLE_TRACKING_URL
            : (string) ($this->getShipmentDataFromRender($data)['tracking_url'] ?? '');

        if ('' === $url) {
            return '';
        }

        $email = $this->getRenderEmail($data);
        $text = isset($shortcodeAtts['text_link']) && '' !== (string) $shortcodeAtts['text_link']
            ? (string) $shortcodeAtts['text_link']
            : __('Track shipment', 'ar-design-dpd');

        if ($email instanceof ShipmentCreatedEmail && (!isset($shortcodeAtts['text_link']) || '' === (string) $shortcodeAtts['text_link'])) {
            $text = (string) $email->get_option('tracking_button_text', __('Track shipment', 'ar-design-dpd'));
        }

        return '<a href="' . esc_url($url) . '" style="display:inline-block;padding:12px 20px;background:#dc0032;color:#ffffff;text-decoration:none;border-radius:4px;">' . esc_html($text) . '</a>';
    }

    private function getDefaultIntroText(): string
    {
        return __('Your package has been created in our shipping system and the tracking number is now available. You can follow the shipment progress using the link below.', 'ar-design-dpd');
    }

    private function findRootEmail(): ?ShipmentCreatedEmail
    {
        if (!class_exists('\WC_Emails')) {
            return null;
        }

        foreach (\WC_Emails::instance()->get_emails() as $email) {
            if ($email instanceof ShipmentCreatedEmail || ((string) ($email->id ?? '') === 'ard_shipping_shipment_created')) {
                return $email instanceof ShipmentCreatedEmail ? $email : null;
            }
        }

        return null;
    }

    private function isSampleRender($data): bool
    {
        return !empty($data['render_data']['is_sample']);
    }

    private function getRenderEmail($data): ?ShipmentCreatedEmail
    {
        $email = $data['render_data']['email'] ?? null;

        return $email instanceof ShipmentCreatedEmail ? $email : null;
    }

    private function getShipmentDataFromRender($data): array
    {
        $email = $this->getRenderEmail($data);
        if ($email instanceof ShipmentCreatedEmail) {
            $order = $email->object ?? null;
            if ($order instanceof \WC_Order) {
                return Shipment::getShipmentData($order);
            }
        }

        $order = $data['render_data']['order'] ?? null;
        if ($order instanceof \WC_Order) {
            return Shipment::getShipmentData($order);
        }

        return [];
    }
}