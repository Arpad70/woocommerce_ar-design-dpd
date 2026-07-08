<?php

namespace ArDesign\DPD;

use WC_Email;
use WC_Order;

defined('ABSPATH') || exit;

class ShipmentDeliveredEmail extends WC_Email
{
    protected array $shipmentData = [];

    public function __construct()
    {
        $this->id = 'ard_dpd_shipment_delivered';
        $this->customer_email = true;
        $this->title = __('Shipment delivered follow-up', 'ar-design-dpd');
        $this->description = __('This email is sent automatically after the shipment is marked as delivered.', 'ar-design-dpd');
        $this->template_html = 'emails/customer-shipment-delivered.php';
        $this->template_plain = 'emails/plain/customer-shipment-delivered.php';
        $this->template_base = trailingslashit(AR_DESIGN_DPD_PLUGIN_PATH) . 'templates/';
        $this->placeholders = [
            '{order_number}' => '',
            '{customer_first_name}' => '',
            '{tracking_number}' => '',
        ];

        parent::__construct();
    }

    public function get_default_subject()
    {
        return __('Objednávka č. #{order_number} z AR Design bola vybavená.', 'ar-design-dpd');
    }

    public function get_default_heading()
    {
        return __('Vaša objednávka bola vybavená.', 'ar-design-dpd');
    }

    public function trigger(int $order_id, array $shipmentData = [], ?WC_Order $order = null): void
    {
        $this->object = $order instanceof WC_Order ? $order : wc_get_order($order_id);
        if (!$this->object instanceof WC_Order) {
            return;
        }

        if ($this->object->get_meta(Shipment::DELIVERY_EMAIL_SENT_AT_META_KEY, true)) {
            return;
        }

        $this->shipmentData = is_array($shipmentData) ? $shipmentData : Shipment::getShipmentData($this->object);
        $this->recipient = $this->object->get_billing_email();
        $this->placeholders['{order_number}'] = $this->object->get_order_number();
        $this->placeholders['{customer_first_name}'] = $this->object->get_billing_first_name();
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
                \ArDesign\DPD\Shipment::DELIVERY_EMAIL_SENT_AT_META_KEY,
                \ArDesign\DPD\Shipment::DELIVERY_EMAIL_SENT_AT_GMT_META_KEY
            );
            $this->object->save_meta_data();
            $this->object->add_order_note(__('Automatic delivery follow-up email was sent to the customer.', 'ar-design-dpd'));
        }
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
            'thank_you_message' => $this->get_option('thank_you_message', $this->get_default_thank_you_message()),
            'promo_message' => $this->get_option('promo_message', $this->get_default_promo_message()),
            'complaint_information' => $this->get_option('complaint_information', $this->get_default_complaint_information()),
            'claim_form_url' => $this->get_claim_form_url(),
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
            'thank_you_message' => wp_strip_all_tags($this->get_option('thank_you_message', $this->get_default_thank_you_message())),
            'promo_message' => wp_strip_all_tags($this->get_option('promo_message', $this->get_default_promo_message())),
            'complaint_information' => wp_strip_all_tags($this->get_option('complaint_information', $this->get_default_complaint_information())),
            'claim_form_url' => $this->get_claim_form_url(),
        ], '', $this->template_base);
    }

    public function init_form_fields()
    {
        $defaultClaimFormUrl = $this->get_default_claim_form_url();

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
                    '<code>{order_number}</code>, <code>{customer_first_name}</code>, <code>{tracking_number}</code>'
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
            'thank_you_message' => [
                'title' => __('Thank you message', 'ar-design-dpd'),
                'type' => 'textarea',
                'default' => $this->get_default_thank_you_message(),
                'desc_tip' => true,
            ],
            'promo_message' => [
                'title' => __('Promo message', 'ar-design-dpd'),
                'type' => 'textarea',
                'default' => $this->get_default_promo_message(),
                'desc_tip' => true,
            ],
            'complaint_information' => [
                'title' => __('Complaint information', 'ar-design-dpd'),
                'type' => 'textarea',
                'default' => $this->get_default_complaint_information(),
                'desc_tip' => true,
            ],
            'claim_form_url' => [
                'title' => __('Claim form URL', 'ar-design-dpd'),
                'type' => 'text',
                'default' => $defaultClaimFormUrl,
                'description' => $defaultClaimFormUrl
                    ? sprintf(
                        /* translators: %s: detected complaint page URL wrapped in code tags. */
                        __('Use a URL to your complaint/claim page. If you leave this value unchanged, the detected site page will be used: %s', 'ar-design-dpd'),
                        '<code>' . esc_html($defaultClaimFormUrl) . '</code>'
                    )
                    : __('Use a URL to your complaint/claim form page. Interactive forms are not reliably supported in email clients, so a landing page link is the safest option.', 'ar-design-dpd'),
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
        ];
    }

    protected function get_default_thank_you_message(): string
    {
        return __('Vaša objednávka bola úspešne vybavená. Ďakujeme, že ste si vybrali AR DESIGN. Veríme, že Vám objednané produkty prinesú radosť a budú Vám dlho slúžiť.', 'ar-design-dpd');
    }

    protected function get_default_promo_message(): string
    {
        return __('Ak ste boli s nákupom spokojní, veľmi nás poteší, ak sa s Vašou skúsenosťou podelíte. Budeme radi za Vaše hodnotenie na Google alebo na Heureke.', 'ar-design-dpd');
    }

    protected function get_default_complaint_information(): string
    {
        return __('Ak budete mať akékoľvek otázky, radi Vám pomôžeme. Ďakujeme za Vašu dôveru a tešíme sa na Vašu ďalšiu návštevu e-shopu AR-design.sk alebo našej predajne AR DESIGN v Poprade.', 'ar-design-dpd');
    }

    protected function get_claim_form_url(): string
    {
        $configuredUrl = trim((string) $this->get_option('claim_form_url', ''));

        if ($configuredUrl) {
            return esc_url_raw($configuredUrl);
        }

        return $this->get_default_claim_form_url();
    }

    protected function get_default_claim_form_url(): string
    {
        $page = get_page_by_path('reklamacny-poriadok');
        if ($page instanceof \WP_Post && $page->post_status === 'publish') {
            return (string) get_permalink($page);
        }

        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 1,
            's' => 'reklam',
            'orderby' => 'menu_order title',
            'order' => 'ASC',
        ]);

        if (!empty($pages[0]) && $pages[0] instanceof \WP_Post) {
            return (string) get_permalink($pages[0]);
        }

        return '';
    }

    public function get_attachments()
    {
        $attachments = parent::get_attachments();

        if (!$this->object instanceof WC_Order || !\ArDesign\DPD\Automation::shouldSendInvoiceAfterDelivery($this->object)) {
            return $attachments;
        }

        $invoiceFile = (string) $this->object->get_meta(Shipment::INVOICE_FILE_META_KEY, true);
        if (!$invoiceFile) {
            $invoiceFile = \ArDesign\DPD\Automation::ensureInvoiceFile($this->object) ?: '';
        }

        if ($invoiceFile && file_exists($invoiceFile)) {
            $attachments[] = $invoiceFile;
        }

        return array_unique(array_filter($attachments));
    }
}
