<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use Cart;
use Context;
use FrontController;
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
 * Addendum B.3/C, for the full design. This step deliberately persists the
 * raw submitted dimensions with no bounds validation, no bot/token gate, and
 * no pricing/surcharge — those are later steps.
 */
class AddToCartCustomizer
{
    public function __construct(
        private readonly ProductConfigRepository $productConfigRepository
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

        $hasDimensions = $width !== '' && $height !== '';
        $hasCordSide = $cordSide !== '' && $config->getIdCustomizationFieldCordSide() !== null;

        if (!$hasDimensions && !$hasCordSide) {
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

        if ($idCustomization === false) {
            return;
        }
        $_POST['id_customization'] = (int) $idCustomization;
    }
}
