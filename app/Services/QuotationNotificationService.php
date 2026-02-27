<?php

namespace App\Services;

use App\Mail\QuotationApprovalEmail;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuotationNotificationService
{
    const DIR_SALES = [
        ['name' => 'jalu pradipta', 'email' => 'jalupradipta22@gmail.com', 'role' => 'Direktur Sales'],
    ];

    const DIR_KEU = [
        ['name' => 'zamzam akabar', 'email' => 'zamakbar12@gmail.com', 'role' => 'Direktur Keuangan'],
    ];

    public function __construct(
        private readonly DynamicMailerService $dynamicMailer
    ) {
    }

    public function sendApprovalNotification(
        Quotation $quotation,
        string $creatorName,
        string $approvalUrl = '#',
        ?User $senderUser = null,
        ?array $overrideRecipients = null
    ): void {
        $recipients = $overrideRecipients ?? $this->resolveRecipients($quotation);
        $approvalStage = $this->resolveStageLabel($overrideRecipients);

        if (empty($recipients)) {
            Log::info('QuotationNotificationService: no recipients for this stage', [
                'quotation_id' => $quotation->id,
            ]);
            return;
        }

        $sender = $senderUser ?? Auth::user();

        try {
            $mailerConfig = $this->dynamicMailer->setupMailer($sender);
        } catch (\Exception $e) {
            Log::error('QuotationNotificationService: mailer setup failed, using fallback', [
                'error' => $e->getMessage(),
            ]);
            $mailerConfig = [
                'name' => 'smtp',
                'config' => [
                    'address' => config('mail.from.address'),
                    'name' => config('mail.from.name'),
                ],
                'config_source' => 'fallback',
            ];
        }

        $fromAddress = $mailerConfig['config']['address'];
        $fromName = $mailerConfig['config']['name'];

        foreach ($recipients as $recipient) {
            try {
                Mail::mailer($mailerConfig['name'])
                    ->to($recipient['email'])
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
                    ));

                Log::info('QuotationNotificationService: email sent', [
                    'quotation_number' => $quotation->nomor,
                    'recipient' => $recipient['email'],
                    'mailer' => $mailerConfig['name'],
                ]);
            } catch (\Exception $e) {
                Log::error('QuotationNotificationService: failed to send email', [
                    'quotation_number' => $quotation->nomor,
                    'recipient' => $recipient['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolveRecipients(Quotation $quotation): array
    {
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
        if ($recipients === self::DIR_SALES) {
            return 'Persetujuan Direktur Sales';
        }

        if ($recipients === self::DIR_KEU) {
            return 'Persetujuan Direktur Keuangan';
        }

        return 'Selesai';
    }
}