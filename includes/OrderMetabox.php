<?php

namespace ArDesign\DPD;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined('ABSPATH') || exit;

/**
 * OrderMetabox class
 */
class OrderMetabox
{
    public const EXPORT_ACTION_KEY = 'dpd_export';
    public const RESET_ACTION_KEY = 'dpd_reset';
    public const IMPORT_STATUSDATA_ACTION_KEY = 'dpd_import_statusdata';
    public const REPAIR_PARCELSHOP_COD_ACTION_KEY = 'dpd_repair_parcelshop_cod';
    private const MANUAL_STATUSDATA_IMPORT_ACTION = 'ard_dpd_import_statusdata_now';
    private const MANUAL_STATUSDATA_IMPORT_NONCE = 'ard_dpd_import_statusdata_now_nonce';

    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'addMetabox']);
        
        // Handle form submission via dedicated admin action
        add_action('admin_init', [__CLASS__, 'handleFormSubmission']);
        add_action('admin_post_' . self::MANUAL_STATUSDATA_IMPORT_ACTION, [__CLASS__, 'handleManualStatusDataImportRequest']);
    }

    public static function handleManualStatusDataImportRequest(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Nedostatočné oprávnenie.', 'ar-design-dpd'));
        }

        check_admin_referer(self::MANUAL_STATUSDATA_IMPORT_ACTION, self::MANUAL_STATUSDATA_IMPORT_NONCE);

        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

        if ($order_id > 0) {
            self::runStatusDataImport($order_id);
        }

        wp_safe_redirect(self::getOrderEditUrl($order_id));
        exit;
    }

    public static function addMetabox()
    {
        $screen = class_exists(OrderUtil::class) && method_exists(OrderUtil::class, 'custom_orders_table_usage_is_enabled') && OrderUtil::custom_orders_table_usage_is_enabled()
                ? \wc_get_page_screen_id('shop-order')
                : 'shop_order';

        add_meta_box('dpd-export', __('DPD Export', 'ar-design-dpd'), [__CLASS__, 'renderMetabox'], $screen, 'side', 'core');
    }

    /**
     * Handle direct form submissions
     */
    public static function handleFormSubmission()
    {
        // Handle the reset action
        if (isset($_POST[self::RESET_ACTION_KEY]) && isset($_POST['dpd_metabox_nonce'])) {
            if (!wp_verify_nonce($_POST['dpd_metabox_nonce'], 'dpd_metabox_save')) {
                return;
            }
            
            if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
                $order_id = absint($_POST['order_id']);
                Order::reset($order_id);
                
                $order_edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                wp_safe_redirect($order_edit_url);
                exit;
            }
        }
        
        // Handle direct export action
        if (isset($_POST[self::EXPORT_ACTION_KEY]) && isset($_POST['dpd_metabox_nonce'])) {
            if (!wp_verify_nonce($_POST['dpd_metabox_nonce'], 'dpd_metabox_save')) {
                return;
            }
            
            if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
                $order_id = absint($_POST['order_id']);
                self::saveMetaFields($order_id);
                Order::export($order_id);
                
                $order_edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                wp_safe_redirect($order_edit_url);
                exit;
            }
        }

        if (isset($_POST[self::IMPORT_STATUSDATA_ACTION_KEY]) && isset($_POST['dpd_metabox_nonce'])) {
            if (!wp_verify_nonce($_POST['dpd_metabox_nonce'], 'dpd_metabox_save')) {
                return;
            }

            if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
                $order_id = absint($_POST['order_id']);
                self::runStatusDataImport($order_id);
                wp_safe_redirect(self::getOrderEditUrl($order_id));
                exit;
            }
        }

        if (isset($_POST[self::REPAIR_PARCELSHOP_COD_ACTION_KEY]) && isset($_POST['dpd_metabox_nonce'])) {
            if (!wp_verify_nonce($_POST['dpd_metabox_nonce'], 'dpd_metabox_save')) {
                return;
            }

            if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
                $order_id = absint($_POST['order_id']);
                $order = wc_get_order($order_id);
                $messages = [];
                $updated = false;

                $identityResult = \ArDesign\DPD\Order::repairParcelshopIdentityMeta($order_id, true);
                $messages[] = (string) ($identityResult['message'] ?? __('Parcelshop identity metadata did not require any change.', 'ar-design-dpd'));
                $updated = $updated || !empty($identityResult['updated']);

                if ($order instanceof \WC_Order && $order->get_payment_method() === 'cod') {
                    $capabilityResult = \ArDesign\DPD\Order::repairParcelshopCapabilityMeta($order_id, true);
                    $messages[] = (string) ($capabilityResult['message'] ?? __('Parcelshop capability metadata did not require any change.', 'ar-design-dpd'));
                    $updated = $updated || !empty($capabilityResult['updated']);
                }

                $messages = array_values(array_unique(array_filter(array_map('trim', $messages))));
                $noticeMessage = implode(' ', $messages);

                if ($updated) {
                    Notice::success($noticeMessage ?: __('Parcelshop metadata was refreshed from DPD API.', 'ar-design-dpd'));
                } else {
                    Notice::add($noticeMessage ?: __('Parcelshop metadata did not require any change.', 'ar-design-dpd'), 'warning');
                }

                $order_edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                wp_safe_redirect($order_edit_url);
                exit;
            }
        }
    }
    
    /**
     * Save metabox field data
     *
     * @param int $order_id Order ID
     * @return bool
     */
    public static function saveMetaFields(int $order_id)
    {
        if (!$order_id) {
            return false;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order instanceof \WC_Order) {
            return false;
        }
        
        // Save metabox fields
        if (isset($_POST[Order::ADDRESS_ID_META_KEY])) {
            $order->update_meta_data(Order::ADDRESS_ID_META_KEY, sanitize_text_field($_POST[Order::ADDRESS_ID_META_KEY]));
        }

        if (isset($_POST[Order::BANK_ID_META_KEY])) {
            $order->update_meta_data(Order::BANK_ID_META_KEY, sanitize_text_field($_POST[Order::BANK_ID_META_KEY]));
        }

        if (isset($_POST[Order::NOTIFICATION_META_KEY])) {
            $order->update_meta_data(Order::NOTIFICATION_META_KEY, $_POST[Order::NOTIFICATION_META_KEY] == 'on' ? 'yes' : 'no');
        } else {
            $order->update_meta_data(Order::NOTIFICATION_META_KEY, 'no');
        }

        if (isset($_POST[Order::REFERENCE_1_META_KEY])) {
            $order->update_meta_data(Order::REFERENCE_1_META_KEY, sanitize_text_field($_POST[Order::REFERENCE_1_META_KEY]));
        }

        if (isset($_POST[Order::REFERENCE_2_META_KEY])) {
            $order->update_meta_data(Order::REFERENCE_2_META_KEY, sanitize_text_field($_POST[Order::REFERENCE_2_META_KEY]));
        }

        if (isset($_POST[Order::PACKAGE_WEIGHT_META_KEY])) {
            $order->update_meta_data(Order::PACKAGE_WEIGHT_META_KEY, sanitize_text_field($_POST[Order::PACKAGE_WEIGHT_META_KEY]));
        }

        $order->save_meta_data();
        
        return true;
    }

    /**
     * Render export metabox
     *
     * @return void
     */
    public static function renderMetabox(mixed $post_or_order_object): void
    {
        $order = ($post_or_order_object instanceof \WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
        if (!$order instanceof \WC_Order) {
            return;
        }

        $order_id = $order->get_id();

        if (!$order_id) {
            return;
        }

        $default_settings = DpdExportSettings::getDefaultSettings();
        $shipper_configured = DpdExportSettings::isShipperApiConfigured();
        $statusdata_configured = Tracking::isStatusDataConfigured($default_settings);
        $statusdata_sftp_configured = Tracking::isStatusDataSftpConfigured($default_settings);
        $statusdata_directory = isset($default_settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY])
            ? (string) $default_settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY]
            : '';
        $statusdata_diagnostics = self::getStatusDataDiagnostics($default_settings);
        $dpd_export_result = $order->get_meta(Order::EXPORT_STATUS_META_KEY, true);
        $show_parcelshop_repair = self::shouldShowParcelshopCodRepairAction($order);

        if ($dpd_export_result == Order::EXPORT_SUCCESS_STATUS) {
            $dpd_package_number = wp_kses_post($order->get_meta(Order::EXPORT_PACKAGE_NUMBER_META_KEY, true));

            echo '<p>' . __('Export Status', 'ar-design-dpd') . ': ' . __('Success', 'ar-design-dpd') . '</p>';

            $labelButtonHtml = OrderList::getLabelDownloadButtonHtml($order, __('Download DPD label', 'ar-design-dpd'));
            if ($labelButtonHtml !== '') {
                echo '<p>' . $labelButtonHtml . '</p>';
            }

            if ($dpd_package_number) {
                echo '<p>' . __('Package number', 'ar-design-dpd') . ': <strong>' . $dpd_package_number . '</strong></p>';
            }

            echo self::renderStatusDataSyncNotice($order, $default_settings);
            echo self::renderTrackingDiagnostics($order);

            if ($statusdata_configured) {
                echo '<p><small>' . esc_html(sprintf(
                    /* translators: %s: absolute STATUSDATA directory path. */
                    __('STATUSDATA directory: %s', 'ar-design-dpd'),
                    $statusdata_directory
                )) . '</small></p>';
                echo self::renderStatusDataDiagnostics($statusdata_diagnostics);
                if ($statusdata_sftp_configured) {
                    echo '<p><small>' . esc_html__('SFTP download is configured and will run before import.', 'ar-design-dpd') . '</small></p>';
                }
                echo '<p><a class="button" href="' . esc_url(self::getManualStatusDataImportUrl($order_id)) . '">' . esc_html__('Import STATUSDATA now', 'ar-design-dpd') . '</a></p>';
            }

            echo '<form method="post">';
            wp_nonce_field('dpd_metabox_save', 'dpd_metabox_nonce');
            echo '<input type="hidden" name="order_id" value="' . esc_attr($order_id) . '">';

            if ($show_parcelshop_repair) {
                echo '<p><input type="submit" class="button" value="' . esc_attr__('Repair ParcelShop data', 'ar-design-dpd') . '" name="' . esc_attr(self::REPAIR_PARCELSHOP_COD_ACTION_KEY) . '"></p>';
            }

            echo '<input type="submit" class="button" value="' . __('Reset', 'ar-design-dpd') . '" name="' . esc_attr(self::RESET_ACTION_KEY) . '">';
            echo '</form>';

            return;
        }

        $default_bank_id = isset($default_settings[DpdExportSettings::BANK_ID_OPTION_KEY]) && !empty($default_settings[DpdExportSettings::BANK_ID_OPTION_KEY]) ? $default_settings[DpdExportSettings::BANK_ID_OPTION_KEY] : null;
        $default_address_id = isset($default_settings[DpdExportSettings::ADDRESS_ID_OPTION_KEY]) && !empty($default_settings[DpdExportSettings::ADDRESS_ID_OPTION_KEY]) ? $default_settings[DpdExportSettings::ADDRESS_ID_OPTION_KEY] : null;
        $default_notification = isset($default_settings[DpdExportSettings::NOTIFICATION_OPTION_KEY]) && !empty($default_settings[DpdExportSettings::NOTIFICATION_OPTION_KEY]) ? $default_settings[DpdExportSettings::NOTIFICATION_OPTION_KEY] : 'no';

        $bank_id_options = !empty(DpdExportSettings::getRepeaterOptions(Order::BANK_ID_META_KEY)) ? DpdExportSettings::getRepeaterOptions(Order::BANK_ID_META_KEY) : [];
        $selected_bank_id_option = $order->get_meta(Order::BANK_ID_META_KEY, true);

        $address_id_options = !empty(DpdExportSettings::getRepeaterOptions(Order::ADDRESS_ID_META_KEY)) ? DpdExportSettings::getRepeaterOptions(Order::ADDRESS_ID_META_KEY) : [];
        $selected_address_id_option = $order->get_meta(Order::ADDRESS_ID_META_KEY, true);

        $notification = $order->get_meta(Order::NOTIFICATION_META_KEY, true);
        $notification = $notification ? $notification : $default_notification;
        $notification = $notification !== 'no' ? 'yes' : 'no';

        $reference_1 = $order->get_meta(Order::REFERENCE_1_META_KEY, true);
        $reference_2 = $order->get_meta(Order::REFERENCE_2_META_KEY, true);

        $package_weight = $order->get_meta(Order::PACKAGE_WEIGHT_META_KEY, true);

        $tracking_number = $order->get_meta(Order::TRACKING_NUMBER_META_KEY, true);
        ?>

        <form method="post">
            <?php wp_nonce_field('dpd_metabox_save', 'dpd_metabox_nonce'); ?>
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">

            <div class="notice inline <?php echo $shipper_configured ? 'notice-info' : 'notice-warning'; ?>" style="margin: 0 0 12px; padding: 0 10px;">
                <p>
                    <?php if ($shipper_configured) : ?>
                        <strong><?php esc_html_e('DPD SK shipper export mode is active.', 'ar-design-dpd'); ?></strong><br>
                        <?php esc_html_e('Export uses DELIS ID, login email and API key via the DPD shipper `shipment/json` endpoint.', 'ar-design-dpd'); ?>
                    <?php else : ?>
                        <?php esc_html_e('DPD SK shipper credentials are not fully configured yet, so export cannot run successfully.', 'ar-design-dpd'); ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($statusdata_configured) : ?>
                <div class="notice inline notice-success" style="margin: 0 0 12px; padding: 0 10px;">
                    <p>
                        <strong><?php esc_html_e('STATUSDATA tracking import is configured.', 'ar-design-dpd'); ?></strong><br>
                        <?php echo esc_html(sprintf(
                            /* translators: %s: absolute STATUSDATA directory path. */
                            __('Directory: %s', 'ar-design-dpd'),
                            $statusdata_directory
                        )); ?>
                        <?php if ($statusdata_sftp_configured) : ?>
                            <br><?php esc_html_e('SFTP source is configured and will be synchronized before local import.', 'ar-design-dpd'); ?>
                        <?php endif; ?>
                    </p>
                    <?php echo wp_kses_post(self::renderStatusDataDiagnostics($statusdata_diagnostics)); ?>
                </div>
            <?php endif; ?>

            <?php echo wp_kses_post(self::renderStatusDataSyncNotice($order, $default_settings)); ?>
            <?php echo wp_kses_post(self::renderTrackingDiagnostics($order)); ?>
            
			<?php if (!empty($bank_id_options)) : ?>
				<p>
                    <label for="<?php echo esc_attr(Order::BANK_ID_META_KEY); ?>"><?php _e('Bank account ID', 'ar-design-dpd')?>:</label><br>
					<select id="<?php echo esc_attr(Order::BANK_ID_META_KEY); ?>" name="<?php echo esc_attr(Order::BANK_ID_META_KEY); ?>" style="width: 100%;">
						<?php foreach ($bank_id_options as $key => $values):
						    $selected_option = $selected_bank_id_option ? $selected_bank_id_option : $default_bank_id;
						    if ($selected_option) {
						        $selected = $values['value'] == $selected_option ? true : false;
						    } else {
						        $selected = $values['default'] ? true : false;
						    }
						    ?>
							<option value="<?php echo esc_attr($values['value']); ?>" <?php echo $selected ? ' selected="selected"' : ''; ?>>
								<?php echo esc_html($values['nice_value']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>

			<?php if (!empty($address_id_options)) : ?>
				<p>
                    <label for="<?php echo esc_attr(Order::ADDRESS_ID_META_KEY); ?>"><?php _e('Pickup address ID', 'ar-design-dpd')?>:</label><br>
					<select id="<?php echo esc_attr(Order::ADDRESS_ID_META_KEY); ?>" name="<?php echo esc_attr(Order::ADDRESS_ID_META_KEY); ?>" style="width: 100%;">
						<?php foreach ($address_id_options as $key => $values):
						    $selected_option = $selected_address_id_option ? $selected_address_id_option : $default_address_id;
						    if ($selected_option) {
						        $selected = $values['value'] == $selected_option ? true : false;
						    } else {
						        $selected = $values['default'] ? true : false;
						    }
						    ?>
							<option value="<?php echo esc_attr($values['value']); ?>" <?php echo $selected ? ' selected="selected"' : ''; ?>>
								<?php echo esc_html($values['nice_value']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>

			<p class="js-ar-design-dpd-notification-field-row">
				<label for="<?php echo esc_attr(Order::NOTIFICATION_META_KEY); ?>"><?php _e('Notification', 'ar-design-dpd')?>:</label><br>
				<input type="checkbox" id="<?php echo esc_attr(Order::NOTIFICATION_META_KEY); ?>" name="<?php echo esc_attr(Order::NOTIFICATION_META_KEY); ?>" class="js-ar-design-dpd-notification-field" <?php checked($notification, 'yes'); ?>>
			</p>

			<p>
                <label for="<?php echo esc_attr(Order::REFERENCE_1_META_KEY); ?>"><?php echo sprintf(
                    /* translators: %d: reference field number. */
                    __('Reference %d', 'ar-design-dpd'),
                    1
                ); ?>:</label><br>
				<input type="text" id="<?php echo esc_attr(Order::REFERENCE_1_META_KEY); ?>" name="<?php echo esc_attr(Order::REFERENCE_1_META_KEY); ?>" value="<?php echo esc_attr($reference_1); ?>">
			</p>

			<p>
                <label for="<?php echo esc_attr(Order::REFERENCE_2_META_KEY); ?>"><?php echo sprintf(
                    /* translators: %d: reference field number. */
                    __('Reference %d', 'ar-design-dpd'),
                    2
                ); ?>:</label><br>
				<input type="text" id="<?php echo esc_attr(Order::REFERENCE_2_META_KEY); ?>" name="<?php echo esc_attr(Order::REFERENCE_2_META_KEY); ?>" value="<?php echo esc_attr($reference_2); ?>">
			</p>

			<p>
				<label for="<?php echo esc_attr(Order::PACKAGE_WEIGHT_META_KEY); ?>"><?php _e('Package Weight (kg)', 'ar-design-dpd'); ?></label><br>
				<input type="number" id="<?php echo esc_attr(Order::PACKAGE_WEIGHT_META_KEY); ?>" name="<?php echo esc_attr(Order::PACKAGE_WEIGHT_META_KEY); ?>" value="<?php echo esc_attr($package_weight); ?>" step="0.01" min="0"><br>
                <small class="description"><?php _e('Optional. Leave empty to use the default shipment weight of 3.00 kg.', 'ar-design-dpd'); ?></small>
			</p>

			<p>
				<input type="hidden" value="<?php echo $order_id; ?>" name="<?php echo esc_attr(OrderList::EXPORT_ORDER_KEY); ?>">
				<input type="submit" class="button" value="<?php _e('Export to DPD', 'ar-design-dpd'); ?>" name="<?php echo esc_attr(self::EXPORT_ACTION_KEY); ?>">
			</p>

            <?php if ($show_parcelshop_repair) : ?>
                <p>
                    <input type="submit" class="button" value="<?php esc_attr_e('Repair ParcelShop data', 'ar-design-dpd'); ?>" name="<?php echo esc_attr(self::REPAIR_PARCELSHOP_COD_ACTION_KEY); ?>">
                </p>
            <?php endif; ?>

            <?php if ($statusdata_configured) : ?>
                <p>
                    <a class="button" href="<?php echo esc_url(self::getManualStatusDataImportUrl($order_id)); ?>"><?php esc_html_e('Import STATUSDATA now', 'ar-design-dpd'); ?></a>
                </p>
            <?php endif; ?>
        </form>
		<?php
    }

    private static function runStatusDataImport(int $order_id): void
    {
        $settings = DpdExportSettings::getDefaultSettings();
        $directory = isset($settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY])
            ? (string) $settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY]
            : '';

        if (!Tracking::isStatusDataConfigured($settings)) {
            Notice::error(__('STATUSDATA directory is not configured or not readable.', 'ar-design-dpd'));

            return;
        }

        $summary = Tracking::importStatusDataDirectory($directory);
        $summaryMessage = sprintf(
            /* translators: 1: remote files downloaded, 2: remote files skipped, 3: local files processed, 4: local files skipped, 5: orders updated, 6: unmatched parcels, 7: errors. */
            __('STATUSDATA sync finished. Remote downloaded: %1$d, remote skipped: %2$d, files processed: %3$d, local skipped: %4$d, orders updated: %5$d, unmatched parcels: %6$d, errors: %7$d.', 'ar-design-dpd'),
            (int) ($summary['remote_files_downloaded'] ?? 0),
            (int) ($summary['remote_files_skipped'] ?? 0),
            (int) ($summary['files_processed'] ?? 0),
            (int) ($summary['files_skipped'] ?? 0),
            (int) ($summary['orders_updated'] ?? 0),
            (int) ($summary['parcels_unmatched'] ?? 0),
            (int) ($summary['errors'] ?? 0)
        );

        $details = array_values(array_filter(array_map('trim', (array) ($summary['messages'] ?? []))));
        $combinedMessage = $summaryMessage;

        if ($details !== []) {
            $combinedMessage .= ' ' . implode(' ', array_map('wp_strip_all_tags', $details));
        }

        if ((int) ($summary['errors'] ?? 0) > 0) {
            Notice::error($combinedMessage);

            return;
        }

        if ((int) ($summary['files_found'] ?? 0) === 0 && (int) ($summary['remote_files_downloaded'] ?? 0) === 0) {
            Notice::add($combinedMessage, 'warning');

            return;
        }

        Notice::success($combinedMessage);
    }

    private static function getManualStatusDataImportUrl(int $order_id): string
    {
        return wp_nonce_url(
            add_query_arg([
                'action' => self::MANUAL_STATUSDATA_IMPORT_ACTION,
                'order_id' => $order_id,
            ], admin_url('admin-post.php')),
            self::MANUAL_STATUSDATA_IMPORT_ACTION,
            self::MANUAL_STATUSDATA_IMPORT_NONCE
        );
    }

    private static function getOrderEditUrl(int $order_id): string
    {
        if (
            $order_id > 0
            && class_exists(OrderUtil::class)
            && method_exists(OrderUtil::class, 'custom_orders_table_usage_is_enabled')
            && OrderUtil::custom_orders_table_usage_is_enabled()
        ) {
            return admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id);
        }

        return admin_url('post.php?post=' . $order_id . '&action=edit');
    }

    private static function shouldShowParcelshopCodRepairAction(\WC_Order $order): bool
    {
        return Order::hasParcelShpping($order);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private static function getStatusDataDiagnostics(array $settings): array
    {
        $directory = isset($settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY])
            ? trim((string) $settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY])
            : '';
        $localFiles = [];

        if ($directory !== '' && is_dir($directory) && is_readable($directory)) {
            $localFiles = glob(untrailingslashit($directory) . DIRECTORY_SEPARATOR . '*');
            $localFiles = is_array($localFiles)
                ? array_values(array_filter($localFiles, static fn ($filePath) => is_string($filePath) && is_file($filePath)))
                : [];
        }

        return [
            'directory' => $directory,
            'is_dir' => $directory !== '' && is_dir($directory),
            'is_readable' => $directory !== '' && is_readable($directory),
            'is_writable' => $directory !== '' && is_writable($directory),
            'local_file_count' => count($localFiles),
            'sftp_configured' => Tracking::isStatusDataSftpConfigured($settings),
            'ssh2_available' => function_exists('ssh2_connect') && function_exists('ssh2_auth_password') && function_exists('ssh2_sftp'),
            'sftp_host' => isset($settings[DpdExportSettings::STATUSDATA_SFTP_HOST_OPTION_KEY]) ? (string) $settings[DpdExportSettings::STATUSDATA_SFTP_HOST_OPTION_KEY] : '',
            'sftp_remote_directory' => isset($settings[DpdExportSettings::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY]) ? (string) $settings[DpdExportSettings::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY] : '',
            'sftp_archive_directory' => isset($settings[DpdExportSettings::STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY]) ? (string) $settings[DpdExportSettings::STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY] : '',
        ];
    }

    /**
     * @param array<string, mixed> $diagnostics
     */
    private static function renderStatusDataDiagnostics(array $diagnostics): string
    {
        $lines = [];

        $lines[] = sprintf(
            /* translators: 1: directory exists status, 2: readability status, 3: writability status, 4: file count */
            __('Directory check: exists %1$s, readable %2$s, writable %3$s. Local files: %4$d.', 'ar-design-dpd'),
            !empty($diagnostics['is_dir']) ? __('yes', 'ar-design-dpd') : __('no', 'ar-design-dpd'),
            !empty($diagnostics['is_readable']) ? __('yes', 'ar-design-dpd') : __('no', 'ar-design-dpd'),
            !empty($diagnostics['is_writable']) ? __('yes', 'ar-design-dpd') : __('no', 'ar-design-dpd'),
            (int) ($diagnostics['local_file_count'] ?? 0)
        );

        if (!empty($diagnostics['sftp_configured'])) {
            $lines[] = sprintf(
                /* translators: 1: SFTP host, 2: remote directory */
                __('SFTP source: %1$s / %2$s.', 'ar-design-dpd'),
                (string) ($diagnostics['sftp_host'] ?? ''),
                (string) ($diagnostics['sftp_remote_directory'] ?? '')
            );

            if (!empty($diagnostics['sftp_archive_directory'])) {
                $lines[] = sprintf(
                    /* translators: %s: remote archive directory */
                    __('Remote archive directory: %s.', 'ar-design-dpd'),
                    (string) $diagnostics['sftp_archive_directory']
                );
            }

            if (empty($diagnostics['ssh2_available'])) {
                $lines[] = __('SFTP download is currently blocked because the PHP ssh2 extension is missing on this server.', 'ar-design-dpd');
            }
        }

        if ($lines === []) {
            return '';
        }

        $html = '<p><small>';
        $html .= implode('<br>', array_map('esc_html', $lines));
        $html .= '</small></p>';

        return $html;
    }

    private static function renderTrackingDiagnostics(\WC_Order $order): string
    {
        $currentStatus = (string) $order->get_meta('dpd_shipment_tracking_status', true);
        $currentStatusCode = (string) $order->get_meta('dpd_shipment_tracking_status_code', true);
        $currentServiceCode = (string) $order->get_meta('dpd_shipment_tracking_service_code', true);
        $currentLabel = (string) $order->get_meta('dpd_shipment_tracking_label', true);
        $currentDescription = (string) $order->get_meta('dpd_shipment_tracking_description', true);
        $currentDate = (string) $order->get_meta('dpd_shipment_tracking_date', true);
        $currentLocation = (string) $order->get_meta('dpd_shipment_tracking_location', true);
        $lastSyncAt = (string) $order->get_meta('dpd_shipment_tracking_last_sync_at', true);
        $shipmentStatus = (string) $order->get_meta(Shipment::STATUS_META_KEY, true);
        $shipmentStatusLabel = (string) $order->get_meta(Shipment::STATUS_LABEL_META_KEY, true);

        if (
            $currentStatus === ''
            && $currentStatusCode === ''
            && $currentServiceCode === ''
            && $currentLabel === ''
            && $currentDescription === ''
            && $currentDate === ''
            && $currentLocation === ''
            && $lastSyncAt === ''
            && $shipmentStatus === ''
            && $shipmentStatusLabel === ''
        ) {
            return '';
        }

        $html = '<div style="margin-top:12px;padding:10px;border:1px solid #dcdcde;border-radius:4px;background:#f6f7f7;">';
        $html .= '<strong>' . esc_html__('DPD Tracking Diagnostics:', 'ar-design-dpd') . '</strong><br/>';

        if ($currentLabel !== '') {
            $html .= '<strong>' . esc_html__('Current Label:', 'ar-design-dpd') . '</strong> ' . esc_html($currentLabel) . '<br/>';
        }

        if ($currentStatusCode !== '') {
            $html .= '<strong>' . esc_html__('Status Code:', 'ar-design-dpd') . '</strong> <code>' . esc_html($currentStatusCode) . '</code><br/>';
        }

        if ($currentServiceCode !== '') {
            $html .= '<strong>' . esc_html__('Service Code:', 'ar-design-dpd') . '</strong> <code>' . esc_html($currentServiceCode) . '</code><br/>';
        }

        if ($currentStatus !== '') {
            $html .= '<strong>' . esc_html__('Mapped Workflow Status:', 'ar-design-dpd') . '</strong> <code>' . esc_html($currentStatus) . '</code><br/>';
        }

        if ($shipmentStatusLabel !== '') {
            $html .= '<strong>' . esc_html__('Shipment Label:', 'ar-design-dpd') . '</strong> ' . esc_html($shipmentStatusLabel) . '<br/>';
        }

        if ($shipmentStatus !== '') {
            $html .= '<strong>' . esc_html__('Shipment Status:', 'ar-design-dpd') . '</strong> <code>' . esc_html($shipmentStatus) . '</code><br/>';
        }

        if ($currentDescription !== '') {
            $html .= '<strong>' . esc_html__('Carrier Description:', 'ar-design-dpd') . '</strong> ' . esc_html($currentDescription) . '<br/>';
        }

        if ($currentLocation !== '') {
            $html .= '<strong>' . esc_html__('Location:', 'ar-design-dpd') . '</strong> ' . esc_html($currentLocation) . '<br/>';
        }

        if ($currentDate !== '') {
            $html .= '<strong>' . esc_html__('Event Time:', 'ar-design-dpd') . '</strong> ' . esc_html($currentDate) . '<br/>';
        }

        if ($lastSyncAt !== '') {
            $html .= '<strong>' . esc_html__('Last Sync:', 'ar-design-dpd') . '</strong> ' . esc_html($lastSyncAt) . '<br/>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function renderStatusDataSyncNotice(\WC_Order $order, array $settings): string
    {
        $diagnostics = Tracking::getStatusDataLookupDiagnostics($order, $settings);
        $trackingNumber = (string) ($diagnostics['tracking_number'] ?? '');

        if (
            $trackingNumber === ''
            && empty($diagnostics['tracking_enabled'])
            && empty($diagnostics['statusdata_files_exist'])
        ) {
            return '';
        }

        $noticeClass = !empty($diagnostics['sync_running']) && !empty($diagnostics['statusdata_files_exist'])
            ? (!empty($diagnostics['matching_rows_found']) ? 'notice-success' : 'notice-warning')
            : 'notice-warning';
        $lines = [];

        $lines[] = sprintf(
            /* translators: %s: yes/no state */
            __('Tracking sync běží: %s', 'ar-design-dpd'),
            !empty($diagnostics['sync_running']) ? __('ano', 'ar-design-dpd') : __('ne', 'ar-design-dpd')
        );
        $lines[] = sprintf(
            /* translators: 1: yes/no state, 2: number of local STATUSDATA files */
            __('STATUSDATA soubory existují: %1$s (%2$d)', 'ar-design-dpd'),
            !empty($diagnostics['statusdata_files_exist']) ? __('ano', 'ar-design-dpd') : __('ne', 'ar-design-dpd'),
            (int) ($diagnostics['local_file_count'] ?? 0)
        );

        if ($trackingNumber !== '') {
            if (!empty($diagnostics['matching_rows_found'])) {
                $lines[] = sprintf(
                    /* translators: 1: parcel number, 2: number of matching rows, 3: comma separated STATUSDATA files */
                    __('Pro parcel číslo %1$s byly nalezeny %2$d řádky v souborech: %3$s.', 'ar-design-dpd'),
                    $trackingNumber,
                    (int) ($diagnostics['matching_row_count'] ?? 0),
                    implode(', ', array_map('sanitize_text_field', (array) ($diagnostics['matching_files'] ?? [])))
                );
            } elseif (!empty($diagnostics['statusdata_files_exist'])) {
                $lines[] = sprintf(
                    /* translators: %s: parcel number */
                    __('Ale pro parcel číslo %s nebyl nalezen žádný řádek.', 'ar-design-dpd'),
                    $trackingNumber
                );
            }
        }

        $html = '<div class="notice inline ' . esc_attr($noticeClass) . '" style="margin:12px 0 0;padding:0 10px;">';
        $html .= '<p><strong>' . esc_html__('DPD sync diagnostika', 'ar-design-dpd') . '</strong><br>';
        $html .= implode('<br>', array_map('esc_html', $lines));
        $html .= '</p></div>';

        return $html;
    }
}
