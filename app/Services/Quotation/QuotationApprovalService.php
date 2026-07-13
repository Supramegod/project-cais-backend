<?php

namespace App\Services\Quotation;

use App\Jobs\EscalateQuotationJob;
use App\Models\LeadsKebutuhan;
use App\Models\LogApproval;
use App\Models\LogNotification;
use App\Models\Pks;
use App\Models\Quotation;
use App\Models\Spk;
use App\Models\User;
use App\Services\Pks\AddendumService;
use App\Services\Quotation\QuotationNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class QuotationApprovalService
{
    public function __construct(
        protected QuotationNotificationService $quotationNotificationService
    ) {}

    public function submitApproval(Quotation $quotation, array $data, User $user): array
    {
        $isApproved = filter_var($data['is_approved'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $currentDateTime = Carbon::now();
        $notes = $data['notes'] ?? null;

        return match ($user->cais_role_id) {
            96 => $this->handleSalesApproval($quotation, $isApproved, $notes, $user, $currentDateTime),
            97 => $this->handleKeuanganApproval($quotation, $isApproved, $notes, $user, $currentDateTime),
            default => ['success' => false, 'message' => 'User tidak memiliki akses approval.'],
        };
    }

    public function resetApproval(Quotation $quotation, User $user): array
    {
        $allowedRoles = [2, 96, 97];
        if (!in_array($user->cais_role_id, $allowedRoles)) {
            return ['success' => false, 'message' => 'Anda tidak memiliki akses untuk reset approval. Role: ' . $user->cais_role_id];
        }

        $quotation->update([
            'status_quotation_id' => 2,
            'is_aktif' => 0,
            'ot1' => null,
            'ot2' => null,
            'updated_at' => Carbon::now()->toDateTimeString(),
            'updated_by' => $user->full_name,
        ]);

        \Log::info('Reset approval success', [
            'quotation_id' => $quotation->id,
            'reset_by' => $user->full_name,
        ]);

        return ['success' => true, 'data' => $quotation->fresh()];
    }

    private function handleSalesApproval(
        Quotation $quotation,
        bool $isApproved,
        ?string $notes,
        User $user,
        Carbon $now
    ): array {
        $needsLevel2 = $isApproved && $this->requiresLevel2Approval($quotation);

        $quotation->update([
            'ot1' => $user->full_name,
            'status_quotation_id' => $isApproved ? ($needsLevel2 ? 2 : 3) : 8,
            'is_aktif' => $isApproved ? ($needsLevel2 ? 0 : 1) : 0,
            'updated_at' => $now,
            'updated_by' => $user->full_name,
        ]);

        $this->logApproval($quotation, $user, $isApproved, $notes, tingkat: 1, now: $now);

        if ($needsLevel2) {
            $this->notifyDirKeu($quotation->fresh(), $now);
        }

        return $this->finalizeApproval($quotation, $user, $isApproved, $notes);
    }

    private function handleKeuanganApproval(
        Quotation $quotation,
        bool $isApproved,
        ?string $notes,
        User $user,
        Carbon $now
    ): array {
        if (empty($quotation->ot1)) {
            return ['success' => false, 'message' => 'Quotation belum disetujui oleh Direktur Sales.'];
        }

        $quotation->update([
            'ot2' => $user->full_name,
            'status_quotation_id' => $isApproved ? 3 : 8,
            'is_aktif' => $isApproved ? 1 : 0,
            'updated_at' => $now,
            'updated_by' => $user->full_name,
        ]);

        $this->logApproval($quotation, $user, $isApproved, $notes, tingkat: 2, now: $now);

        return $this->finalizeApproval($quotation, $user, $isApproved, $notes);
    }

    private function requiresLevel2Approval(Quotation $quotation): bool
    {
        $quotation->loadMissing('quotationDetails.wage');

        $hasNonProvisionalThr = $quotation->quotationDetails->contains(function ($detail) {
            $thr = strtolower(trim($detail->wage->thr ?? ''));
            return !empty($thr) && !in_array($thr, ['diprovisikan', 'tidak ada']);
        });

        $isLongTop = trim($quotation->top) === 'Lebih Dari 7 Hari';

        return $isLongTop || $hasNonProvisionalThr;
    }

    private function finalizeApproval(
        Quotation $quotation,
        User $user,
        bool $isApproved,
        ?string $notes
    ): array {
        $freshQuotation = $quotation->fresh();

        $this->sendNotificationToSales($freshQuotation, $user, $isApproved, $notes);

        if (
            $isApproved
            && $freshQuotation->status_quotation_id === 3
            && $freshQuotation->tipe_quotation === 'addendum'
        ) {
            app(AddendumService::class)->process($freshQuotation, $user->full_name, $user->id);
        }

        if (
            $isApproved
            && $freshQuotation->status_quotation_id === 3
            && $freshQuotation->tipe_quotation === 'revisi'
        ) {
            Spk::whereHas('spkSites', fn ($q) =>
                $q->where('quotation_id', $freshQuotation->id)
            )->update(['status_spk_id' => 1]);

            Pks::whereHas('sites', fn ($q) =>
                $q->where('quotation_id', $freshQuotation->id)
            )->update(['status_pks_id' => 5]);
        }

        if (
            !$isApproved
            && $freshQuotation->status_quotation_id === 8
            && $freshQuotation->tipe_quotation === 'revisi'
        ) {
            $quotationIds = array_filter([$freshQuotation->id, $freshQuotation->quotation_referensi_id]);

            Spk::whereHas('spkSites', fn ($q) =>
                $q->whereIn('quotation_id', $quotationIds)
            )->update(['status_spk_id' => 6]);

            Pks::whereHas('sites', fn ($q) =>
                $q->whereIn('quotation_id', $quotationIds)
            )->update(['status_pks_id' => 10]);
        }

        return ['success' => true, 'data' => $freshQuotation];
    }

    private function logApproval(
        Quotation $quotation,
        User $user,
        bool $isApproved,
        ?string $notes,
        int $tingkat,
        Carbon $now
    ): void {
        LogApproval::create([
            'tabel' => 'quotation',
            'doc_id' => $quotation->id,
            'tingkat' => $tingkat,
            'is_approve' => $isApproved,
            'note' => $notes,
            'user_id' => $user->id,
            'approval_date' => $now,
            'created_at' => $now,
            'created_by' => $user->full_name,
            'created_by_user_id' => $user->id,
        ]);
    }

    private function sendNotificationToSales(Quotation $quotation, User $approver, bool $isApproved, ?string $notes): void
    {
        $leadsKebutuhan = LeadsKebutuhan::with('timSalesD')
            ->where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->first();

        if (!$leadsKebutuhan || !$leadsKebutuhan->timSalesD) {
            return;
        }

        $salesUserId = $leadsKebutuhan->timSalesD->user_id ?? null;
        if (!$salesUserId) {
            return;
        }

        $status = $isApproved ? 'disetujui' : 'ditolak';
        $approverRole = $approver->cais_role_id == 96 ? 'Direktur Sales' : 'Direktur Keuangan';
        $msg = "Quotation dengan nomor: {$quotation->nomor} telah {$status} oleh {$approverRole}.";
        if ($notes) {
            $msg .= " Catatan: {$notes}";
        }

        LogNotification::create([
            'user_id' => $salesUserId,
            'doc_id' => $quotation->id,
            'transaksi' => 'Quotation',
            'tabel' => 'sl_quotation',
            'pesan' => $msg,
            'is_read' => 0,
            'created_at' => Carbon::now(),
            'created_by' => $approver->full_name,
            'created_by_user_id' => $approver->id,
        ]);
    }

    private function notifyDirKeu(Quotation $quotation, Carbon $currentDateTime): void
    {
        $dirKeu = [27928, 16986, 127823];

        $hasNonProvisionalThr = $quotation->quotationDetails->contains(function ($detail) {
            $thr = strtolower(trim($detail->wage->thr ?? ''));
            return $thr !== 'diprovisikan';
        });

        if (!($quotation->top == 'Lebih Dari 7 Hari' || $hasNonProvisionalThr)) {
            return;
        }

        $leadsKebutuhan = LeadsKebutuhan::with('timSalesD')
            ->where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->first();

        $creatorName = $leadsKebutuhan->timSalesD->nama ?? Auth::user()->full_name;
        $msg = "Quotation dengan nomor: {$quotation->nomor} telah disetujui Direktur Sales dan membutuhkan persetujuan Direktur Keuangan.";

        foreach ($dirKeu as $userId) {
            LogNotification::create([
                'user_id' => $userId,
                'doc_id' => $quotation->id,
                'transaksi' => 'Quotation',
                'tabel' => 'sl_quotation',
                'pesan' => $msg,
                'is_read' => 0,
                'created_at' => $currentDateTime,
                'created_by' => $creatorName,
                'created_by_user_id' => Auth::id(),
            ]);
        }

        $approvalUrl = 'https://cais2.shelterapp2.co.id/quotation/view/' . $quotation->id;
        $this->quotationNotificationService->sendApprovalNotification(
            quotation: $quotation,
            creatorName: $creatorName,
            approvalUrl: $approvalUrl,
            overrideRecipients: QuotationNotificationService::dirKeu()
        );
        dispatch(new EscalateQuotationJob($quotation->id, 'Keuangan', $currentDateTime))
            ->delay(now()->addDay());
    }

    private function notifyDirSales(Quotation $quotation, Carbon $currentDateTime): void
    {
        $dirSales = [27927, 127822];

        $leadsKebutuhan = LeadsKebutuhan::with('timSalesD')
            ->where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->first();

        $creatorName = $leadsKebutuhan->timSalesD->nama ?? Auth::user()->full_name;
        $msg = "Quotation dengan nomor: {$quotation->nomor} telah selesai dibuat oleh {$creatorName} dan membutuhkan persetujuan Direktur sales.";

        foreach ($dirSales as $userId) {
            LogNotification::create([
                'user_id' => $userId,
                'doc_id' => $quotation->id,
                'transaksi' => 'Quotation',
                'tabel' => 'sl_quotation',
                'pesan' => $msg,
                'is_read' => 0,
                'created_at' => $currentDateTime,
                'created_by' => $creatorName,
                'created_by_user_id' => Auth::id(),
            ]);
        }

        $approvalUrl = 'https://cais2.shelterapp2.co.id/quotation/view/' . $quotation->id;
        $this->quotationNotificationService->sendApprovalNotification(
            quotation: $quotation,
            creatorName: $creatorName,
            approvalUrl: $approvalUrl,
            overrideRecipients: QuotationNotificationService::dirSales()
        );
        dispatch(new EscalateQuotationJob($quotation->id, 'Sales', $currentDateTime))
            ->delay(now()->addDay());
    }
}
