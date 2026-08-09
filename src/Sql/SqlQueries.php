<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Sql;

final class SqlQueries
{
    /**
     * @return list<string>
     */
    public static function installQueries(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'tpp_product_config` (
              `id_product` int(10) unsigned NOT NULL,
              `min_width` decimal(10,2) unsigned DEFAULT NULL,
              `max_width` decimal(10,2) unsigned DEFAULT NULL,
              `min_height` decimal(10,2) unsigned DEFAULT NULL,
              `max_height` decimal(10,2) unsigned DEFAULT NULL,
              `unit` varchar(8) NOT NULL DEFAULT \'cm\',
              `id_customization_field_width` int(10) unsigned DEFAULT NULL,
              `id_customization_field_height` int(10) unsigned DEFAULT NULL,
              `id_customization_field_cord_side` int(10) unsigned DEFAULT NULL,
              `cassette_price_per_meter` decimal(20,2) DEFAULT NULL,
              `id_customization_field_cassette` int(10) unsigned DEFAULT NULL,
              `id_attribute_group_mechanism_color` int(10) unsigned DEFAULT NULL,
              `id_customization_field_mechanism_color` int(10) unsigned DEFAULT NULL,
              PRIMARY KEY (`id_product`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'tpp_combination_config` (
              `id_product_attribute` int(10) unsigned NOT NULL,
              `price_per_sqm` decimal(20,2) NOT NULL DEFAULT \'0.00\',
              PRIMARY KEY (`id_product_attribute`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;',
        ];
    }

    /**
     * @return list<string>
     */
    public static function uninstallQueries(): array
    {
        return [
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'tpp_combination_config`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'tpp_product_config`',
        ];
    }
}
