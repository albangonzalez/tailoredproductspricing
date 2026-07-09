<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use PrestaShop\Module\TailoredProductsPricing\Entity\TppProductConfig;
use PrestaShop\Module\TailoredProductsPricing\Repository\ProductConfigRepository;

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
        private readonly ProductConfigRepository $repository
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
            ->setUnit($unit);

        $this->repository->save($config);
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
}
