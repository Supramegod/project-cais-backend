<?php

namespace App\Services\Quotation\Steps;

class StepMapper
{
    /**
     * Resolves the logical update method name based on quotation version and UI step.
     */
    public static function resolveUpdateMethod(int $version, int $uiStep): string
    {
        if ($version === 1) {
            return match ($uiStep) {
                1 => 'updateJenisKontrak',
                2 => 'updateDetailKontrak',
                3 => 'updateHeadcount',
                4 => 'updateCosting',
                5 => 'updateBpjs',
                6 => 'updateAplikasiPendukung',
                7 => 'updateKaporlap',
                8 => 'updatePeralatan',
                9 => 'updateChemical',
                10 => 'updateOperasional',
                11 => 'updatePricing',
                12 => 'updateFinalization',
                13 => 'updateFinalization',
                default => 'notFound',
            };
        }

        // Version 2 (13 steps) -> now 14 steps
        return match ($uiStep) {
            1 => 'updateDataSite',
            2 => 'updateJenisKontrak',
            3 => 'updateDetailKontrak',
            4 => 'updateHeadcount',
            5 => 'updateBpjs',
            6 => 'updateAplikasiPendukung',
            7 => 'updateKaporlap',
            8 => 'updatePeralatan',
            9 => 'updateChemical',
            10 => 'updateOperasional',
            11 => 'updateCosting',
            12 => 'updateDriver',
            13 => 'updatePricing',
            14 => 'updateFinalization',
            default => 'notFound',
        };
    }
}
