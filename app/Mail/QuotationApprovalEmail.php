<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class QuotationApprovalEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;
    public string $recipientRole;
    public string $quotationNumber;
    public string $creatorName;
    public string $approvalStage;
    public string $approvalUrl;
    public ?string $top;
    public string $fromAddress;
    public string $fromName;
    public ?string $namaPerusahaan;

    public function __construct(
        string $recipientName,
        string $recipientRole,
        string $quotationNumber,
        string $creatorName,
        string $approvalStage,
        string $approvalUrl = '#',
        ?string $top = null,
        string $fromAddress = '',
        string $fromName = '',
        ?string $namaPerusahaan = null,
    ) {
        $this->recipientName = $recipientName;
        $this->recipientRole = $recipientRole;
        $this->quotationNumber = $quotationNumber;
        $this->creatorName = $creatorName;
        $this->approvalStage = $approvalStage;
        $this->approvalUrl = $approvalUrl;
        $this->top = $top;
        $this->fromAddress = $fromAddress ?: config('mail.from.address');
        $this->fromName = $fromName ?: config('mail.from.name');
        $this->namaPerusahaan = $namaPerusahaan;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: "[Persetujuan Diperlukan] Quotation {$this->quotationNumber} — {$this->approvalStage}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quotation-approval',
            with: [
                'recipientName' => $this->recipientName,
                'recipientRole' => $this->recipientRole,
                'quotationNumber' => $this->quotationNumber,
                'creatorName' => $this->creatorName,
                'approvalStage' => $this->approvalStage,
                'approvalUrl' => $this->approvalUrl,
                'top' => $this->top,
                'fromAddress' => $this->fromAddress,
                'fromName' => $this->fromName,
                'sentAt' => now(),
                'namaPerusahaan' => $this->namaPerusahaan,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}