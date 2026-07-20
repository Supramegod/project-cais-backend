<?php

namespace App\Mail;

use App\Models\Pks;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class VisitScheduleNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Pks $pks,
        public Collection $schedules,
        public User $pic,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Jadwal Visit PKS - ' . $this->pks->nomor,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.visit-schedule-notification',
            with: [
                'pks'       => $this->pks,
                'schedules' => $this->schedules,
                'pic'       => $this->pic,
            ],
        );
    }
}
