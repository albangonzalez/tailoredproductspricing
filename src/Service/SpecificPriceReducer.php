<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Service;

use Address;
use Context;
use Customer;
use Group;
use SpecificPrice;
use Tools;

/**
 * Applies a product's configured catalog discount (a PrestaShop "Specific
 * Price" reduction — the Admin > Catalog > Prices > Discounts entry,
 * amount or percentage) onto an already-computed tailored price.
 *
 * Deliberately NOT delegated to core's own `Product::getPriceStatic()`
 * reduction step: core computes and subtracts the reduction against its
 * own summed price (base + attribute + customization), which
 * `CartCustomizationPriceAdjuster` discards wholesale in favor of the pure
 * customization price (see that class's docblock) — so by the time our
 * `actionProductPriceCalculation` hook runs, core has already applied the
 * reduction to the wrong base and we've thrown that result away. The
 * reduction has to be recomputed against the *replacement* price instead,
 * or it would be silently dropped in the cart, and it's never applied at
 * all for the AJAX quote, which never goes through
 * `Product::getPriceStatic()` in the first place.
 *
 * Mirrors core's own reduction math (`classes/Product.php::priceCalculation()`)
 * exactly, so a discount looks the same here as on any other product. Only
 * the specific-price *reduction* is handled — a specific price that
 * overrides the base `price` outright (`specific_price['price'] >= 0`) is
 * out of scope: this module's price is never the base price to begin with.
 * Group reductions and ecotax are likewise out of scope — see
 * `CartCustomizationPriceAdjuster`'s own known-scope-limit note.
 */
class SpecificPriceReducer
{
    /**
     * Resolves the specific price row applicable to the current visitor for
     * $idProduct, using the same address/group/currency resolution
     * `Product::getPriceStatic()` uses by default. For callers (the AJAX
     * quote) that don't already have one handed to them by core.
     *
     * @return array<string, mixed> empty when no specific price applies
     */
    public function resolveForCurrentContext(int $idProduct, ?int $idProductAttribute): array
    {
        $context = Context::getContext();
        $address = Address::initialize(null, true);

        $idCustomer = 0;
        if ($context->customer !== null && $context->customer->id) {
            $idCustomer = (int) $context->customer->id;
        }

        $idGroup = $idCustomer ? (int) Customer::getDefaultGroupId($idCustomer) : 0;
        if (!$idGroup) {
            $idGroup = (int) Group::getCurrent()->id;
        }

        $idCart = ($context->cart !== null && $context->cart->id) ? (int) $context->cart->id : 0;

        $specificPrice = SpecificPrice::getSpecificPrice(
            $idProduct,
            (int) $context->shop->id,
            (int) $context->currency->id,
            (int) $address->id_country,
            $idGroup,
            1,
            $idProductAttribute,
            $idCustomer,
            $idCart,
            1
        );

        return $specificPrice ?: [];
    }

    /**
     * @param array<string, mixed> $specificPrice as returned by
     *                                             {@see self::resolveForCurrentContext()}, or read straight off
     *                                             core's own `actionProductPriceCalculation` params
     *                                             (`$params['specific_price']`)
     */
    public function computeReduction(float $price, array $specificPrice, int $idCurrency, bool $useTax, float $taxRate): float
    {
        if (empty($specificPrice) || (float) $specificPrice['reduction'] <= 0) {
            return 0.0;
        }

        if ($specificPrice['reduction_type'] === 'amount') {
            $reduction = (float) $specificPrice['reduction'];
            if (!$specificPrice['id_currency']) {
                $reduction = Tools::convertPrice($reduction, $idCurrency);
            }

            if (!$useTax && $specificPrice['reduction_tax']) {
                $reduction /= (1 + $taxRate / 100);
            }
            if ($useTax && !$specificPrice['reduction_tax']) {
                $reduction *= (1 + $taxRate / 100);
            }

            return $reduction;
        }

        return $price * (float) $specificPrice['reduction'];
    }
}
