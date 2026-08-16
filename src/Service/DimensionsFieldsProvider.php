<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Service;

use PrestaShop\Module\TailoredProducts\Repository\TailoredProductSettingsRepository;

/**
 * Pure data/gating service for the front-office dimensions form
 * (`hookDisplayProductCustomization`). Does not render anything — rendering
 * is the module class's job via `Module::display()` — so it needs no
 * `\Context`, no Smarty, no Twig.
 */
final class DimensionsFieldsProvider
{
    private const STEP_BY_UNIT = ['cm' => '0.1', 'mm' => '1'];

    public function __construct(
        private readonly TailoredProductSettingsRepository $productConfigRepository,
    ) {
    }

    /**
     * Returns the Smarty view-model for the FO dimensions form, or null when the
     * module is not enabled for this product (same signal as the combination flow).
     *
     * @return array{unit:string, step:string, minWidth:?string, maxWidth:?string, minHeight:?string, maxHeight:?string}|null
     */
    public function getViewData(int $idProduct): ?array
    {
        $config = $this->productConfigRepository->findByProductId($idProduct);
        if ($config === null) {
            return null;
        }

        $unit = $config->getUnit();

        return [
            'unit' => $unit,
            'step' => self::STEP_BY_UNIT[$unit] ?? '0.1',
            'minWidth' => $config->getMinWidth(),
            'maxWidth' => $config->getMaxWidth(),
            'minHeight' => $config->getMinHeight(),
            'maxHeight' => $config->getMaxHeight(),
        ];
    }
}
