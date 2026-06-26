<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Form\Modifier;

use PrestaShop\Module\TailoredProductsPricing\Form\Type\TailoredProductsSettingsType;
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
        private readonly FormBuilderModifier $formBuilderModifier
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
            []
        );
    }
}
