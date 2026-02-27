<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CustomerActivityEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $emailSubject;
    public $emailBody;
    public $fromAddress;
    public $fromName;
    public $attachmentFiles;

    /**
     * Create a new message instance.
     */
    public function __construct(
        string $subject,
        string $body,
        string $fromAddress,
        string $fromName,
        array $attachmentFiles = []
    ) {
        $this->emailSubject = $subject;
        $this->emailBody = $body;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
        $this->attachmentFiles = $attachmentFiles;

        Log::info('CustomerActivityEmail created:', [
            'subject' => $subject,
            'body_length' => strlen($body),
            'from' => "{$fromName} <{$fromAddress}>",
            'attachments_count' => count($attachmentFiles)
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
                'fromName' => $this->fromName,
                'fromAddress' => $this->fromAddress,
                'hasAttachments' => count($this->attachmentFiles) > 0,
                'attachmentsCount' => count($this->attachmentFiles)
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
        $attachments = [];

        if (empty($this->attachmentFiles)) {
            Log::info('No attachments to add');
            return $attachments;
        }

        Log::info('Processing attachments:', ['count' => count($this->attachmentFiles)]);

        foreach ($this->attachmentFiles as $file) {
            try {
                // Cek apakah file adalah UploadedFile object atau path string
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    // File dari upload langsung
                    $attachments[] = Attachment::fromPath($file->getRealPath())
                        ->as($file->getClientOriginalName())
                        ->withMime($file->getMimeType());

                    Log::info('Added uploaded file attachment:', [
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType()
                    ]);
                } elseif (is_string($file)) {
                    // File path dari storage
                    if (Storage::disk('customer-activity')->exists($file)) {
                        $filePath = Storage::disk('customer-activity')->path($file);
                        $fileName = basename($file);

                        $attachments[] = Attachment::fromPath($filePath)
                            ->as($fileName);

                        Log::info('Added storage file attachment:', [
                            'name' => $fileName,
                            'path' => $filePath
                        ]);
                    } else {
                        Log::warning('File not found in storage:', ['file' => $file]);
                    }
                } elseif (is_array($file) && isset($file['path'])) {
                    // Format array dengan path
                    $filePath = $file['path'];
                    $fileName = $file['name'] ?? basename($filePath);

                    if (file_exists($filePath)) {
                        $attachments[] = Attachment::fromPath($filePath)
                            ->as($fileName);

                        Log::info('Added array path attachment:', [
                            'name' => $fileName,
                            'path' => $filePath
                        ]);
                    } else {
                        Log::warning('File not found:', ['path' => $filePath]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to add attachment:', [
                    'file' => is_string($file) ? $file : 'object',
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Total attachments added:', ['count' => count($attachments)]);

        return $attachments;
    }
}