<?php

namespace ArDesign\DPD;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

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

    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'addMetabox']);
        
        // Handle form submission via dedicated admin action
        add_action('admin_init', [__CLASS__, 'handleFormSubmission']);
    }

    public static function addMetabox()
    {
        $screen = class_exists(CustomOrdersTableController::class) && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
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
                $settings = DpdExportSettings::getDefaultSettings();
                $directory = isset($settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY])
                    ? (string) $settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY]
                    : '';

                if (!Tracking::isStatusDataConfigured($settings)) {
                    Notice::error(__('STATUSDATA directory is not configured or not readable.', 'ar-design-dpd'));
                } else {
                    $summary = Tracking::importStatusDataDirectory($directory);
                    Notice::success(sprintf(
                        /* translators: 1: remote files downloaded, 2: remote files skipped, 3: local files processed, 4: local files skipped, 5: orders updated, 6: unmatched parcels, 7: errors. */
                        __('STATUSDATA sync finished. Remote downloaded: %1$d, remote skipped: %2$d, files processed: %3$d, local skipped: %4$d, orders updated: %5$d, unmatched parcels: %6$d, errors: %7$d.', 'ar-design-dpd'),
                        (int) ($summary['remote_files_downloaded'] ?? 0),
                        (int) ($summary['remote_files_skipped'] ?? 0),
                        (int) ($summary['files_processed'] ?? 0),
                        (int) ($summary['files_skipped'] ?? 0),
                        (int) ($summary['orders_updated'] ?? 0),
                        (int) ($summary['parcels_unmatched'] ?? 0),
                        (int) ($summary['errors'] ?? 0)
                    ));
                }

                $order_edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                wp_safe_redirect($order_edit_url);
                exit;
            }
        }

        if (isset($_POST[self::REPAIR_PARCELSHOP_COD_ACTION_KEY]) && isset($_POST['dpd_metabox_nonce'])) {
            if (!wp_verify_nonce($_POST['dpd_metabox_nonce'], 'dpd_metabox_save')) {
                return;
            }

            if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
                $order_id = absint($_POST['order_id']);
                $result = Order::repairParcelshopCapabilityMeta($order_id, true);

                if (!empty($result['updated'])) {
                    Notice::success((string) ($result['message'] ?? __('Parcelshop capability metadata was refreshed from DPD API.', 'ar-design-dpd')));
                } else {
                    Notice::add((string) ($result['message'] ?? __('Parcelshop capability metadata did not require any change.', 'ar-design-dpd')), 'warning');
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
        $dpd_export_result = $order->get_meta(Order::EXPORT_STATUS_META_KEY, true);
        $show_parcelshop_repair = self::shouldShowParcelshopCodRepairAction($order);

        if ($dpd_export_result == Order::EXPORT_SUCCESS_STATUS) {
            $dpd_label_url = $order->get_meta(Order::EXPORT_LABEL_URL_META_KEY, true);
            $dpd_package_number = wp_kses_post($order->get_meta(Order::EXPORT_PACKAGE_NUMBER_META_KEY, true));

            echo '<p>' . __('Export Status', 'ar-design-dpd') . ': ' . __('Success', 'ar-design-dpd') . '</p>';

            if ($dpd_label_url) {
                echo '<p><a href="' . esc_url($dpd_label_url) . '">' . __('Download DPD label', 'ar-design-dpd') . '</a></p>';
            }

            if ($dpd_package_number) {
                echo '<p>' . __('Package number', 'ar-design-dpd') . ': <strong>' . $dpd_package_number . '</strong></p>';
            }

            if ($statusdata_configured) {
                echo '<p><small>' . esc_html(sprintf(
                    /* translators: %s: absolute STATUSDATA directory path. */
                    __('STATUSDATA directory: %s', 'ar-design-dpd'),
                    $statusdata_directory
                )) . '</small></p>';
                if ($statusdata_sftp_configured) {
                    echo '<p><small>' . esc_html__('SFTP download is configured and will run before import.', 'ar-design-dpd') . '</small></p>';
                }
                echo '<form method="post" style="margin-bottom:8px;">';
                wp_nonce_field('dpd_metabox_save', 'dpd_metabox_nonce');
                echo '<input type="hidden" name="order_id" value="' . esc_attr($order_id) . '">';
                echo '<input type="submit" class="button" value="' . esc_attr__('Import STATUSDATA now', 'ar-design-dpd') . '" name="' . esc_attr(self::IMPORT_STATUSDATA_ACTION_KEY) . '">';
                echo '</form>';
            }

            echo '<form method="post">';
            wp_nonce_field('dpd_metabox_save', 'dpd_metabox_nonce');
            echo '<input type="hidden" name="order_id" value="' . esc_attr($order_id) . '">';

            if ($show_parcelshop_repair) {
                echo '<p><input type="submit" class="button" value="' . esc_attr__('Repair ParcelShop COD data', 'ar-design-dpd') . '" name="' . esc_attr(self::REPAIR_PARCELSHOP_COD_ACTION_KEY) . '"></p>';
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
                </div>
            <?php endif; ?>
            
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
                    <input type="submit" class="button" value="<?php esc_attr_e('Repair ParcelShop COD data', 'ar-design-dpd'); ?>" name="<?php echo esc_attr(self::REPAIR_PARCELSHOP_COD_ACTION_KEY); ?>">
                </p>
            <?php endif; ?>

            <?php if ($statusdata_configured) : ?>
                <p>
                    <input type="submit" class="button" value="<?php esc_attr_e('Import STATUSDATA now', 'ar-design-dpd'); ?>" name="<?php echo esc_attr(self::IMPORT_STATUSDATA_ACTION_KEY); ?>">
                </p>
            <?php endif; ?>
        </form>
		<?php
    }

    private static function shouldShowParcelshopCodRepairAction(\WC_Order $order): bool
    {
        if (!Order::hasParcelShpping($order)) {
            return false;
        }

        return $order->get_payment_method() === 'cod';
    }
}
