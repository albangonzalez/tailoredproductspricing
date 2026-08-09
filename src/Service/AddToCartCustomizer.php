<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use Cart;
use Context;
use FrontController;
use PrestaShop\Module\TailoredProductsPricing\Adapter\ColorAttributeProvider;
use PrestaShop\Module\TailoredProductsPricing\Repository\ProductConfigRepository;
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
 * See .claude/docs/specs/tailoredproductspricing-add-to-cart-customization.md,
 * Addendum B.3/C, and .claude/docs/specs/mechanism-color-choice.md §3.10, for
 * the full design. This step deliberately persists the raw submitted
 * dimensions and cassette choice with no bounds validation, no bot/token
 * gate, and no pricing/surcharge — those are later steps. The mechanism
 * color, in contrast, IS validated (§3.10): the submitted `id_attribute` is
 * checked against the live attribute list of the product's configured group
 * before anything is persisted.
 */
class AddToCartCustomizer
{
    private const CASSETTE_VALUES = ['without', 'with'];

    public function __construct(
        private readonly ProductConfigRepository $productConfigRepository,
        private readonly ColorAttributeProvider $colorAttributeProvider
    ) {
    }

    public function handle(FrontController $controller, Cart $cart): void
    {
        // TEMP DEBUG — remove once actionCartControllerInitAfter firing is confirmed.
        \PrestaShopLogger::addLog(
            sprintf('[tailoredproductspricing] AddToCartCustomizer::handle fired, POST=%s', json_encode($_POST)),
            1,
            null,
            'AddToCartCustomizer'
        );

        if (!Tools::getIsset('add')) {
            return;
        }

        $idProduct = (int) Tools::getValue('id_product');
        $config = $this->productConfigRepository->findByProductId($idProduct);
        if ($config === null) {
            return;
        }

        $width = (string) Tools::getValue('tpp_width');
        $height = (string) Tools::getValue('tpp_height');
        $cordSide = (string) Tools::getValue('tpp_cord_side');
        $cassette = (string) Tools::getValue('tpp_cassette');
        $mechanismColor = (int) Tools::getValue('tpp_mechanism_color');

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

        if (!$hasDimensions && !$hasCordSide && !$hasCassette && !$hasMechanismColor) {
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

        if ($idCustomization === false) {
            return;
        }
        $_POST['id_customization'] = (int) $idCustomization;
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
