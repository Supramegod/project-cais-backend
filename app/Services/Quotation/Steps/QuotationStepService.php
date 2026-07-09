<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use App\Services\Quotation\QuotationService;
use App\Services\Quotation\Steps\StepUpdateService;

/**
 * BC-compatible wrapper — delegates all calls to new StepUpdateService.
 * Can be safely removed once all consumers are updated.
 */
class QuotationStepService
{
    private StepUpdateService $inner;

    public function __construct()
    {
        $this->inner = app(StepUpdateService::class);
    }

    public function getQuotationService(): QuotationService
    {
        return $this->inner->getQuotationService();
    }

    public function getStepRelations(int $step): array
    {
        return $this->inner->getStepRelations($step);
    }

    public function prepareStepData(Quotation $quotation, int $step): array
    {
        return $this->inner->prepareStepData($quotation, $step);
    }

    public function __call(string $method, array $args): void
    {
        if (preg_match('/^updateStep(\d+)$/', $method, $m)) {
            $step = (int) $m[1];
            $handlers = [
                1 => app(\App\Services\Quotation\Steps\Step1Service::class),
                2 => app(\App\Services\Quotation\Steps\Step2Service::class),
                3 => app(\App\Services\Quotation\Steps\Step3Service::class),
                4 => app(\App\Services\Quotation\Steps\Step4Service::class),
                5 => app(\App\Services\Quotation\Steps\Step5Service::class),
                6 => app(\App\Services\Quotation\Steps\Step6Service::class),
                7 => app(\App\Services\Quotation\Steps\Step7Service::class),
                8 => app(\App\Services\Quotation\Steps\Step8Service::class),
                9 => app(\App\Services\Quotation\Steps\Step9Service::class),
                10 => app(\App\Services\Quotation\Steps\Step10Service::class),
                11 => app(\App\Services\Quotation\Steps\Step11Service::class),
                12 => app(\App\Services\Quotation\Steps\Step12Service::class),
            ];

            if (isset($handlers[$step])) {
                $handlers[$step]->execute($args[0], $args[1]);
                return;
            }
        }
        throw new \BadMethodCallException("Method {$method} not found");
    }
}
