<?php

namespace App\Services;

use App\Models\City;
use App\Models\Province;
use App\Models\Umk;
use App\Models\Umsk;
use App\Models\Ump;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpahService
{
    // ── Level 1 ───────────────────────────────────────────────────────────────

    public function getProvinsiList(int $perPage = 15): LengthAwarePaginator
    {
        try {
            $paginated = Province::active()
                ->with('activeUmp')
                ->orderBy('name')
                ->paginate($perPage);

            // Map setiap item, biarkan struktur pagination tetap utuh
            $paginated->through(fn(Province $province) => [
                'id' => $province->id,
                'nama' => $province->name,
                'ump' => $province->activeUmp
                    ? [
                        'id' => $province->activeUmp->id,
                        'nilai' => (float) $province->activeUmp->ump,
                        'formatted' => $province->activeUmp->formatump(),
                        'tgl_berlaku' => $province->activeUmp->tgl_berlaku,
                        'sumber' => $province->activeUmp->sumber,
                    ]
                    : null,
            ]);

            return $paginated;

        } catch (QueryException $e) {
            Log::error('[UpahService::getProvinsiList] Query error', [
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil data provinsi. Periksa koneksi database.', previous: $e);

        } catch (\Throwable $e) {
            Log::error('[UpahService::getProvinsiList] Unexpected error', [
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Terjadi kesalahan tak terduga saat mengambil data provinsi.', previous: $e);
        }
    }

    // ── Level 2 ───────────────────────────────────────────────────────────────

    public function getKotaList(int $provinceId, int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        try {
            $query = City::active()
                ->byProvince($provinceId)
                ->with('activeUmk');

            // Apply search filter if provided
            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            $paginated = $query
                ->orderBy('name')
                ->paginate($perPage);

            $paginated->through(fn(City $city) => [
                'id' => $city->id,
                'nama' => $city->name,
                'umk' => $city->activeUmk
                    ? [
                        'id' => $city->activeUmk->id,
                        'nilai' => (float) $city->activeUmk->umk,
                        'formatted' => $city->activeUmk->formatumk(),
                        'tgl_berlaku' => $city->activeUmk->tgl_berlaku,
                        'sumber' => $city->activeUmk->sumber,
                    ]
                    : null,
            ]);

            return $paginated;

        } catch (QueryException $e) {
            Log::error('[UpahService::getKotaList] Query error', [
                'province_id' => $provinceId,
                'search' => $search,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil data kota/kabupaten. Periksa koneksi database.', previous: $e);

        } catch (\Throwable $e) {
            Log::error('[UpahService::getKotaList] Unexpected error', [
                'province_id' => $provinceId,
                'search' => $search,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Terjadi kesalahan tak terduga saat mengambil data kota/kabupaten.', previous: $e);
        }
    }

    // ── Level 3 ───────────────────────────────────────────────────────────────

    public function getDetailKota(int $cityId): array
    {
        try {
            $city = City::active()
                ->with('province') // hanya province, satu koneksi
                ->findOrFail($cityId);

            // Lazy load — aman meski beda koneksi
            $city->activeUmsks; // otomatis query ke koneksi mysql
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;

        } catch (QueryException $e) {
            Log::error('[UpahService::getDetailKota] Query error saat load city', [
                'city_id' => $cityId,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil data kota. Periksa koneksi database.', previous: $e);
        }

        try {
            $umkHistory = Umk::byCity($cityId)
                ->withTrashed()
                ->orderByDesc('tgl_berlaku')
                ->get();

        } catch (QueryException $e) {
            Log::error('[UpahService::getDetailKota] Query error saat load UMK history', [
                'city_id' => $cityId,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil riwayat UMK.', previous: $e);
        }

        try {
            $umskHistory = Umsk::byCity($cityId)
                ->withTrashed()
                ->orderBy('city_name')
                ->orderByDesc('tgl_berlaku')
                ->get();

        } catch (QueryException $e) {
            Log::error('[UpahService::getDetailKota] Query error saat load UMSK history', [
                'city_id' => $cityId,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil riwayat UMSK.', previous: $e);
        }

        return [
            'kota' => [
                'id' => $city->id,
                'nama' => $city->name,
                'provinsi' => $city->province
                    ? [
                        'id' => $city->province->id,
                        'nama' => $city->province->nama,
                    ]
                    : null,
                'umk_aktif' => $city->activeUmk
                    ? [
                        'id' => $city->activeUmk->id,
                        'nilai' => (float) $city->activeUmk->umk,
                        'formatted' => $city->activeUmk->formatumk(),
                        'tgl_berlaku' => $city->activeUmk->tgl_berlaku,
                        'sumber' => $city->activeUmk->sumber,
                    ]
                    : null,
                'umsk_aktif' => $city->activeUmsks
                    ? [
                        'id' => $city->activeUmsks->id,
                        'nilai' => (float) $city->activeUmsks->umsk,
                        'formatted' => $city->activeUmsks->formatumsk(),
                        'tgl_berlaku' => $city->activeUmsks->tgl_berlaku,
                        'sumber' => $city->activeUmsks->sumber,
                    ]
                    : null,
            ],

            'umk_history' => $umkHistory->map(fn(Umk $umk) => [
                'id' => $umk->id,
                'nilai' => (float) $umk->umk,
                'formatted' => $umk->formatumk(),
                'tgl_berlaku' => $umk->tgl_berlaku,
                'sumber' => $umk->sumber,
                'is_aktif' => $umk->is_aktif,
                'created_by' => $umk->created_by,
                'deleted_at' => $umk->deleted_at?->format('d-m-Y'),
            ])->values(),

            'umsk_history' => $umskHistory->map(fn(Umsk $umsk) => [
                'id' => $umsk->id,
                'nilai' => (float) $umsk->umsk,
                'formatted' => $umsk->formatumsk(),
                'tgl_berlaku' => $umsk->tgl_berlaku,
                'sumber' => $umsk->sumber,
                'is_aktif' => $umsk->is_aktif,
                'created_by' => $umsk->created_by,
                'deleted_at' => $umsk->deleted_at?->format('d-m-Y'),
            ])->values(),
        ];
    }

    // ── Store UMP ─────────────────────────────────────────────────────────────

    public function storeUmp(array $validated, string $actor): Ump
    {
        try {
            return DB::transaction(function () use ($validated, $actor): Ump {

                try {
                    Ump::deactivatePrevious('province_id', $validated['province_id'], $actor);
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmp] Gagal menonaktifkan UMP lama', [
                        'province_id' => $validated['province_id'],
                        'actor' => $actor,
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException(
                        "Gagal menonaktifkan UMP lama untuk province_id {$validated['province_id']}.",
                        previous: $e
                    );
                }

                try {
                    return Ump::create([
                        ...$validated,
                        'is_aktif' => true,
                        'created_by' => $actor,
                    ]);
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmp] Gagal insert UMP baru', [
                        'province_id' => $validated['province_id'],
                        'actor' => $actor,
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException('Gagal menyimpan data UMP baru.', previous: $e);
                }
            });

        } catch (\RuntimeException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('[UpahService::storeUmp] Unexpected error', [
                'province_id' => $validated['province_id'] ?? null,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Terjadi kesalahan tak terduga saat menyimpan UMP.', previous: $e);
        }
    }

    // ── Store UMK ─────────────────────────────────────────────────────────────

    public function storeUmk(array $validated, string $actor): Umk
    {
        try {
            return DB::transaction(function () use ($validated, $actor): Umk {

                try {
                    Umk::deactivatePrevious('city_id', $validated['city_id'], $actor);
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmk] Gagal menonaktifkan UMK lama', [
                        'city_id' => $validated['city_id'],
                        'actor' => $actor,
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException(
                        "Gagal menonaktifkan UMK lama untuk city_id {$validated['city_id']}.",
                        previous: $e
                    );
                }

                try {
                    return Umk::create([
                        ...$validated,
                        'is_aktif' => true,
                        'created_by' => $actor,
                    ]);
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmk] Gagal insert UMK baru', [
                        'city_id' => $validated['city_id'],
                        'actor' => $actor,
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException('Gagal menyimpan data UMK baru.', previous: $e);
                }
            });

        } catch (\RuntimeException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('[UpahService::storeUmk] Unexpected error', [
                'city_id' => $validated['city_id'] ?? null,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Terjadi kesalahan tak terduga saat menyimpan UMK.', previous: $e);
        }
    }

    // ── Store UMSK ────────────────────────────────────────────────────────────

    public function storeUmsk(array $validated, string $actor): Umsk
    {
        try {
            return DB::transaction(function () use ($validated, $actor): Umsk {

                try {
                    Umsk::deactivatePreviousBySector(
                        $validated['city_id'],
                        $actor
                    );
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmsk] Gagal menonaktifkan UMSK lama', [
                        'city_id' => $validated['city_id'],
                        'actor' => $actor,
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException(
                        "Gagal menonaktifkan UMSK lama untuk city_id {$validated['city_id']}.",
                        previous: $e
                    );
                }

                try {
                    return Umsk::create([
                        ...$validated,
                        'is_aktif' => true,
                        'created_by' => $actor,
                    ]);
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmsk] Gagal insert UMSK baru', [
                        'city_id' => $validated['city_id'],
                        'actor' => $actor,
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException('Gagal menyimpan data UMSK baru.', previous: $e);
                }
            });

        } catch (\RuntimeException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('[UpahService::storeUmsk] Unexpected error', [
                'city_id' => $validated['city_id'] ?? null,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Terjadi kesalahan tak terduga saat menyimpan UMSK.', previous: $e);
        }
    }
}