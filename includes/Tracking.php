<?php

namespace ArDesign\DPD;

use WC_Order;

defined('ABSPATH') || exit;

class Tracking
{
    public const CRON_HOOK = 'ard_dpd_tracking_sync_event';
    public const CRON_SCHEDULE = 'ard_dpd_tracking_every_thirty_minutes';
    private const STATUSDATA_PROCESSED_FILES_OPTION_KEY = 'ard_dpd_statusdata_processed_files';
    private const STATUSDATA_REMOTE_DOWNLOADS_OPTION_KEY = 'ard_dpd_statusdata_remote_downloads';
    public const CURRENT_STATUS_META_KEY = 'dpd_shipment_tracking_status';
    public const CURRENT_STATUS_CODE_META_KEY = 'dpd_shipment_tracking_status_code';
    public const CURRENT_SERVICE_CODE_META_KEY = 'dpd_shipment_tracking_service_code';
    public const CURRENT_STATUS_LABEL_META_KEY = 'dpd_shipment_tracking_label';
    public const CURRENT_STATUS_DESCRIPTION_META_KEY = 'dpd_shipment_tracking_description';
    public const CURRENT_STATUS_DATE_META_KEY = 'dpd_shipment_tracking_date';
    public const CURRENT_STATUS_LOCATION_META_KEY = 'dpd_shipment_tracking_location';
    public const STATUS_HISTORY_META_KEY = 'dpd_shipment_tracking_history';
    public const LAST_SYNC_AT_META_KEY = 'dpd_shipment_tracking_last_sync_at';
    public const LAST_SYNC_AT_GMT_META_KEY = 'dpd_shipment_tracking_last_sync_at_gmt';
    public const LAST_SYNC_ERROR_META_KEY = 'dpd_shipment_tracking_last_error';
    public const DELIVERY_CONFIRMED_AT_META_KEY = 'dpd_shipment_delivered_at';
    public const DELIVERY_CONFIRMED_AT_GMT_META_KEY = 'dpd_shipment_delivered_at_gmt';
    public const DELIVERY_EMAIL_SENT_AT_META_KEY = 'dpd_shipment_delivery_followup_sent_at';

    public static function init()
    {
        add_filter('cron_schedules', [__CLASS__, 'registerCronSchedules']);
        add_action('init', [__CLASS__, 'maybeScheduleTrackingCron']);
        add_action(self::CRON_HOOK, [__CLASS__, 'syncOpenShipments']);
        add_action('ard_dpd_after_order_export', [__CLASS__, 'primeTrackingAfterExport'], 10, 2);
        add_action('woocommerce_order_details_after_order_table', [__CLASS__, 'displayTrackingInfo'], 20, 1);
        add_action('woocommerce_admin_order_data_after_billing_address', [__CLASS__, 'displayAdminTrackingInfo'], 20, 1);
    }

    public static function registerCronSchedules(array $schedules): array
    {
        $schedules[self::CRON_SCHEDULE] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 minutes (AR Design DPD tracking)', 'ar-design-dpd'),
        ];

        return $schedules;
    }

    public static function maybeScheduleTrackingCron()
    {
        if (!self::isTrackingEnabled()) {
            while ($timestamp = wp_next_scheduled(self::CRON_HOOK)) {
                wp_unschedule_event($timestamp, self::CRON_HOOK);
            }

            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + (5 * MINUTE_IN_SECONDS), self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public static function isTrackingEnabled(): bool
    {
        $settings = DpdExportSettings::getDefaultSettings();

        return isset($settings[DpdExportSettings::TRACKING_ENABLED_OPTION_KEY])
            && $settings[DpdExportSettings::TRACKING_ENABLED_OPTION_KEY] === 'yes';
    }

    public static function isTrackingConfigured(): bool
    {
        if (!self::isTrackingEnabled()) {
            return false;
        }

        return self::isStatusDataConfigured();
    }

    public static function isStatusDataConfigured(?array $settings = null): bool
    {
        $settings = is_array($settings) ? $settings : DpdExportSettings::getDefaultSettings();
        $directory = isset($settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY])
            ? (string) $settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY]
            : '';

        if ($directory === '') {
            return false;
        }

        return is_dir($directory) && is_readable($directory);
    }

    public static function isStatusDataSftpConfigured(?array $settings = null): bool
    {
        $settings = is_array($settings) ? $settings : DpdExportSettings::getDefaultSettings();

        return !empty($settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY])
            && !empty($settings[DpdExportSettings::STATUSDATA_SFTP_HOST_OPTION_KEY])
            && !empty($settings[DpdExportSettings::STATUSDATA_SFTP_USERNAME_OPTION_KEY])
            && !empty($settings[DpdExportSettings::STATUSDATA_SFTP_PASSWORD_OPTION_KEY])
            && isset($settings[DpdExportSettings::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY])
            && trim((string) $settings[DpdExportSettings::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY]) !== '';
    }

    public static function primeTrackingAfterExport(WC_Order $order, array $response = []): void
    {
        $packageNumber = (string) $order->get_meta(Order::EXPORT_PACKAGE_NUMBER_META_KEY, true);
        if (!$packageNumber && !empty($response[Order::EXPORT_PACKAGE_NUMBER_META_KEY])) {
            $packageNumber = (string) $response[Order::EXPORT_PACKAGE_NUMBER_META_KEY];
        }

        if (!$packageNumber) {
            return;
        }

        $order->update_meta_data(Order::TRACKING_NUMBER_META_KEY, $packageNumber);

        self::storeTrackingSnapshot($order, [
            'current_status' => 'exported',
            'current_label' => __('Shipment exported to DPD', 'ar-design-dpd'),
            'current_description' => __('The shipment data was handed over to DPD and is now waiting for STATUSDATA updates.', 'ar-design-dpd'),
            'current_date' => current_time('mysql'),
            'current_location' => '',
            'events' => [
                [
                    'status' => 'exported',
                    'label' => __('Shipment exported to DPD', 'ar-design-dpd'),
                    'description' => __('The shipment data was handed over to DPD and is now waiting for STATUSDATA updates.', 'ar-design-dpd'),
                    'date' => current_time('mysql'),
                    'location' => '',
                    'current' => true,
                    'reached' => true,
                    'source' => 'export',
                ],
            ],
        ], false);

        $order->save_meta_data();

        if (self::isTrackingConfigured()) {
            wp_schedule_single_event(time() + (5 * MINUTE_IN_SECONDS), self::CRON_HOOK);
        }
    }

    public static function syncOpenShipments()
    {
        if (!self::isTrackingConfigured()) {
            return;
        }

        $settings = DpdExportSettings::getDefaultSettings();

        self::importStatusDataDirectory((string) $settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY]);
    }

    public static function importStatusDataDirectory(string $directory): array
    {
        $directory = trim($directory);
        $settings = DpdExportSettings::getDefaultSettings();
        $summary = [
            'files_found' => 0,
            'files_processed' => 0,
            'files_skipped' => 0,
            'orders_updated' => 0,
            'parcels_unmatched' => 0,
            'errors' => 0,
            'remote_files_found' => 0,
            'remote_files_downloaded' => 0,
            'remote_files_skipped' => 0,
            'remote_files_archived' => 0,
            'messages' => [],
        ];

        if (self::isStatusDataSftpConfigured($settings)) {
            $downloadSummary = self::downloadStatusDataFromSftp($settings, $directory);

            foreach (['remote_files_found', 'remote_files_downloaded', 'remote_files_skipped', 'remote_files_archived', 'errors'] as $key) {
                $summary[$key] += (int) ($downloadSummary[$key] ?? 0);
            }

            $summary['messages'] = array_merge($summary['messages'], (array) ($downloadSummary['messages'] ?? []));
        }

        if ($directory === '' || !is_dir($directory) || !is_readable($directory)) {
            ard_dpd_log('STATUSDATA directory is not readable', $directory);

            $summary['errors']++;
            $summary['messages'][] = __('STATUSDATA directory is not configured or not readable.', 'ar-design-dpd');

            return $summary;
        }

        $files = self::getStatusDataFiles($directory);
        $summary['files_found'] = count($files);
        if ($files === []) {
            $summary['messages'][] = __('No local STATUSDATA files were found in the configured directory.', 'ar-design-dpd');

            return $summary;
        }

        foreach ($files as $filePath) {
            try {
                if (!self::shouldImportStatusDataFile($filePath)) {
                    $summary['files_skipped']++;
                    continue;
                }

                $fileSummary = self::importStatusDataFile($filePath);
                self::markStatusDataFileProcessed($filePath);
                $summary['files_processed']++;
                $summary['orders_updated'] += (int) ($fileSummary['orders_updated'] ?? 0);
                $summary['parcels_unmatched'] += (int) ($fileSummary['parcels_unmatched'] ?? 0);
            } catch (\Throwable $exception) {
                ard_dpd_log('STATUSDATA import failed', [
                    'file' => $filePath,
                    'message' => $exception->getMessage(),
                ]);
                $summary['errors']++;
                $summary['messages'][] = sprintf(
                    /* translators: 1: STATUSDATA filename, 2: error message */
                    __('Import of file %1$s failed: %2$s', 'ar-design-dpd'),
                    basename($filePath),
                    $exception->getMessage()
                );
            }
        }

        return $summary;
    }

    private static function downloadStatusDataFromSftp(array $settings, string $localDirectory): array
    {
        $summary = [
            'remote_files_found' => 0,
            'remote_files_downloaded' => 0,
            'remote_files_skipped' => 0,
            'remote_files_archived' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        if (!function_exists('ssh2_connect') || !function_exists('ssh2_auth_password') || !function_exists('ssh2_sftp')) {
            ard_dpd_log('STATUSDATA SFTP download skipped because ssh2 extension is not available');
            $summary['errors']++;
            $summary['messages'][] = __('STATUSDATA SFTP download is configured, but the PHP ssh2 extension is not available on this server.', 'ar-design-dpd');

            return $summary;
        }

        if ($localDirectory === '') {
            ard_dpd_log('STATUSDATA SFTP download skipped because local directory is empty');
            $summary['errors']++;
            $summary['messages'][] = __('STATUSDATA SFTP download could not start because the local STATUSDATA directory is empty.', 'ar-design-dpd');

            return $summary;
        }

        if (!is_dir($localDirectory) && !wp_mkdir_p($localDirectory)) {
            ard_dpd_log('STATUSDATA SFTP download failed because local directory could not be created', $localDirectory);
            $summary['errors']++;
            $summary['messages'][] = __('The local STATUSDATA directory could not be created before SFTP download.', 'ar-design-dpd');

            return $summary;
        }

        if (!is_dir($localDirectory) || !is_writable($localDirectory)) {
            ard_dpd_log('STATUSDATA SFTP download failed because local directory is not writable', $localDirectory);
            $summary['errors']++;
            $summary['messages'][] = __('The local STATUSDATA directory is not writable, so downloaded SFTP files cannot be stored.', 'ar-design-dpd');

            return $summary;
        }

        $host = (string) ($settings[DpdExportSettings::STATUSDATA_SFTP_HOST_OPTION_KEY] ?? '');
        $port = (int) ($settings[DpdExportSettings::STATUSDATA_SFTP_PORT_OPTION_KEY] ?? 22);
        $username = (string) ($settings[DpdExportSettings::STATUSDATA_SFTP_USERNAME_OPTION_KEY] ?? '');
        $password = (string) ($settings[DpdExportSettings::STATUSDATA_SFTP_PASSWORD_OPTION_KEY] ?? '');
        $remoteDirectory = self::normalizeRemoteDirectory((string) ($settings[DpdExportSettings::STATUSDATA_SFTP_REMOTE_DIRECTORY_OPTION_KEY] ?? ''));
        $archiveDirectory = self::normalizeRemoteDirectory((string) ($settings[DpdExportSettings::STATUSDATA_SFTP_ARCHIVE_DIRECTORY_OPTION_KEY] ?? ''));

        $connection = @\ssh2_connect($host, $port > 0 ? $port : 22);
        if (!$connection) {
            ard_dpd_log('STATUSDATA SFTP connection failed', ['host' => $host, 'port' => $port]);
            $summary['errors']++;
            $summary['messages'][] = sprintf(
                /* translators: 1: SFTP host, 2: SFTP port */
                __('Could not connect to STATUSDATA SFTP %1$s:%2$d.', 'ar-design-dpd'),
                $host,
                $port > 0 ? $port : 22
            );

            return $summary;
        }

        if (!@\ssh2_auth_password($connection, $username, $password)) {
            ard_dpd_log('STATUSDATA SFTP authentication failed', ['host' => $host, 'username' => $username]);
            $summary['errors']++;
            $summary['messages'][] = __('STATUSDATA SFTP authentication failed. Check username and password.', 'ar-design-dpd');

            return $summary;
        }

        $sftp = @\ssh2_sftp($connection);
        if (!$sftp) {
            ard_dpd_log('STATUSDATA SFTP subsystem could not be initialized', ['host' => $host]);
            $summary['errors']++;
            $summary['messages'][] = __('STATUSDATA SFTP subsystem could not be initialized after login.', 'ar-design-dpd');

            return $summary;
        }

        $remoteFiles = self::listRemoteStatusDataFiles($sftp, $remoteDirectory);
        $summary['remote_files_found'] = count($remoteFiles);

        if ($remoteFiles === []) {
            $summary['messages'][] = __('No STATUSDATA files were found in the configured remote SFTP directory.', 'ar-design-dpd');
        }

        foreach ($remoteFiles as $remoteFile) {
            $downloadResult = self::downloadRemoteStatusDataFile($connection, $sftp, $remoteFile, $localDirectory, $archiveDirectory);

            $summary['remote_files_downloaded'] += !empty($downloadResult['downloaded']) ? 1 : 0;
            $summary['remote_files_skipped'] += !empty($downloadResult['skipped']) ? 1 : 0;
            $summary['remote_files_archived'] += !empty($downloadResult['archived']) ? 1 : 0;
            $summary['errors'] += (int) ($downloadResult['errors'] ?? 0);
        }

        return $summary;
    }

    private static function getStatusDataFiles(string $directory): array
    {
        $directory = untrailingslashit($directory);
        $items = glob($directory . DIRECTORY_SEPARATOR . '*');
        if (!is_array($items)) {
            return [];
        }

        $files = array_filter($items, static function ($filePath) {
            if (!is_string($filePath) || !is_file($filePath) || !is_readable($filePath)) {
                return false;
            }

            $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
            if (in_array($extension, ['pdf', 'xlsx', 'xls', 'sem'], true)) {
                return false;
            }

            return true;
        });

        usort($files, static function ($left, $right) {
            return strcmp((string) $left, (string) $right);
        });

        return array_values($files);
    }

    private static function listRemoteStatusDataFiles(mixed $sftp, string $remoteDirectory): array
    {
        $remoteUrl = self::buildSftpUrl($sftp, $remoteDirectory);
        $handle = @opendir($remoteUrl);

        if (!is_resource($handle)) {
            return [];
        }

        $files = [];
        while (($name = readdir($handle)) !== false) {
            if (!is_string($name) || $name === '.' || $name === '..') {
                continue;
            }

            $remotePath = self::joinRemotePath($remoteDirectory, $name);
            $stat = @stat(self::buildSftpUrl($sftp, $remotePath));

            if (!is_array($stat) || !self::isRemoteRegularFile($stat) || !self::isStatusDataFilename($name)) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'path' => $remotePath,
                'size' => isset($stat['size']) ? (int) $stat['size'] : 0,
                'mtime' => isset($stat['mtime']) ? (int) $stat['mtime'] : 0,
            ];
        }

        closedir($handle);

        usort($files, static function ($left, $right) {
            $leftName = (string) ($left['name'] ?? '');
            $rightName = (string) ($right['name'] ?? '');

            return strcmp($leftName, $rightName);
        });

        return $files;
    }

    private static function downloadRemoteStatusDataFile(mixed $connection, mixed $sftp, array $remoteFile, string $localDirectory, string $archiveDirectory): array
    {
        $result = [
            'downloaded' => false,
            'skipped' => false,
            'archived' => false,
            'errors' => 0,
        ];

        $remoteName = sanitize_file_name((string) ($remoteFile['name'] ?? ''));
        $remotePath = (string) ($remoteFile['path'] ?? '');
        $remoteSize = (int) ($remoteFile['size'] ?? 0);
        $remoteMtime = (int) ($remoteFile['mtime'] ?? 0);

        if ($remoteName === '' || $remotePath === '') {
            $result['errors']++;

            return $result;
        }

        if (self::wasRemoteFileAlreadyDownloaded($remoteName, $remoteSize, $remoteMtime)) {
            $result['skipped'] = true;

            return $result;
        }

        $localPath = trailingslashit($localDirectory) . $remoteName;
        if (is_file($localPath) && is_readable($localPath)) {
            $localSize = @filesize($localPath);

            if ($localSize !== false && (int) $localSize === $remoteSize && $remoteSize > 0) {
                self::markRemoteFileDownloaded($remoteName, $remoteSize, $remoteMtime);
                $result['skipped'] = true;

                return $result;
            }
        }

        $remoteUrl = self::buildSftpUrl($sftp, $remotePath);
        $input = @fopen($remoteUrl, 'rb');
        $output = @fopen($localPath, 'wb');

        if (!is_resource($input) || !is_resource($output)) {
            if (is_resource($input)) {
                fclose($input);
            }

            if (is_resource($output)) {
                fclose($output);
            }

            ard_dpd_log('STATUSDATA SFTP download failed to open streams', ['remote' => $remotePath, 'local' => $localPath]);
            $result['errors']++;

            return $result;
        }

        $copied = stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);

        if ($copied === false || $copied === 0) {
            @unlink($localPath);
            ard_dpd_log('STATUSDATA SFTP download produced empty file', ['remote' => $remotePath, 'local' => $localPath]);
            $result['errors']++;

            return $result;
        }

        if ($remoteMtime > 0) {
            @touch($localPath, $remoteMtime);
        }

        self::markRemoteFileDownloaded($remoteName, $remoteSize, $remoteMtime);
        $result['downloaded'] = true;

        if ($archiveDirectory !== '') {
            $result['archived'] = self::archiveRemoteStatusDataFile($connection, $sftp, $remotePath, $remoteName, $archiveDirectory);
        }

        return $result;
    }

    private static function archiveRemoteStatusDataFile(mixed $connection, mixed $sftp, string $remotePath, string $remoteName, string $archiveDirectory): bool
    {
        if (!function_exists('ssh2_sftp_mkdir')) {
            return false;
        }

        @\ssh2_sftp_mkdir($sftp, $archiveDirectory, 0775, true);

        $archivePath = self::joinRemotePath($archiveDirectory, $remoteName);
        $sourceUrl = self::buildSftpUrl($sftp, $remotePath);
        $targetUrl = self::buildSftpUrl($sftp, $archivePath);

        if (@rename($sourceUrl, $targetUrl)) {
            return true;
        }

        ard_dpd_log('STATUSDATA SFTP archive move failed', ['from' => $remotePath, 'to' => $archivePath]);

        return false;
    }

    private static function wasRemoteFileAlreadyDownloaded(string $name, int $size, int $mtime): bool
    {
        $signature = self::buildRemoteFileSignature($name, $size, $mtime);
        if ($signature === '') {
            return false;
        }

        $downloadedFiles = get_option(self::STATUSDATA_REMOTE_DOWNLOADS_OPTION_KEY, []);
        $downloadedFiles = is_array($downloadedFiles) ? $downloadedFiles : [];

        return in_array($signature, $downloadedFiles, true);
    }

    private static function markRemoteFileDownloaded(string $name, int $size, int $mtime): void
    {
        $signature = self::buildRemoteFileSignature($name, $size, $mtime);
        if ($signature === '') {
            return;
        }

        $downloadedFiles = get_option(self::STATUSDATA_REMOTE_DOWNLOADS_OPTION_KEY, []);
        $downloadedFiles = is_array($downloadedFiles) ? $downloadedFiles : [];
        $downloadedFiles[] = $signature;
        $downloadedFiles = array_values(array_unique(array_filter(array_map('sanitize_text_field', $downloadedFiles))));

        if (count($downloadedFiles) > 500) {
            $downloadedFiles = array_slice($downloadedFiles, -500);
        }

        update_option(self::STATUSDATA_REMOTE_DOWNLOADS_OPTION_KEY, $downloadedFiles, false);
    }

    private static function buildRemoteFileSignature(string $name, int $size, int $mtime): string
    {
        $name = sanitize_file_name($name);

        if ($name === '') {
            return '';
        }

        return sanitize_text_field(sprintf('%s|%d|%d', $name, $size, $mtime));
    }

    private static function buildSftpUrl(mixed $sftp, string $path): string
    {
        return sprintf('ssh2.sftp://%s%s', (string) intval($sftp), self::normalizeRemoteDirectory($path));
    }

    private static function normalizeRemoteDirectory(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            return '';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return untrailingslashit($path);
    }

    private static function joinRemotePath(string $directory, string $name): string
    {
        return untrailingslashit($directory) . '/' . ltrim($name, '/');
    }

    private static function isRemoteRegularFile(array $stat): bool
    {
        if (!isset($stat['mode'])) {
            return true;
        }

        return (((int) $stat['mode']) & 0170000) === 0100000;
    }

    private static function isStatusDataFilename(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($extension, ['pdf', 'xlsx', 'xls', 'sem'], true)) {
            return false;
        }

        if (stripos($name, 'STATUSDATA') !== false) {
            return true;
        }

        return in_array($extension, ['', 'csv', 'txt', 'dat'], true);
    }

    private static function shouldImportStatusDataFile(string $filePath): bool
    {
        $signature = self::getStatusDataFileSignature($filePath);
        if ($signature === '') {
            return false;
        }

        $processedFiles = get_option(self::STATUSDATA_PROCESSED_FILES_OPTION_KEY, []);
        $processedFiles = is_array($processedFiles) ? $processedFiles : [];

        return !in_array($signature, $processedFiles, true);
    }

    private static function markStatusDataFileProcessed(string $filePath): void
    {
        $signature = self::getStatusDataFileSignature($filePath);
        if ($signature === '') {
            return;
        }

        $processedFiles = get_option(self::STATUSDATA_PROCESSED_FILES_OPTION_KEY, []);
        $processedFiles = is_array($processedFiles) ? $processedFiles : [];
        $processedFiles[] = $signature;
        $processedFiles = array_values(array_unique(array_filter(array_map('sanitize_text_field', $processedFiles))));

        if (count($processedFiles) > 200) {
            $processedFiles = array_slice($processedFiles, -200);
        }

        update_option(self::STATUSDATA_PROCESSED_FILES_OPTION_KEY, $processedFiles, false);
    }

    private static function getStatusDataFileSignature(string $filePath): string
    {
        if (!is_file($filePath)) {
            return '';
        }

        $basename = wp_basename($filePath);
        $size = @filesize($filePath);
        $mtime = @filemtime($filePath);

        return sanitize_text_field(sprintf('%s|%s|%s', $basename, (string) $size, (string) $mtime));
    }

    private static function importStatusDataFile(string $filePath): array
    {
        $summary = [
            'orders_updated' => 0,
            'parcels_unmatched' => 0,
        ];

        $rows = self::parseStatusDataFile($filePath);
        if ($rows === []) {
            return $summary;
        }

        $rowsByParcel = [];
        foreach ($rows as $row) {
            $parcelNumber = (string) ($row['PARCELNO'] ?? '');
            if ($parcelNumber === '') {
                continue;
            }

            if (!isset($rowsByParcel[$parcelNumber])) {
                $rowsByParcel[$parcelNumber] = [];
            }

            $rowsByParcel[$parcelNumber][] = $row;
        }

        foreach ($rowsByParcel as $parcelNumber => $parcelRows) {
            $order = self::findOrderByTrackingNumber($parcelNumber);
            if (!$order instanceof WC_Order) {
                ard_dpd_log('STATUSDATA parcel not matched to order', [
                    'parcel_number' => $parcelNumber,
                    'file' => $filePath,
                ]);

                $summary['parcels_unmatched']++;

                continue;
            }

            $trackingData = self::buildTrackingDataFromStatusDataRows($order, $parcelRows, $filePath);
            if ($trackingData === []) {
                continue;
            }

            self::storeTrackingSnapshot($order, $trackingData);
            $order->save_meta_data();
            $summary['orders_updated']++;
        }

        return $summary;
    }

    private static function parseStatusDataFile(string $filePath): array
    {
        $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || $lines === []) {
            return [];
        }

        $header = self::trimStatusDataColumns(str_getcsv((string) array_shift($lines), ';'));
        if ($header === [] || !in_array('PARCELNO', $header, true) || !in_array('SCAN_CODE', $header, true)) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            $columns = self::trimStatusDataColumns(str_getcsv((string) $line, ';'));
            if ($columns === []) {
                continue;
            }

            if (count($columns) < count($header)) {
                $columns = array_pad($columns, count($header), '');
            }

            $row = array_combine($header, array_slice($columns, 0, count($header)));
            if (!is_array($row)) {
                continue;
            }

            $rows[] = array_map(static function ($value) {
                return is_string($value) ? trim($value) : $value;
            }, $row);
        }

        return $rows;
    }

    private static function trimStatusDataColumns(array $columns): array
    {
        while ($columns !== [] && end($columns) === '') {
            array_pop($columns);
        }

        return $columns;
    }

    private static function findOrderByTrackingNumber(string $trackingNumber): ?WC_Order
    {
        static $orderCache = [];

        $trackingNumber = sanitize_text_field($trackingNumber);
        if ($trackingNumber === '') {
            return null;
        }

        if (array_key_exists($trackingNumber, $orderCache)) {
            return $orderCache[$trackingNumber] instanceof WC_Order ? $orderCache[$trackingNumber] : null;
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'return' => 'objects',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => Order::EXPORT_PACKAGE_NUMBER_META_KEY,
                    'value' => $trackingNumber,
                ],
                [
                    'key' => Order::TRACKING_NUMBER_META_KEY,
                    'value' => $trackingNumber,
                ],
                [
                    'key' => Shipment::PRIMARY_TRACKING_NUMBER_META_KEY,
                    'value' => $trackingNumber,
                ],
            ],
        ]);

        $order = isset($orders[0]) && $orders[0] instanceof WC_Order ? $orders[0] : null;
        $orderCache[$trackingNumber] = $order;

        return $order;
    }

    private static function buildTrackingDataFromStatusDataRows(WC_Order $order, array $rows, string $filePath): array
    {
        usort($rows, static function ($left, $right) {
            return strcmp((string) ($left['EVENT_DATE_TIME'] ?? ''), (string) ($right['EVENT_DATE_TIME'] ?? ''));
        });

        $events = [];
        $currentEvent = [];
        $latestRow = [];
        $totalRows = count($rows);

        foreach ($rows as $index => $row) {
            $normalizedEvent = self::normalizeStatusDataRowToEvent($row, $index === ($totalRows - 1));
            if ($normalizedEvent === []) {
                continue;
            }

            $events[] = $normalizedEvent;

            if (!empty($normalizedEvent['current'])) {
                $currentEvent = $normalizedEvent;
                $latestRow = $row;
            }
        }

        if ($events === [] || $currentEvent === []) {
            return [];
        }

        return [
            'current_status' => (string) ($currentEvent['status'] ?? ''),
            'current_label' => (string) ($currentEvent['label'] ?? ''),
            'current_description' => (string) ($currentEvent['description'] ?? ''),
            'current_date' => (string) ($currentEvent['date'] ?? current_time('mysql')),
            'current_location' => (string) ($currentEvent['location'] ?? ''),
            'events' => $events,
            'source' => 'statusdata',
            'file' => wp_basename($filePath),
            'tracking_number' => (string) ($latestRow['PARCELNO'] ?? ''),
            'latest_scan_code' => (string) ($latestRow['SCAN_CODE'] ?? ''),
            'latest_service_code' => (string) ($latestRow['SERVICE'] ?? ''),
            'latest_additional_codes' => array_values(array_filter([
                (string) ($latestRow['ADD_SERVICE_1'] ?? ''),
                (string) ($latestRow['ADD_SERVICE_2'] ?? ''),
                (string) ($latestRow['ADD_SERVICE_3'] ?? ''),
            ])),
            'latest_raw_record' => $latestRow,
            'existing_summary' => self::getCurrentTrackingSummary($order),
        ];
    }

    private static function normalizeStatusDataRowToEvent(array $row, bool $isCurrent = false): array
    {
        $scanCode = sanitize_text_field((string) ($row['SCAN_CODE'] ?? ''));
        $parcelNumber = sanitize_text_field((string) ($row['PARCELNO'] ?? ''));

        if ($scanCode === '' || $parcelNumber === '') {
            return [];
        }

        $mappedStatus = self::mapStatusDataStatus($row);
        $eventDate = self::normalizeStatusDataDate((string) ($row['EVENT_DATE_TIME'] ?? ''));
        $location = sanitize_text_field((string) ($row['LOCATION'] ?: ($row['DEPOTNAME'] ?? '')));

        return [
            'status' => (string) ($mappedStatus['status'] ?? ('scan_' . $scanCode)),
            'label' => (string) ($mappedStatus['label'] ?? self::getStatusDataScanLabel($scanCode)),
            'description' => (string) ($mappedStatus['description'] ?? ''),
            'date' => $eventDate ?: current_time('mysql'),
            'location' => $location,
            'current' => $isCurrent,
            'reached' => true,
            'source' => 'statusdata',
            'raw_scan_code' => $scanCode,
            'raw_service_code' => sanitize_text_field((string) ($row['SERVICE'] ?? '')),
            'raw_tracking_number' => $parcelNumber,
        ];
    }

    private static function mapStatusDataStatus(array $row): array
    {
        $scanCode = sanitize_text_field((string) ($row['SCAN_CODE'] ?? ''));
        $serviceCode = sanitize_text_field((string) ($row['SERVICE'] ?? ''));
        $additionalCodes = array_values(array_filter([
            sanitize_text_field((string) ($row['ADD_SERVICE_1'] ?? '')),
            sanitize_text_field((string) ($row['ADD_SERVICE_2'] ?? '')),
            sanitize_text_field((string) ($row['ADD_SERVICE_3'] ?? '')),
        ]));
        $infoText = sanitize_text_field((string) ($row['INFO_TEXT'] ?? ''));
        $scanLabel = self::getStatusDataScanLabel($scanCode);

        if (in_array($serviceCode, ['298', '300'], true)) {
            return [
                'status' => 'returning_to_sender',
                'label' => __('Returning to sender', 'ar-design-dpd'),
                'description' => self::buildStatusDataDescription($scanLabel, $additionalCodes, $infoText, $serviceCode),
            ];
        }

        switch ($scanCode) {
            case '05':
            case '15':
                $status = 'picked_up';
                $label = __('Picked up by courier', 'ar-design-dpd');
                break;
            case '02':
            case '10':
            case '17':
            case '20':
                $status = 'in_transit';
                $label = __('In transit', 'ar-design-dpd');
                break;
            case '03':
            case '09':
                $status = 'out_for_delivery';
                $label = __('Out for delivery', 'ar-design-dpd');
                break;
            case '13':
                $status = 'delivered';
                $label = __('Delivered', 'ar-design-dpd');
                break;
            case '14':
                $status = 'delivery_attempt_failed';
                $label = __('Delivery attempt unsuccessful', 'ar-design-dpd');
                break;
            case '23':
                $status = 'ready_for_pickup';
                $label = __('Ready for pickup point collection', 'ar-design-dpd');
                break;
            case '06':
                $status = 'returning_to_sender';
                $label = __('Returning to sender', 'ar-design-dpd');
                break;
            case '04':
                $status = 'courier_returned';
                $label = __('Returned by courier', 'ar-design-dpd');
                break;
            case '12':
                $status = 'customs_clearance';
                $label = __('Customs clearance', 'ar-design-dpd');
                break;
            case '18':
                $status = 'additional_information';
                $label = __('Additional shipment information', 'ar-design-dpd');
                break;
            default:
                $status = 'scan_' . $scanCode;
                $label = $scanLabel;
                break;
        }

        return [
            'status' => $status,
            'label' => $label,
            'description' => self::buildStatusDataDescription($scanLabel, $additionalCodes, $infoText, $serviceCode),
        ];
    }

    private static function buildStatusDataDescription(string $scanLabel, array $additionalCodes, string $infoText, string $serviceCode): string
    {
        $parts = [];
        if ($scanLabel !== '') {
            $parts[] = $scanLabel;
        }

        $serviceLabel = self::getStatusDataServiceLabel($serviceCode);
        if ($serviceLabel !== '') {
            $parts[] = sprintf(
                /* translators: %s: DPD service label. */
                __('Service: %s', 'ar-design-dpd'),
                $serviceLabel
            );
        }

        $additionalLabels = [];
        foreach ($additionalCodes as $code) {
            $label = self::getStatusDataAdditionalCodeLabel($code);
            $additionalLabels[] = $label !== '' ? sprintf('%s (%s)', $label, $code) : $code;
        }

        if ($additionalLabels !== []) {
            $parts[] = sprintf(
                /* translators: %s: comma-separated DPD additional service codes. */
                __('Additional codes: %s', 'ar-design-dpd'),
                implode(', ', $additionalLabels)
            );
        }

        if ($infoText !== '') {
            $parts[] = $infoText;
        }

        return sanitize_text_field(implode(' | ', array_filter($parts)));
    }

    private static function normalizeStatusDataDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('YmdHis', $value);
        if ($date instanceof \DateTimeImmutable) {
            return $date->format('Y-m-d H:i:s');
        }

        return self::normalizeLooseDate($value);
    }

    private static function normalizeLooseDate(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : sanitize_text_field($value);
    }

    private static function getStatusDataScanLabel(string $scanCode): string
    {
        $labels = [
            '01' => __('Consolidation', 'ar-design-dpd'),
            '02' => __('Inbound', 'ar-design-dpd'),
            '03' => __('Out for delivery', 'ar-design-dpd'),
            '04' => __('Driver return', 'ar-design-dpd'),
            '05' => __('Pick-up', 'ar-design-dpd'),
            '06' => __('System return', 'ar-design-dpd'),
            '08' => __('Warehouse', 'ar-design-dpd'),
            '09' => __('Again out for delivery', 'ar-design-dpd'),
            '10' => __('HUB scan', 'ar-design-dpd'),
            '12' => __('Customs clearance process', 'ar-design-dpd'),
            '13' => __('Delivered', 'ar-design-dpd'),
            '14' => __('Delivery attempt not successful', 'ar-design-dpd'),
            '15' => __('Driver pick-up', 'ar-design-dpd'),
            '17' => __('Export / Import cleared', 'ar-design-dpd'),
            '18' => __('Additional information', 'ar-design-dpd'),
            '20' => __('Loading', 'ar-design-dpd'),
            '23' => __('Arrived at pickup point', 'ar-design-dpd'),
            'SO' => __('Sorter pass-through', 'ar-design-dpd'),
        ];

        return isset($labels[$scanCode]) ? (string) $labels[$scanCode] : sprintf(
            /* translators: %s: raw DPD STATUSDATA scan code. */
            __('Scan %s', 'ar-design-dpd'),
            $scanCode
        );
    }

    private static function getStatusDataServiceLabel(string $serviceCode): string
    {
        $labels = [
            '298' => __('System return to sender', 'ar-design-dpd'),
            '300' => __('System return to sending depot', 'ar-design-dpd'),
            '327' => __('Normal parcel B2C', 'ar-design-dpd'),
            '328' => __('Small parcel B2C', 'ar-design-dpd'),
        ];

        return isset($labels[$serviceCode]) ? (string) $labels[$serviceCode] : '';
    }

    private static function getStatusDataAdditionalCodeLabel(string $code): string
    {
        $labels = [
            '011' => __('Wrong address', 'ar-design-dpd'),
            '015' => __('Refused by consignee', 'ar-design-dpd'),
            '019' => __('Consignee not present - first notification', 'ar-design-dpd'),
            '025' => __('Closed', 'ar-design-dpd'),
            '030' => __('Consignee on holiday', 'ar-design-dpd'),
            '050' => __('Access code required', 'ar-design-dpd'),
            '061' => __('Delay due to unknown reason', 'ar-design-dpd'),
            '072' => __('Parcel not ready for shipment', 'ar-design-dpd'),
            '080' => __('Appointment scheduled', 'ar-design-dpd'),
            '083' => __('Address changed', 'ar-design-dpd'),
            '095' => __('File import', 'ar-design-dpd'),
            '099' => __('Canceled', 'ar-design-dpd'),
        ];

        $normalizedCode = str_pad(preg_replace('/\D+/', '', $code), 3, '0', STR_PAD_LEFT);

        return isset($labels[$normalizedCode]) ? (string) $labels[$normalizedCode] : '';
    }

    public static function storeTrackingSnapshot(WC_Order $order, array $trackingData, bool $allowDeliveryHooks = true): void
    {
        $previousStatus = (string) $order->get_meta(self::CURRENT_STATUS_META_KEY, true);
        $currentStatus = (string) ($trackingData['current_status'] ?? '');
        $currentStatusCode = (string) ($trackingData['latest_scan_code'] ?? '');
        $currentServiceCode = (string) ($trackingData['latest_service_code'] ?? '');
        $currentLabel = (string) ($trackingData['current_label'] ?? $currentStatus);
        $currentDescription = (string) ($trackingData['current_description'] ?? '');
        $currentDate = (string) ($trackingData['current_date'] ?? current_time('mysql'));
        $currentLocation = (string) ($trackingData['current_location'] ?? '');
        $events = isset($trackingData['events']) && is_array($trackingData['events']) ? $trackingData['events'] : [];

        $order->update_meta_data(self::CURRENT_STATUS_META_KEY, $currentStatus);
        $order->update_meta_data(self::CURRENT_STATUS_CODE_META_KEY, $currentStatusCode);
        $order->update_meta_data(self::CURRENT_SERVICE_CODE_META_KEY, $currentServiceCode);
        $order->update_meta_data(self::CURRENT_STATUS_LABEL_META_KEY, $currentLabel);
        $order->update_meta_data(self::CURRENT_STATUS_DESCRIPTION_META_KEY, $currentDescription);
        $order->update_meta_data(self::CURRENT_STATUS_DATE_META_KEY, $currentDate);
        $order->update_meta_data(self::CURRENT_STATUS_LOCATION_META_KEY, $currentLocation);
        Shipment::storeTimestampMeta($order, self::LAST_SYNC_AT_META_KEY, self::LAST_SYNC_AT_GMT_META_KEY);
        $order->delete_meta_data(self::LAST_SYNC_ERROR_META_KEY);
        $order->update_meta_data(self::STATUS_HISTORY_META_KEY, self::mergeTrackingHistory($order, $events));

        self::syncNormalizedShipmentTracking($order, $trackingData);

        if ($currentStatus && $currentStatus !== $previousStatus) {
            $order->add_order_note(sprintf(
                /* translators: 1: current tracking status label, 2: optional location in parentheses. */
                __('DPD tracking update: %1$s%2$s', 'ar-design-dpd'),
                $currentLabel ?: $currentStatus,
                $currentLocation ? ' (' . $currentLocation . ')' : ''
            ));
        }

        if ($allowDeliveryHooks) {
            OrderWorkflow::handleTrackingUpdate($order, $trackingData);
        }

        if ($allowDeliveryHooks && self::isDeliveredStatus($currentStatus, $currentLabel, $currentDescription)) {
            self::handleDeliveredShipment($order, $trackingData);
        }
    }

    public static function mergeTrackingHistory(WC_Order $order, array $events): array
    {
        $history = $order->get_meta(self::STATUS_HISTORY_META_KEY, true);
        $history = is_array($history) ? $history : [];
        $historyIndex = [];

        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }

            $historyIndex[self::getHistoryHash($item)] = $item;
        }

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $normalizedEvent = [
                'status' => (string) ($event['status'] ?? ''),
                'status_code' => (string) ($event['raw_scan_code'] ?? ''),
                'service_code' => (string) ($event['raw_service_code'] ?? ''),
                'label' => (string) ($event['label'] ?? ''),
                'description' => (string) ($event['description'] ?? ''),
                'date' => (string) ($event['date'] ?? current_time('mysql')),
                'location' => (string) ($event['location'] ?? ''),
                'current' => !empty($event['current']),
                'reached' => !empty($event['reached']),
                'source' => (string) ($event['source'] ?? 'tracking'),
            ];

            $historyIndex[self::getHistoryHash($normalizedEvent)] = $normalizedEvent;
        }

        $history = array_values($historyIndex);

        usort($history, static function ($left, $right) {
            return strcmp((string) ($left['date'] ?? ''), (string) ($right['date'] ?? ''));
        });

        return $history;
    }

    public static function getHistoryHash(array $event): string
    {
        return md5(wp_json_encode([
            (string) ($event['status'] ?? ''),
            (string) ($event['label'] ?? ''),
            (string) ($event['description'] ?? ''),
            (string) ($event['date'] ?? ''),
            (string) ($event['location'] ?? ''),
        ]));
    }

    public static function handleDeliveredShipment(WC_Order $order, array $trackingData = []): void
    {
        if ($order->get_meta(self::DELIVERY_CONFIRMED_AT_META_KEY, true)) {
            return;
        }

        Shipment::storeTimestampMeta($order, self::DELIVERY_CONFIRMED_AT_META_KEY, self::DELIVERY_CONFIRMED_AT_GMT_META_KEY);

        $shipmentData = Shipment::markDelivered($order, self::buildShipmentDataFromTracking($order, $trackingData, true));

        $settings = DpdExportSettings::getDefaultSettings();
        if (!empty($settings[DpdExportSettings::TRACKING_AUTO_COMPLETE_ORDER_OPTION_KEY]) && $settings[DpdExportSettings::TRACKING_AUTO_COMPLETE_ORDER_OPTION_KEY] === 'yes' && !$order->has_status(['completed', 'cancelled', 'refunded'])) {
            $order->update_status('completed', __('DPD delivery status confirmed successful delivery.', 'ar-design-dpd'));
        } else {
            $order->add_order_note(__('DPD delivery status confirmed successful delivery.', 'ar-design-dpd'));
        }

        do_action('ard_dpd_shipment_delivered_notification', $order->get_id(), $trackingData, $shipmentData, $order);
    }

    public static function syncNormalizedShipmentTracking(WC_Order $order, array $trackingData = []): void
    {
        $shipmentData = self::buildShipmentDataFromTracking($order, $trackingData);

        if (empty($shipmentData['status']) && empty($shipmentData['tracking_number'])) {
            return;
        }

        Shipment::storeShipmentData($order, $shipmentData);
    }

    public static function buildShipmentDataFromTracking(WC_Order $order, array $trackingData = [], bool $isDelivered = false): array
    {
        $existingShipment = Shipment::getShipmentData($order);
        $trackingNumber = $existingShipment['tracking_number'] ?: (string) $order->get_meta(Order::EXPORT_PACKAGE_NUMBER_META_KEY, true);
        $currentStatus = (string) ($trackingData['current_status'] ?? $existingShipment['status']);
        $currentLabel = (string) ($trackingData['current_label'] ?? $existingShipment['status_label']);

        return array_merge($existingShipment, [
            'carrier' => Shipment::CARRIER,
            'reference' => $existingShipment['reference'] ?: (string) $order->get_meta(Order::EXPORT_MPSID_META_KEY, true),
            'tracking_number' => $trackingNumber,
            'tracking_numbers' => $trackingNumber ? [$trackingNumber] : (array) ($existingShipment['tracking_numbers'] ?? []),
            'tracking_url' => Shipment::buildTrackingUrl($trackingNumber, $order),
            'label_url' => $existingShipment['label_url'] ?: (string) $order->get_meta(Order::EXPORT_LABEL_URL_META_KEY, true),
            'status' => $isDelivered ? 'delivered' : $currentStatus,
            'status_label' => $isDelivered ? __('Shipment delivered', 'ar-design-dpd') : ($currentLabel ?: $currentStatus),
            'updated_at' => Shipment::currentGmtMysql(),
            'payload' => $trackingData ?: (array) ($existingShipment['payload'] ?? []),
        ]);
    }

    public static function isDeliveredStatus(string $status = '', string $label = '', string $description = ''): bool
    {
        $normalizedStatus = self::normalizeStatusText($status);
        if (in_array($normalizedStatus, ['ready_for_pickup', 'pickup_point_ready'], true)) {
            return false;
        }

        $haystack = self::normalizeStatusText($status . ' ' . $label . ' ' . $description);

        foreach (['delivered', 'dorucen', 'doručen', 'successful delivery'] as $needle) {
            if (str_contains($haystack, self::normalizeStatusText($needle))) {
                return true;
            }
        }

        return false;
    }

    public static function isTerminalStatus(string $status = ''): bool
    {
        $normalizedStatus = self::normalizeStatusText($status);

        foreach (['delivered', 'dorucen', 'doručen', 'returned', 'return', 'canceled', 'cancelled', 'storno'] as $needle) {
            if (str_contains($normalizedStatus, self::normalizeStatusText($needle))) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeStatusText(string $value): string
    {
        $value = remove_accents(wp_strip_all_tags($value));
        $value = strtolower($value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    public static function getCurrentTrackingSummary(WC_Order $order): array
    {
        return [
            'status' => (string) $order->get_meta(self::CURRENT_STATUS_META_KEY, true),
            'label' => (string) $order->get_meta(self::CURRENT_STATUS_LABEL_META_KEY, true),
            'description' => (string) $order->get_meta(self::CURRENT_STATUS_DESCRIPTION_META_KEY, true),
            'date' => (string) $order->get_meta(self::CURRENT_STATUS_DATE_META_KEY, true),
            'location' => (string) $order->get_meta(self::CURRENT_STATUS_LOCATION_META_KEY, true),
            'last_sync_at' => Shipment::getPreferredTimestamp($order, self::LAST_SYNC_AT_GMT_META_KEY, self::LAST_SYNC_AT_META_KEY),
            'last_error' => (string) $order->get_meta(self::LAST_SYNC_ERROR_META_KEY, true),
            'history' => (array) $order->get_meta(self::STATUS_HISTORY_META_KEY, true),
        ];
    }

    /**
     * @return array{
     *   tracking_enabled: bool,
     *   cron_scheduled: bool,
     *   sync_running: bool,
     *   directory: string,
     *   statusdata_files_exist: bool,
     *   local_file_count: int,
     *   tracking_number: string,
     *   matching_rows_found: bool,
     *   matching_row_count: int,
     *   matching_files: array<int, string>
     * }
     */
    public static function getStatusDataLookupDiagnostics(WC_Order $order, ?array $settings = null): array
    {
        $settings = is_array($settings) ? $settings : DpdExportSettings::getDefaultSettings();
        $directory = isset($settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY])
            ? trim((string) $settings[DpdExportSettings::STATUSDATA_DIRECTORY_OPTION_KEY])
            : '';
        $trackingNumber = self::getOrderTrackingNumber($order);
        $trackingEnabled = self::isTrackingEnabled();
        $cronScheduled = (bool) wp_next_scheduled(self::CRON_HOOK);
        $files = ($directory !== '' && is_dir($directory) && is_readable($directory))
            ? self::getStatusDataFiles($directory)
            : [];

        $diagnostics = [
            'tracking_enabled' => $trackingEnabled,
            'cron_scheduled' => $cronScheduled,
            'sync_running' => $trackingEnabled && $cronScheduled,
            'directory' => $directory,
            'statusdata_files_exist' => $files !== [],
            'local_file_count' => count($files),
            'tracking_number' => $trackingNumber,
            'matching_rows_found' => false,
            'matching_row_count' => 0,
            'matching_files' => [],
        ];

        if ($trackingNumber === '' || $files === []) {
            return $diagnostics;
        }

        static $lookupCache = [];
        $cacheKey = md5($directory . '|' . $trackingNumber);

        if (isset($lookupCache[$cacheKey]) && is_array($lookupCache[$cacheKey])) {
            return array_merge($diagnostics, $lookupCache[$cacheKey]);
        }

        $matchingFiles = [];
        $matchingRowCount = 0;

        foreach ($files as $filePath) {
            $rows = self::parseStatusDataFile($filePath);
            if ($rows === []) {
                continue;
            }

            $fileMatched = false;

            foreach ($rows as $row) {
                if ((string) ($row['PARCELNO'] ?? '') !== $trackingNumber) {
                    continue;
                }

                $matchingRowCount++;
                $fileMatched = true;
            }

            if ($fileMatched) {
                $matchingFiles[] = wp_basename($filePath);
            }
        }

        $lookupCache[$cacheKey] = [
            'matching_rows_found' => $matchingRowCount > 0,
            'matching_row_count' => $matchingRowCount,
            'matching_files' => array_values(array_unique($matchingFiles)),
        ];

        return array_merge($diagnostics, $lookupCache[$cacheKey]);
    }

    private static function getOrderTrackingNumber(WC_Order $order): string
    {
        foreach ([
            Order::EXPORT_PACKAGE_NUMBER_META_KEY,
            Order::TRACKING_NUMBER_META_KEY,
            Shipment::PRIMARY_TRACKING_NUMBER_META_KEY,
        ] as $metaKey) {
            $value = trim((string) $order->get_meta($metaKey, true));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array{
     *   carrier: string,
     *   carrier_label: string,
     *   label_url: string,
     *   tracking_links: array<int, array{number: string, url: string}>,
    *   carrier_cost_value: string,
    *   carrier_cost_placeholder: string,
     *   status: string,
     *   status_label: string,
     *   status_description: string,
     *   status_date: string,
     *   status_location: string,
     *   last_sync_at: string,
     *   last_error: string
     * }
     */
    private static function buildShipmentSummaryViewModel(WC_Order $order): array
    {
        $shipment = Shipment::getShipmentData($order);
        $summary = self::getCurrentTrackingSummary($order);
        $carrier = sanitize_key((string) ($shipment['carrier'] ?? ''));

        if ($carrier !== '' && $carrier !== Shipment::CARRIER) {
            return [];
        }

        $trackingNumbers = isset($shipment['tracking_numbers']) && is_array($shipment['tracking_numbers'])
            ? array_values(array_filter(array_map('strval', $shipment['tracking_numbers'])))
            : [];

        $primaryTrackingNumber = trim((string) ($shipment['tracking_number'] ?? ''));
        if ($primaryTrackingNumber !== '' && !in_array($primaryTrackingNumber, $trackingNumbers, true)) {
            array_unshift($trackingNumbers, $primaryTrackingNumber);
        }

        $labelUrl = self::buildUnifiedLabelUrl($carrier, $order, $shipment);
        $trackingLinks = [];
        $carrierCostMeta = self::extractCarrierCostMeta($carrier, $shipment);

        foreach ($trackingNumbers as $trackingNumber) {
            $trackingNumber = trim((string) $trackingNumber);
            if ($trackingNumber === '') {
                continue;
            }

            $trackingLinks[] = [
                'number' => $trackingNumber,
                'url' => self::buildUnifiedTrackingUrl($carrier, $trackingNumber, $order, $shipment),
            ];
        }

        $payloadStatusMeta = self::extractPayloadStatusMeta(is_array($shipment['payload'] ?? null) ? $shipment['payload'] : []);
        $status = trim((string) ($summary['status'] ?: ($shipment['status'] ?? '')));
        $statusLabel = trim((string) ($summary['label'] ?: ($shipment['status_label'] ?? $status)));
        $statusDescription = trim((string) ($summary['description'] ?: ($payloadStatusMeta['description'] ?? '')));
        $statusDate = trim((string) ($summary['date'] ?: ($payloadStatusMeta['date'] ?? '')));
        $statusLocation = trim((string) ($summary['location'] ?: ($payloadStatusMeta['location'] ?? '')));
        $lastSyncAt = trim((string) ($summary['last_sync_at'] ?? ''));
        $lastError = trim((string) ($summary['last_error'] ?? ''));

        if ($carrier === '' && $labelUrl === '' && $trackingLinks === [] && $statusLabel === '' && $status === '') {
            return [];
        }

        return [
            'carrier' => $carrier,
            'carrier_label' => self::getShipmentCarrierLabel($carrier),
            'label_url' => $labelUrl,
            'tracking_links' => $trackingLinks,
            'carrier_cost_value' => $carrierCostMeta['value'],
            'carrier_cost_placeholder' => $carrierCostMeta['placeholder'],
            'status' => $status,
            'status_label' => $statusLabel,
            'status_description' => $statusDescription,
            'status_date' => $statusDate,
            'status_location' => $statusLocation,
            'last_sync_at' => $lastSyncAt,
            'last_error' => $lastError,
        ];
    }

    private static function buildUnifiedTrackingUrl(string $carrier, string $trackingNumber, WC_Order $order, array $shipment): string
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            return '';
        }

        $primaryTrackingNumber = trim((string) ($shipment['tracking_number'] ?? ''));
        $storedTrackingUrl = esc_url_raw((string) ($shipment['tracking_url'] ?? ''));

        if ($storedTrackingUrl !== '' && ($primaryTrackingNumber === '' || $primaryTrackingNumber === $trackingNumber)) {
            return $storedTrackingUrl;
        }

        return esc_url_raw(Shipment::buildTrackingUrl($trackingNumber, $order));
    }

    private static function buildUnifiedLabelUrl(string $carrier, WC_Order $order, array $shipment): string
    {
        return esc_url_raw((string) ($shipment['label_url'] ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{description: string, date: string, location: string}
     */
    private static function extractPayloadStatusMeta(array $payload): array
    {
        $meta = [
            'description' => '',
            'date' => '',
            'location' => '',
        ];

        if (isset($payload['events']) && is_array($payload['events'])) {
            $currentEvent = [];

            foreach ($payload['events'] as $event) {
                if (!is_array($event)) {
                    continue;
                }

                $currentEvent = $event;
                if (!empty($event['current'])) {
                    break;
                }
            }

            if ($currentEvent !== []) {
                $meta['description'] = sanitize_text_field((string) ($currentEvent['description'] ?? ''));
                $meta['date'] = sanitize_text_field((string) ($currentEvent['date'] ?? ''));
                $meta['location'] = sanitize_text_field((string) ($currentEvent['location'] ?? ''));

                return $meta;
            }
        }

        $meta['description'] = sanitize_text_field((string) ($payload['StatusInfo'] ?? ''));
        $meta['location'] = sanitize_text_field((string) ($payload['DepotCity'] ?? ''));

        return $meta;
    }

    /**
     * @param array<string, mixed> $shipment
     * @return array{value: string, placeholder: string}
     */
    private static function extractCarrierCostMeta(string $carrier, array $shipment): array
    {
        $payload = isset($shipment['payload']) && is_array($shipment['payload'])
            ? $shipment['payload']
            : [];

        $amount = '';
        foreach (['carrier_cost_value', 'carrier_cost_amount', 'carrier_cost'] as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            $amount = trim((string) $payload[$key]);
            if ($amount !== '') {
                break;
            }
        }

        $currency = '';
        foreach (['carrier_cost_currency', 'currency'] as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            $currency = strtoupper(trim((string) $payload[$key]));
            if ($currency !== '') {
                break;
            }
        }

        $value = trim($amount . ($currency !== '' ? ' ' . $currency : ''));

        return [
            'value' => sanitize_text_field($value),
            'placeholder' => '',
        ];
    }

    private static function getShipmentCarrierLabel(string $carrier): string
    {
        return 'DPD';
    }

    public static function getTrackingSummaryHtml(WC_Order $order, string $type = 'customer'): string
    {
        $view = self::buildShipmentSummaryViewModel($order);
        if ($view === []) {
            return '';
        }

        $html = ard_dpd_include_template('shipment-tracking-summary.php', [
            'type' => $type,
            'view' => $view,
            'order' => $order,
        ]);

        return is_string($html) ? $html : '';
    }

    public static function displayTrackingInfo(WC_Order $order): void
    {
        echo self::getTrackingSummaryHtml($order, 'customer');
    }

    public static function displayAdminTrackingInfo(WC_Order $order): void
    {
        echo self::getTrackingSummaryHtml($order, 'admin');
    }
}
