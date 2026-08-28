<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Form\Modifier;

use PrestaShop\Module\TailoredProducts\Form\Type\UnitPriceType;
use PrestaShop\Module\TailoredProducts\Service\CombinationEnablementChecker;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Injects the `tpp_unit_price` field into the admin Combination modal
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

        $combinationFormBuilder->add('tpp_unit_price', UnitPriceType::class, [
            'data' => $data['tpp_unit_price'] ?? 0,
        ]);
    }
}
