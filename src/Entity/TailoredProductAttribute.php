<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity mirroring `tailored_product_attribute` (one row
 * per combination with a price-per-m² override). Natural,
 * application-assigned PK (`id_product_attribute`) — no GeneratedValue.
 *
 * Deliberately no `@ORM\Table(name=...)`: an explicit table name bypasses
 * `DoctrineNamingStrategy`'s automatic `ps_` prefixing and causes a hard
 * "table doesn't exist" fatal in production (see commit `5f811d7`, which
 * fixed exactly this on this entity when it was named `TpCombinationConfig`).
 * Let the naming strategy derive `tailored_product_attribute` from
 * the class name and apply the prefix.
 *
 * @ORM\Entity(repositoryClass="PrestaShop\Module\TailoredProducts\Repository\TailoredProductAttributeRepository")
 */
class TailoredProductAttribute
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_product_attribute", type="integer", options={"unsigned"=true})
     */
    private $idProductAttribute;

    /**
     * @var string
     *
     * @ORM\Column(name="price_per_sqm", type="decimal", precision=20, scale=2, options={"default"="0.00"})
     */
    private $pricePerSqm = '0.00';

    public function __construct(int $idProductAttribute)
    {
        $this->idProductAttribute = $idProductAttribute;
    }

    public function getIdProductAttribute(): int
    {
        return $this->idProductAttribute;
    }

    public function getPricePerSqm(): string
    {
        return $this->pricePerSqm;
    }

    public function setPricePerSqm(string $pricePerSqm): self
    {
        $this->pricePerSqm = $pricePerSqm;

        return $this;
    }
}
