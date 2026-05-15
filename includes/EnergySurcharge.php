<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

class EnergySurcharge
{
    public static function init(): void
    {
        add_filter('woocommerce_package_rates', [__CLASS__, 'applyToRates'], 9999, 2);
    }

    public static function applyToRates(array $rates, array $package): array
    {
        $settings = DpdExportSettings::getDefaultSettings();
        $energyPercent = self::toFloat($settings[DpdExportSettings::ENERGY_SURCHARGE_PERCENT_OPTION_KEY] ?? 0);
        $energyFixed = self::toFloat($settings[DpdExportSettings::ENERGY_SURCHARGE_FIXED_OPTION_KEY] ?? 0);
        $tollPerKg = self::toFloat($settings['dpd_toll_surcharge_per_kg'] ?? 0);

        if ($energyPercent <= 0 && $energyFixed <= 0 && $tollPerKg <= 0) {
            return $rates;
        }

        $packageWeightKg = self::getPackageWeightKg($package);
        $tollUnits = $packageWeightKg > 0 ? (int) ceil($packageWeightKg) : 0;

        foreach ($rates as $rateKey => $rate) {
            if (!$rate instanceof \WC_Shipping_Rate) {
                continue;
            }

            $methodId = strtolower((string) $rate->get_method_id());
            $rateId = strtolower((string) $rate->get_id());

            $isDpdMethod = strpos($methodId, 'wc_dpd_') === 0 || strpos($rateId, 'wc_dpd_') !== false;
            if (!$isDpdMethod) {
                continue;
            }

            $baseCost = (float) $rate->get_cost();
            if ($baseCost <= 0) {
                continue;
            }

            $multiplier = 1 + ($energyPercent / 100);
            $newCost = ($baseCost * $multiplier) + $energyFixed + ($tollUnits * $tollPerKg);
            $newCost = (float) wc_format_decimal($newCost, wc_get_price_decimals());

            $rates[$rateKey]->set_cost($newCost);
        }

        return $rates;
    }

    private static function toFloat($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function getPackageWeightKg(array $package): float
    {
        $weight = 0.0;

        foreach ((array) ($package['contents'] ?? []) as $item) {
            if (empty($item['data']) || !$item['data'] instanceof \WC_Product) {
                continue;
            }

            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
            $productWeight = (float) $item['data']->get_weight();

            if ($quantity <= 0 || $productWeight <= 0) {
                continue;
            }

            $weight += wc_get_weight($productWeight, 'kg') * $quantity;
        }

        return $weight;
    }
}
