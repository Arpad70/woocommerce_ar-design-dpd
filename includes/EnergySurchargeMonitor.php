<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

class EnergySurchargeMonitor
{
    private const CRON_HOOK = 'ard_dpd_energy_surcharge_sync_event';
    private const CRON_SCHEDULE = 'ard_weekly';
    private const MANUAL_SYNC_ACTION = 'ard_dpd_energy_surcharge_sync_now';
    private const MANUAL_SYNC_NONCE = 'ard_dpd_energy_surcharge_sync_now_nonce';
    private const STATE_OPTION_KEY = 'ar_design_dpd_energy_surcharge_monitor_state';
    private const SOURCE_URL = 'https://www.dpd.com/sk/sk/cennik-dpd/';

    public static function init(): void
    {
        add_filter('cron_schedules', [__CLASS__, 'registerCronSchedule']);
        add_action(self::CRON_HOOK, [__CLASS__, 'runSync']);
        add_action('admin_post_' . self::MANUAL_SYNC_ACTION, [__CLASS__, 'handleManualSyncRequest']);
        add_action('init', [__CLASS__, 'ensureCronScheduled']);
        add_action('admin_notices', [__CLASS__, 'renderAdminNotice']);
    }

    public static function handleManualSyncRequest(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Nedostatečné oprávnění.', 'ar-design-dpd'));
        }

        check_admin_referer(self::MANUAL_SYNC_ACTION, self::MANUAL_SYNC_NONCE);

        self::runSync();

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('admin.php?page=wc-settings&tab=shipping&section=' . DpdExportSettings::SETTINGS_ID_KEY);
        }

        wp_safe_redirect(add_query_arg('ard_dpd_manual_sync', '1', $redirect));
        exit;
    }

    public static function getManualSyncUrl(): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=' . self::MANUAL_SYNC_ACTION),
            self::MANUAL_SYNC_ACTION,
            self::MANUAL_SYNC_NONCE
        );
    }

    public static function getAdminStatusHtml(): string
    {
        $state = self::getState();
        $checkedAt = !empty($state['last_checked_at']) ? wp_date('d.m.Y H:i', (int) $state['last_checked_at']) : null;
        $energyPercent = isset($state['energy_percent']) ? self::formatPercent((float) $state['energy_percent']) . ' %' : __('není dostupné', 'ar-design-dpd');
        $tollPerKg = isset($state['toll_per_kg']) ? self::formatMoney((float) $state['toll_per_kg']) . ' EUR/kg' : __('není dostupné', 'ar-design-dpd');

        $html = '<p><strong>' . esc_html__('Stav z CRONu', 'ar-design-dpd') . ':</strong> ';
        $html .= $checkedAt ? esc_html(sprintf(__('naposledy %s', 'ar-design-dpd'), $checkedAt)) : esc_html__('zatím neproběhl', 'ar-design-dpd');
        $html .= '</p>';
        $html .= '<p>' . esc_html(sprintf(__('Energetický poplatok: %1$s. Mýtny poplatok: %2$s.', 'ar-design-dpd'), $energyPercent, $tollPerKg)) . '</p>';
        $html .= '<p><a class="button button-primary" href="' . esc_url(self::getManualSyncUrl()) . '">' . esc_html__('Načíst ceny ručně', 'ar-design-dpd') . '</a></p>';

        return $html;
    }

    public static function registerCronSchedule(array $schedules): array
    {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Once weekly', 'ar-design-dpd'),
            ];
        }

        return $schedules;
    }

    public static function ensureCronScheduled(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        $nextRun = self::getNextMondayTenTimestamp();

        if (!$timestamp) {
            wp_schedule_event($nextRun, self::CRON_SCHEDULE, self::CRON_HOOK);
            return;
        }

        if (abs($timestamp - $nextRun) > DAY_IN_SECONDS) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
            wp_schedule_event($nextRun, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public static function runSync(): void
    {
        $state = self::getState();
        $result = self::fetchCurrentEnergySurcharge();

        $state['last_checked_at'] = time();
        $state['last_error'] = $result['error'] ?? '';

        $newValues = [];
        if (array_key_exists('value', $result) && $result['value'] !== null) {
            $newValues['energy_percent'] = (float) $result['value'];
        }
        if (array_key_exists('toll_per_kg', $result) && $result['toll_per_kg'] !== null) {
            $newValues['toll_per_kg'] = (float) $result['toll_per_kg'];
        }

        if (!empty($newValues)) {
            $newHash = md5(wp_json_encode($newValues));
            $oldHash = (string) ($state['last_hash'] ?? '');

            if ($oldHash !== '' && $oldHash !== $newHash) {
                $state['pending_notice'] = self::buildChangeNotice((array) ($state['values'] ?? []), $newValues);
            }

            $state['values'] = $newValues;
            $state['energy_percent'] = $newValues['energy_percent'] ?? null;
            $state['toll_per_kg'] = $newValues['toll_per_kg'] ?? null;
            $state['last_hash'] = $newHash;
            $state['source_url'] = self::SOURCE_URL;
        }

        update_option(self::STATE_OPTION_KEY, $state, false);
    }

    public static function renderAdminNotice(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $state = self::getState();
        $notice = trim((string) ($state['pending_notice'] ?? ''));

        if ($notice === '') {
            return;
        }

        echo '<div class="notice notice-warning"><p>' . esc_html($notice) . '</p></div>';

        $state['pending_notice'] = '';
        update_option(self::STATE_OPTION_KEY, $state, false);
    }

    public static function getHelperText(): string
    {
        $helpers = self::getHelperTexts();

        return (string) ($helpers[DpdExportSettings::ENERGY_SURCHARGE_PERCENT_OPTION_KEY] ?? '');
    }

    public static function getHelperTexts(): array
    {
        $state = self::getState();

        $checkedAt = !empty($state['last_checked_at']) ? wp_date('d.m.Y H:i', (int) $state['last_checked_at']) : null;
        $prefix = $checkedAt
            ? sprintf(__('Poslední kontrola CRONem (%s): ', 'ar-design-dpd'), $checkedAt)
            : __('Poslední kontrola CRONem: ', 'ar-design-dpd');

        $energyHelper = isset($state['energy_percent'])
            ? $prefix . sprintf(__('energetický poplatek je aktuálně %s %% podle ceníku DPD (zdroj: %s).', 'ar-design-dpd'), self::formatPercent((float) $state['energy_percent']), self::SOURCE_URL)
            : __('Aktuální energetický poplatek se zatím nepodařilo z ceníku zjistit. Zkontrolujte jej prosím ručně.', 'ar-design-dpd');

        $tollHelper = isset($state['toll_per_kg'])
            ? $prefix . sprintf(__('mýtný poplatek je aktuálně %s EUR za každý začatý kilogram zásilky (zdroj: %s).', 'ar-design-dpd'), self::formatMoney((float) $state['toll_per_kg']), self::SOURCE_URL)
            : __('Aktuální mýtný poplatek se zatím nepodařilo z ceníku zjistit. Zkontrolujte jej prosím ručně.', 'ar-design-dpd');

        $fixedHelper = __('Pevná korekce je ruční. CRON sleduje měsíční energetický poplatek a mýtný poplatek za kg z ceníku DPD.', 'ar-design-dpd');

        if (!empty($state['last_error'])) {
            $suffix = sprintf(__(' Posledná chyba synchronizácie: %s', 'ar-design-dpd'), (string) $state['last_error']);
            $energyHelper .= $suffix;
            $tollHelper .= $suffix;
            $fixedHelper .= $suffix;
        }

        return [
            DpdExportSettings::ENERGY_SURCHARGE_PERCENT_OPTION_KEY => $energyHelper,
            DpdExportSettings::ENERGY_SURCHARGE_FIXED_OPTION_KEY => $fixedHelper,
            'dpd_toll_surcharge_per_kg' => $tollHelper,
        ];
    }

    private static function fetchCurrentEnergySurcharge(): array
    {
        $response = wp_remote_get(self::SOURCE_URL, [
            'timeout' => 20,
            'redirection' => 5,
            'user-agent' => 'AR-Design-Surcharge-Monitor/1.0',
        ]);

        if (is_wp_error($response)) {
            return ['value' => null, 'error' => $response->get_error_message()];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return ['value' => null, 'error' => sprintf('HTTP %d for %s', $status, self::SOURCE_URL)];
        }

        $body = (string) wp_remote_retrieve_body($response);
        $values = self::extractSurcharges($body);

        $errors = [];
        if ($values['energy_percent'] === null) {
            $errors[] = __('Energetický poplatok sa nepodarilo vyparsovať zo stránky cenníka DPD.', 'ar-design-dpd');
        }
        if ($values['toll_per_kg'] === null) {
            $errors[] = __('Mýtny poplatok sa nepodarilo vyparsovať zo stránky cenníka DPD.', 'ar-design-dpd');
        }

        return [
            'value' => $values['energy_percent'] ?? null,
            'toll_per_kg' => $values['toll_per_kg'] ?? null,
            'error' => $errors ? implode(' ', $errors) : '',
        ];
    }

    private static function extractSurcharges(string $html): array
    {
        if ($html === '') {
            return [
                'energy_percent' => null,
                'toll_per_kg' => null,
            ];
        }

        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;

        $energyContext = self::extractContext($text, 'Energetický poplatok', 2000, 7000);
        $tollContext = self::extractContext($text, 'Mýtny poplatok', 1200, 2600);

        $energy = self::extractLatestMonthPercent($energyContext);
        $toll = self::extractTollPerKg($tollContext);

        return [
            'energy_percent' => $energy,
            'toll_per_kg' => $toll,
        ];
    }

    private static function extractContext(string $text, string $keyword, int $before, int $length): string
    {
        $context = $text;
        $pos = mb_stripos($text, $keyword, 0, 'UTF-8');
        if ($pos !== false) {
            $start = max(0, $pos - $before);
            $context = mb_substr($text, $start, $length, 'UTF-8');
        }

        return $context;
    }

    private static function extractTollPerKg(string $context): ?float
    {
        if ($context === '') {
            return null;
        }

        // Strict: use the official "changes from X to Y" statement and take Y.
        if (preg_match_all('/z\s+p[ôo]vodn[ýy]ch\s+[0-9]{1,2}(?:[\.,][0-9]{1,3})?\s*(?:EUR|€)\s+na\s+([0-9]{1,2}(?:[\.,][0-9]{1,3})?)\s*(?:EUR|€)[^\n\r]{0,140}(?:kg|kilogram|kilogramu)/iu', $context, $matches) && !empty($matches[1])) {
            $value = end($matches[1]);
            return self::toFloat($value);
        }

        // Strict alternative: explicit single value sentence tied to kilograms.
        if (preg_match_all('/([0-9]{1,2}(?:[\.,][0-9]{1,3})?)\s*(?:EUR|€)[^\n\r]{0,140}(?:za\s*každ[ýy]\s*začat[ýy]\s*kilogram|kg|kilogram|kilogramu)/iu', $context, $matches) && !empty($matches[1])) {
            $value = end($matches[1]);
            return self::toFloat($value);
        }

        return null;
    }

    private static function extractLatestMonthPercent(string $text): ?float
    {
        if (!preg_match_all('/(\d{2})\s*\/\s*(\d{4})/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $latestDate = null;
        $latestPercent = null;
        $rowsCount = count($matches[0]);

        for ($i = 0; $i < $rowsCount; $i++) {
            $month = (int) $matches[1][$i][0];
            $year = (int) $matches[2][$i][0];

            if ($month < 1 || $month > 12) {
                continue;
            }

            $rowStart = (int) $matches[0][$i][1];
            $nextRowStart = $i + 1 < $rowsCount ? (int) $matches[0][$i + 1][1] : strlen($text);
            $rowLength = max(0, min($nextRowStart - $rowStart, 300));
            $rowText = $rowLength > 0 ? substr($text, $rowStart, $rowLength) : '';

            if ($rowText === '' || !preg_match_all('/([0-9]{1,2}(?:[\.,][0-9]{1,2})?)\s*%/u', $rowText, $rowPercents)) {
                continue;
            }

            $percent = null;
            foreach ((array) ($rowPercents[1] ?? []) as $rawPercent) {
                $candidate = self::toFloat($rawPercent);
                if ($candidate <= 0 || $candidate > 40) {
                    continue;
                }
                $percent = $candidate;
            }

            if ($percent === null) {
                continue;
            }

            $dateKey = sprintf('%04d-%02d', $year, $month);
            if ($latestDate === null || strcmp($dateKey, $latestDate) > 0) {
                $latestDate = $dateKey;
                $latestPercent = $percent;
            }
        }

        return $latestPercent;
    }

    private static function getState(): array
    {
        $state = get_option(self::STATE_OPTION_KEY, []);

        return is_array($state) ? $state : [];
    }

    private static function buildChangeNotice(array $oldValues, array $newValues): string
    {
        $parts = [];

        $oldEnergy = isset($oldValues['energy_percent']) ? self::formatPercent((float) $oldValues['energy_percent']) . ' %' : 'n/a';
        $newEnergy = isset($newValues['energy_percent']) ? self::formatPercent((float) $newValues['energy_percent']) . ' %' : 'n/a';
        $parts[] = sprintf(__('energetický %1$s → %2$s', 'ar-design-dpd'), $oldEnergy, $newEnergy);

        $oldToll = isset($oldValues['toll_per_kg']) ? self::formatMoney((float) $oldValues['toll_per_kg']) . ' EUR/kg' : 'n/a';
        $newToll = isset($newValues['toll_per_kg']) ? self::formatMoney((float) $newValues['toll_per_kg']) . ' EUR/kg' : 'n/a';
        $parts[] = sprintf(__('mýtny %1$s → %2$s', 'ar-design-dpd'), $oldToll, $newToll);

        return sprintf(
            __('DPD príplatky sa zmenili: %s. Skontrolujte nastavenia dopravy.', 'ar-design-dpd'),
            implode(', ', $parts)
        );
    }

    private static function getNextMondayTenTimestamp(): int
    {
        $tz = wp_timezone();
        $now = new \DateTimeImmutable('now', $tz);
        $target = $now->setTime(10, 0, 0);
        $weekday = (int) $now->format('N');
        $daysUntilMonday = (8 - $weekday) % 7;
        $next = $target->modify('+' . $daysUntilMonday . ' days');

        if ($daysUntilMonday === 0 && $now >= $target) {
            $next = $next->modify('+7 days');
        }

        return $next->getTimestamp();
    }

    private static function toFloat($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function formatPercent(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private static function formatMoney(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}
