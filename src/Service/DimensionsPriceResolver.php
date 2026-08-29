<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Service;

use PrestaShop\Module\TailoredProducts\Entity\TailoredProductAttribute;
use PrestaShop\Module\TailoredProducts\Entity\TailoredProductSettings;
use PrestaShop\Module\TailoredProducts\Repository\TailoredProductMatrixRepository;

/**
 * Resolves the dimension-priced term of a quote: either a bracket-matrix
 * lookup (`TailoredProductSettings::isMatrix()` true) or the existing
 * per-m² formula (`unit_price × width_m × height_m`). Named generically,
 * not "Roller...", because this module also prices venetian blinds and
 * Japanese panels — the mode switch itself is product-shape-agnostic.
 * Follows the module's existing `DimensionsFieldsProvider` naming.
 *
 * See .claude/docs/specs/bracket-matrix-pricing.md §3.4/§5.1-§5.3. Per the
 * project owner's correction to that spec: there is no per-product unit
 * setting (dimensions are always centimeters), so the only normalization
 * before the matrix lookup is rounding to the nearest whole centimeter —
 * no unit-branching logic.
 */
class DimensionsPriceResolver
{
    private const CM_PER_METER = 100.0;

    public function __construct(
        private readonly TailoredProductMatrixRepository $matrixRepository,
    ) {
    }

    /**
     * @return array{status: 'ok'|'out_of_range'|'unavailable', price: float} price is meaningless unless status is 'ok'
     */
    public function resolve(
        TailoredProductSettings $settings,
        ?TailoredProductAttribute $attributeConfig,
        float $widthRaw,
        float $heightRaw
    ): array {
        if ($settings->isMatrix()) {
            return $this->resolveFromMatrix($settings, $widthRaw, $heightRaw);
        }

        return $this->resolveFromFormula($attributeConfig, $widthRaw, $heightRaw);
    }

    /**
     * @return array{status: 'ok'|'out_of_range'|'unavailable', price: float}
     */
    private function resolveFromMatrix(TailoredProductSettings $settings, float $widthRaw, float $heightRaw): array
    {
        $idProduct = $settings->getIdProduct();
        $width = (int) round($widthRaw);
        $height = (int) round($heightRaw);

        $ceilings = $this->matrixRepository->resolveCeilings($idProduct, $width, $height);
        if ($ceilings['width_ceiling'] === null || $ceilings['height_ceiling'] === null) {
            return ['status' => 'out_of_range', 'price' => 0.0];
        }

        $cell = $this->matrixRepository->findCell($idProduct, $ceilings['width_ceiling'], $ceilings['height_ceiling']);
        if ($cell === null) {
            return ['status' => 'unavailable', 'price' => 0.0];
        }

        return ['status' => 'ok', 'price' => (float) $cell->getPrice()];
    }

    /**
     * Byte-identical to the formula that predates matrix pricing:
     * `unit_price × width_m × height_m`.
     *
     * @return array{status: 'ok'|'out_of_range'|'unavailable', price: float}
     */
    private function resolveFromFormula(?TailoredProductAttribute $attributeConfig, float $widthRaw, float $heightRaw): array
    {
        $unitPrice = $attributeConfig !== null ? (float) $attributeConfig->getUnitPrice() : 0.0;
        $widthInMeters = $widthRaw / self::CM_PER_METER;
        $heightInMeters = $heightRaw / self::CM_PER_METER;

        return ['status' => 'ok', 'price' => $unitPrice * $widthInMeters * $heightInMeters];
    }
}
