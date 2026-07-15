<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use Tools;

/**
 * The single home of the tailored-pricing arithmetic (base + area × rate).
 *
 * Pure: no DB, no repository, no \Context, no request/hook awareness. Given already-normalized,
 * already-clamped numbers, it produces the area, the surcharge, and the final unit price so both
 * the {@see DimensionPriceCalculator} hook delegate and the preview AJAX action compute the exact
 * same number, by construction.
 *
 * It does NOT: clamp, validate bounds, read price_per_sqm from storage, resolve an id_customization,
 * or make tax decisions. All of that is the caller's responsibility.
 */
final class DimensionPriceFormula
{
    /**
     * m² per (unit²): cm² → m² = /10000 ; mm² → m² = /1000000.
     */
    private const AREA_DIVISOR_BY_UNIT = [
        'cm' => 10000.0,
        'mm' => 1000000.0,
    ];

    /**
     * Area in square metres for width × height expressed in $unit. Full precision, no rounding.
     */
    public function areaSquareMeters(float $width, float $height, string $unit): float
    {
        $divisor = self::AREA_DIVISOR_BY_UNIT[$unit] ?? self::AREA_DIVISOR_BY_UNIT['cm'];

        return ($width * $height) / $divisor;
    }

    /**
     * The dimension surcharge (area_m² × pricePerSqm). Full precision, NOT rounded.
     */
    public function surcharge(float $pricePerSqm, float $width, float $height, string $unit): float
    {
        return $this->areaSquareMeters($width, $height, $unit) * $pricePerSqm;
    }

    /**
     * Final tailored unit price = round(basePrice + surcharge).
     *
     * Precision and rounding mode are passed IN by the caller (resolved from $params / Context
     * there, never read here) so this service stays pure. Rounds ONCE, at the end, via
     * Tools::ps_round (deterministic arithmetic — the only core call permitted here, no I/O).
     */
    public function computeUnitPrice(
        float $basePrice,
        float $pricePerSqm,
        float $width,
        float $height,
        string $unit,
        int $precision,
        int $roundMode
    ): float {
        $unitPrice = $basePrice + $this->surcharge($pricePerSqm, $width, $height, $unit);

        return (float) Tools::ps_round($unitPrice, $precision, $roundMode);
    }
}
