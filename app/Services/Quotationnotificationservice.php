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
        // ['name' => 'Nino', 'email' => 'nino@shelterindonesia.id', 'role' => 'Dir. Sales'],
        ['name' => 'jalu', 'email' => 'jalupradipta22@gmail.com', 'role' => 'Dir. Sales'],
    ];

    const DIR_KEU = [
        // ['name' => 'Alivian', 'email' => 'alivian@shelterindonesia.id', 'role' => 'Dir. Keuangan'],
        ['name' => 'zamzam', 'email' => 'zamakbar12@gmail.com', 'role' => 'Dir. Keuangan'],
    ];

    const DIR_UMUM = [];

    public function __construct(
        private readonly DynamicMailerService $dynamicMailer
    ) {}

    public function sendApprovalNotification(
        Quotation $quotation,
        string $creatorName,
        string $approvalUrl = '#',
        ?User $senderUser = null
    ): void {
        $recipients    = $this->resolveRecipients($quotation);
        $approvalStage = $this->resolveStageLabel($quotation);

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
                'name'          => 'smtp',
                'config'        => [
                    'address' => config('mail.from.address'),
                    'name'    => config('mail.from.name'),
                ],
                'config_source' => 'fallback',
            ];
        }

        $fromAddress = $mailerConfig['config']['address'];
        $fromName    = $mailerConfig['config']['name'];

        foreach ($recipients as $recipient) {
            try {
                Mail::mailer($mailerConfig['name'])
                    ->to($recipient['email'])
                    ->send(new QuotationApprovalEmail(
                        recipientName:   $recipient['name'],
                        recipientRole:   $recipient['role'],
                        quotationNumber: $quotation->nomor,
                        creatorName:     $creatorName,
                        approvalStage:   $approvalStage,
                        approvalUrl:     $approvalUrl,
                        top:             $quotation->top ?? null,
                        fromAddress:     $fromAddress,
                        fromName:        $fromName,
                    ));

                Log::info('QuotationNotificationService: email sent', [
                    'quotation_number' => $quotation->nomor,
                    'recipient'        => $recipient['email'],
                    'mailer'           => $mailerConfig['name'],
                ]);
            } catch (\Exception $e) {
                Log::error('QuotationNotificationService: failed to send email', [
                    'quotation_number' => $quotation->nomor,
                    'recipient'        => $recipient['email'],
                    'error'            => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolveRecipients(Quotation $quotation): array
    {
        if (empty($quotation->ot1)) {
            return self::DIR_SALES;
        }

        if (empty($quotation->ot2) && $quotation->top === 'Lebih Dari 7 Hari') {
            return self::DIR_KEU;
        }

        if (empty($quotation->ot3) && $quotation->top === 'Lebih Dari 7 Hari') {
            return self::DIR_UMUM;
        }

        return [];
    }

    private function resolveStageLabel(Quotation $quotation): string
    {
        if (empty($quotation->ot1)) {
            return 'Persetujuan Dir. Sales';
        }

        if (empty($quotation->ot2) && $quotation->top === 'Lebih Dari 7 Hari') {
            return 'Persetujuan Dir. Keuangan';
        }

        if (empty($quotation->ot3) && $quotation->top === 'Lebih Dari 7 Hari') {
            return 'Persetujuan Dir. Umum';
        }

        return 'Selesai';
    }
}