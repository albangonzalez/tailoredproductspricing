<?php

declare(strict_types=1);

use PrestaShop\Module\TailoredProductsPricing\Repository\CombinationConfigRepository;
use PrestaShop\Module\TailoredProductsPricing\Repository\ProductConfigRepository;
use PrestaShopBundle\Utils\FloatParser;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TailoredProductsPricingAjaxModuleFrontController extends ModuleFrontController
{
    private const METERS_PER_UNIT = [
        'cm' => 100.0,
        'mm' => 1000.0,
    ];

    /**
     * Computes the tailored unit price for the given
     * dimensions.
     */
    public function displayAjaxQuote(): void
    {
        header('Content-Type: application/json');

        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        $floatParser = new FloatParser();
        $width = $floatParser->fromString((string) Tools::getValue('width'));
        $height = $floatParser->fromString((string) Tools::getValue('height'));

        $productConfig = $this->getProductConfigRepository()->findByProductId($idProduct);
        $pricePerSqm = $this->getCombinationConfigRepository()->getPricePerSqm($idProductAttribute);

        $metersPerUnit = self::METERS_PER_UNIT[$productConfig->getUnit()];
        $widthInMeters = $width / $metersPerUnit;
        $heightInMeters = $height / $metersPerUnit;

        $price = $pricePerSqm * $widthInMeters * $heightInMeters;

        $currency = $this->context->currency;
        $priceFormatted = $this->context->getCurrentLocale()->formatPrice($price, $currency->iso_code);

        $this->ajaxRender(json_encode([
            'success' => true,
            'price' => $price,
            'priceFormatted' => $priceFormatted,
        ]));
    }

    private function getProductConfigRepository(): ProductConfigRepository
    {
        /** @var ProductConfigRepository $repository */
        $repository = $this->module->get(ProductConfigRepository::class);

        return $repository;
    }

    private function getCombinationConfigRepository(): CombinationConfigRepository
    {
        /** @var CombinationConfigRepository $repository */
        $repository = $this->module->get(CombinationConfigRepository::class);

        return $repository;
    }
}
