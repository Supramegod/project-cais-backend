<?php

namespace App\Services\Quotation;

use App\DTO\QuotationCalculationResult;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotation\Calculation\QuotationCalculationService;
use App\Services\Quotation\Calculation\QuotationItemCalculationService;
use App\Services\Quotation\Calculation\QuotationFinancialService;
use App\Services\Quotation\QuotationQueryService;
use App\Services\Quotation\QuotationApprovalService;
use App\Services\Quotation\QuotationStoreService;
use Illuminate\Http\Request;

/**
 * Backward-compatible facade — delegates to domain-specific services under App\Services\Quotation\.
 *
 * All callers still using App\Services\QuotationService continue to work.
 * New code should inject the specific domain service directly.
 */
class QuotationService
{
    protected QuotationCalculationService $calculationService;

    protected QuotationQueryService $queryService;

    protected QuotationApprovalService $approvalService;

    protected QuotationStoreService $storeService;

    protected QuotationItemCalculationService $itemService;

    protected QuotationFinancialService $financialService;

    /**
     * Accept both old-style (2 legacy params) and container-resolved injection.
     */
    public function __construct(
        $legacy1 = null,
        $legacy2 = null,
    ) {
        // Always resolve new domain services from container
        $this->calculationService = app(QuotationCalculationService::class);
        $this->queryService = app(QuotationQueryService::class);
        $this->approvalService = app(QuotationApprovalService::class);
        $this->storeService = app(QuotationStoreService::class);
        $this->itemService = app(QuotationItemCalculationService::class);
        $this->financialService = app(QuotationFinancialService::class);
    }

    public function calculateQuotation($quotation): QuotationCalculationResult
    {
        return $this->calculationService->calculateQuotation($quotation);
    }

    public function getFilteredQuotationsList(Request $request): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->queryService->getFilteredQuotationsList($request);
    }

    public function submitApproval(Quotation $quotation, array $data, User $user): array
    {
        return $this->approvalService->submitApproval($quotation, $data, $user);
    }

    public function resetApproval(Quotation $quotation, User $user): array
    {
        return $this->approvalService->resetApproval($quotation, $user);
    }
}
