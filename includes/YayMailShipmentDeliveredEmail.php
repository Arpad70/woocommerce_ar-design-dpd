<?php

namespace ArDesign\DPD;

use YayMail\Abstracts\BaseEmail;
use YayMail\Elements\ElementsLoader;
use YayMail\Utils\SingletonTrait;

defined('ABSPATH') || exit;

class YayMailShipmentDeliveredEmail extends BaseEmail
{
	use SingletonTrait;

	private const SAMPLE_TRACKING_NUMBER = '12345678901234';
	private const SAMPLE_CLAIM_FORM_URL = 'https://example.test/reklamacny-poriadok';

	protected function __construct()
	{
		$email = $this->findRootEmail();
		if (! $email instanceof ShipmentDeliveredEmail) {
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
					'rich_text' => __('Your order has been delivered', 'ar-design-dpd'),
				],
			],
			[
				'type' => 'Text',
				'attributes' => [
					'rich_text' => '<p>[ard_dpd_delivered_thank_you_message]</p><p><strong>'
						. esc_html__('Tracking number', 'ar-design-dpd')
						. ':</strong> [ard_dpd_delivered_tracking_number]</p><p>[ard_dpd_delivered_promo_message]</p><p>[ard_dpd_delivered_complaint_information]</p><p>[ard_dpd_delivered_claim_form_link]</p>',
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
		return yaymail_get_template('templates/yaymail/customer-shipment-delivered.php', '', AR_DESIGN_DPD_PLUGIN_PATH);
	}

	public function registerEmailShortcodes(mixed $email): void
	{
		if (! is_object($email) || ! method_exists($email, 'register_shortcodes')) {
			return;
		}

		$email->register_shortcodes([
			[
				'name' => 'ard_dpd_delivered_thank_you_message',
				'description' => __('Delivered thank you message', 'ar-design-dpd'),
				'group' => 'shippings',
				'callback' => [$this, 'shortcodeThankYouMessage'],
			],
			[
				'name' => 'ard_dpd_delivered_tracking_number',
				'description' => __('Delivered tracking number', 'ar-design-dpd'),
				'group' => 'shippings',
				'callback' => [$this, 'shortcodeTrackingNumber'],
			],
			[
				'name' => 'ard_dpd_delivered_promo_message',
				'description' => __('Delivered promo message', 'ar-design-dpd'),
				'group' => 'shippings',
				'callback' => [$this, 'shortcodePromoMessage'],
			],
			[
				'name' => 'ard_dpd_delivered_complaint_information',
				'description' => __('Delivered complaint information', 'ar-design-dpd'),
				'group' => 'shippings',
				'callback' => [$this, 'shortcodeComplaintInformation'],
			],
			[
				'name' => 'ard_dpd_delivered_claim_form_link',
				'description' => __('Delivered claim form link', 'ar-design-dpd'),
				'group' => 'shippings',
				'callback' => [$this, 'shortcodeClaimFormLink'],
			],
		]);
	}

	public function shortcodeThankYouMessage(mixed $data): string
	{
		if ($this->isSampleRender($data)) {
			return esc_html($this->getDefaultThankYouMessage());
		}

		$email = $this->getRenderEmail($data);
		if ($email instanceof ShipmentDeliveredEmail) {
			return wp_kses_post(wpautop($email->get_option('thank_you_message', $this->getDefaultThankYouMessage())));
		}

		return esc_html($this->getDefaultThankYouMessage());
	}

	public function shortcodeTrackingNumber(mixed $data): string
	{
		if ($this->isSampleRender($data)) {
			return self::SAMPLE_TRACKING_NUMBER;
		}

		$shipmentData = $this->getShipmentDataFromRender($data);

		return esc_html((string) ($shipmentData['tracking_number'] ?? ''));
	}

	public function shortcodePromoMessage(mixed $data): string
	{
		if ($this->isSampleRender($data)) {
			return esc_html($this->getDefaultPromoMessage());
		}

		$email = $this->getRenderEmail($data);
		if ($email instanceof ShipmentDeliveredEmail) {
			return wp_kses_post(wpautop($email->get_option('promo_message', $this->getDefaultPromoMessage())));
		}

		return esc_html($this->getDefaultPromoMessage());
	}

	public function shortcodeComplaintInformation(mixed $data): string
	{
		if ($this->isSampleRender($data)) {
			return esc_html($this->getDefaultComplaintInformation());
		}

		$email = $this->getRenderEmail($data);
		if ($email instanceof ShipmentDeliveredEmail) {
			return wp_kses_post(wpautop($email->get_option('complaint_information', $this->getDefaultComplaintInformation())));
		}

		return esc_html($this->getDefaultComplaintInformation());
	}

	public function shortcodeClaimFormLink(mixed $data): string
	{
		$url = $this->isSampleRender($data)
			? self::SAMPLE_CLAIM_FORM_URL
			: $this->getClaimFormUrl($data);

		if ('' === $url) {
			return '';
		}

		return '<a href="' . esc_url($url) . '">' . esc_html__('Complaint procedure and claim page', 'ar-design-dpd') . '</a>';
	}

	private function getDefaultThankYouMessage(): string
	{
		return __('Thank you for your purchase. We believe your order has safely reached you and we hope it brings you joy right away.', 'ar-design-dpd');
	}

	private function getDefaultPromoMessage(): string
	{
		return __('As a thank-you, keep an eye on our current promotions and news. We would love to see you back soon.', 'ar-design-dpd');
	}

	private function getDefaultComplaintInformation(): string
	{
		return __('If anything is not right, you can use our complaint procedure. Please prepare your order number, a short description of the issue and photos if they help explain the problem.', 'ar-design-dpd');
	}

	private function findRootEmail(): ?ShipmentDeliveredEmail
	{
		if (! class_exists('\\WC_Emails')) {
			return null;
		}

		foreach (\WC_Emails::instance()->get_emails() as $email) {
			if ($email instanceof ShipmentDeliveredEmail || ((string) ($email->id ?? '') === 'ard_dpd_shipment_delivered')) {
				return $email instanceof ShipmentDeliveredEmail ? $email : null;
			}
		}

		return null;
	}

	private function isSampleRender(mixed $data): bool
	{
		return ! empty($data['render_data']['is_sample']);
	}

	private function getRenderEmail(mixed $data): ?ShipmentDeliveredEmail
	{
		$email = $data['render_data']['email'] ?? null;

		return $email instanceof ShipmentDeliveredEmail ? $email : null;
	}

	private function getShipmentDataFromRender(mixed $data): array
	{
		$email = $this->getRenderEmail($data);
		if ($email instanceof ShipmentDeliveredEmail) {
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

	private function getClaimFormUrl(mixed $data): string
	{
		$email = $this->getRenderEmail($data);
		if ($email instanceof ShipmentDeliveredEmail) {
			$configuredUrl = trim((string) $email->get_option('claim_form_url', ''));
			if ('' !== $configuredUrl) {
				return $configuredUrl;
			}
		}

		return $this->detectClaimFormUrl();
	}

	private function detectClaimFormUrl(): string
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

		if (! empty($pages[0]) && $pages[0] instanceof \WP_Post) {
			return (string) get_permalink($pages[0]);
		}

		return '';
	}
}