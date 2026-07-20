<?php

namespace App\Mail;

use App\Models\Pks;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ItemFulfillmentReminderNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Pks    $pks           PKS aktif yang perlu diingatkan.
     * @param  array  $pendingItems  Item belum terpenuhi: [nama, qty_diminta, qty_terpenuhi, remaining, site].
     */
    public function __construct(
        public Pks $pks,
        public array $pendingItems,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengingat Pemenuhan Barang PKS - ' . $this->pks->nomor,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.item-fulfillment-reminder',
            with: [
                'pks' => $this->pks,
                'pendingItems' => $this->pendingItems,
            ],
        );
    }
}
