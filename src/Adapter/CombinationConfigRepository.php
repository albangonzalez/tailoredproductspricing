<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Adapter;

use Db;

/**
 * Isolated persistence boundary for `tpp_combination_config` and the
 * product-attribute → product resolution needed to know whether Tailored
 * Products Pricing is enabled for a given combination's parent product.
 *
 * This is the only class in the module allowed to reach `Db::getInstance()`
 * for the combination flow (legacy exception confined here per the module's
 * mandate against raw DB access outside a dedicated adapter).
 */
final class CombinationConfigRepository
{
    /**
     * Tells whether the module is enabled for the product behind a
     * combination, i.e. that product has a row in tpp_product_config.
     */
    public function isModuleEnabledForCombination(int $idProductAttribute): bool
    {
        if ($idProductAttribute <= 0) {
            return false;
        }

        $idProduct = (int) Db::getInstance()->getValue(
            'SELECT id_product FROM `' . _DB_PREFIX_ . 'product_attribute`
             WHERE id_product_attribute = ' . $idProductAttribute
        );

        if ($idProduct <= 0) {
            return false;
        }

        $row = Db::getInstance()->getRow(
            'SELECT id_product FROM `' . _DB_PREFIX_ . 'tpp_product_config` WHERE id_product = ' . $idProduct
        );

        return $row !== false && $row !== null;
    }

    public function getPricePerSqm(int $idProductAttribute): float
    {
        if ($idProductAttribute <= 0) {
            return 0;
        }

        $row = Db::getInstance()->getRow(
            'SELECT price_per_sqm FROM `' . _DB_PREFIX_ . 'tpp_combination_config`
             WHERE id_product_attribute = ' . $idProductAttribute
        );

        return $row ? (float) $row['price_per_sqm'] : 0;
    }

    public function upsert(int $idProductAttribute, float $pricePerSqm): void
    {
        if ($idProductAttribute <= 0) {
            return;
        }

        $exists = Db::getInstance()->getValue(
            'SELECT id_product_attribute FROM `' . _DB_PREFIX_ . 'tpp_combination_config`
             WHERE id_product_attribute = ' . $idProductAttribute
        );

        if ($exists) {
            Db::getInstance()->update(
                'tpp_combination_config',
                ['price_per_sqm' => $pricePerSqm],
                'id_product_attribute = ' . $idProductAttribute
            );
        } else {
            Db::getInstance()->insert('tpp_combination_config', [
                'id_product_attribute' => $idProductAttribute,
                'price_per_sqm' => $pricePerSqm,
            ]);
        }
    }
}
