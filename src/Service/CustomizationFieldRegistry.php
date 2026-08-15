<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\Module\TailoredProducts\Entity\TpProductCustomizationField;
use PrestaShop\Module\TailoredProducts\Repository\TpProductCustomizationFieldRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Owns the lifecycle of the native `customization_field` rows (Width,
 * Height, Cord side, Cassette, Mechanism color, Roll direction) that carry a tailored
 * product's entered choices (spec Iteration 6, D1/6-E), so the merchant
 * never touches PrestaShop's own Customization tab.
 *
 * Writes to core-owned tables (`customization_field`, `customization_field_lang`,
 * `product`, `product_shop`) go through a parameterized DBAL `Connection` —
 * and deliberately **not** the CQRS `SetProductCustomizationFieldsCommand`
 * (6-E): the command sets a product's *entire* customization-field set and
 * recomputes `customizable`/counters in ways that would fight this module's
 * fine-grained requirements (forced `is_module=1`, `required=0`,
 * `customizable=1` never `2`, non-destructive coexistence with independent
 * merchant fields on disable). This is the module's sanctioned
 * persistence-boundary adapter for the core tables — confined to this single
 * class, per the module layering rule.
 *
 * The module's own child table, `tp_product_customization_field` (which
 * records which `customization_field` id was provisioned for which
 * (product, slug)), is a Doctrine entity like the rest of this module's own
 * tables — writes to it go through the injected `EntityManagerInterface` /
 * `TpProductCustomizationFieldRepository`, never a separate DBAL write, so
 * this table is never written through two persistence mechanisms in the
 * same request. See {@see self::setStoredFieldIds()} for the explicit-flush
 * requirement this implies.
 *
 * Triggered from the existing `actionAfterUpdateProductFormHandler` hook via
 * `ProductConfigManager::saveFromFormData()` — no new hook.
 */
final class CustomizationFieldRegistry
{
    private const TYPE_TEXTFIELD = 1; // Product::CUSTOMIZE_TEXTFIELD
    private const TRANSLATION_DOMAIN = 'Modules.Tailoredproducts.Admin';

    /**
     * Public, named constants for the six field slugs — the shared source of
     * truth for both this class and any other consumer (e.g.
     * {@see \PrestaShop\Module\TailoredProducts\Service\AddToCartCustomizer})
     * that needs to select a specific slot out of a slug-keyed map. Prefer
     * these over restringing `'width'`/`'height'`/etc. elsewhere — a typo'd
     * literal fails silently to `null` (the child table has no SQL FK to
     * lean on), a typo'd constant name fails loudly at parse time.
     */
    public const SLUG_WIDTH = 'width';
    public const SLUG_HEIGHT = 'height';
    public const SLUG_CORD_SIDE = 'cord_side';
    public const SLUG_CASSETTE = 'cassette';
    public const SLUG_MECHANISM_COLOR = 'mechanism_color';
    public const SLUG_ROLL_DIRECTION = 'roll_direction';

    /**
     * The single source of truth for the six (product, slug) rows this
     * module provisions in `tp_product_customization_field`. Any code
     * needing "all six slugs" (e.g. `getStoredFieldIds()`'s default map)
     * must read this constant rather than restring the list.
     */
    private const FIELD_SLUGS = [
        self::SLUG_WIDTH,
        self::SLUG_HEIGHT,
        self::SLUG_CORD_SIDE,
        self::SLUG_CASSETTE,
        self::SLUG_MECHANISM_COLOR,
        self::SLUG_ROLL_DIRECTION,
    ];

    /** Slug => English label for the fields provisioned unconditionally. */
    private const REQUIRED_FIELD_LABELS = [
        self::SLUG_WIDTH => 'Width',
        self::SLUG_HEIGHT => 'Height',
        self::SLUG_CORD_SIDE => 'Cord side',
    ];

    /** Slug => English label for the fields provisioned only when the merchant enables them. */
    private const OPTIONAL_FIELD_LABELS = [
        self::SLUG_CASSETTE => 'Cassette',
        self::SLUG_MECHANISM_COLOR => 'Mechanism color',
        self::SLUG_ROLL_DIRECTION => 'Roll direction',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TpProductCustomizationFieldRepository $customizationFieldRepository,
    ) {
    }

    /**
     * Ensures the three unconditional `is_module=1` Width/Height/Cord-side
     * fields exist for $idProduct, plus one field per {@see self::OPTIONAL_FIELD_LABELS}
     * slug gated on `$optionalFields[$slug]` (missing key reads as false).
     * Idempotent: reuses the ids already stored in `tp_product_customization_field`
     * when the referenced rows still exist and are live; otherwise
     * (re)provisions a fresh field. A false gate soft-deletes any stored
     * field for that slot instead.
     *
     * Also sets `product.customizable = 1` (never `2`) and keeps
     * `text_fields` / `uploadable_files` counters consistent.
     *
     * @param array<string, bool> $optionalFields slug-keyed gates, see {@see self::OPTIONAL_FIELD_LABELS}
     */
    public function register(int $idProduct, array $optionalFields): void
    {
        $stored = $this->getStoredFieldIds($idProduct);

        $ids = [];

        foreach (self::REQUIRED_FIELD_LABELS as $slug => $label) {
            $ids[$slug] = $this->resolveLiveFieldId($stored[$slug], $idProduct)
                ?? $this->createField($idProduct, $label);
        }

        foreach (self::OPTIONAL_FIELD_LABELS as $slug => $label) {
            if ($optionalFields[$slug] ?? false) {
                $ids[$slug] = $this->resolveLiveFieldId($stored[$slug], $idProduct)
                    ?? $this->createField($idProduct, $label);
            } else {
                $this->softDeleteFields($idProduct, array_values(array_filter([$stored[$slug]])));
                $ids[$slug] = null;
            }
        }

        $this->setStoredFieldIds($idProduct, $ids);
        $this->setCustomizable($idProduct, 1);
        $this->refreshCustomizationCounters($idProduct);
    }

    /**
     * Soft-deletes (`is_deleted = 1`) the module's fields — never
     * hard-delete (6-C) — clears the product's `tp_product_customization_field`
     * rows, and recomputes `product.customizable` from whatever live
     * (non-deleted) customization fields remain for the product, rather than
     * blindly zeroing it: an independent, non-module customization field the
     * merchant added separately must survive untouched (6-F).
     */
    public function unregister(int $idProduct): void
    {
        $stored = $this->getStoredFieldIds($idProduct);

        $this->softDeleteFields($idProduct, array_values(array_filter($stored)));

        $this->setStoredFieldIds($idProduct, array_fill_keys(self::FIELD_SLUGS, null));
        $this->recomputeCustomizableFromRemainingFields($idProduct);
        $this->refreshCustomizationCounters($idProduct);
    }

    /**
     * @return array<string, int|null> keyed by {@see self::FIELD_SLUGS}
     */
    private function getStoredFieldIds(int $idProduct): array
    {
        $rows = $this->customizationFieldRepository->findByProductId($idProduct);

        $stored = [];
        foreach ($rows as $row) {
            $stored[$row->getFieldSlug()] = $row->getIdCustomizationField();
        }

        return array_fill_keys(self::FIELD_SLUGS, null) + $stored;
    }

    /**
     * Replaces the product's entire `tp_product_customization_field` row set:
     * bulk-deletes what's there, then persists one new row per non-null slug
     * in $ids. Delete-then-insert, not upsert — the whole set is recomputed
     * on every `register()`/`unregister()` call anyway, and this keeps
     * "absence of a row means not provisioned" true by construction.
     *
     * Writes through the same EntityManager used elsewhere in the request
     * (never a separate/untracked DBAL write for this table) and **flushes
     * explicitly before returning** — do not rely on some later, unrelated
     * flush to pick this up. Deferring the flush is exactly how the identity
     * map staleness hazard this table extraction was meant to remove would
     * reappear (see the module's schema-normalization spec, §0.4/§3.3). Do
     * not "optimize" this flush away.
     *
     * @param array<string, int|null> $ids keyed by {@see self::FIELD_SLUGS}
     */
    private function setStoredFieldIds(int $idProduct, array $ids): void
    {
        $this->customizationFieldRepository->deleteByProductId($idProduct);
        $this->entityManager->clear(TpProductCustomizationField::class);

        foreach (self::FIELD_SLUGS as $slug) {
            $idCustomizationField = $ids[$slug] ?? null;
            if ($idCustomizationField === null) {
                continue;
            }

            $this->entityManager->persist(
                new TpProductCustomizationField($idProduct, $slug, $idCustomizationField)
            );
        }

        $this->entityManager->flush();
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
