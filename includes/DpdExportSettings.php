<?php

namespace WcDPD;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined('ABSPATH') || exit;

class DpdExportSettings extends \WC_Shipping_Method
{
    public const SETTINGS_ID_KEY = 'dpd_export';
    public const SETTINGS_OPTION_KEY = 'woocommerce_dpd_export_settings';
    public const DELIS_ID_OPTION_KEY = 'dpd_delis_id';
    public const EMAIL_OPTION_KEY = 'dpd_api_email';
    public const API_KEY_OPTION_KEY = 'dpd_api_key';
    public const BANK_ID_OPTION_KEY = 'dpd_bank_id';
    public const ADDRESS_ID_OPTION_KEY = 'dpd_address_id';
    public const SHIPPING_OPTION_KEY = 'dpd_shipping';
    public const NOTIFICATION_OPTION_KEY = 'dpd_notification';
    public const LABELS_FORMAT_OPTION_KEY = 'dpd_labels_format';
    public const MAP_WIDGET_ENABLED_OPTION_KEY = 'dpd_map_widget_enabled';
    public const MAP_API_KEY_OPTION_KEY = 'dpd_map_api_key';
    public const LANGUAGE_OPTION_KEY = 'dpd_language';

    public $dpd_delis_id = null;
    public $dpd_api_key = null;
    public $dpd_api_email = null;
    public $dpd_bank_id = null;
    public $dpd_address_id = null;
    public $dpd_shipping = null;
    public $dpd_notification = null;
    public $dpd_labels_format = null;
    public $dpd_map_widget_enabled = null;
    public $dpd_map_api_key = null;
    public $dpd_language = null;

    public function __construct()
    {
        $this->id = self::SETTINGS_ID_KEY;
        $this->method_title = __('DPD Export Settings', 'wc-dpd');
        $this->method_description = __('Default settings for DPD export', 'wc-dpd');
        $this->title =  __('DPD Export Settings', 'wc-dpd');
        $this->enabled = "yes";
        $this->dpd_delis_id = $this->get_option(self::DELIS_ID_OPTION_KEY);
        $this->dpd_api_key = $this->get_option(self::API_KEY_OPTION_KEY);
        $this->dpd_api_email = $this->get_option(self::EMAIL_OPTION_KEY);
        $this->dpd_bank_id = $this->get_option(self::BANK_ID_OPTION_KEY);
        $this->dpd_address_id = $this->get_option(self::ADDRESS_ID_OPTION_KEY);
        $this->dpd_shipping = $this->get_option(self::SHIPPING_OPTION_KEY);
        $this->dpd_notification = $this->get_option(self::NOTIFICATION_OPTION_KEY);
        $this->dpd_labels_format = $this->get_option(self::LABELS_FORMAT_OPTION_KEY);
        $this->dpd_map_widget_enabled = $this->get_option(self::MAP_WIDGET_ENABLED_OPTION_KEY);
        $this->dpd_map_api_key = $this->get_option(self::MAP_API_KEY_OPTION_KEY);
        $this->dpd_language = $this->get_option(self::LANGUAGE_OPTION_KEY);

        $this->adjustPostData();

        $this->init_form_fields();
        $this->init_settings();

        add_action('admin_footer', [__CLASS__, 'addScripts'], 10, 1);
        add_filter('woocommerce_generate_repeater_html', [$this, 'addRepeaterFieldHtml'], 10, 4);

        add_action('woocommerce_update_options_shipping_' . self::SETTINGS_ID_KEY, [$this, 'process_admin_options']);
    }

    /**
     * Get language options
     *
     * @return array
     */
    public static function getLanguageOptions()
    {
        return [
            'sk' => __('Slovak', 'wc-dpd'),
            'en' => __('English', 'wc-dpd'),
            'hu' => __('Hungarian', 'wc-dpd'),
            'de' => __('German', 'wc-dpd'),
            'fr' => __('French', 'wc-dpd'),
        ];
    }

    /**
     * Get shipping options
     *
     * @return array
     */
    public static function getShippingOptions()
    {
        return  [
            '0' => __('Choose', 'wc-dpd'),
            '1' => __('DPD Classic', 'wc-dpd'),
            '9' => __('DPD Home', 'wc-dpd'),
            '3' => __('DPD 10:00', 'wc-dpd'),
            '4' => __('DPD 12:00', 'wc-dpd'),
            '2' => __('DPD 18:00 / DPD Guarantee', 'wc-dpd'),
        ];
    }

    /**
     * Get required notification shipping keys
     *
     * @return array
     */
    public static function getRequiredNotificationsShippingKeys()
    {
        return ['9', '3', '4', '2'];
    }

    /**
     * Check if notification is required for the shipment type
     *
     * @param mixed $shipment_key
     *
     * @return bool
     */
    public static function isNotificationRequired($shipment_key)
    {
        if (in_array($shipment_key, self::getRequiredNotificationsShippingKeys())) {
            return true;
        }

        return false;
    }

    /**
     * Get repeatable fields ids
     *
     * @return array
     */
    public static function getRepeaterFieldsIds()
    {
        return [
            self::BANK_ID_OPTION_KEY,
            self::ADDRESS_ID_OPTION_KEY
        ];
    }

    /**
     * Adjust form post data before save
     *
     * @return void
     */
    public function adjustPostData()
    {
        if (empty($_POST)) {
            return;
        }

        $repeater_fields_keys = self::getRepeaterFieldsIds();
        foreach ($repeater_fields_keys as $field_key) {
            $repeater_values = [];
            $default_value = '';


            if (!empty($_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key . '_default'])) {
                $default_value = sanitize_text_field($_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key . '_default'][0]);
            }

            if (!empty($_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key . '_value'])) {
                foreach ($_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key . '_value'] as $key => $value) {
                    $repeater_values[$key]['value'] = sanitize_text_field($value);
                    $repeater_values[$key]['default'] = ($value === $default_value);
                }

                unset($_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key . '_value']);
            }

            if (!empty($_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key . '_nice_value'])) {
                foreach ($_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key . '_nice_value'] as $key => $value) {
                    $repeater_values[$key]['nice_value'] = sanitize_text_field($value);
                }

                unset($_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key . '_nice_value']);
            }

            unset($_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key . '_default']);

            if (!empty($repeater_values)) {
                $_POST['woocommerce_' . self::SETTINGS_ID_KEY . '_' . $field_key] = serialize($repeater_values);
            }
        }
    }

    /**
     * Initialize method options fields
     *
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            self::DELIS_ID_OPTION_KEY => [
                'title' => __('ID delis', 'wc-dpd'),
                'label' => __('This is the username you got from DPD to login to their service', 'wc-dpd'),
                'description' => __('Unique customer identifier assigned by DPD.', 'wc-dpd'),
                'desc_tip' => true,
                'type' => 'text'
            ],
            self::EMAIL_OPTION_KEY => [
                'title' => __('Client email', 'wc-dpd'),
                'type' => 'text'
            ],
            self::API_KEY_OPTION_KEY => [
                'title' => __('API key', 'wc-dpd'),
                'label' => __('Your api key', 'wc-dpd'),
                'description' => __('Unique authentication key required to access API.', 'wc-dpd'),
                'desc_tip' => true,
                'type' => 'text'
            ],
            self::BANK_ID_OPTION_KEY => [
                'title' => __('Bank account ID', 'wc-dpd'),
                'type' => 'repeater',
                'max_rows' => 10,
                'label_text' => __('Bank account', 'wc-dpd'),
                'add_btn_text' => __('Add bank account', 'wc-dpd'),
                'repeater_description' => __('Select your default bank account.', 'wc-dpd'),
            ],
            self::ADDRESS_ID_OPTION_KEY => [
                'title' => __('ID of the collection address', 'wc-dpd'),
                'type' => 'repeater',
                'max_rows' => 10,
                'label_text' => __('Address', 'wc-dpd'),
                'add_btn_text' => __('Add address', 'wc-dpd'),
                'repeater_description' => __('Select your default address.', 'wc-dpd'),
            ],
            self::SHIPPING_OPTION_KEY => [
                'title' => __('Transport', 'wc-dpd'),
                'type' => 'select',
                'options' => self::getShippingOptions(),
                'class' => 'js-wc-dpd-shipping-type-field'
            ],
            self::NOTIFICATION_OPTION_KEY => [
                'title' => __('Notifications', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => 'no',
                'class' => 'js-wc-dpd-notification-field'
            ],
            self::LABELS_FORMAT_OPTION_KEY => [
                'title' => __('Labels format', 'wc-dpd'),
                'type' => 'select',
                'default' => 'a4',
                'options' => [
                    'A4' => 'A4',
                    'A6' => 'A6',
                ]
            ],
            self::MAP_WIDGET_ENABLED_OPTION_KEY => [
                'title' => __('Enable Map Widget', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Enable this option to display the map widget. If enabled, a valid Map API Key is required for the widget to function properly.', 'wc-dpd'),
                'desc_tip' => true,
            ],
            self::MAP_API_KEY_OPTION_KEY => [
                'title' => __('Map API Key', 'wc-dpd'),
                'type' => 'text',
                'description' => __('Enter a valid DPD Map API Key. This is required if the Map Widget is enabled.', 'wc-dpd'),
                'desc_tip' => true,
            ],
            self::LANGUAGE_OPTION_KEY => [
                'title' => __('Language', 'wc-dpd'),
                'type' => 'select',
                'default' => 'sk',
                'options' => self::getLanguageOptions(),
                'description' => __('Select the language for DPD map widget', 'wc-dpd'),
                'desc_tip' => true,
            ]
        ];
    }

    /**
     * Add repeater field html
     *
     * @param string $html
     * @param string $key
     * @param array $data
     * @param object $wc_settings
     *
     * @return string
     */
    public function addRepeaterFieldHtml($html = '', $key = '', $data = [], $wc_settings = null)
    {
        if (!in_array($key, self::getRepeaterFieldsIds())) {
            return $html;
        }

        $field_key = $this->get_field_key($key);

        $defaults  = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'label_text' => '',
            'desc_tip' => false,
            'max_rows' => 10,
            'type' => 'repeater',
            'add_btn_text' => '',
            'description' => '',
            'repeater_description' => '',
        );

        $data = wp_parse_args($data, $defaults);

        $values = self::getRepeaterOptions($key);
        $values = htmlspecialchars(json_encode($values), ENT_QUOTES, 'UTF-8');

        $props = [
            'inputName' => $field_key,
            'labelText' => $data['label_text'],
            'removeLabel' => __('Remove', 'wc-dpd'),
        ];

        switch ($key) {
            case self::BANK_ID_OPTION_KEY:
                $props['titlePlaceholder'] = __('Bank account name', 'wc-dpd');
                $props['valuePlaceholder'] = __('Bank account ID', 'wc-dpd');
                break;
            case self::ADDRESS_ID_OPTION_KEY:
                $props['titlePlaceholder'] = __('Address name', 'wc-dpd');
                $props['valuePlaceholder'] = __('Address ID', 'wc-dpd');
                break;
        }

        $props = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.?></label>
				</th>

				<td class="forminp">
					 <fieldset class="repeatable-field repeatable-field--<?php echo $key; ?> <?php echo esc_attr($data['class']); ?>" data-component="field-repeater" data-props="<?php echo $props; ?>" data-inputs-data="<?php echo $values; ?>" tabindex="0">
						<legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>

						<ol class="repeatable-field__rows" data-ref="rowList"></ol>

						<?php if (!empty($data['repeater_description'])) : ?>
							<p><small><?php echo wp_kses_post($data['repeater_description']); ?></small></p>
						<?php endif; ?>

						<div class="repeatable-field__bottom">
							<button class="repeatable-field__add-button button" data-ref="addButton" type="button">+ <?php echo esc_attr($data['add_btn_text']); ?></button>
						</div>

						<?php echo $this->get_description_html($data); // WPCS: XSS ok.?>
					</fieldset>
				</td>
			</tr>
		<?php

        return ob_get_clean();
    }

    /**
     * Get repeater option list of options
     *
     * @param string $option_key
     *
     * @return array
     */
    public static function getRepeaterOptions($option_key = '')
    {
        $values = maybe_unserialize((new DpdExportSettings())->get_option($option_key));

        // Backwards value compatibility with previous plugin version
        if (is_string($values)) {
            $values = [['default' => true, 'nice_value' => (string) $values, 'value' => (string) $values]];
        }

        return $values;
    }

    /**
     * Get repeater field value
     *
     * @param string $option_key
     *
     * @return string
     */
    public static function getRepeaterValue($option_key = '')
    {
        $options = self::getRepeaterOptions($option_key);

        // Try to get default value
        foreach ($options as $key => $value) {
            if (!empty($value['default']) && $value['default']) {
                return $value['value'];
            }
        }

        // Return first option value
        return !empty($options[0]['value']) ? $options[0]['value'] : '';
    }

    /**
     * Get default settings
     *
     * @return array
     */
    public static function getDefaultSettings()
    {
        $settings = get_option(self::SETTINGS_OPTION_KEY);
        $repeater_fields_keys = self::getRepeaterFieldsIds();

        foreach ($settings as $key => $value) {
            if (in_array($key, $repeater_fields_keys)) {
                $settings[$key] = self::getRepeaterValue($key);
            }
        }

        $settings = is_array($settings) ? $settings : [];

        return [
            self::DELIS_ID_OPTION_KEY => isset($settings[self::DELIS_ID_OPTION_KEY]) && !empty($settings[self::DELIS_ID_OPTION_KEY]) ? sanitize_text_field($settings[self::DELIS_ID_OPTION_KEY]) : '',
            self::EMAIL_OPTION_KEY => isset($settings[self::EMAIL_OPTION_KEY]) && !empty($settings[self::EMAIL_OPTION_KEY]) ? sanitize_text_field($settings[self::EMAIL_OPTION_KEY]) : '',
            self::API_KEY_OPTION_KEY => isset($settings[self::API_KEY_OPTION_KEY]) && !empty($settings[self::API_KEY_OPTION_KEY]) ? sanitize_text_field($settings[self::API_KEY_OPTION_KEY]) : '',
            self::BANK_ID_OPTION_KEY => isset($settings[self::BANK_ID_OPTION_KEY]) && !empty($settings[self::BANK_ID_OPTION_KEY]) ? sanitize_text_field($settings[self::BANK_ID_OPTION_KEY]) : '',
            self::ADDRESS_ID_OPTION_KEY => isset($settings[self::ADDRESS_ID_OPTION_KEY]) && !empty($settings[self::ADDRESS_ID_OPTION_KEY]) ? sanitize_text_field($settings[self::ADDRESS_ID_OPTION_KEY]) : '',
            self::SHIPPING_OPTION_KEY => isset($settings[self::SHIPPING_OPTION_KEY]) && !empty($settings[self::SHIPPING_OPTION_KEY]) ? sanitize_text_field($settings[self::SHIPPING_OPTION_KEY]) : '',
            self::NOTIFICATION_OPTION_KEY => isset($settings[self::NOTIFICATION_OPTION_KEY]) && !empty($settings[self::NOTIFICATION_OPTION_KEY]) ? sanitize_text_field($settings[self::NOTIFICATION_OPTION_KEY]) : 'no',
            self::LABELS_FORMAT_OPTION_KEY => isset($settings[self::LABELS_FORMAT_OPTION_KEY]) && !empty($settings[self::LABELS_FORMAT_OPTION_KEY]) ? sanitize_text_field($settings[self::LABELS_FORMAT_OPTION_KEY]) : 'A4',
            self::LANGUAGE_OPTION_KEY => isset($settings[self::LANGUAGE_OPTION_KEY]) && !empty($settings[self::LANGUAGE_OPTION_KEY]) ? sanitize_text_field($settings[self::LANGUAGE_OPTION_KEY]) : 'sk',
            self::MAP_WIDGET_ENABLED_OPTION_KEY => isset($settings[self::MAP_WIDGET_ENABLED_OPTION_KEY]) && !empty($settings[self::MAP_WIDGET_ENABLED_OPTION_KEY]) ? sanitize_text_field($settings[self::MAP_WIDGET_ENABLED_OPTION_KEY]) : 'no',
            self::MAP_API_KEY_OPTION_KEY => isset($settings[self::MAP_API_KEY_OPTION_KEY]) && !empty($settings[self::MAP_API_KEY_OPTION_KEY]) ? sanitize_text_field($settings[self::MAP_API_KEY_OPTION_KEY]) : ''
        ];
    }

    /**
     * Get map widget enabled status directly from WordPress options
     *
     * @return bool
     */
    public static function isMapWidgetEnabled()
    {
        $settings = get_option(self::SETTINGS_OPTION_KEY, []);
        return isset($settings[self::MAP_WIDGET_ENABLED_OPTION_KEY])
            && $settings[self::MAP_WIDGET_ENABLED_OPTION_KEY] === 'yes';
    }

    /**
     * Add admin scripts
     *
     * @return void
     */
    public static function addScripts()
    {
        // Only on the settings page
        if (!self::isCurrentPageSettingsPage() && !self::isCurrentPageOrderDetail()) {
            return;
        }

        wp_enqueue_script(self::SETTINGS_ID_KEY . '_scripts', WCDPD_PLUGIN_ASSETS_URL . 'scripts/dpd-export-settings-admin.js', [], wc_dpd_get_plugin_version(), true);
        wp_localize_script(self::SETTINGS_ID_KEY . '_scripts', 'wc_dpd_settings', ['required_notifications_shipping_keys' => self::getRequiredNotificationsShippingKeys()]);

        // Repeater field assets
        wp_enqueue_script(self::SETTINGS_ID_KEY . '_repeater_field', WCDPD_PLUGIN_ASSETS_URL . 'scripts/dpd-export-settings-admin-repeater.js', [], wc_dpd_get_plugin_version(), true);
        wp_enqueue_style(self::SETTINGS_ID_KEY . '_repeater_field', WCDPD_PLUGIN_ASSETS_URL . 'styles/dpd-export-repeater-settings-field.css', [], wc_dpd_get_plugin_version(), 'all');
    }

    /**
     * Check if the current page is plugin settings page
     *
     * @return boolean
     */
    public static function isCurrentPageSettingsPage()
    {
        if (
            is_admin() &&
            !empty($_GET['page']) && $_GET['page'] == 'wc-settings' &&
            !empty($_GET['tab']) && $_GET['tab'] == 'shipping' &&
            !empty($_GET['section']) && $_GET['section'] == self::SETTINGS_ID_KEY
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if the current page is order detail
     *
     * @return boolean
     */
    public static function isCurrentPageOrderDetail()
    {
        if (
            is_admin() &&
            !empty($_GET['post']) && (int) $_GET['post'] &&
            (class_exists(OrderUtil::class) && OrderUtil::get_order_type($_GET['post']))
        ) {
            return true;
        }

        return false;
    }
}
