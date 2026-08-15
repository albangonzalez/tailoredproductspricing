<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity mirroring `tp_product_customization_field` (one row per
 * (product, slug) provisioned `customization_field` id). Composite natural,
 * application-assigned PK (`id_product`, `field_slug`) — no GeneratedValue,
 * matching the module's convention (see `TpProductConfig`, `TpCombinationConfig`).
 *
 * Absence of a row means "not provisioned" — there is no nullable id column
 * here, unlike the six positional columns this table replaces.
 *
 * Deliberately no `@ORM\Table(name=...)`: an explicit table name bypasses
 * `DoctrineNamingStrategy`'s automatic `ps_` prefixing and causes a hard
 * "table doesn't exist" fatal in production (see commit `5f811d7`). Let the
 * naming strategy derive `tp_product_customization_field` from the class
 * name and apply the prefix.
 *
 * @ORM\Entity(repositoryClass="PrestaShop\Module\TailoredProducts\Repository\TpProductCustomizationFieldRepository")
 */
class TpProductCustomizationField
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_product", type="integer", options={"unsigned"=true})
     */
    private $idProduct;

    /**
     * One of {@see \PrestaShop\Module\TailoredProducts\Service\CustomizationFieldRegistry::FIELD_SLUGS}.
     *
     * @var string
     *
     * @ORM\Id
     * @ORM\Column(name="field_slug", type="string", length=32)
     */
    private $fieldSlug;

    /**
     * Id of the module-provisioned `customization_field` row for this slug.
     * No SQL FK to `customization_field` (core-owned) — the registry's
     * `resolveLiveFieldId()` liveness re-check remains the integrity
     * mechanism.
     *
     * @var int
     *
     * @ORM\Column(name="id_customization_field", type="integer", options={"unsigned"=true})
     */
    private $idCustomizationField;

    public function __construct(int $idProduct, string $fieldSlug, int $idCustomizationField)
    {
        $this->idProduct = $idProduct;
        $this->fieldSlug = $fieldSlug;
        $this->idCustomizationField = $idCustomizationField;
    }

    public function getIdProduct(): int
    {
        return $this->idProduct;
    }

    public function getFieldSlug(): string
    {
        return $this->fieldSlug;
    }

    public function getIdCustomizationField(): int
    {
        return $this->idCustomizationField;
    }

    public function setIdCustomizationField(int $idCustomizationField): self
    {
        $this->idCustomizationField = $idCustomizationField;

        return $this;
    }
}
