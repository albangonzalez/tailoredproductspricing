<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity mirroring `tp_combination_config` (one row per
 * combination with a price-per-m² override). Natural, application-assigned
 * PK (`id_product_attribute`) — no GeneratedValue.
 *
 * @ORM\Entity(repositoryClass="PrestaShop\Module\TailoredProducts\Repository\CombinationConfigRepository")
 */
class TpCombinationConfig
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
