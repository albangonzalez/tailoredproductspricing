<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Service;

use PrestaShop\Module\TailoredProducts\Adapter\ColorAttributeProvider;
use PrestaShop\Module\TailoredProducts\Entity\TppProductConfig;
use PrestaShop\Module\TailoredProducts\Repository\ProductConfigRepository;

/**
 * Owns all `tpp_product_config` business logic: unit allow-list validation,
 * numeric normalization, find-or-create/delete, and entity <-> array
 * mapping for the product Details-tab hook flow. Pulled out of the module
 * class so the hook methods stay thin delegations.
 */
final class ProductConfigManager
{
    private const ALLOWED_UNITS = ['cm', 'mm'];
    private const DEFAULT_UNIT = 'cm';

    public function __construct(
        private readonly ProductConfigRepository $repository,
        private readonly CustomizationFieldRegistry $customizationFieldRegistry,
        private readonly ColorAttributeProvider $colorAttributeProvider,
        private readonly int $contextShopId
    ) {
    }

    /**
     * Builds the `details.tpp_settings.*` array shape expected by the
     * product form data-provider hook.
     */
    public function toFormData(int $idProduct): array
    {
        $config = $idProduct ? $this->repository->findByProductId($idProduct) : null;

        return [
            'tpp_enabled' => $config ? 1 : 0,
            'tpp_unit' => $config ? $config->getUnit() : self::DEFAULT_UNIT,
            'tpp_min_width' => $config ? $config->getMinWidth() : null,
            'tpp_max_width' => $config ? $config->getMaxWidth() : null,
            'tpp_min_height' => $config ? $config->getMinHeight() : null,
            'tpp_max_height' => $config ? $config->getMaxHeight() : null,
            'tpp_cassette_enabled' => ($config && $config->getCassettePricePerMeter() !== null) ? 1 : 0,
            'tpp_cassette_price' => $config ? $config->getCassettePricePerMeter() : null,
            'tpp_mechanism_color_group' => $config ? $config->getIdAttributeGroupMechanismColor() : null,
            'tpp_roll_direction_enabled' => ($config && $config->isRollDirectionEnabled()) ? 1 : 0,
        ];
    }

    /**
     * Persists (or deletes, if disabled) the Details-tab form data after
     * the product edit form is saved.
     */
    public function saveFromFormData(int $idProduct, array $tppSettings): void
    {
        if (!$idProduct) {
            return;
        }

        $enabled = (bool) ($tppSettings['tpp_enabled'] ?? false);

        if (!$enabled) {
            // Unregister the module's customization fields (and clear the
            // stored ids) before the row itself is removed, so the
            // registry can still resolve which fields belong to it.
            $this->customizationFieldRegistry->unregister($idProduct);
            $this->repository->removeByProductId($idProduct);

            return;
        }

        $unit = in_array($tppSettings['tpp_unit'] ?? self::DEFAULT_UNIT, self::ALLOWED_UNITS, true)
            ? $tppSettings['tpp_unit']
            : self::DEFAULT_UNIT;

        $config = $this->repository->findByProductId($idProduct) ?? new TppProductConfig($idProduct);

        $config
            ->setMinWidth($this->nullableDecimal($tppSettings['tpp_min_width'] ?? null))
            ->setMaxWidth($this->nullableDecimal($tppSettings['tpp_max_width'] ?? null))
            ->setMinHeight($this->nullableDecimal($tppSettings['tpp_min_height'] ?? null))
            ->setMaxHeight($this->nullableDecimal($tppSettings['tpp_max_height'] ?? null))
            ->setUnit($unit)
            ->setCassettePricePerMeter($this->cassettePrice($tppSettings))
            ->setIdAttributeGroupMechanismColor($this->mechanismColorGroupId($tppSettings))
            ->setRollDirectionEnabled((bool) ($tppSettings['tpp_roll_direction_enabled'] ?? false));

        // Persist the base settings first so the `tpp_product_config` row
        // exists for the registry to record the resolved field ids onto.
        $this->repository->save($config);

        $this->customizationFieldRegistry->register($idProduct, [
            'cassette' => $config->getCassettePricePerMeter() !== null,
            'mechanism_color' => $config->getIdAttributeGroupMechanismColor() !== null,
            'roll_direction' => $config->isRollDirectionEnabled(),
        ]);
    }

    /**
     * Returns the legacy `SELECT *` row shape for the module's public
     * `getProductConfig()` API.
     */
    public function getConfigArray(int $idProduct): ?array
    {
        if (!$idProduct) {
            return null;
        }

        $config = $this->repository->findByProductId($idProduct);
        if ($config === null) {
            return null;
        }

        return [
            'id_product' => $config->getIdProduct(),
            'min_width' => $config->getMinWidth(),
            'max_width' => $config->getMaxWidth(),
            'min_height' => $config->getMinHeight(),
            'max_height' => $config->getMaxHeight(),
            'unit' => $config->getUnit(),
            'cassette_price_per_meter' => $config->getCassettePricePerMeter(),
            'id_attribute_group_mechanism_color' => $config->getIdAttributeGroupMechanismColor(),
            'roll_direction_enabled' => $config->isRollDirectionEnabled(),
        ];
    }

    /**
     * Normalizes a legacy-locale numeric input (which may use a comma as
     * decimal separator) into a decimal string suitable for the entity's
     * `decimal` column, or null when empty. Reuses the comma->dot logic
     * previously inlined on the module class verbatim.
     */
    private function nullableDecimal($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return (string) (float) str_replace(',', '.', $value);
    }

    /**
     * Enforces the switch state <=> column nullness invariant: off stores
     * null, on always stores a non-null decimal (blank/negative/non-numeric
     * input clamps to '0.00' rather than leaving the column null while the
     * switch is on).
     */
    private function cassettePrice(array $tppSettings): ?string
    {
        if (!($tppSettings['tpp_cassette_enabled'] ?? false)) {
            return null;
        }

        $price = $this->nullableDecimal($tppSettings['tpp_cassette_price'] ?? null);
        if ($price === null || (float) $price < 0) {
            return '0.00';
        }

        return $price;
    }

    /**
     * Normalizes the picked color attribute group id: empty/0 => null (choice
     * not offered), otherwise the id, but only if it still resolves to a live
     * `group_type = 'color'` group in this shop. Anything else is stored as
     * null rather than dangling — the module never trusts an id it did not
     * just re-read from core.
     */
    private function mechanismColorGroupId(array $tppSettings): ?int
    {
        $idGroup = (int) ($tppSettings['tpp_mechanism_color_group'] ?? 0);
        if ($idGroup <= 0) {
            return null;
        }

        return $this->colorAttributeProvider->isColorGroup($idGroup, $this->contextShopId) ? $idGroup : null;
    }
}
