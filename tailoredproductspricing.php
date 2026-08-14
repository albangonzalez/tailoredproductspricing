<?php

use PrestaShop\Module\TailoredProductsPricing\Form\Modifier\CombinationFormModifier;
use PrestaShop\Module\TailoredProductsPricing\Form\Modifier\ProductFormModifier;
use PrestaShop\Module\TailoredProductsPricing\Install\Installer;
use PrestaShop\Module\TailoredProductsPricing\Repository\CombinationConfigRepository;
use PrestaShop\Module\TailoredProductsPricing\Repository\ProductConfigRepository;
use PrestaShop\Module\TailoredProductsPricing\Service\AddToCartCustomizer;
use PrestaShop\Module\TailoredProductsPricing\Service\DimensionsFieldsProvider;
use PrestaShop\Module\TailoredProductsPricing\Service\ProductChoicesProvider;
use PrestaShop\Module\TailoredProductsPricing\Service\ProductConfigManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class TailoredProductsPricing extends Module
{
    const FORM_THEME = '@PrestaShop/Admin/TwigTemplateForm/prestashop_ui_kit_base.html.twig';

    public function __construct()
    {
        $this->name = 'tailoredproductspricing';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'Pentalux';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Tailored Products Pricing', [], 'Modules.Tailoredproductspricing.Admin');
        $this->description = $this->trans(
            'Let customers define custom dimensions (width × height) for each product — price is calculated automatically.',
            [],
            'Modules.Tailoredproductspricing.Admin'
        );

        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        return (new Installer())->install($this);
    }

    public function uninstall()
    {
        (new Installer())->uninstall();

        return parent::uninstall();
    }

    // -------------------------------------------------------------------------
    // Admin product form — Details tab
    // -------------------------------------------------------------------------

    public function hookActionProductFormBuilderModifier(array $params): void
    {
        $productFormModifier = $this->get(ProductFormModifier::class);
        $productFormModifier->modify((int) ($params['id'] ?? 0), $params['form_builder']);
    }

    /**
     * Pre-fills the Details-tab fields with the stored configuration when the
     * product edit form is loaded.
     */
    public function hookActionProductFormDataProviderData(array $params): void
    {
        $idProduct = (int) ($params['id'] ?? 0);

        $params['data']['details']['tpp_settings'] = $this->get(ProductConfigManager::class)->toFormData($idProduct);
    }

    /**
     * Persists the Details-tab fields after the product edit form is saved.
     */
    public function hookActionAfterUpdateProductFormHandler(array $params): void
    {
        $idProduct = (int) ($params['id'] ?? 0);
        $tpp = $params['form_data']['details']['tpp_settings'] ?? [];

        $this->get(ProductConfigManager::class)->saveFromFormData($idProduct, $tpp);
    }

    // -------------------------------------------------------------------------
    // Combination modal — price per sqm per combination
    // -------------------------------------------------------------------------

    public function hookActionCombinationFormFormBuilderModifier(array $params): void
    {
        $this->get(CombinationFormModifier::class)->modify(
            (int) ($params['id'] ?? 0),
            $params['form_builder'],
            $params['data'] ?? []
        );
    }

    public function hookActionCombinationFormFormDataProviderData(array $params): void
    {
        $params['data']['tpp_price_per_sqm'] = $this->get(CombinationConfigRepository::class)
            ->getPricePerSqm((int) ($params['id'] ?? 0));
    }

    public function hookActionAfterUpdateCombinationFormFormHandler(array $params): void
    {
        $this->get(CombinationConfigRepository::class)->save(
            (int) ($params['id'] ?? 0),
            (float) ($params['form_data']['tpp_price_per_sqm'] ?? 0)
        );
    }

    // -------------------------------------------------------------------------
    // Front office — product page dimensions form
    // -------------------------------------------------------------------------

    public function hookDisplayProductCustomizationTop(array $params): string
    {
        $idProduct = (int) ($params['product']['id'] ?? $params['product']['id_product'] ?? 0);

        $data = $this->get(DimensionsFieldsProvider::class)->getViewData($idProduct);
        if ($data === null) {
            return '';
        }

        $data['ajaxUrl'] = $this->context->link->getModuleLink($this->name, 'ajax');

        $this->context->smarty->assign($data);

        return $this->display(__FILE__, 'product-dimensions.tpl');
    }

    public function hookDisplayProductCustomizationBottom(array $params): string
    {
        $idProduct = (int) ($params['product']['id'] ?? $params['product']['id_product'] ?? 0);

        $data = $this->get(ProductChoicesProvider::class)->getViewData(
            $idProduct,
            (int) $this->context->language->id,
            (int) $this->context->shop->id
        );
        if ($data === null) {
            return '';
        }

        $this->context->smarty->assign($data);

        return $this->display(__FILE__, 'product-choices.tpl');
    }

    public function hookActionFrontControllerSetMedia(): void
    {
        if ('product' !== $this->context->controller->php_self) {
            return;
        }

        $idProduct = (int) Tools::getValue('id_product');
        if ($idProduct <= 0 || $this->get(ProductConfigRepository::class)->findByProductId($idProduct) === null) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'tailoredproductspricing-product-customization',
            'modules/' . $this->name . '/views/css/product-customization.css',
            ['media' => 'all', 'priority' => 200]
        );

        $this->context->controller->registerJavascript(
            'tailoredproductspricing-dimensions-price',
            'modules/' . $this->name . '/views/js/dimensions-price.js',
            ['position' => 'bottom', 'priority' => 200]
        );
    }

    // -------------------------------------------------------------------------
    // Front office — add-to-cart interception (single-request customization)
    // -------------------------------------------------------------------------

    public function hookActionCartControllerInitAfter(array $params): void
    {
        $this->get(AddToCartCustomizer::class)->handle($params['cart']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getProductConfig(int $idProduct): ?array
    {
        return $this->get(ProductConfigManager::class)->getConfigArray($idProduct);
    }
}
