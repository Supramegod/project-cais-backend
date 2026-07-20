<?php

namespace App\Jobs;

use App\Mail\VisitScheduleNotification;
use App\Models\Pks;
use App\Models\PksVisitSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendVisitScheduleEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Pks $pks,
    ) {}

    public function handle(): void
    {
        $schedules = PksVisitSchedule::with([
            'picUser:id,full_name,email',
            'site:id,nama_site',
        ])
            ->where('pks_id', $this->pks->id)
            ->select('id', 'pks_id', 'site_id', 'role', 'pic_user_id', 'tgl_jadwal', 'status')
            ->get();

        // Group by PIC — send one email per PIC
        foreach ($schedules->groupBy('pic_user_id') as $userId => $userSchedules) {
            $pic = $userSchedules->first()->picUser;
            if ($pic && $pic->email) {
                Mail::to($pic->email)->send(
                    new VisitScheduleNotification($this->pks, $userSchedules, $pic)
                );
            }
        }
    }
}
