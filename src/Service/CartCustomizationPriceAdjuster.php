<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Service;

use Address;
use Customization;
use PrestaShop\Module\TailoredProducts\Repository\TailoredProductSettingsRepository;
use Product;
use TaxManagerFactory;
use Tools;

/**
 * Hooked on `actionProductPriceCalculation` — the single point where
 * `Product::getPriceStatic()` lets a module rewrite the final price via
 * `'price' => &$price` (`classes/Product.php`) — to strip the product's own
 * base price back out of the cart/order total for tpp-enabled products.
 *
 * `product_shop.price` on a tailored product is set purely for SEO/listing
 * display (rich snippets, category grid, "starting from" price) — the real
 * price is `unit_price × width_m × height_m` (+ cassette surcharge),
 * already computed and stamped tax-exclusive onto `customized_data.price`
 * by `PriceWriter` at add-to-cart time (see CLAUDE.md, "Front office — add
 * to cart"). Core's own price calculation ADDS that customization price on
 * top of the base price rather than replacing it, so left alone every cart
 * line double-counts: base price + the real computed price. This class
 * recomputes the line price from the customization price alone, applying
 * the same currency conversion and tax steps core just did, and discards
 * the base/combination price core folded in.
 *
 * Only acts when `id_customization` is set — i.e. an actual cart/order
 * line — never on the bare product-page/listing price lookup (no
 * customization there), so the SEO base price keeps showing untouched
 * exactly as intended. Also only acts on tpp-configured products, so
 * unrelated products' pricing is never touched.
 *
 * The product's catalog discount (specific price reduction), if any, is
 * reapplied against the replacement price via {@see SpecificPriceReducer}
 * — core already computed and subtracted one against its own (wrong)
 * summed price before this hook ran, which is discarded along with the
 * rest of core's `$price`; `$params['specific_price']`/`$params['use_reduc']`
 * are reused as-is rather than re-querying `SpecificPrice`.
 *
 * Known scope limit: this bypasses ecotax and group reductions that core's
 * calculation would otherwise fold in alongside the base price — not
 * currently used by this module's products, so left unhandled rather than
 * adding speculative complexity.
 */
class CartCustomizationPriceAdjuster
{
    public function __construct(
        private readonly TailoredProductSettingsRepository $productConfigRepository,
        private readonly SpecificPriceReducer $specificPriceReducer,
    ) {
    }

    public function adjust(array $params): void
    {
        $idCustomization = (int) ($params['id_customization'] ?? 0);
        if ($idCustomization <= 0) {
            return;
        }

        $idProduct = (int) ($params['id_product'] ?? 0);
        if ($this->productConfigRepository->findByProductId($idProduct) === null) {
            return;
        }

        $price = Customization::getCustomizationPrice($idCustomization);

        $idCurrency = (int) ($params['id_currency'] ?? 0);
        if ($idCurrency) {
            $price = Tools::convertPrice($price, $idCurrency);
        }

        $taxRate = 0.0;
        if (!empty($params['use_tax'])) {
            /** @var Address $address */
            $address = $params['address'];
            $taxCalculator = TaxManagerFactory::getManager(
                $address,
                Product::getIdTaxRulesGroupByIdProduct($idProduct, $params['context'] ?? null)
            )->getTaxCalculator();
            $price = $taxCalculator->addTaxes($price);
            $taxRate = $taxCalculator->getTotalRate();
        }

        if (!empty($params['use_reduc'])) {
            $reduction = $this->specificPriceReducer->computeReduction(
                $price,
                $params['specific_price'] ?? [],
                $idCurrency,
                !empty($params['use_tax']),
                $taxRate
            );
            $price = max(0.0, $price - $reduction);
        }

        $params['price'] = $price;
    }
}
