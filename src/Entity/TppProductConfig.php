<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity mirroring `tpp_product_config` (one row per product with
 * Tailored Products Pricing enabled). Natural, application-assigned PK
 * (`id_product`) — no GeneratedValue.
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="PrestaShop\Module\TailoredProductsPricing\Repository\ProductConfigRepository")
 */
class TppProductConfig
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_product", type="integer", options={"unsigned"=true})
     */
    private $idProduct;

    /**
     * @var string|null
     *
     * @ORM\Column(name="min_width", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $minWidth;

    /**
     * @var string|null
     *
     * @ORM\Column(name="max_width", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $maxWidth;

    /**
     * @var string|null
     *
     * @ORM\Column(name="min_height", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $minHeight;

    /**
     * @var string|null
     *
     * @ORM\Column(name="max_height", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $maxHeight;

    /**
     * @var string
     *
     * @ORM\Column(name="unit", type="string", length=8, options={"default"="cm"})
     */
    private $unit = 'cm';

    public function __construct(int $idProduct)
    {
        $this->idProduct = $idProduct;
    }

    public function getIdProduct(): int
    {
        return $this->idProduct;
    }

    public function getMinWidth(): ?string
    {
        return $this->minWidth;
    }

    public function setMinWidth(?string $minWidth): self
    {
        $this->minWidth = $minWidth;

        return $this;
    }

    public function getMaxWidth(): ?string
    {
        return $this->maxWidth;
    }

    public function setMaxWidth(?string $maxWidth): self
    {
        $this->maxWidth = $maxWidth;

        return $this;
    }

    public function getMinHeight(): ?string
    {
        return $this->minHeight;
    }

    public function setMinHeight(?string $minHeight): self
    {
        $this->minHeight = $minHeight;

        return $this;
    }

    public function getMaxHeight(): ?string
    {
        return $this->maxHeight;
    }

    public function setMaxHeight(?string $maxHeight): self
    {
        $this->maxHeight = $maxHeight;

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }
}
