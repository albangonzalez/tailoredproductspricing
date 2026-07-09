<?php

use PrestaShop\Module\TailoredProductsPricing\Form\Modifier\CombinationFormModifier;
use PrestaShop\Module\TailoredProductsPricing\Form\Modifier\ProductFormModifier;
use PrestaShop\Module\TailoredProductsPricing\Repository\CombinationConfigRepository;
use PrestaShop\Module\TailoredProductsPricing\Service\ProductConfigManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class TailoredProductsPricing extends Module
{
    const INSTALL_SQL_FILE = 'sql/install.sql';
    const UNINSTALL_SQL_FILE = 'sql/uninstall.sql';

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
        if (!$this->runSqlFile(self::INSTALL_SQL_FILE)) {
            return false;
        }

        return parent::install()
            && $this->registerHook('actionProductFormBuilderModifier')
            && $this->registerHook('actionProductFormDataProviderData')
            && $this->registerHook('actionAfterUpdateProductFormHandler')
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
    // Helpers
    // -------------------------------------------------------------------------

    public function getProductConfig(int $idProduct): ?array
    {
        return $this->get(ProductConfigManager::class)->getConfigArray($idProduct);
    }
}
