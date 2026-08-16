<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Service;

use PrestaShop\Module\TailoredProducts\Entity\TailoredProductSettings;
use PrestaShopBundle\Utils\FloatParser;

/**
 * Stateless pricing formula, server-side. Mirrors the copy in
 * `controllers/front/ajax.php` (`displayAjaxQuote()`) — that endpoint is out
 * of scope for this change and keeps its own inline copy; keep both in sync
 * if the formula changes. See .claude/docs/specs/add-to-cart-pricing.md §3.4.
 */
class PriceCalculator
{
    private const METERS_PER_UNIT = [
        'cm' => 100.0,
        'mm' => 1000.0,
    ];

    /**
     * @return array{rollerPrice: float, cassettePrice: float}
     */
    public function calculate(
        TailoredProductSettings $config,
        float $pricePerSqm,
        string $rawWidth,
        string $rawHeight,
        bool $withCassette
    ): array {
        if (!isset(self::METERS_PER_UNIT[$config->getUnit()])) {
            return ['rollerPrice' => 0.0, 'cassettePrice' => 0.0];
        }
        $metersPerUnit = self::METERS_PER_UNIT[$config->getUnit()];
        $floatParser = new FloatParser();
        $widthInMeters = $floatParser->fromString($rawWidth) / $metersPerUnit;
        $heightInMeters = $floatParser->fromString($rawHeight) / $metersPerUnit;

        $rollerPrice = $pricePerSqm * $widthInMeters * $heightInMeters;

        $cassettePricePerMeter = $config->getCassettePricePerMeter();
        $cassettePrice = 0.0;
        if ($withCassette && $cassettePricePerMeter !== null) {
            $cassettePrice = (float) $cassettePricePerMeter * $widthInMeters;
        }

        return [
            'rollerPrice' => max(0.0, round($rollerPrice, 6)),
            'cassettePrice' => max(0.0, round($cassettePrice, 6)),
        ];
    }
}
