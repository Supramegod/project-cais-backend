<?php

namespace App\Services\Steps;

use App\DTO\CalculationSummary;
use App\Jobs\ProcessQuotationFinalization;
use App\Models\LogApproval;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\Umk;
use App\Services\QuotationService;
use App\Services\Steps\Traits\StepHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step12Service
{
    use StepHelperTrait;

    private ?QuotationService $quotationService = null;

    public function __construct() {}

    public function setQuotationService(QuotationService $service): void
    {
        $this->quotationService = $service;
    }

    private function getQuotationService(): QuotationService
    {
        if ($this->quotationService === null) {
            return app(QuotationService::class);
        }

        return $this->quotationService;
    }

    public function execute(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $currentDateTime = Carbon::now();
            $user = Auth::user()->full_name;
            $userId = Auth::id();

            $calculationResult = $this->getQuotationService()->calculateQuotation($quotation);
            $summary = $calculationResult->calculation_summary;

            $statusData = $this->calculateFinalStatus($quotation, $summary);
            $dbUpdateData = array_filter($statusData, fn ($key) => $key !== 'notes', ARRAY_FILTER_USE_KEY);

            Quotation::where('id', $quotation->id)
                ->update(array_merge([
                    'step' => 100,
                    'updated_by' => $user,
                    'updated_at' => $currentDateTime,
                ], $dbUpdateData));

            // Sync audit trail
            if ($statusData['status_quotation_id'] === 8) {
                LogApproval::create([
                    'tabel' => 'quotation',
                    'doc_id' => $quotation->id,
                    'tingkat' => 0,
                    'is_approve' => false,
                    'user_id' => $userId,
                    'approval_date' => $currentDateTime,
                    'note' => $statusData['notes'],
                    'created_by' => $user,
                    'created_by_user_id' => $userId,
                ]);
            }

            // Background: kerjasama, requirements, notifikasi, downstream, soft-delete
            $oldQuotationId = null;
            if (in_array($statusData['status_quotation_id'], [2, 3]) && $quotation->tipe_quotation == 'revisi') {
                $oldQuotation = Quotation::find($quotation->quotation_referensi_id);
                $oldQuotationId = $oldQuotation?->id;
            }

            // Kerjasama sync is handled inside ProcessQuotationFinalization
            ProcessQuotationFinalization::dispatch(
                $quotation->id,
                $request->has('quotation_kerjasamas') ? $request->quotation_kerjasamas : null,
                $user,
                $statusData['status_quotation_id'],
                $quotation->tipe_quotation ?? '',
                $oldQuotationId,
                $userId
            );

            DB::commit();

            Log::info('Step 12 completed successfully', [
                'quotation_id' => $quotation->id,
                'final_status' => $statusData,
                'step' => 100,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateStep12', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function calculateFinalStatus(Quotation $quotation, ?CalculationSummary $summary = null): array
    {
        if ($summary === null) {
            $summary = $this->getQuotationService()
                ->calculateQuotation($quotation)
                ->calculation_summary;
        }

        $quotation->loadMissing([
            'quotationDetails.wage',
            'quotationDetails.quotationSite',
        ]);

        $user = Auth::user();
        $skipAutoRejectRoles = [54, 55, 56];
        if ($user && in_array((int) $user->cais_role_id, $skipAutoRejectRoles)) {
            return $this->checkNeedsApproval($quotation)
                ? $this->makeStatusResult(0, 2)
                : $this->makeStatusResult(1, 3);
        }

        $rejectResult = $this->checkAutoReject($quotation, $summary);
        if ($rejectResult !== null) {
            return $rejectResult;
        }

        return $this->checkNeedsApproval($quotation)
            ? $this->makeStatusResult(0, 2)
            : $this->makeStatusResult(1, 3);
    }

    private function checkAutoReject(Quotation $quotation, CalculationSummary $summary): ?array
    {
        if (strtolower((string) $quotation->jenis_kontrak) !== 'reguler') {
            return null;
        }

        if ($this->isInvalidBpjsTk($quotation)) {
            return $this->makeRejectResult('BPJS TK tidak memenuhi minimum program');
        }

        if ($this->isMissingBpjsKesForReguler($quotation)) {
            return $this->makeRejectResult('BPJS Kesehatan wajib untuk kontrak reguler');
        }

        if ($this->isBelowSalesMargin($summary)) {
            return $this->makeRejectResult('margin dibawah standard');
        }

        return null;
    }

    private function checkNeedsApproval(Quotation $quotation): bool
    {
        return
            $this->isBelowMinimumHc($quotation) ||
            $this->hasMissingBpjsDetail($quotation) ||
            $this->hasUnconventionalBenefits($quotation) ||
            $this->isUnderMinimumWage($quotation) ||
            $this->isLowPercentage($quotation) ||
            $quotation->company_id == 17 ||
            $quotation->top === 'Lebih Dari 7 Hari';
    }

    private function isInvalidBpjsTk(Quotation $quotation): bool
    {
        return QuotationDetail::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->whereRaw(
                '(CAST(is_bpjs_jkk AS UNSIGNED)
                + CAST(is_bpjs_jkm AS UNSIGNED)
                + CAST(is_bpjs_jht AS UNSIGNED)
                + CAST(is_bpjs_jp  AS UNSIGNED)) < 3'
            )
            ->exists();
    }

    private function isMissingBpjsKesForReguler(Quotation $quotation): bool
    {
        return QuotationDetail::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->where('is_bpjs_kes', 0)
            ->exists();
    }

    private function isBelowSalesMargin(CalculationSummary $summary): bool
    {
        $user = Auth::user();

        if (! $user || (int) $user->cais_role_id !== 29) {
            return false;
        }

        return (float) $summary->gpm < 2.8;
    }

    private function isBelowMinimumHc(Quotation $quotation): bool
    {
        $kebutuhanId = (int) $quotation->kebutuhan_id;

        if (! in_array($kebutuhanId, [1, 2, 3], true)) {
            return false;
        }

        $totalHc = QuotationDetail::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->sum('jumlah_hc');

        return match ($kebutuhanId) {
            2 => $totalHc < 10,
            1, 3 => $totalHc < 5,
            default => false,
        };
    }

    private function hasMissingBpjsDetail(Quotation $quotation): bool
    {
        return QuotationDetail::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('is_bpjs_jkk', 0)
                    ->orWhere('is_bpjs_jkm', 0)
                    ->orWhere('is_bpjs_jht', 0)
                    ->orWhere('is_bpjs_jp', 0);
            })
            ->exists();
    }

    private function hasUnconventionalBenefits(Quotation $quotation): bool
    {
        return QuotationDetail::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->whereHas('wage', function ($q) {
                $q->where('kompensasi', 'Tidak Ada')
                    ->orWhere('thr', 'Tidak Ada')
                    ->orWhere('thr', '!=', 'Diprovisikan');
            })
            ->exists();
    }

    private function isUnderMinimumWage(Quotation $quotation): bool
    {
        foreach ($quotation->quotationDetails as $detail) {
            $wage = $detail->wage;
            $site = $detail->quotationSite;
            $jenis_kontrak = strtolower((string) $quotation->jenis_kontrak);

            if ($jenis_kontrak !== 'reguler') {
                continue;
            }

            if (! $wage || ! $site || $wage->upah !== 'Custom') {
                continue;
            }

            $umkData = Umk::byCity($site->kota_id)->active()->first();
            if (! $umkData) {
                continue;
            }

            if ((float) $wage->nominal_upah < ((float) $umkData->umk * 0.85)) {
                return true;
            }
        }

        return false;
    }

    private function isLowPercentage(Quotation $quotation): bool
    {
        $threshold = ((int) $quotation->kebutuhan_id === 1) ? 7.0 : 6.0;

        return (float) ($quotation->persentase ?? 0) < $threshold;
    }

    private function makeRejectResult(string $notes): array
    {
        return $this->makeStatusResult(0, 8, $notes);
    }

    private function makeStatusResult(
        int $isAktif,
        int $statusQuotationId,
        ?string $notes = null
    ): array {
        return [
            'is_aktif' => $isAktif,
            'status_quotation_id' => $statusQuotationId,
            'notes' => $notes,
        ];
    }
}
