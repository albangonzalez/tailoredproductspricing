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
        'displayProductCustomizationTop',
        'displayProductCustomizationBottom',
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
        if (!$this->deregisterCustomizationFields()) {
            return false;
        }

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

    /**
     * Soft-deletes the module's customization fields, then resets
     * customizable/text_fields/uploadable_files to 0 for every affected product.
     */
    private function deregisterCustomizationFields(): bool
    {
        $db = \Db::getInstance();
        $prefix = _DB_PREFIX_;

        $softDeleted = $db->execute(
            'UPDATE `' . $prefix . 'customization_field` cf
             INNER JOIN `' . $prefix . 'tpp_product_config` c ON cf.`id_product` = c.`id_product`
             SET cf.`is_deleted` = 1
             WHERE cf.`is_deleted` = 0
             AND cf.`id_customization_field` IN (c.`id_customization_field_width`, c.`id_customization_field_height`, c.`id_customization_field_cord_side`, c.`id_customization_field_cassette`, c.`id_customization_field_mechanism_color`, c.`id_customization_field_roll_direction`)'
        );

        if (!$softDeleted) {
            return false;
        }

        $resetData = [
            'customizable' => 0,
            'text_fields' => 0,
            'uploadable_files' => 0,
        ];

        return $this->resetProductCustomizations($db, $prefix, 'product', $resetData)
            && $this->resetProductCustomizations($db, $prefix, 'product_shop', $resetData);
    }

    /**
     * @param array<string, int> $resetData
     *
     * Applies $resetData columns via a set-based UPDATE joined on tpp_product_config,
     * scoped to a single table (product or product_shop).
     */
    private function resetProductCustomizations(\Db $db, string $prefix, string $table, array $resetData): bool
    {
        $set = implode(', ', array_map(
            static fn (string $column, int $value): string => '`' . $table . '`.`' . $column . '` = ' . $value,
            array_keys($resetData),
            array_values($resetData)
        ));

        return $db->execute(
            'UPDATE `' . $prefix . $table . '` `' . $table . '`
             INNER JOIN `' . $prefix . 'tpp_product_config` c ON c.`id_product` = `' . $table . '`.`id_product`
             SET ' . $set
        );
    }
}
