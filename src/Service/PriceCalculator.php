<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Service;

use PrestaShop\Module\TailoredProducts\Entity\TailoredProductAttribute;
use PrestaShop\Module\TailoredProducts\Entity\TailoredProductSettings;
use PrestaShopBundle\Utils\FloatParser;

/**
 * Stateless pricing formula, server-side. `controllers/front/ajax.php`
 * (`displayAjaxQuote()`) now delegates to this class instead of keeping its
 * own inline copy of the arithmetic — see
 * .claude/docs/specs/bracket-matrix-pricing.md §3.5. See also
 * .claude/docs/specs/add-to-cart-pricing.md §3.4.
 *
 * The dimension-priced term (matrix cell lookup or the per-m² formula) is
 * delegated to {@see DimensionsPriceResolver}; this class keeps ownership of
 * unit conversion for the cassette surcharge, the surcharge math itself, and
 * the `max(0.0, round(..., 6))` clamping. See
 * .claude/docs/specs/bracket-matrix-pricing.md §3.4.
 */
class PriceCalculator
{
    private const CM_PER_METER = 100.0;

    public function __construct(
        private readonly DimensionsPriceResolver $dimensionsPriceResolver,
    ) {
    }

    /**
     * @return array{status: 'ok'|'out_of_range'|'unavailable', rollerPrice: float, cassettePrice: float}
     */
    public function calculate(
        TailoredProductSettings $config,
        ?TailoredProductAttribute $attributeConfig,
        string $rawWidth,
        string $rawHeight,
        bool $withCassette
    ): array {
        $floatParser = new FloatParser();
        $width = $floatParser->fromString($rawWidth);
        $height = $floatParser->fromString($rawHeight);
        $widthInMeters = $width / self::CM_PER_METER;

        $dimensionsPrice = $this->dimensionsPriceResolver->resolve($config, $attributeConfig, $width, $height);

        $cassettePricePerMeter = $config->getCassettePricePerMeter();
        $cassettePrice = 0.0;
        if ($withCassette && $cassettePricePerMeter !== null) {
            $cassettePrice = (float) $cassettePricePerMeter * $widthInMeters;
        }

        return [
            'status' => $dimensionsPrice['status'],
            'rollerPrice' => max(0.0, round($dimensionsPrice['price'], 6)),
            'cassettePrice' => max(0.0, round($cassettePrice, 6)),
        ];
    }
}
