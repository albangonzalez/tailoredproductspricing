<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use PrestaShop\Module\TailoredProductsPricing\Adapter\ColorAttributeProvider;
use PrestaShop\Module\TailoredProductsPricing\Repository\ProductConfigRepository;

/**
 * Pure data/gating service for the FO `displayProductCustomizationBottom`
 * choices block (cord side, cassette, mechanism color, ...). No `\Context`,
 * no Smarty — same contract as {@see DimensionsFieldsProvider}.
 */
final class ProductChoicesProvider
{
    public function __construct(
        private readonly ProductConfigRepository $productConfigRepository,
        private readonly ColorAttributeProvider $colorAttributeProvider,
    ) {
    }

    /**
     * @return array{
     *     idProduct:int,
     *     cassetteEnabled:bool,
     *     mechanismColorOptions:list<array{id:int,name:string,color:?string}>,
     *     rollDirectionEnabled:bool
     * }|null null when the module is not enabled for this product
     */
    public function getViewData(int $idProduct, int $idLang, int $idShop): ?array
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
            'mechanismColorOptions' => $this->mechanismColorOptions($config->getIdAttributeGroupMechanismColor(), $idLang, $idShop),
            'rollDirectionEnabled' => $config->isRollDirectionEnabled(),
        ];
    }

    /**
     * @return list<array{id:int,name:string,color:?string}>
     */
    private function mechanismColorOptions(?int $idAttributeGroup, int $idLang, int $idShop): array
    {
        if ($idAttributeGroup === null) {
            return [];
        }

        $options = [];

        foreach ($this->colorAttributeProvider->findGroupAttributes($idAttributeGroup, $idLang, $idShop) as $attribute) {
            $options[] = [
                'id' => $attribute['id'],
                'name' => $attribute['name'] !== '' ? $attribute['name'] : ('#' . $attribute['id']),
                'color' => $this->normalizeColor($attribute['color']),
            ];
        }

        return $options;
    }

    private function normalizeColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }

        $color = trim($color);

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1 ? $color : null;
    }
}
