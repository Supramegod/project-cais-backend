<?php

namespace App\Services\Pks;

use App\Models\Pks;
use App\Models\PksVisitSchedule;
use App\Models\PksVisitTarget;
use App\Models\PksVisitTargetMaster;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VisitSchedulingService
{
    public function __construct(
        private BranchResolutionService $branchResolutionService,
    ) {}

    /**
     * Snapshot target visit per role saat PKS diaktivasi (status_pks_id = 7).
     */
    public function snapshotTargets(Pks $pks): void
    {
        // Hitung total HC dari quotation_detail
        $totalHc = DB::table('sl_quotation_detail')
            ->where('quotation_id', $pks->quotation_id)
            ->whereNull('deleted_at')
            ->sum('jumlah_hc');

        if ($totalHc <= 0) {
            return;
        }

        $kebutuhanId = $pks->layanan_id;
        if (! $kebutuhanId) {
            return;
        }

        // Lookup matrix
        $master = PksVisitTargetMaster::lookupTarget($kebutuhanId, (int) $totalHc);
        if (! $master) {
            return;
        }

        $targetPerTahun = $master->target_visit_per_tahun;

        // Hitung durasi kontrak (minimal 1 tahun)
        $kontrakAwal = Carbon::parse($pks->kontrak_awal);
        $kontrakAkhir = Carbon::parse($pks->kontrak_akhir);
        $durasiTahun = max(1, (int) ceil($kontrakAwal->diffInYears($kontrakAkhir)));

        $targetTotal = $targetPerTahun * $durasiTahun;

        // Insert/update untuk kedua role — jangan reset target_terpakai
        // saat dipanggil ulang (re-aktivasi/backfill)
        foreach (['operasional', 'crm'] as $role) {
            $target = PksVisitTarget::firstOrNew(['pks_id' => $pks->id, 'role' => $role]);

            $target->kategori_sesuai_hc_id = $master->kategori_sesuai_hc_id;
            $target->target_total = $targetTotal;
            $target->updated_by = Auth::user()?->full_name;

            if (! $target->exists) {
                $target->target_terpakai = 0;
                $target->created_by = Auth::user()?->full_name;
                $target->created_by_user_id = Auth::id();
            }

            $target->save();
        }
    }

    /**
     * Generate slot jadwal visit — dipanggil setelah snapshotTargets().
     */
    public function generateSchedules(Pks $pks): Collection
    {
        $targets = PksVisitTarget::where('pks_id', $pks->id)->get();
        $siteAnchor = $this->branchResolutionService->resolveSiteAnchor($pks);

        if (! $siteAnchor) {
            return collect();
        }

        $schedules = collect();
        $kontrakAwal = Carbon::parse($pks->kontrak_awal);
        $kontrakAkhir = Carbon::parse($pks->kontrak_akhir);
        $durasiTahun = max(1, (int) ceil($kontrakAwal->diffInYears($kontrakAkhir)));

        foreach ($targets as $target) {
            $totalSlots = $target->target_total;
            if ($totalSlots <= 0) {
                continue;
            }

            // Guard duplikasi: skip role yang sudah punya jadwal
            // (re-aktivasi / command backfill)
            $sudahAda = PksVisitSchedule::where('pks_id', $pks->id)
                ->where('role', $target->role)
                ->exists();
            if ($sudahAda) {
                continue;
            }

            // Hitung interval berdasarkan target/tahun
            $targetPerTahun = intdiv($totalSlots, $durasiTahun);

            $intervalMonths = match (true) {
                $targetPerTahun >= 12 => 1,
                $targetPerTahun >= 6 => 2,
                $targetPerTahun >= 4 => 3,
                default => 6,
            };

            $current = $kontrakAwal->copy();
            $slotCount = 0;

            while ($current->lte($kontrakAkhir) && $slotCount < $totalSlots) {
                $schedules->push([
                    'pks_id' => $pks->id,
                    'site_id' => $siteAnchor->id,
                    'leads_id' => $pks->leads_id,
                    'role' => $target->role,
                    'pic_user_id' => $this->resolvePicForRole($pks, $target->role),
                    'tgl_jadwal' => $current->toDateString(),
                    'tgl_jadwal_asli' => null,
                    'status' => 'scheduled',
                    'created_by' => Auth::user()?->full_name,
                    'created_by_user_id' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $current->addMonths($intervalMonths);
                $slotCount++;
            }
        }

        // Bulk insert
        if ($schedules->isNotEmpty()) {
            PksVisitSchedule::insert($schedules->toArray());
        }

        return $schedules;
    }

    /**
     * Input jadwal manual oleh Admin/CRM Supervisor.
     */
    public function createManualSchedule(array $data, User $user): PksVisitSchedule
    {
        $pks = Pks::findOrFail($data['pks_id']);

        // Validasi rentang kontrak
        $tgl = Carbon::parse($data['tgl_jadwal']);
        if ($tgl->lt(Carbon::parse($pks->kontrak_awal)) || $tgl->gt(Carbon::parse($pks->kontrak_akhir))) {
            throw new \RuntimeException('Tanggal di luar rentang kontrak.');
        }

        // Cek duplikat (customer + tanggal + role)
        $exists = PksVisitSchedule::where('leads_id', $data['leads_id'])
            ->where('tgl_jadwal', $data['tgl_jadwal'])
            ->where('role', $data['role'])
            ->exists();

        if ($exists) {
            throw new \RuntimeException('Sudah ada jadwal untuk customer dan tanggal yang sama.');
        }

        return PksVisitSchedule::create([
            'pks_id' => $data['pks_id'],
            'site_id' => $data['site_id'],
            'leads_id' => $data['leads_id'],
            'role' => $data['role'],
            'pic_user_id' => $data['pic_user_id'],
            'tgl_jadwal' => $data['tgl_jadwal'],
            'status' => 'scheduled',
            'created_by' => $user->full_name,
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * Reschedule — simpan tgl_jadwal_asli, timpa tgl_jadwal.
     */
    public function reschedule(PksVisitSchedule $schedule, Carbon $newDate, string $alasan, User $user): PksVisitSchedule
    {
        $pks = $schedule->pks;

        // Validasi rentang
        if ($newDate->lt(Carbon::parse($pks->kontrak_awal)) || $newDate->gt(Carbon::parse($pks->kontrak_akhir))) {
            throw new \RuntimeException('Tanggal baru di luar rentang kontrak.');
        }

        // Cek bentrok
        $bentrok = PksVisitSchedule::where('leads_id', $schedule->leads_id)
            ->where('role', $schedule->role)
            ->where('tgl_jadwal', $newDate->toDateString())
            ->where('id', '!=', $schedule->id)
            ->exists();

        if ($bentrok) {
            throw new \RuntimeException('Bentrok dengan jadwal lain.');
        }

        // Simpan tgl asli jika belum ada
        if (! $schedule->tgl_jadwal_asli) {
            $schedule->tgl_jadwal_asli = $schedule->tgl_jadwal;
        }

        $schedule->tgl_jadwal = $newDate->toDateString();
        $schedule->status = 'rescheduled';
        $schedule->alasan_reschedule = $alasan;
        $schedule->direschedule_oleh = $user->id;
        $schedule->updated_by = $user->full_name;
        $schedule->save();

        return $schedule;
    }

    /**
     * Cron job — tandai jadwal lewat tempo sebagai missed.
     */
    public function markMissed(): int
    {
        return PksVisitSchedule::whereIn('status', ['scheduled', 'rescheduled'])
            ->where('tgl_jadwal', '<', now()->toDateString())
            ->update(['status' => 'missed', 'updated_at' => now()]);
    }

    /**
     * Resolve PIC user_id untuk role tertentu dari data PKS.
     * crm          → crm_id_1 / crm_id_2 / crm_id_3
     * operasional  → ro_id_1 / ro_id_2 / ro_id_3
     * Fallback: Auth::id()
     */
    private function resolvePicForRole(Pks $pks, string $role): ?int
    {
        if ($role === 'crm') {
            return $pks->crm_id_1
                ?? $pks->crm_id_2
                ?? $pks->crm_id_3
                ?? Auth::id();
        }

        if ($role === 'operasional') {
            return $pks->ro_id_1
                ?? $pks->ro_id_2
                ?? $pks->ro_id_3
                ?? Auth::id();
        }

        return Auth::id();
    }
}
