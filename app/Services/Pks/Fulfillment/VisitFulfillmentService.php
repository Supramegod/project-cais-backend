<?php

namespace App\Services\Pks\Fulfillment;

use App\Models\Pks;
use App\Models\PksFulfillmentLog;
use App\Models\PksVisitRecord;
use App\Models\PksVisitRecordFoto;
use App\Models\PksVisitSchedule;
use App\Models\PksVisitTarget;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitFulfillmentService
{
    public function __construct(
        private VisitPhotoService $visitPhotoService,
    ) {}

    public function getScheduleByPks(Pks $pks, ?string $role = null): Collection
    {
        $query = PksVisitSchedule::with([
            'site:id,nama_site,kota_id',
            'pks:id,nomor,kontrak_awal,kontrak_akhir,leads_id',
        ])
            ->where('pks_id', $pks->id)
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->select('id', 'pks_id', 'site_id', 'leads_id', 'role', 'pic_user_id', 'tgl_jadwal', 'tgl_jadwal_asli', 'status')
            ->orderBy('tgl_jadwal');

        if ($role) {
            $query->where('role', $role);
        }

        return $query->get();
    }

    public function getVisitTarget(Pks $pks, ?string $role = null): array
    {
        $query = PksVisitTarget::where('pks_id', $pks->id);

        if ($role) {
            $query->where('role', $role);
        }

        return $query->get()->map(function ($target) {
            return [
                'role' => $target->role,
                'target_total' => $target->target_total,
                'target_terpakai' => $target->target_terpakai,
                'sisa' => $target->target_total - $target->target_terpakai,
            ];
        })->toArray();
    }

    public function createVisitRecord(array $data, array $fotos, User $user): PksVisitRecord
    {
        // Upload semua foto SEBELUM transaksi — hindari upload dobel saat
        // retry, file orphan saat rollback, dan koneksi DB tertahan
        $uploadedFotos = [];
        foreach ($fotos as $foto) {
            $uploadedFotos[] = $this->visitPhotoService->uploadAndCompress($foto);
        }

        try {
            return DB::transaction(function () use ($data, $uploadedFotos, $user) {
                // Cek sisa target
                $target = PksVisitTarget::where('pks_id', $data['pks_id'])
                    ->where('role', $data['role'])
                    ->first();

                if ($target && ($target->target_total - $target->target_terpakai) <= 0) {
                    throw new \RuntimeException(
                        "Target visit {$data['role']} untuk PKS ini sudah terpenuhi."
                    );
                }

                // Insert visit record
                $record = PksVisitRecord::create([
                    'schedule_id' => $data['schedule_id'] ?? null,
                    'pks_id' => $data['pks_id'],
                    'site_id' => $data['site_id'],
                    'leads_id' => $data['leads_id'],
                    'role' => $data['role'],
                    'user_id' => $user->id,
                    'tgl_visit_aktual' => $data['tgl_visit_aktual'],
                    'hasil_visit' => $data['hasil_visit'],
                    'catatan' => $data['catatan'],
                    'created_by' => $user->full_name,
                    'created_by_user_id' => $user->id,
                ]);

                // Simpan foto yang sudah terupload
                foreach ($uploadedFotos as $uploaded) {
                    PksVisitRecordFoto::create([
                        'visit_record_id' => $record->id,
                        'url_file' => $uploaded['url_file'],
                        'nama_file' => $uploaded['nama_file'],
                        'created_by' => $user->full_name,
                        'created_by_user_id' => $user->id,
                    ]);
                }

                // Update jadwal → done (jika schedule_id ada)
                if ($record->schedule_id) {
                    PksVisitSchedule::where('id', $record->schedule_id)
                        ->update(['status' => 'done', 'updated_at' => now()]);
                }

                // Atomic increment target_terpakai
                if ($target) {
                    $affected = PksVisitTarget::where('id', $target->id)
                        ->whereRaw('(target_total - target_terpakai) > 0')
                        ->update([
                            'target_terpakai' => DB::raw('target_terpakai + 1'),
                            'updated_at' => now(),
                        ]);

                    // Gagal atomic (race dua request paralel) → rollback,
                    // visit terblokir sesuai PRD saat sisa target 0
                    if ($affected === 0) {
                        throw new \RuntimeException(
                            "Target visit {$data['role']} untuk PKS ini sudah terpenuhi."
                        );
                    }
                }

                // Log modul fulfillment jenis visit. Catatan hidup di sini.
                PksFulfillmentLog::create([
                    'pks_id' => $record->pks_id,
                    'site_id' => $record->site_id,
                    'jenis' => PksFulfillmentLog::JENIS_VISIT,
                    'reference_id' => $record->id,
                    'aksi' => 'create',
                    'catatan' => $data['catatan'],
                    'meta' => [
                        'role' => $data['role'],
                        'schedule_id' => $record->schedule_id,
                        'hasil_visit' => $data['hasil_visit'],
                        'tgl_visit_aktual' => $data['tgl_visit_aktual'],
                        'jumlah_foto' => count($uploadedFotos),
                        // Sisa target setelah increment (null bila role tanpa target).
                        'sisa_target' => $target
                            ? max(0, $target->target_total - $target->target_terpakai - 1)
                            : null,
                    ],
                    'created_by' => $user->full_name,
                    'created_by_user_id' => $user->id,
                ]);

                return $record->load('fotos');
            });
        } catch (\Throwable $e) {
            // Transaksi gagal setelah foto terupload — hapus file S3 (best-effort)
            foreach ($uploadedFotos as $uploaded) {
                try {
                    $this->visitPhotoService->deletePhoto($uploaded['nama_file']);
                } catch (\Throwable $cleanupError) {
                    \Log::warning("VisitFulfillment: Gagal hapus foto orphan {$uploaded['nama_file']}: {$cleanupError->getMessage()}");
                }
            }

            throw $e;
        }
    }

    public function getVisitHistory(Pks $pks): Collection
    {
        return PksVisitRecord::with([
            'fotos:id,visit_record_id,url_file,nama_file',
            'schedule:id,tgl_jadwal,status',
            'user:id,full_name',
        ])
            ->where('pks_id', $pks->id)
            ->select('id', 'schedule_id', 'pks_id', 'site_id', 'leads_id', 'role', 'user_id', 'tgl_visit_aktual', 'hasil_visit', 'catatan', 'created_by', 'created_at')
            ->orderBy('tgl_visit_aktual', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
