CREATE TABLE IF NOT EXISTS `PREFIX_tpp_product_config` (
  `id_product` int(10) unsigned NOT NULL,
  `min_width` decimal(10,2) unsigned DEFAULT NULL,
  `max_width` decimal(10,2) unsigned DEFAULT NULL,
  `min_height` decimal(10,2) unsigned DEFAULT NULL,
  `max_height` decimal(10,2) unsigned DEFAULT NULL,
  `unit` varchar(8) NOT NULL DEFAULT 'cm',
  `id_customization_field_width` int(10) unsigned DEFAULT NULL,
  `id_customization_field_height` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_product`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_tpp_combination_config` (
  `id_product_attribute` int(10) unsigned NOT NULL,
  `price_per_sqm` decimal(20,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id_product_attribute`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;
