<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Service;

use Cart;
use Context;
use PrestaShop\Module\TailoredProducts\Adapter\ColorAttributeProvider;
use PrestaShop\Module\TailoredProducts\Entity\TppProductConfig;
use PrestaShop\Module\TailoredProducts\Repository\CombinationConfigRepository;
use PrestaShop\Module\TailoredProducts\Repository\ProductConfigRepository;
use PrestaShopException;
use Product;
use Tools;
use Validate;

/**
 * Intercepts the add-to-cart submission on tpp-enabled products at
 * `actionCartControllerInitAfter` time (i.e. before `CartController::init()`
 * reads `id_customization` from the request), creates the core customization
 * rows, and injects `id_customization` into `$_POST` so core's own
 * `CartController` picks it up natively.
 *
 * See .claude/docs/specs/tailoredproducts-add-to-cart-customization.md,
 * Addendum B.3/C, and .claude/docs/specs/mechanism-color-choice.md §3.10, for
 * the full design. This step deliberately persists the raw submitted
 * dimensions, cassette choice and roll direction with no bounds validation
 * and no bot/token gate — those are still later steps. The mechanism color,
 * in contrast, IS validated (§3.10): the submitted `id_attribute` is checked
 * against the live attribute list of the product's configured group before
 * anything is persisted.
 *
 * Pricing (see .claude/docs/specs/add-to-cart-pricing.md): once the
 * `customized_data` rows exist, {@see self::stampPrices()} prices and stamps
 * them via `PriceCalculator`/`PriceWriter`.
 */
class AddToCartCustomizer
{
    private const CASSETTE_VALUES = ['without', 'with'];
    private const CASSETTE_SELECTED = 'with';
    private const ROLL_DIRECTION_VALUES = ['standard', 'reverse'];

    public function __construct(
        private readonly ProductConfigRepository $productConfigRepository,
        private readonly ColorAttributeProvider $colorAttributeProvider,
        private readonly CombinationConfigRepository $combinationConfigRepository,
        private readonly PriceCalculator $priceCalculator,
        private readonly PriceWriter $priceWriter
    ) {
    }

    public function handle(Cart $cart): void
    {
        if (!Tools::getIsset('add')) {
            return;
        }

        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = $this->resolveIdProductAttribute($idProduct);
        $config = $this->productConfigRepository->findByProductId($idProduct);
        if ($config === null) {
            return;
        }

        $width = (string) Tools::getValue('tpp_width');
        $height = (string) Tools::getValue('tpp_height');
        $cordSide = (string) Tools::getValue('tpp_cord_side');
        $cassette = (string) Tools::getValue('tpp_cassette');
        $mechanismColor = (int) Tools::getValue('tpp_mechanism_color');
        $rollDirection = (string) Tools::getValue('tpp_roll_direction');
        $hasDimensions = $width !== '' && $height !== '';
        $hasCordSide = $cordSide !== '' && $config->getIdCustomizationFieldCordSide() !== null;
        $hasCassette = in_array($cassette, self::CASSETTE_VALUES, true)
            && $config->getIdCustomizationFieldCassette() !== null;

        $mechanismColorName = null;
        if ($mechanismColor > 0
            && $config->getIdAttributeGroupMechanismColor() !== null
            && $config->getIdCustomizationFieldMechanismColor() !== null
        ) {
            $mechanismColorName = $this->resolveColorName(
                (int) $config->getIdAttributeGroupMechanismColor(),
                $mechanismColor
            );
        }
        $hasMechanismColor = $mechanismColorName !== null;

        $hasRollDirection = in_array($rollDirection, self::ROLL_DIRECTION_VALUES, true)
            && $config->getIdCustomizationFieldRollDirection() !== null;

        if (!$hasDimensions && !$hasCordSide && !$hasCassette && !$hasMechanismColor && !$hasRollDirection) {
            return;
        }

        if (!$cart->id) {
            $cart->add();
            if (!Validate::isLoadedObject($cart)) {
                return;
            }
            Context::getContext()->cookie->id_cart = (int) $cart->id;
        }

        $idCustomization = false;

        if ($hasDimensions) {
            $cart->addTextFieldToProduct($idProduct, (int) $config->getIdCustomizationFieldWidth(), Product::CUSTOMIZE_TEXTFIELD, $width, true);
            $idCustomization = $cart->addTextFieldToProduct($idProduct, (int) $config->getIdCustomizationFieldHeight(), Product::CUSTOMIZE_TEXTFIELD, $height, true);
        }

        if ($hasCordSide) {
            $idCustomization = $cart->addTextFieldToProduct($idProduct, (int) $config->getIdCustomizationFieldCordSide(), Product::CUSTOMIZE_TEXTFIELD, $cordSide, true);
        }

        if ($hasCassette) {
            $idCustomization = $cart->addTextFieldToProduct($idProduct, (int) $config->getIdCustomizationFieldCassette(), Product::CUSTOMIZE_TEXTFIELD, $cassette, true);
        }

        if ($hasMechanismColor) {
            $idCustomization = $cart->addTextFieldToProduct($idProduct, (int) $config->getIdCustomizationFieldMechanismColor(), Product::CUSTOMIZE_TEXTFIELD, $mechanismColorName, true);
        }

        if ($hasRollDirection) {
            $idCustomization = $cart->addTextFieldToProduct($idProduct, (int) $config->getIdCustomizationFieldRollDirection(), Product::CUSTOMIZE_TEXTFIELD, $rollDirection, true);
        }

        if ($idCustomization === false) {
            return;
        }

        $this->stampPrices((int) $idCustomization, $config, $idProductAttribute, [
            'width' => $width,
            'height' => $height,
            'hasDimensions' => $hasDimensions,
            'hasCordSide' => $hasCordSide,
            'hasCassette' => $hasCassette,
            'cassetteSelected' => $cassette === self::CASSETTE_SELECTED,
            'hasMechanismColor' => $hasMechanismColor,
            'hasRollDirection' => $hasRollDirection,
        ]);

        $_POST['id_customization'] = (int) $idCustomization;
    }

    /**
     * Prices and stamps every row this request just wrote, then zeroes any
     * row this module owns on the same $idCustomization that was not
     * re-written this request (deselected cassette on a re-submit — see
     * .claude/docs/specs/add-to-cart-pricing.md §5.4).
     *
     * @param array{width: string, height: string, hasDimensions: bool, hasCordSide: bool, hasCassette: bool, cassetteSelected: bool, hasMechanismColor: bool, hasRollDirection: bool} $submitted
     */
    private function stampPrices(
        int $idCustomization,
        TppProductConfig $config,
        int $idProductAttribute,
        array $submitted
    ): void {
        $pricePerSqm = $this->combinationConfigRepository->getPricePerSqm($idProductAttribute);
        $breakdown = $this->priceCalculator->calculate(
            $config,
            $pricePerSqm,
            $submitted['width'],
            $submitted['height'],
            // hasCassette only means "a valid with/without value was submitted" (it also
            // gates whether the row gets written at all) — the surcharge must key off
            // the actual selection, or "without" gets charged as if "with" were chosen.
            $submitted['cassetteSelected']
        );

        // Width and height are two rows of the same id_customization, and
        // getCustomizationPrice() SUMs every row of it — the roller price
        // (price_per_sqm x width_m x height_m) must be written on exactly
        // ONE of them, never both, or the customer is charged twice. Width
        // carries it, height stays 0; do not "balance" this.
        $writtenIndexes = [];

        if ($submitted['hasDimensions']) {
            $idWidth = (int) $config->getIdCustomizationFieldWidth();
            $idHeight = (int) $config->getIdCustomizationFieldHeight();
            $this->priceWriter->stamp($idCustomization, Product::CUSTOMIZE_TEXTFIELD, $idWidth, $breakdown['rollerPrice']);
            $this->priceWriter->stamp($idCustomization, Product::CUSTOMIZE_TEXTFIELD, $idHeight, 0.0);
            $writtenIndexes[] = $idWidth;
            $writtenIndexes[] = $idHeight;
        }

        if ($submitted['hasCordSide']) {
            $idCordSide = (int) $config->getIdCustomizationFieldCordSide();
            $this->priceWriter->stamp($idCustomization, Product::CUSTOMIZE_TEXTFIELD, $idCordSide, 0.0);
            $writtenIndexes[] = $idCordSide;
        }

        if ($submitted['hasCassette']) {
            $idCassette = (int) $config->getIdCustomizationFieldCassette();
            $this->priceWriter->stamp($idCustomization, Product::CUSTOMIZE_TEXTFIELD, $idCassette, $breakdown['cassettePrice']);
            $writtenIndexes[] = $idCassette;
        }

        if ($submitted['hasMechanismColor']) {
            $idMechanismColor = (int) $config->getIdCustomizationFieldMechanismColor();
            $this->priceWriter->stamp($idCustomization, Product::CUSTOMIZE_TEXTFIELD, $idMechanismColor, 0.0);
            $writtenIndexes[] = $idMechanismColor;
        }

        if ($submitted['hasRollDirection']) {
            $idRollDirection = (int) $config->getIdCustomizationFieldRollDirection();
            $this->priceWriter->stamp($idCustomization, Product::CUSTOMIZE_TEXTFIELD, $idRollDirection, 0.0);
            $writtenIndexes[] = $idRollDirection;
        }

        $moduleFieldIndexes = array_values(array_filter([
            $config->getIdCustomizationFieldWidth(),
            $config->getIdCustomizationFieldHeight(),
            $config->getIdCustomizationFieldCordSide(),
            $config->getIdCustomizationFieldCassette(),
            $config->getIdCustomizationFieldMechanismColor(),
            $config->getIdCustomizationFieldRollDirection(),
        ], static fn (?int $id): bool => $id !== null));
        $moduleFieldIndexes = array_map('intval', $moduleFieldIndexes);

        $this->priceWriter->resetStaleRows($idCustomization, $moduleFieldIndexes, $writtenIndexes);
    }

    /**
     * This hook fires from `FrontController::init()`, before core's own
     * `CartController::processChangeProductInCart()` runs — which is where
     * `id_product_attribute` normally gets resolved from the submitted
     * `group[]` array (theme combination selectors post `group[N]=idAttribute`
     * pairs, not a resolved `id_product_attribute`, until that later step
     * runs `Product::getIdProductAttributeByIdAttributes()`). Reading
     * `Tools::getValue('id_product_attribute')` alone here would read 0 on
     * such a submission and silently price every combination at the base
     * (unset) rate — so replicate core's own resolution order.
     */
    private function resolveIdProductAttribute(int $idProduct): int
    {
        $idProductAttribute = (int) Tools::getValue('id_product_attribute', Tools::getValue('ipa'));
        if ($idProductAttribute > 0) {
            return $idProductAttribute;
        }

        $groups = Tools::getValue('group');
        if (empty($groups) || !is_array($groups)) {
            return 0;
        }

        try {
            return (int) Product::getIdProductAttributeByIdAttributes($idProduct, $groups, true);
        } catch (PrestaShopException $e) {
            return 0;
        }
    }

    /**
     * Resolves the translated name of $idAttribute, but only when it belongs
     * to $idAttributeGroup's live attribute list — an attacker-controlled
     * `id_attribute` must never resolve against another product's group (or
     * a non-color attribute) and land in an order record.
     */
    private function resolveColorName(int $idAttributeGroup, int $idAttribute): ?string
    {
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        foreach ($this->colorAttributeProvider->findGroupAttributes($idAttributeGroup, $idLang, $idShop) as $attribute) {
            if ($attribute['id'] === $idAttribute) {
                return $attribute['name'];
            }
        }

        return null;
    }
}
