<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Install;

use PrestaShop\Module\TailoredProductsPricing\Sql\SqlQueries;

final class Installer
{
    /**
     * @var list<string>
     */
    private const HOOKS = [
        'actionProductFormBuilderModifier',
        'actionProductFormDataProviderData',
        'actionAfterUpdateProductFormHandler',
        'actionCombinationFormFormBuilderModifier',
        'actionCombinationFormFormDataProviderData',
        'actionAfterUpdateCombinationFormFormHandler',
        'displayProductCustomization',
        'actionFrontControllerSetMedia',
        'actionCartControllerInitAfter',
    ];

    public function install(\Module $module): bool
    {
        if (!$this->installDatabase()) {
            return false;
        }

        return $this->registerHooks($module);
    }

    public function uninstall(): bool
    {
        return $this->uninstallDatabase();
    }

    private function installDatabase(): bool
    {
        return $this->executeQueries(SqlQueries::installQueries());
    }

    private function uninstallDatabase(): bool
    {
        return $this->executeQueries(SqlQueries::uninstallQueries());
    }

    private function registerHooks(\Module $module): bool
    {
        foreach (self::HOOKS as $hook) {
            if (!$module->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $queries
     */
    private function executeQueries(array $queries): bool
    {
        foreach ($queries as $query) {
            if (!\Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }
}
