<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Owns the lifecycle of the native `customization_field` rows (Width,
 * Height, Cord side, Cassette, Mechanism color) that carry a tailored
 * product's entered choices (spec Iteration 6, D1/6-E), so the merchant
 * never touches PrestaShop's own Customization tab.
 *
 * Deliberately a **dedicated DBAL adapter** — parameterized `Connection`
 * writes to core-owned tables (`customization_field`, `customization_field_lang`,
 * `product`, `product_shop`) — and **not** the CQRS
 * `SetProductCustomizationFieldsCommand` (6-E): the command sets a product's
 * *entire* customization-field set and recomputes `customizable`/counters in
 * ways that would fight this module's fine-grained requirements (forced
 * `is_module=1`, `required=0`, `customizable=1` never `2`, non-destructive
 * coexistence with independent merchant fields on disable). This is the
 * module's sanctioned persistence-boundary adapter for this concern —
 * confined to this single class, per the module layering rule.
 *
 * Triggered from the existing `actionAfterUpdateProductFormHandler` hook via
 * `ProductConfigManager::saveFromFormData()` — no new hook.
 */
final class CustomizationFieldRegistry
{
    private const TYPE_TEXTFIELD = 1; // Product::CUSTOMIZE_TEXTFIELD
    private const TRANSLATION_DOMAIN = 'Modules.Tailoredproductspricing.Admin';

    /**
     * Slug => `tpp_product_config` column holding the field id. Keyed map
     * instead of a positional tuple: a five-element positional array plus a
     * five-argument setter is a silent-transposition bug waiting to happen.
     */
    private const FIELD_COLUMNS = [
        'width' => 'id_customization_field_width',
        'height' => 'id_customization_field_height',
        'cord_side' => 'id_customization_field_cord_side',
        'cassette' => 'id_customization_field_cassette',
        'mechanism_color' => 'id_customization_field_mechanism_color',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Ensures the three unconditional `is_module=1` Width/Height/Cord-side
     * fields exist for $idProduct, plus a fourth field (Cassette) gated on
     * $withCassette and a fifth (Mechanism color) gated on
     * $withMechanismColor. Idempotent: reuses the ids already stored on
     * `tpp_product_config` when the referenced rows still exist and are
     * live; otherwise (re)provisions a fresh field. A false gate soft-deletes
     * any stored field for that slot instead.
     *
     * Also sets `product.customizable = 1` (never `2`) and keeps
     * `text_fields` / `uploadable_files` counters consistent.
     */
    public function register(int $idProduct, bool $withCassette, bool $withMechanismColor): void
    {
        $stored = $this->getStoredFieldIds($idProduct);

        $ids = [
            'width' => $this->resolveLiveFieldId($stored['width'], $idProduct),
            'height' => $this->resolveLiveFieldId($stored['height'], $idProduct),
            'cord_side' => $this->resolveLiveFieldId($stored['cord_side'], $idProduct),
        ];

        if ($ids['width'] === null) {
            $ids['width'] = $this->createField($idProduct, 'Width');
        }

        if ($ids['height'] === null) {
            $ids['height'] = $this->createField($idProduct, 'Height');
        }

        if ($ids['cord_side'] === null) {
            $ids['cord_side'] = $this->createField($idProduct, 'Cord side');
        }

        if ($withCassette) {
            $ids['cassette'] = $this->resolveLiveFieldId($stored['cassette'], $idProduct)
                ?? $this->createField($idProduct, 'Cassette');
        } else {
            $this->softDeleteFields($idProduct, array_values(array_filter([$stored['cassette']])));
            $ids['cassette'] = null;
        }

        if ($withMechanismColor) {
            $ids['mechanism_color'] = $this->resolveLiveFieldId($stored['mechanism_color'], $idProduct)
                ?? $this->createField($idProduct, 'Mechanism color');
        } else {
            $this->softDeleteFields($idProduct, array_values(array_filter([$stored['mechanism_color']])));
            $ids['mechanism_color'] = null;
        }

        $this->setStoredFieldIds($idProduct, $ids);
        $this->setCustomizable($idProduct, 1);
        $this->refreshCustomizationCounters($idProduct);
    }

    /**
     * Soft-deletes (`is_deleted = 1`) the module's fields — never
     * hard-delete (6-C) — clears the stored id references, and recomputes
     * `product.customizable` from whatever live (non-deleted) customization
     * fields remain for the product, rather than blindly zeroing it: an
     * independent, non-module customization field the merchant added
     * separately must survive untouched (6-F).
     */
    public function unregister(int $idProduct): void
    {
        $stored = $this->getStoredFieldIds($idProduct);

        $this->softDeleteFields($idProduct, array_values(array_filter($stored)));

        $this->setStoredFieldIds($idProduct, array_fill_keys(array_keys(self::FIELD_COLUMNS), null));
        $this->recomputeCustomizableFromRemainingFields($idProduct);
        $this->refreshCustomizationCounters($idProduct);
    }

    /**
     * @return array<string, int|null> keyed by {@see self::FIELD_COLUMNS} slug
     */
    private function getStoredFieldIds(int $idProduct): array
    {
        $row = $this->connection->createQueryBuilder()
            ->select(...array_values(self::FIELD_COLUMNS))
            ->from(_DB_PREFIX_ . 'tpp_product_config')
            ->where('id_product = :idProduct')
            ->setParameter('idProduct', $idProduct)
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return array_fill_keys(array_keys(self::FIELD_COLUMNS), null);
        }

        $ids = [];
        foreach (self::FIELD_COLUMNS as $slug => $column) {
            $ids[$slug] = $row[$column] !== null ? (int) $row[$column] : null;
        }

        return $ids;
    }

    /**
     * @param array<string, int|null> $ids keyed by {@see self::FIELD_COLUMNS} slug
     */
    private function setStoredFieldIds(int $idProduct, array $ids): void
    {
        $data = [];
        foreach (self::FIELD_COLUMNS as $slug => $column) {
            $data[$column] = $ids[$slug] ?? null;
        }

        $this->connection->update(_DB_PREFIX_ . 'tpp_product_config', $data, ['id_product' => $idProduct]);
    }

    /**
     * @param list<int> $ids
     */
    private function softDeleteFields(int $idProduct, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->connection->createQueryBuilder()
            ->update(_DB_PREFIX_ . 'customization_field')
            ->set('is_deleted', ':deleted')
            ->where('id_product = :idProduct')
            ->andWhere('id_customization_field IN (:ids)')
            ->setParameter('deleted', 1)
            ->setParameter('idProduct', $idProduct)
            ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY)
            ->executeStatement();
    }

    /**
     * Returns $storedId if it still points at a live (`is_deleted = 0`)
     * `is_module = 1` field belonging to $idProduct, null otherwise
     * (nothing stored, or the row is missing/soft-deleted — provision a
     * fresh one).
     */
    private function resolveLiveFieldId(?int $storedId, int $idProduct): ?int
    {
        if ($storedId === null) {
            return null;
        }

        $id = $this->connection->createQueryBuilder()
            ->select('id_customization_field')
            ->from(_DB_PREFIX_ . 'customization_field')
            ->where('id_customization_field = :id')
            ->andWhere('id_product = :idProduct')
            ->andWhere('is_module = :isModule')
            ->andWhere('is_deleted = :isDeleted')
            ->setParameter('id', $storedId)
            ->setParameter('idProduct', $idProduct)
            ->setParameter('isModule', 1)
            ->setParameter('isDeleted', 0)
            ->executeQuery()
            ->fetchOne();

        return $id !== false ? (int) $id : null;
    }

    /**
     * Inserts one `customization_field` row (`is_module=1`, `required=0`,
     * `type=CUSTOMIZE_TEXTFIELD`) plus its `customization_field_lang` rows
     * for every active language x shop, and returns the new field id.
     */
    private function createField(int $idProduct, string $englishLabel): int
    {
        $this->connection->insert(_DB_PREFIX_ . 'customization_field', [
            'id_product' => $idProduct,
            'type' => self::TYPE_TEXTFIELD,
            'required' => 0,
            'is_module' => 1,
            'is_deleted' => 0,
        ]);

        $fieldId = (int) $this->connection->lastInsertId();

        foreach ($this->getActiveLanguages() as $language) {
            $label = $this->translator->trans($englishLabel, [], self::TRANSLATION_DOMAIN, $language['locale']);

            foreach ($this->getActiveShopIds() as $idShop) {
                $this->connection->insert(_DB_PREFIX_ . 'customization_field_lang', [
                    'id_customization_field' => $fieldId,
                    'id_lang' => $language['id_lang'],
                    'id_shop' => $idShop,
                    'name' => $label,
                ]);
            }
        }

        return $fieldId;
    }

    /**
     * @return list<array{id_lang: int, locale: string}>
     */
    private function getActiveLanguages(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id_lang', 'locale')
            ->from(_DB_PREFIX_ . 'lang')
            ->where('active = :active')
            ->setParameter('active', 1)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => ['id_lang' => (int) $row['id_lang'], 'locale' => (string) $row['locale']],
            $rows
        );
    }

    /**
     * @return list<int>
     */
    private function getActiveShopIds(): array
    {
        $ids = $this->connection->createQueryBuilder()
            ->select('id_shop')
            ->from(_DB_PREFIX_ . 'shop')
            ->where('active = :active')
            ->setParameter('active', 1)
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map('intval', $ids);
    }

    private function setCustomizable(int $idProduct, int $value): void
    {
        $this->connection->update(_DB_PREFIX_ . 'product', ['customizable' => $value], ['id_product' => $idProduct]);
        $this->connection->update(_DB_PREFIX_ . 'product_shop', ['customizable' => $value], ['id_product' => $idProduct]);
    }

    /**
     * Counts the customization fields still live for $idProduct
     * (`is_deleted = 0`, any `is_module`) after a soft-delete and sets
     * `product.customizable` accordingly: `0` if none remain, `1` if some
     * remain and none are required, `2` if any remaining field is
     * `required = 1` — matching core's own convention. Never blindly zeros
     * `customizable` while other live fields (e.g. independent merchant
     * customization) exist.
     */
    private function recomputeCustomizableFromRemainingFields(int $idProduct): void
    {
        $remaining = $this->connection->createQueryBuilder()
            ->select('required')
            ->from(_DB_PREFIX_ . 'customization_field')
            ->where('id_product = :idProduct')
            ->andWhere('is_deleted = :isDeleted')
            ->setParameter('idProduct', $idProduct)
            ->setParameter('isDeleted', 0)
            ->executeQuery()
            ->fetchFirstColumn();

        if ($remaining === []) {
            $this->setCustomizable($idProduct, 0);

            return;
        }

        $hasRequiredField = in_array(1, array_map('intval', $remaining), true);
        $this->setCustomizable($idProduct, $hasRequiredField ? 2 : 1);
    }

    /**
     * Keeps `product.text_fields` / `product.uploadable_files` (and their
     * `product_shop` counterparts) consistent with the live
     * `customization_field` rows, mirroring how core itself derives these
     * counters (`Product::CUSTOMIZE_TEXTFIELD` / `Product::CUSTOMIZE_FILE`
     * counts).
     */
    private function refreshCustomizationCounters(int $idProduct): void
    {
        $counts = $this->connection->createQueryBuilder()
            ->select('type', 'COUNT(id_customization_field) as cnt')
            ->from(_DB_PREFIX_ . 'customization_field')
            ->where('id_product = :idProduct')
            ->andWhere('is_deleted = :isDeleted')
            ->setParameter('idProduct', $idProduct)
            ->setParameter('isDeleted', 0)
            ->groupBy('type')
            ->executeQuery()
            ->fetchAllAssociative();

        $textFields = 0;
        $uploadableFiles = 0;

        foreach ($counts as $row) {
            if ((int) $row['type'] === self::TYPE_TEXTFIELD) {
                $textFields = (int) $row['cnt'];
            } else {
                $uploadableFiles = (int) $row['cnt'];
            }
        }

        $data = ['text_fields' => $textFields, 'uploadable_files' => $uploadableFiles];
        $this->connection->update(_DB_PREFIX_ . 'product', $data, ['id_product' => $idProduct]);
        $this->connection->update(_DB_PREFIX_ . 'product_shop', $data, ['id_product' => $idProduct]);
    }
}
