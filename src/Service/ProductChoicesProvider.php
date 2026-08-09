<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use PrestaShop\Module\TailoredProductsPricing\Repository\ProductConfigRepository;

/**
 * Pure data/gating service for the FO `displayProductCustomizationBottom`
 * choices block (cord side, cassette, ...). No `\Context`, no Smarty — same
 * contract as {@see DimensionsFieldsProvider}.
 */
final class ProductChoicesProvider
{
    public function __construct(
        private readonly ProductConfigRepository $productConfigRepository,
    ) {
    }

    /**
     * @return array{idProduct:int, cassetteEnabled:bool}|null null when the module is not enabled for this product
     */
    public function getViewData(int $idProduct): ?array
    {
        if ($idProduct <= 0) {
            return null;
        }

        $config = $this->productConfigRepository->findByProductId($idProduct);
        if ($config === null) {
            return null;
        }

        return [
            'idProduct' => $idProduct,
            'cassetteEnabled' => $config->getCassettePricePerMeter() !== null,
        ];
    }
}
