<?php

namespace App\Services;

use App\Mail\QuotationApprovalEmail;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuotationNotificationService
{
    const DIR_SALES = [
        // ['name' => 'Muhammad Nino Mayvi Dian', 'email' => 'nino.shelter@gmail.com', 'role' => 'Direktur Sales'],
        ['name' => 'Muhammad Nino Mayvi Dian', 'email' => 'jalupradipta22@gmail.com', 'role' => 'Direktur Sales'],
    ];

    const DIR_KEU = [
        // ['name' => 'Alivian Pranatyas Hening Lazuardi', 'email' => 'alivian.shelter@gmail.com', 'role' => 'Direktur Keuangan'],
        ['name' => 'Alivian Pranatyas Hening Lazuardi', 'email' => 'zamakbar12@gmail.com', 'role' => 'Direktur Keuangan'],
    ];
    const GM_OPERASIONAL = [
        // ['name' => 'Marien Ristanti', 'email' => 'marin.shelter@gmail.com', 'role' => 'General Manager Operasional'],
        ['name' => 'Marien Ristanti', 'email' => 'jluppradipta728@gmail.com', 'role' => 'General Manager Operasional'],
    ];

    const GM_HRM = [
        // ['name' => 'Miftakhul Arif', 'email' => 'miftahularifshelter@gmail.com', 'role' => 'General Manager HRM'],
        ['name' => 'Miftakhul Arif', 'email' => 'zamakbar01@gmail.com', 'role' => 'General Manager HRD'],
    ];
    // ✅ Constructor tidak perlu DynamicMailerService lagi
    public function __construct()
    {
    }

    public function sendApprovalNotification(
        Quotation $quotation,
        string $creatorName,
        string $approvalUrl = '#',
        ?User $senderUser = null,       // ✅ parameter ini tidak dipakai lagi, tapi dibiarkan agar tidak breaking change
        ?array $overrideRecipients = null
    ): void {
        try {
            $recipients = $overrideRecipients ?? $this->resolveRecipients($quotation);
            $approvalStage = $this->resolveStageLabel($overrideRecipients);

            if (empty($recipients)) {
                Log::info('QuotationNotificationService: no recipients for this stage', [
                    'quotation_id' => $quotation->id,
                ]);
                return;
            }
            // ✅ Ambil from address & name langsung dari .env / config/mail.php
            $fromAddress = config('mail.from.address');
            $fromName = config('mail.from.name');
            foreach ($recipients as $recipient) {
                // ✅ Tidak perlu ->mailer(...), langsung pakai default mailer dari .env
                Mail::to($recipient['email'])
                    ->send(new QuotationApprovalEmail(
                        recipientName: $recipient['name'],
                        recipientRole: $recipient['role'],
                        quotationNumber: $quotation->nomor,
                        creatorName: $creatorName,
                        approvalStage: $approvalStage,
                        approvalUrl: $approvalUrl,
                        top: $quotation->top ?? null,
                        fromAddress: $fromAddress,
                        fromName: $fromName,
                        namaPerusahaan: $quotation->nama_perusahaan ?? null,
                        jumlahHariInvoice: $quotation->jumlah_hari_invoice ?? null,
                        tipeHariInvoice: $quotation->tipe_hari_invoice ?? null,
                    ));

                Log::info('QuotationNotificationService: email sent', [
                    'quotation_number' => $quotation->nomor,
                    'recipient' => $recipient['email'],
                    'mailer' => config('mail.default'),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('QuotationNotificationService: failed to send email', [
                'quotation_number' => $quotation->nomor,
                'recipient' => $recipient['email'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveRecipients(Quotation $quotation): array
    {
        // tidak ada perubahan di sini
        if (empty($quotation->ot1)) {
            return self::DIR_SALES;
        }

        if (empty($quotation->ot2)) {
            $hasNonProvisionalThr = $quotation->quotationDetails->contains(function ($detail) {
                $thr = strtolower(trim($detail->wage->thr ?? ''));
                return $thr !== 'diprovisikan';
            });

            if ($quotation->top === 'Lebih Dari 7 Hari' || $hasNonProvisionalThr) {
                return self::DIR_KEU;
            }
        }

        return [];
    }

    private function resolveStageLabel(?array $recipients): string
{
    if ($recipients === self::GM_OPERASIONAL) return 'Persetujuan General Manager Operasional';
    if ($recipients === self::GM_HRM)         return 'Persetujuan General Manager HRD';
    if ($recipients === self::DIR_SALES)      return 'Persetujuan Direktur Sales';
    if ($recipients === self::DIR_KEU)        return 'Persetujuan Direktur Keuangan';

    return 'Selesai';
}
}