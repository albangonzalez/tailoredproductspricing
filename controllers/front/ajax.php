<?php

declare(strict_types=1);

use PrestaShop\Module\TailoredProducts\Repository\TailoredProductAttributeRepository;
use PrestaShop\Module\TailoredProducts\Repository\TailoredProductSettingsRepository;
use PrestaShop\Module\TailoredProducts\Service\PriceCalculator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TailoredProductsAjaxModuleFrontController extends ModuleFrontController
{
    // Mirrors AddToCartCustomizer::CASSETTE_VALUES; duplicated deliberately,
    // see cassette-pricing spec §3.1.
    private const CASSETTE_VALUES = ['without', 'with'];
    private const CASSETTE_SELECTED = 'with';

    /**
     * Computes the tailored price for the given dimensions, delegating the
     * arithmetic to PriceCalculator (formula or bracket-matrix, depending on
     * the product's `is_matrix` setting — see
     * .claude/docs/specs/bracket-matrix-pricing.md §3.5).
     */
    public function displayAjaxQuote(): void
    {
        header('Content-Type: application/json');

        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        $rawWidth = (string) Tools::getValue('width');
        $rawHeight = (string) Tools::getValue('height');
        $cassette = (string) Tools::getValue('cassette');

        $productConfig = $this->getTailoredProductSettingsRepository()->findByProductId($idProduct);

        if ($productConfig === null) {
            $this->ajaxRender(json_encode(['success' => false]));

            return;
        }

        $attributeConfig = $this->getTailoredProductAttributeRepository()->findByPk($idProductAttribute);

        $breakdown = $this->getPriceCalculator()->calculate(
            $productConfig,
            $attributeConfig,
            $rawWidth,
            $rawHeight,
            $cassette === self::CASSETTE_SELECTED
        );

        if ($breakdown['status'] !== 'ok') {
            $messages = [
                'out_of_range' => 'This size exceeds what we can produce',
                'unavailable' => 'This size is not available',
            ];

            $this->ajaxRender(json_encode([
                'success' => false,
                'reason' => $breakdown['status'],
                'message' => $messages[$breakdown['status']] ?? '',
            ]));

            return;
        }

        $price = $breakdown['rollerPrice'] + $breakdown['cassettePrice'];

        $taxRate = (new Product($idProduct))->getTaxesRate();
        $priceWithTax = $this->getPriceCalculator()->applyTaxRate($price, $taxRate);
        
        $currency = $this->context->currency;
        $priceFormatted = $this->context->getCurrentLocale()->formatPrice($priceWithTax, $currency->iso_code);

        $this->ajaxRender(json_encode([
            'success' => true,
            'price' => $priceWithTax,
            'priceFormatted' => $priceFormatted,
        ]));
    }

    private function getTailoredProductSettingsRepository(): TailoredProductSettingsRepository
    {
        /** @var TailoredProductSettingsRepository $repository */
        $repository = $this->module->get(TailoredProductSettingsRepository::class);

        return $repository;
    }

    private function getTailoredProductAttributeRepository(): TailoredProductAttributeRepository
    {
        /** @var TailoredProductAttributeRepository $repository */
        $repository = $this->module->get(TailoredProductAttributeRepository::class);

        return $repository;
    }

    private function getPriceCalculator(): PriceCalculator
    {
        /** @var PriceCalculator $priceCalculator */
        $priceCalculator = $this->module->get(PriceCalculator::class);

        return $priceCalculator;
    }
}
