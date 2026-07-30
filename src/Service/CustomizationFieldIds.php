<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

/**
 * Immutable pair of the two module-provisioned `customization_field` ids
 * (Width, Height) resolved/created by {@see CustomizationFieldProvisioner}.
 *
 * `ProductConfigManager` records these into `tpp_product_config`
 * (`id_customization_field_width` / `..._height`, spec 6-D); the FO form
 * emits `name="customization[<id>]"` against them.
 */
final class CustomizationFieldIds
{
    public function __construct(
        public readonly int $widthFieldId,
        public readonly int $heightFieldId,
    ) {
    }
}
