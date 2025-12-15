<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CustomerActivityEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $emailSubject;
    public $emailBody;
    public $fromAddress;
    public $fromName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, string $body, string $fromAddress, string $fromName)
    {
        $this->emailSubject = $subject;
        $this->emailBody = $body;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
        
        Log::info('CustomerActivityEmail created:', [
            'subject' => $subject,
            'body_length' => strlen($body),
            'from' => "{$fromName} <{$fromAddress}>"
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        Log::info('Building envelope:', [
            'from' => $this->fromAddress,
            'subject' => $this->emailSubject
        ]);
        
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        Log::info('Building email content');
        
        return new Content(
            view: 'emails.customer-activity',
            with: [
                'subject' => $this->emailSubject,
                'body' => $this->emailBody,
                'fromName' => $this->fromName,     // Sesuaikan dengan view
                'fromAddress' => $this->fromAddress // Sesuaikan dengan view
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}