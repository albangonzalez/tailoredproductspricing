<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Owns the lifecycle of the two native `customization_field` rows (Width,
 * Height) that carry a tailored product's entered dimensions (spec
 * Iteration 6, D1/6-E), so the merchant never touches PrestaShop's own
 * Customization tab.
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

    public function __construct(
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Ensures the three unconditional `is_module=1` Width/Height/Cord-side
     * fields exist for $idProduct, plus a fourth, optional Cassette field
     * gated on $withCassette. Idempotent: reuses the ids already stored on
     * `tpp_product_config` when the referenced rows still exist and are
     * live; otherwise (re)provisions a fresh field. $withCassette === false
     * soft-deletes any stored cassette field instead.
     *
     * Also sets `product.customizable = 1` (never `2`) and keeps
     * `text_fields` / `uploadable_files` counters consistent.
     */
    public function register(int $idProduct, bool $withCassette): void
    {
        [$storedWidthId, $storedHeightId, $storedCordSideId, $storedCassetteId] = $this->getStoredFieldIds($idProduct);

        $widthId = $this->resolveLiveFieldId($storedWidthId, $idProduct);
        $heightId = $this->resolveLiveFieldId($storedHeightId, $idProduct);
        $cordSideId = $this->resolveLiveFieldId($storedCordSideId, $idProduct);

        if ($widthId === null) {
            $widthId = $this->createField($idProduct, 'Width');
        }

        if ($heightId === null) {
            $heightId = $this->createField($idProduct, 'Height');
        }

        if ($cordSideId === null) {
            $cordSideId = $this->createField($idProduct, 'Cord side');
        }

        if ($withCassette) {
            $cassetteId = $this->resolveLiveFieldId($storedCassetteId, $idProduct)
                ?? $this->createField($idProduct, 'Cassette');
        } else {
            $this->softDeleteFields($idProduct, array_values(array_filter([$storedCassetteId])));
            $cassetteId = null;
        }

        $this->setStoredFieldIds($idProduct, $widthId, $heightId, $cordSideId, $cassetteId);
        $this->setCustomizable($idProduct, 1);
        $this->refreshCustomizationCounters($idProduct);
    }

    /**
     * Soft-deletes (`is_deleted = 1`) the module's four fields — never
     * hard-delete (6-C) — clears the stored id references, and recomputes
     * `product.customizable` from whatever live (non-deleted) customization
     * fields remain for the product, rather than blindly zeroing it: an
     * independent, non-module customization field the merchant added
     * separately must survive untouched (6-F).
     */
    public function unregister(int $idProduct): void
    {
        [$storedWidthId, $storedHeightId, $storedCordSideId, $storedCassetteId] = $this->getStoredFieldIds($idProduct);

        $this->softDeleteFields(
            $idProduct,
            array_values(array_filter([$storedWidthId, $storedHeightId, $storedCordSideId, $storedCassetteId]))
        );

        $this->setStoredFieldIds($idProduct, null, null, null, null);
        $this->recomputeCustomizableFromRemainingFields($idProduct);
        $this->refreshCustomizationCounters($idProduct);
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: int|null, 3: int|null}
     */
    private function getStoredFieldIds(int $idProduct): array
    {
        $row = $this->connection->createQueryBuilder()
            ->select(
                'id_customization_field_width',
                'id_customization_field_height',
                'id_customization_field_cord_side',
                'id_customization_field_cassette'
            )
            ->from(_DB_PREFIX_ . 'tpp_product_config')
            ->where('id_product = :idProduct')
            ->setParameter('idProduct', $idProduct)
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return [null, null, null, null];
        }

        return [
            $row['id_customization_field_width'] !== null ? (int) $row['id_customization_field_width'] : null,
            $row['id_customization_field_height'] !== null ? (int) $row['id_customization_field_height'] : null,
            $row['id_customization_field_cord_side'] !== null ? (int) $row['id_customization_field_cord_side'] : null,
            $row['id_customization_field_cassette'] !== null ? (int) $row['id_customization_field_cassette'] : null,
        ];
    }

    private function setStoredFieldIds(int $idProduct, ?int $widthId, ?int $heightId, ?int $cordSideId, ?int $cassetteId): void
    {
        $this->connection->update(
            _DB_PREFIX_ . 'tpp_product_config',
            [
                'id_customization_field_width' => $widthId,
                'id_customization_field_height' => $heightId,
                'id_customization_field_cord_side' => $cordSideId,
                'id_customization_field_cassette' => $cassetteId,
            ],
            ['id_product' => $idProduct]
        );
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
