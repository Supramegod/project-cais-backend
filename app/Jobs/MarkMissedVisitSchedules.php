<?php

namespace App\Jobs;

use App\Services\Pks\VisitSchedulingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkMissedVisitSchedules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(VisitSchedulingService $service): void
    {
        $count = $service->markMissed();

        \Log::info("MarkMissedVisitSchedules: {$count} jadwal ditandai 'missed'.");
    }
}
