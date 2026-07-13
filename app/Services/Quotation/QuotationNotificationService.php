<?php

namespace App\Services\Quotation;

use App\Mail\QuotationApprovalEmail;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuotationNotificationService
{
    // ✅ Sekarang pakai config/notification-contacts.php (bisa di-override via .env per environment)
    // Email lama (referensi):
    // DIR_SALES: nino@shelterindonesia.id / jalupradipta22@gmail.com
    // DIR_KEU:   alivian.shelter@gmail.com / zamakbar12@gmail.com
    // DIRUT:     jluppradipta@gmail.com
    // GM_OPERASIONAL: marin.shelter@gmail.com / jluppradipta728@gmail.com
    // GM_HRM:         miftahularifshelter@gmail.com / zamakbar01@gmail.com
    // ✅ Constructor tidak perlu DynamicMailerService lagi
    public function __construct()
    {
    }

    public static function dirSales(): array
    {
        return [
            [
                'name' => config('notification-contacts.dir_sales.name'),
                'email' => config('notification-contacts.dir_sales.email'),
                'role' => 'Direktur Sales',
            ],
        ];
    }

    public static function dirKeu(): array
    {
        return [
            [
                'name' => config('notification-contacts.dir_keu.name'),
                'email' => config('notification-contacts.dir_keu.email'),
                'role' => 'Direktur Keuangan',
            ],
        ];
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
        if (empty($quotation->ot1)) {
            return self::dirSales();
        }

        if (empty($quotation->ot2)) {
            $hasNonProvisionalThr = $quotation->quotationDetails->contains(function ($detail) {
                $thr = strtolower(trim($detail->wage->thr ?? ''));
                return $thr !== 'diprovisikan';
            });

            if ($quotation->top === 'Lebih Dari 7 Hari' || $hasNonProvisionalThr) {
                return self::dirKeu();
            }
        }

        return [];
    }

    private function resolveStageLabel(?array $recipients): string
    {
        if (empty($recipients)) {
            return 'Selesai';
        }
        $role = $recipients[0]['role'] ?? '';
        return match ($role) {
            'Direktur Sales' => 'Persetujuan Direktur Sales',
            'Direktur Keuangan' => 'Persetujuan Direktur Keuangan',
            default => 'Selesai',
        };
    }
}