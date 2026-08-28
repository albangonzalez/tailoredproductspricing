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
    private const CM_PER_METER = 100.0;

    /**
     * @return array{rollerPrice: float, cassettePrice: float}
     */
    public function calculate(
        TailoredProductSettings $config,
        float $unitPrice,
        string $rawWidth,
        string $rawHeight,
        bool $withCassette
    ): array {
        $floatParser = new FloatParser();
        $widthInMeters = $floatParser->fromString($rawWidth) / self::CM_PER_METER;
        $heightInMeters = $floatParser->fromString($rawHeight) / self::CM_PER_METER;

        $rollerPrice = $unitPrice * $widthInMeters * $heightInMeters;

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
