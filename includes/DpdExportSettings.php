<?php

namespace ArDesign\DPD;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined('ABSPATH') || exit;

class DpdExportSettings extends \WC_Shipping_Method
{
    public const SETTINGS_ID_KEY = 'dpd_export';
    public const SETTINGS_OPTION_KEY = 'woocommerce_dpd_export_settings';
    public const SETTINGS_SECTION_TITLE = 'DPD Export Settings';
    public const PRINT_FORMAT_OPTION_KEY = 'dpd_print_format';
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
    public const TRACKING_ENABLED_OPTION_KEY = 'dpd_tracking_enabled';
    public const STATUSDATA_DIRECTORY_OPTION_KEY = 'dpd_statusdata_directory';
    public const STATUSDATA_SFTP_HOST_OPTION_KEY = 'dpd_statusdata_sftp_host';
    public const STATUSDATA_SFTP_PORT_OPTION_KEY = 'dpd_statusdata_sftp_port';
    public const STATUSDATA_SFTP_USERNAME_OPTION_KEY = 'dpd_statusdata_sftp_username';
    public const STATUSDATA_SFTP_PASSWORD_OPTION_KEY = 'dpd_statusdata_sftp_password';
    public const STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY = 'dpd_statusdata_sftp_remote_directory';
    public const STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY = 'dpd_statusdata_sftp_archive_directory';
    public const TRACKING_AUTO_COMPLETE_ORDER_OPTION_KEY = 'dpd_tracking_auto_complete_order';

    public ?string $dpd_delis_id = null;
    public ?string $dpd_api_key = null;
    public ?string $dpd_api_email = null;
    public string|array|null $dpd_bank_id = null;
    public string|array|null $dpd_address_id = null;
    public ?string $dpd_shipping = null;
    public ?string $dpd_notification = null;
    public ?string $dpd_labels_format = null;
    public ?string $dpd_map_widget_enabled = null;
    public ?string $dpd_map_api_key = null;
    public ?string $dpd_language = null;
    public ?string $dpd_tracking_enabled = null;
    public ?string $dpd_statusdata_directory = null;
    public ?string $dpd_statusdata_sftp_host = null;
    public ?string $dpd_statusdata_sftp_port = null;
    public ?string $dpd_statusdata_sftp_username = null;
    public ?string $dpd_statusdata_sftp_password = null;
    public ?string $dpd_statusdata_sftp_remote_directory = null;
    public ?string $dpd_statusdata_sftp_archive_directory = null;
    public ?string $dpd_tracking_auto_complete_order = null;
    public ?string $dpd_print_format = null;

    public static function init(): void
    {
        add_filter('woocommerce_get_sections_shipping', [__CLASS__, 'addShippingSection'], 50, 1);
        add_filter('woocommerce_get_settings_shipping', [__CLASS__, 'getShippingSectionSettings'], 50, 2);
        add_action('woocommerce_admin_field_repeater', [__CLASS__, 'renderRepeaterAdminField']);
        add_filter('woocommerce_admin_settings_sanitize_option', [__CLASS__, 'sanitizeAdminSectionOption'], 10, 3);
        add_action('admin_footer', [__CLASS__, 'addScripts'], 10, 1);
    }

    public function __construct()
    {
        $this->id = self::SETTINGS_ID_KEY;
        $this->method_title = __('DPD Export Settings', 'ar-design-dpd');
        $this->method_description = __('Default settings for DPD export', 'ar-design-dpd');
        $this->title =  __('DPD Export Settings', 'ar-design-dpd');
        $this->enabled = "yes";
        $this->dpd_print_format = $this->get_option(self::PRINT_FORMAT_OPTION_KEY);
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
        $this->dpd_tracking_enabled = $this->get_option(self::TRACKING_ENABLED_OPTION_KEY);
        $this->dpd_statusdata_directory = $this->get_option(self::STATUSDATA_DIRECTORY_OPTION_KEY);
        $this->dpd_statusdata_sftp_host = $this->get_option(self::STATUSDATA_SFTP_HOST_OPTION_KEY);
        $this->dpd_statusdata_sftp_port = $this->get_option(self::STATUSDATA_SFTP_PORT_OPTION_KEY);
        $this->dpd_statusdata_sftp_username = $this->get_option(self::STATUSDATA_SFTP_USERNAME_OPTION_KEY);
        $this->dpd_statusdata_sftp_password = $this->get_option(self::STATUSDATA_SFTP_PASSWORD_OPTION_KEY);
        $this->dpd_statusdata_sftp_remote_directory = $this->get_option(self::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY);
        $this->dpd_statusdata_sftp_archive_directory = $this->get_option(self::STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY);
        $this->dpd_tracking_auto_complete_order = $this->get_option(self::TRACKING_AUTO_COMPLETE_ORDER_OPTION_KEY);

        $this->init_form_fields();
        $this->init_settings();
    }

    public static function addShippingSection(array $sections): array
    {
        $sections[self::SETTINGS_ID_KEY] = __('DPD Export Settings', 'ar-design-dpd');

        return $sections;
    }

    public static function getShippingSectionSettings(array $settings, mixed $current_section): array
    {
        if ($current_section !== self::SETTINGS_ID_KEY) {
            return $settings;
        }

        return self::getAdminSectionSettings();
    }

    public static function getAdminSectionSettings(): array
    {
        $instance = new self();
        $stored_settings = get_option(self::SETTINGS_OPTION_KEY, []);
        $stored_settings = is_array($stored_settings) ? $stored_settings : [];

        $settings = [
            [
                'title' => __('DPD Export Settings', 'ar-design-dpd'),
                'type' => 'title',
                'id' => self::SETTINGS_ID_KEY,
            ],
        ];

        foreach ($instance->form_fields as $key => $field) {
            $settings[] = self::mapFormFieldToAdminSetting($key, $field, $stored_settings);
        }

        $settings[] = [
            'type' => 'sectionend',
            'id' => self::SETTINGS_ID_KEY,
        ];

        return $settings;
    }

    private static function mapFormFieldToAdminSetting(string $key, array $field, array $stored_settings): array
    {
        $field['id'] = self::SETTINGS_OPTION_KEY . '_' . $key;
        $field['field_name'] = self::SETTINGS_OPTION_KEY . '[' . $key . ']';
        $field['setting_key'] = $key;
        $field['container_option_name'] = self::SETTINGS_OPTION_KEY;
        $field['value'] = $stored_settings[$key] ?? ($field['default'] ?? '');

        return $field;
    }

    public static function renderRepeaterAdminField(array $data): void
    {
        if (($data['container_option_name'] ?? '') !== self::SETTINGS_OPTION_KEY) {
            return;
        }

        $field_description = \WC_Admin_Settings::get_field_description($data);
        $values = maybe_unserialize($data['value']);
        $values = is_array($values) ? $values : [];
        $values = htmlspecialchars(json_encode($values), ENT_QUOTES, 'UTF-8');

        $props = [
            'inputName' => $data['id'],
            'labelText' => $data['label_text'] ?? '',
            'removeLabel' => __('Remove', 'ar-design-dpd'),
        ];

        switch ($data['setting_key'] ?? '') {
            case self::BANK_ID_OPTION_KEY:
                $props['titlePlaceholder'] = __('Bank account name', 'ar-design-dpd');
                $props['valuePlaceholder'] = __('Bank account ID', 'ar-design-dpd');
                break;
            case self::ADDRESS_ID_OPTION_KEY:
                $props['titlePlaceholder'] = __('Address name', 'ar-design-dpd');
                $props['valuePlaceholder'] = __('Address ID', 'ar-design-dpd');
                break;
        }

        $props = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($data['id']); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $field_description['tooltip_html']; ?></label>
            </th>
            <td class="forminp">
                <fieldset class="repeatable-field repeatable-field--<?php echo esc_attr($data['setting_key'] ?? ''); ?> <?php echo esc_attr($data['class'] ?? ''); ?>" data-component="field-repeater" data-props="<?php echo $props; ?>" data-inputs-data="<?php echo $values; ?>" tabindex="0">
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <ol class="repeatable-field__rows" data-ref="rowList"></ol>

                    <?php if (!empty($data['repeater_description'])) : ?>
                        <p><small><?php echo wp_kses_post($data['repeater_description']); ?></small></p>
                    <?php endif; ?>

                    <div class="repeatable-field__bottom">
                        <button class="repeatable-field__add-button button" data-ref="addButton" type="button">+ <?php echo esc_attr($data['add_btn_text'] ?? __('Add row', 'ar-design-dpd')); ?></button>
                    </div>

                    <?php echo $field_description['description']; ?>
                </fieldset>
            </td>
        </tr>
        <?php
    }

    public static function sanitizeAdminSectionOption(mixed $value, array $option, mixed $raw_value): mixed
    {
        if (($option['container_option_name'] ?? '') !== self::SETTINGS_OPTION_KEY) {
            return $value;
        }

        if (($option['type'] ?? '') === 'repeater') {
            return self::sanitizeRepeaterOption($option);
        }

        $setting_key = $option['setting_key'] ?? '';
        if ($setting_key !== self::MAP_WIDGET_ENABLED_OPTION_KEY) {
            return $value;
        }

        $posted_settings = self::getPostedSettings();
        $is_map_widget_enabled = $value === 'yes';
        $api_key = isset($posted_settings[self::API_KEY_OPTION_KEY]) ? trim(sanitize_text_field((string) $posted_settings[self::API_KEY_OPTION_KEY])) : '';
        $map_api_key = isset($posted_settings[self::MAP_API_KEY_OPTION_KEY]) ? trim(sanitize_text_field((string) $posted_settings[self::MAP_API_KEY_OPTION_KEY])) : '';

        if ($is_map_widget_enabled && $map_api_key === '') {
            \WC_Admin_Settings::add_error(__('Map Widget requires a dedicated DPD Map API Key. The widget was disabled until a valid Map API Key is entered.', 'ar-design-dpd'));

            return 'no';
        }

        if ($is_map_widget_enabled && $api_key !== '' && hash_equals($api_key, $map_api_key)) {
            \WC_Admin_Settings::add_error(__('Map API Key must be a dedicated DPD Map/PUDO key. The standard DPD API key for export/login will not work in the map widget, so the widget was disabled.', 'ar-design-dpd'));

            return 'no';
        }

        if ($is_map_widget_enabled) {
            \WC_Admin_Settings::add_message(__('Map Widget is enabled. If checkout reports "invalid_api_key", ask DPD for a dedicated Map/PUDO widget key for this account.', 'ar-design-dpd'));
        }

        return $value;
    }

    private static function sanitizeRepeaterOption(array $option): string
    {
        $base_input_name = $option['id'];
        $repeater_values = [];

        $default_values = isset($_POST[$base_input_name . '_default']) ? (array) wp_unslash($_POST[$base_input_name . '_default']) : [];
        $selected_default = isset($default_values[0]) ? sanitize_text_field($default_values[0]) : '';
        $value_values = isset($_POST[$base_input_name . '_value']) ? (array) wp_unslash($_POST[$base_input_name . '_value']) : [];
        $nice_values = isset($_POST[$base_input_name . '_nice_value']) ? (array) wp_unslash($_POST[$base_input_name . '_nice_value']) : [];

        foreach ($value_values as $index => $value) {
            $sanitized_value = sanitize_text_field($value);
            if ($sanitized_value === '') {
                continue;
            }

            $repeater_values[$index] = [
                'value' => $sanitized_value,
                'nice_value' => sanitize_text_field($nice_values[$index] ?? $sanitized_value),
                'default' => $sanitized_value === $selected_default,
            ];
        }

        return serialize(array_values($repeater_values));
    }

    private static function getPostedSettings(): array
    {
        if (empty($_POST[self::SETTINGS_OPTION_KEY]) || !is_array($_POST[self::SETTINGS_OPTION_KEY])) {
            return [];
        }

        return wp_unslash($_POST[self::SETTINGS_OPTION_KEY]);
    }

    /**
     * Get language options
     *
     * @return array
     */
    public static function getLanguageOptions()
    {
        return [
            'sk' => __('Slovak', 'ar-design-dpd'),
            'en' => __('English', 'ar-design-dpd'),
            'hu' => __('Hungarian', 'ar-design-dpd'),
            'de' => __('German', 'ar-design-dpd'),
            'fr' => __('French', 'ar-design-dpd'),
        ];
    }

    public static function getPrintFormatOptions()
    {
        return [
            'pdf' => 'PDF',
            'zpl' => 'ZPL',
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
            '0' => __('Choose', 'ar-design-dpd'),
            '1' => __('DPD Classic', 'ar-design-dpd'),
            '9' => __('DPD Home', 'ar-design-dpd'),
            '3' => __('DPD 10:00', 'ar-design-dpd'),
            '4' => __('DPD 12:00', 'ar-design-dpd'),
            '2' => __('DPD 18:00 / DPD Guarantee', 'ar-design-dpd'),
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
     * Process and validate admin options
     *
     * @return bool
     */
    public function process_admin_options()
    {
        $enabled_field = $this->get_field_key(self::MAP_WIDGET_ENABLED_OPTION_KEY);
        $api_key_field = $this->get_field_key(self::API_KEY_OPTION_KEY);
        $map_api_key_field = $this->get_field_key(self::MAP_API_KEY_OPTION_KEY);

        $is_map_widget_enabled = isset($_POST[$enabled_field]) && wc_string_to_bool(wp_unslash($_POST[$enabled_field]));
        $api_key = isset($_POST[$api_key_field]) ? trim(sanitize_text_field(wp_unslash($_POST[$api_key_field]))) : '';
        $map_api_key = isset($_POST[$map_api_key_field]) ? trim(sanitize_text_field(wp_unslash($_POST[$map_api_key_field]))) : '';

        if ($is_map_widget_enabled && $map_api_key === '') {
            \WC_Admin_Settings::add_error(__('Map Widget requires a dedicated DPD Map API Key. The widget was disabled until a valid Map API Key is entered.', 'ar-design-dpd'));
            unset($_POST[$enabled_field]);

            return parent::process_admin_options();
        }

        if ($is_map_widget_enabled && $api_key !== '' && hash_equals($api_key, $map_api_key)) {
            \WC_Admin_Settings::add_error(__('Map API Key must be a dedicated DPD Map/PUDO key. The standard DPD API key for export/login will not work in the map widget, so the widget was disabled.', 'ar-design-dpd'));
            unset($_POST[$enabled_field]);

            return parent::process_admin_options();
        }

        if ($is_map_widget_enabled) {
            \WC_Admin_Settings::add_message(__('Map Widget is enabled. If checkout reports "invalid_api_key", ask DPD for a dedicated Map/PUDO widget key for this account.', 'ar-design-dpd'));
        }

        return parent::process_admin_options();
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
                'title' => __('DELIS ID', 'ar-design-dpd'),
                'type' => 'text',
                'description' => __('Unique customer identifier assigned by DPD for shipper API.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::EMAIL_OPTION_KEY => [
                'title' => __('DPD login email', 'ar-design-dpd'),
                'type' => 'text',
                'description' => __('Login email used for DPD shipper API authentication.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::API_KEY_OPTION_KEY => [
                'title' => __('DPD API key', 'ar-design-dpd'),
                'type' => 'password',
                'description' => __('API key issued by DPD integration support for shipper API access. This key is used for export/login communication and is not the same as the Map API Key for pickup point widget.', 'ar-design-dpd'),
                'desc_tip' => false,
                'class' => 'js-ar-design-dpd-api-key-field',
            ],
            self::SHIPPING_OPTION_KEY => [
                'title' => __('Default DPD product', 'ar-design-dpd'),
                'type' => 'select',
                'default' => '1',
                'options' => self::getShippingOptions(),
                'description' => __('Default DPD shipper product code used for export. ParcelShop deliveries are switched automatically to ParcelShop product.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::PRINT_FORMAT_OPTION_KEY => [
                'title' => __('Label print format', 'ar-design-dpd'),
                'type' => 'select',
                'default' => 'pdf',
                'options' => self::getPrintFormatOptions(),
                'description' => __('Label output format used for DPD shipper label generation.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::BANK_ID_OPTION_KEY => [
                'title' => __('Bank account ID', 'ar-design-dpd'),
                'type' => 'repeater',
                'max_rows' => 10,
                'label_text' => __('Bank account', 'ar-design-dpd'),
                'add_btn_text' => __('Add bank account', 'ar-design-dpd'),
                'repeater_description' => __('Select your default bank account.', 'ar-design-dpd'),
            ],
            self::ADDRESS_ID_OPTION_KEY => [
                'title' => __('ID of the collection address', 'ar-design-dpd'),
                'type' => 'repeater',
                'max_rows' => 10,
                'label_text' => __('Address', 'ar-design-dpd'),
                'add_btn_text' => __('Add address', 'ar-design-dpd'),
                'repeater_description' => __('Select your default address.', 'ar-design-dpd'),
            ],
            self::NOTIFICATION_OPTION_KEY => [
                'title' => __('Notifications', 'ar-design-dpd'),
                'type' => 'checkbox',
                'default' => 'no',
                'class' => 'js-ar-design-dpd-notification-field'
            ],
            self::LABELS_FORMAT_OPTION_KEY => [
                'title' => __('Labels format', 'ar-design-dpd'),
                'type' => 'select',
                'default' => 'a4',
                'options' => [
                    'A4' => 'A4',
                    'A6' => 'A6',
                ]
            ],
            self::MAP_WIDGET_ENABLED_OPTION_KEY => [
                'title' => __('Enable Map Widget', 'ar-design-dpd'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Enable this option to display the DPD pickup point map widget. It requires a dedicated Map API Key from DPD; the standard export API key will not work here.', 'ar-design-dpd'),
                'desc_tip' => false,
                'class' => 'js-ar-design-dpd-map-widget-enabled-field',
            ],
            self::MAP_API_KEY_OPTION_KEY => [
                'title' => __('Map API Key', 'ar-design-dpd'),
                'type' => 'text',
                'description' => __('Enter the dedicated DPD Map/PUDO API key for the widget. Do not paste the standard DPD API key used for export/login here. If checkout shows "invalid_api_key", this widget key is missing, wrong, or not activated by DPD.', 'ar-design-dpd'),
                'desc_tip' => false,
                'class' => 'js-ar-design-dpd-map-api-key-field',
            ],
            self::LANGUAGE_OPTION_KEY => [
                'title' => __('Language', 'ar-design-dpd'),
                'type' => 'select',
                'default' => 'sk',
                'options' => self::getLanguageOptions(),
                'description' => __('Select the language for DPD map widget', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::TRACKING_ENABLED_OPTION_KEY => [
                'title' => __('Enable tracking sync', 'ar-design-dpd'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Enable periodic DPD shipment tracking synchronization and delivered automation.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::STATUSDATA_DIRECTORY_OPTION_KEY => [
                'title' => __('STATUSDATA directory', 'ar-design-dpd'),
                'type' => 'text',
                'description' => __('Absolute path to a local directory that contains DPD STATUSDATA CSV files downloaded from SFTP.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::STATUSDATA_SFTP_HOST_OPTION_KEY => [
                'title' => __('STATUSDATA SFTP host', 'ar-design-dpd'),
                'type' => 'text',
                'description' => __('Optional. If set together with username, password and remote directory, the plugin will download STATUSDATA files from this SFTP server into the local STATUSDATA directory before import.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::STATUSDATA_SFTP_PORT_OPTION_KEY => [
                'title' => __('STATUSDATA SFTP port', 'ar-design-dpd'),
                'type' => 'number',
                'default' => '22',
                'description' => __('Optional SFTP port. Default is 22.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::STATUSDATA_SFTP_USERNAME_OPTION_KEY => [
                'title' => __('STATUSDATA SFTP username', 'ar-design-dpd'),
                'type' => 'text',
                'description' => __('Username used for SFTP access to STATUSDATA files.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::STATUSDATA_SFTP_PASSWORD_OPTION_KEY => [
                'title' => __('STATUSDATA SFTP password', 'ar-design-dpd'),
                'type' => 'password',
                'description' => __('Password used for SFTP access to STATUSDATA files. Password-based authentication is used by the current downloader implementation.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY => [
                'title' => __('STATUSDATA SFTP remote directory', 'ar-design-dpd'),
                'type' => 'text',
                'description' => __('Remote SFTP directory that contains STATUSDATA files, for example `/out/statusdata`.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY => [
                'title' => __('STATUSDATA SFTP archive directory', 'ar-design-dpd'),
                'type' => 'text',
                'description' => __('Optional remote directory where successfully downloaded files should be moved after import, for example `/out/statusdata/archive`. Leave empty to keep files on the SFTP server.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            self::TRACKING_AUTO_COMPLETE_ORDER_OPTION_KEY => [
                'title' => __('Autocomplete delivered orders', 'ar-design-dpd'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('When enabled, DPD delivery confirmation may set the WooCommerce order to completed immediately.', 'ar-design-dpd'),
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
            'removeLabel' => __('Remove', 'ar-design-dpd'),
        ];

        switch ($key) {
            case self::BANK_ID_OPTION_KEY:
                $props['titlePlaceholder'] = __('Bank account name', 'ar-design-dpd');
                $props['valuePlaceholder'] = __('Bank account ID', 'ar-design-dpd');
                break;
            case self::ADDRESS_ID_OPTION_KEY:
                $props['titlePlaceholder'] = __('Address name', 'ar-design-dpd');
                $props['valuePlaceholder'] = __('Address ID', 'ar-design-dpd');
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
        $settings = get_option(self::SETTINGS_OPTION_KEY, []);
        $settings = is_array($settings) ? $settings : [];
        $repeater_fields_keys = self::getRepeaterFieldsIds();

        foreach ($settings as $key => $value) {
            if (in_array($key, $repeater_fields_keys)) {
                $settings[$key] = self::getRepeaterValue($key);
            }
        }

        return [
            self::PRINT_FORMAT_OPTION_KEY => isset($settings[self::PRINT_FORMAT_OPTION_KEY]) && !empty($settings[self::PRINT_FORMAT_OPTION_KEY]) ? sanitize_text_field($settings[self::PRINT_FORMAT_OPTION_KEY]) : 'pdf',
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
            self::MAP_API_KEY_OPTION_KEY => isset($settings[self::MAP_API_KEY_OPTION_KEY]) && !empty($settings[self::MAP_API_KEY_OPTION_KEY]) ? sanitize_text_field($settings[self::MAP_API_KEY_OPTION_KEY]) : '',
            self::TRACKING_ENABLED_OPTION_KEY => isset($settings[self::TRACKING_ENABLED_OPTION_KEY]) && !empty($settings[self::TRACKING_ENABLED_OPTION_KEY]) ? sanitize_text_field($settings[self::TRACKING_ENABLED_OPTION_KEY]) : 'no',
            self::STATUSDATA_DIRECTORY_OPTION_KEY => isset($settings[self::STATUSDATA_DIRECTORY_OPTION_KEY]) && !empty($settings[self::STATUSDATA_DIRECTORY_OPTION_KEY]) ? sanitize_text_field($settings[self::STATUSDATA_DIRECTORY_OPTION_KEY]) : '',
            self::STATUSDATA_SFTP_HOST_OPTION_KEY => isset($settings[self::STATUSDATA_SFTP_HOST_OPTION_KEY]) && !empty($settings[self::STATUSDATA_SFTP_HOST_OPTION_KEY]) ? sanitize_text_field($settings[self::STATUSDATA_SFTP_HOST_OPTION_KEY]) : '',
            self::STATUSDATA_SFTP_PORT_OPTION_KEY => isset($settings[self::STATUSDATA_SFTP_PORT_OPTION_KEY]) && $settings[self::STATUSDATA_SFTP_PORT_OPTION_KEY] !== '' ? absint($settings[self::STATUSDATA_SFTP_PORT_OPTION_KEY]) : 22,
            self::STATUSDATA_SFTP_USERNAME_OPTION_KEY => isset($settings[self::STATUSDATA_SFTP_USERNAME_OPTION_KEY]) && !empty($settings[self::STATUSDATA_SFTP_USERNAME_OPTION_KEY]) ? sanitize_text_field($settings[self::STATUSDATA_SFTP_USERNAME_OPTION_KEY]) : '',
            self::STATUSDATA_SFTP_PASSWORD_OPTION_KEY => isset($settings[self::STATUSDATA_SFTP_PASSWORD_OPTION_KEY]) && !empty($settings[self::STATUSDATA_SFTP_PASSWORD_OPTION_KEY]) ? sanitize_text_field($settings[self::STATUSDATA_SFTP_PASSWORD_OPTION_KEY]) : '',
            self::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY => isset($settings[self::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY]) && !empty($settings[self::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY]) ? sanitize_text_field($settings[self::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY]) : '',
            self::STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY => isset($settings[self::STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY]) && !empty($settings[self::STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY]) ? sanitize_text_field($settings[self::STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY]) : '',
            self::TRACKING_AUTO_COMPLETE_ORDER_OPTION_KEY => isset($settings[self::TRACKING_AUTO_COMPLETE_ORDER_OPTION_KEY]) && !empty($settings[self::TRACKING_AUTO_COMPLETE_ORDER_OPTION_KEY]) ? sanitize_text_field($settings[self::TRACKING_AUTO_COMPLETE_ORDER_OPTION_KEY]) : 'no',
        ];
    }

    public static function isShipperApiConfigured(): bool
    {
        $settings = self::getDefaultSettings();

        return !empty($settings[self::DELIS_ID_OPTION_KEY])
            && !empty($settings[self::EMAIL_OPTION_KEY])
            && !empty($settings[self::API_KEY_OPTION_KEY])
            && !empty($settings[self::ADDRESS_ID_OPTION_KEY]);
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

        wp_enqueue_script(self::SETTINGS_ID_KEY . '_scripts', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'scripts/dpd-export-settings-admin.js', [], ard_dpd_get_plugin_version(), true);
        wp_localize_script(self::SETTINGS_ID_KEY . '_scripts', 'ard_dpd_settings', ['required_notifications_shipping_keys' => self::getRequiredNotificationsShippingKeys()]);

        wp_enqueue_script(self::SETTINGS_ID_KEY . '_map_key_validation', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'scripts/dpd-export-settings-map-key-validation.js', [], ard_dpd_get_plugin_version(), true);
        wp_localize_script(self::SETTINGS_ID_KEY . '_map_key_validation', 'ard_dpd_map_key_validation_settings', [
            'missing_map_key' => __('Map Widget is enabled, but Map API Key is empty. Enter the dedicated DPD Map/PUDO key before saving.', 'ar-design-dpd'),
            'same_key_error' => __('Map API Key matches the standard DPD API key. These are different credentials; use a dedicated DPD Map/PUDO key.', 'ar-design-dpd'),
            'helper_text' => __('Use a dedicated DPD Map/PUDO key for the widget. If checkout returns "invalid_api_key", contact DPD and verify that the map key is activated for this account.', 'ar-design-dpd'),
        ]);

        // Repeater field assets
        wp_enqueue_script(self::SETTINGS_ID_KEY . '_repeater_field', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'scripts/dpd-export-settings-admin-repeater.js', [], ard_dpd_get_plugin_version(), true);
        wp_enqueue_style(self::SETTINGS_ID_KEY . '_repeater_field', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'styles/dpd-export-repeater-settings-field.css', [], ard_dpd_get_plugin_version(), 'all');
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
