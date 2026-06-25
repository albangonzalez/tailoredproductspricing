<?php

use Symfony\Component\Form\Extension\Core\Type\NumberType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TailoredProductsPricing extends Module
{
    const INSTALL_SQL_FILE = 'sql/install.sql';
    const UNINSTALL_SQL_FILE = 'sql/uninstall.sql';

    public function __construct()
    {
        $this->name = 'tailoredproductspricing';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'Alban González';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Tailored Products Pricing', [], 'Modules.Tailoredproductspricing.Admin');
        $this->description = $this->trans(
            'Let customers define custom dimensions (width × height) for each product — price is calculated automatically.',
            [],
            'Modules.Tailoredproductspricing.Admin'
        );

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!$this->runSqlFile(self::INSTALL_SQL_FILE)) {
            return false;
        }

        return parent::install()
            && $this->registerHook('displayAdminProductsExtra')
            && $this->registerHook('actionProductSave')
            && $this->registerHook('actionCombinationFormFormBuilderModifier')
            && $this->registerHook('actionCombinationFormFormDataProviderData')
            && $this->registerHook('actionAfterUpdateCombinationFormFormHandler');
    }

    public function uninstall()
    {
        $this->runSqlFile(self::UNINSTALL_SQL_FILE);

        return parent::uninstall();
    }

    private function runSqlFile(string $file): bool
    {
        $path = dirname(__FILE__) . '/' . $file;
        if (!file_exists($path)) {
            return false;
        }

        $sql = file_get_contents($path);
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $queries = preg_split("/;\s*[\r\n]+/", trim($sql));

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query && !Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Admin product tab
    // -------------------------------------------------------------------------

    public function hookDisplayAdminProductsExtra($params)
    {
        $idProduct = (int) ($params['id_product'] ?? 0);
        $config = $this->getProductConfig($idProduct);

        $this->context->smarty->assign([
            'tpp_config' => $config,
            'tpp_id_product' => $idProduct,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/product_tab.tpl');
    }

    public function hookActionProductSave($params)
    {
        $idProduct = (int) ($params['id_product'] ?? 0);
        if (!$idProduct) {
            return;
        }

        $enabled = (bool) Tools::getValue('tpp_enabled');

        if (!$enabled) {
            Db::getInstance()->delete('tpp_product_config', 'id_product = ' . $idProduct);
            return;
        }

        $data = [
            'id_product'   => $idProduct,
            'min_width'    => $this->nullableDecimal(Tools::getValue('tpp_min_width')),
            'max_width'    => $this->nullableDecimal(Tools::getValue('tpp_max_width')),
            'min_height'   => $this->nullableDecimal(Tools::getValue('tpp_min_height')),
            'max_height'   => $this->nullableDecimal(Tools::getValue('tpp_max_height')),
            'unit'         => in_array(Tools::getValue('tpp_unit'), ['cm', 'mm']) ? Tools::getValue('tpp_unit') : 'cm',
        ];

        $exists = Db::getInstance()->getValue(
            'SELECT id_product FROM `' . _DB_PREFIX_ . 'tpp_product_config` WHERE id_product = ' . $idProduct
        );

        if ($exists) {
            Db::getInstance()->update('tpp_product_config', $data, 'id_product = ' . $idProduct);
        } else {
            Db::getInstance()->insert('tpp_product_config', $data);
        }
    }

    // -------------------------------------------------------------------------
    // Combination modal — price per sqm per combination
    // -------------------------------------------------------------------------

    public function hookActionCombinationFormFormBuilderModifier(array $params): void
    {
        // Only expose the per-combination price field when the parent product has
        // the module enabled — i.e. it has a row in tpp_product_config.
        if (!$this->isCombinationProductEnabled((int) ($params['id'] ?? 0))) {
            return;
        }

        $params['form_builder']->add('tpp_price_per_sqm', NumberType::class, [
            'label' => $this->trans('Price per m²', [], 'Modules.Tailoredproductspricing.Admin'),
            'scale' => 6,
            'attr' => [
                'placeholder' => '0.000000',
                'min' => '0',
                'step' => 'any',
            ],
            'required' => false,
            'data' => $params['data']['tpp_price_per_sqm'] ?? 0,
            'form_theme' => '@PrestaShop/Admin/TwigTemplateForm/prestashop_ui_kit_base.html.twig',
        ]);
    }

    public function hookActionCombinationFormFormDataProviderData(array $params): void
    {
        $combinationId = (int) ($params['id'] ?? 0);

        $row = Db::getInstance()->getRow(
            'SELECT price_per_sqm FROM `' . _DB_PREFIX_ . 'tpp_combination_config`
             WHERE id_product_attribute = ' . $combinationId
        );

        $params['data']['tpp_price_per_sqm'] = $row ? (float) $row['price_per_sqm'] : 0;
    }

    public function hookActionAfterUpdateCombinationFormFormHandler(array $params): void
    {
        $combinationId = (int) ($params['id'] ?? 0);
        if (!$combinationId) {
            return;
        }

        $pricePerSqm = (float) ($params['form_data']['tpp_price_per_sqm'] ?? 0);

        $exists = Db::getInstance()->getValue(
            'SELECT id_product_attribute FROM `' . _DB_PREFIX_ . 'tpp_combination_config`
             WHERE id_product_attribute = ' . $combinationId
        );

        if ($exists) {
            Db::getInstance()->update(
                'tpp_combination_config',
                ['price_per_sqm' => $pricePerSqm],
                'id_product_attribute = ' . $combinationId
            );
        } else {
            Db::getInstance()->insert('tpp_combination_config', [
                'id_product_attribute' => $combinationId,
                'price_per_sqm' => $pricePerSqm,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getProductConfig(int $idProduct): ?array
    {
        if (!$idProduct) {
            return null;
        }

        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'tpp_product_config` WHERE id_product = ' . $idProduct
        );

        return $row ?: null;
    }

    /**
     * Tells whether the module is enabled for the product behind a combination,
     * i.e. that product has a row in tpp_product_config.
     */
    private function isCombinationProductEnabled(int $idProductAttribute): bool
    {
        if (!$idProductAttribute) {
            return false;
        }

        $idProduct = (int) Db::getInstance()->getValue(
            'SELECT id_product FROM `' . _DB_PREFIX_ . 'product_attribute`
             WHERE id_product_attribute = ' . $idProductAttribute
        );

        return $idProduct > 0 && $this->getProductConfig($idProduct) !== null;
    }

    private function nullableDecimal($value): ?float
    {
        $value = trim((string) $value);
        if ($value === '' || $value === null) {
            return null;
        }
        return (float) str_replace(',', '.', $value);
    }
}
