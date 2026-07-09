<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Form\Modifier;

use PrestaShop\Module\TailoredProductsPricing\Form\Type\PricePerSqmType;
use PrestaShop\Module\TailoredProductsPricing\Service\CombinationEnablementChecker;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Injects the `tpp_price_per_sqm` field into the admin Combination modal
 * form, when the parent product has Tailored Products Pricing enabled.
 *
 * Unlike {@see ProductFormModifier}, this does not use the core
 * FormBuilderModifier: the combination form is flat (no sub-tabs), so there
 * is no positional anchor field to splice against with addBefore()/
 * addAfter(). The field is appended directly to the root builder, exactly
 * as the previous inline implementation did.
 */
final class CombinationFormModifier
{
    public function __construct(
        private readonly CombinationEnablementChecker $combinationEnablementChecker
    ) {
    }

    public function modify(?int $combinationId, FormBuilderInterface $combinationFormBuilder, array $data): void
    {
        if (!$this->combinationEnablementChecker->isModuleEnabledForCombination((int) $combinationId)) {
            return;
        }

        $combinationFormBuilder->add('tpp_price_per_sqm', PricePerSqmType::class, [
            'data' => $data['tpp_price_per_sqm'] ?? 0,
        ]);
    }
}
