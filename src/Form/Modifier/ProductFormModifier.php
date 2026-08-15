<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Form\Modifier;

use PrestaShop\Module\TailoredProducts\Adapter\ColorAttributeProvider;
use PrestaShop\Module\TailoredProducts\Form\Type\TailoredProductsSettingsType;
use PrestaShopBundle\Form\FormBuilderModifier;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Injects the Tailored Products Pricing settings into the admin product form.
 *
 * Follows the PS9 form-modifier pattern: the modifier is a DI service that
 * receives the core {@see FormBuilderModifier} and is delegated to from the
 * module's actionProductFormBuilderModifier hook.
 */
final class ProductFormModifier
{
    public function __construct(
        private readonly FormBuilderModifier $formBuilderModifier,
        private readonly ColorAttributeProvider $colorAttributeProvider,
        private readonly int $contextLangId,
        private readonly int $contextShopId
    ) {
    }

    public function modify(?int $productId, FormBuilderInterface $productFormBuilder): void
    {
        if (!$productFormBuilder->has('details')) {
            return;
        }

        $detailsTabFormBuilder = $productFormBuilder->get('details');

        $this->formBuilderModifier->addBefore(
            $detailsTabFormBuilder,
            'references',
            'tpp_settings',
            TailoredProductsSettingsType::class,
            ['color_attribute_groups' => $this->colorGroupChoices()]
        );
    }

    /**
     * @return array<string, int>
     */
    private function colorGroupChoices(): array
    {
        $choices = [];

        foreach ($this->colorAttributeProvider->findColorGroups($this->contextLangId, $this->contextShopId) as $group) {
            $label = $group['name'] !== '' ? $group['name'] : ('#' . $group['id']);
            $choices[$label] = $group['id'];
        }

        return $choices;
    }
}
