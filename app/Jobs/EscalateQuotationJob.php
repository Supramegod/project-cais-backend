<?php

namespace App\Jobs;

use App\Mail\EscalationMail;
use App\Models\LeadsKebutuhan;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EscalateQuotationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $quotationId;
    protected string $targetLevel; // 'GM', 'Sales', 'Keuangan'
    protected Carbon $entryDateTime;

    /**
     * Create a new job instance.
     */
    public function __construct(int $quotationId, string $targetLevel, Carbon $entryDateTime)
    {
        $this->quotationId = $quotationId;
        $this->targetLevel = $targetLevel;
        $this->entryDateTime = $entryDateTime;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $quotation = Quotation::with(['leads'])->find($this->quotationId);

        if (!$quotation) {
            Log::warning('EscalateQuotationJob: Quotation not found', ['id' => $this->quotationId]);
            return;
        }

        // Cek apakah masih perlu eskalasi berdasarkan target level
        $shouldEscalate = match ($this->targetLevel) {
            'GM'        => empty($quotation->ot3) || empty($quotation->ot4),
            'Sales'     => empty($quotation->ot1),
            'Keuangan'  => empty($quotation->ot2),
            default     => false,
        };

        if (!$shouldEscalate) {
            // Sudah diproses, tidak perlu kirim email
            Log::info('EscalateQuotationJob: Quotation sudah diproses, eskalasi dibatalkan', [
                'quotation_id' => $quotation->id,
                'level' => $this->targetLevel
            ]);
            return;
        }

        // Dapatkan nama pembuat dari leads_kebutuhan
        $leadsKebutuhan = LeadsKebutuhan::with('timSalesD')
            ->where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->first();

        $creatorName = $leadsKebutuhan?->timSalesD?->nama ?? 'Unknown';

        // Tentukan label approver yang telat
        $approverLabel = match ($this->targetLevel) {
            'GM'        => 'GM Operasional & GM HRM',
            'Sales'     => 'Direktur Sales',
            'Keuangan'  => 'Direktur Keuangan',
        };

        // Data untuk email
        $data = [
            'quotation_number' => $quotation->nomor,
            'creator_name'     => $creatorName,
            'company_name'     => $quotation->nama_perusahaan ?? '-',
            'approver_label'   => $approverLabel,
            'entry_date'       => $this->entryDateTime->format('d-m-Y'),
            'entry_time'       => $this->entryDateTime->format('H:i'),
        ];

        // Kirim email ke Direktur Utama
        try {
            Mail::to('jluppradipta728@gmail.com')
                ->send(new EscalationMail($data));

            Log::info('EscalateQuotationJob: Email eskalasi terkirim', [
                'quotation_id' => $quotation->id,
                'level' => $this->targetLevel
            ]);
        } catch (\Exception $e) {
            Log::error('EscalateQuotationJob: Gagal kirim email', [
                'error' => $e->getMessage(),
                'quotation_id' => $quotation->id
            ]);
        }
    }
}