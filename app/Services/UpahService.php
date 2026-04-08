<?php

// app/Services/UpahService.php

namespace App\Services;

use App\Models\City;
use App\Models\Province;
use App\Models\Umk;
use App\Models\Umsk;
use App\Models\Ump;
use App\Models\Umsp;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpahService
{
    // ── Level 1: Provinsi (tanpa UMSP) ───────────────────────────────────────

    public function getProvinsiList(int $perPage = 15): LengthAwarePaginator
    {
        try {
            $paginated = Province::active()
                ->with('activeUmp')
                ->orderBy('name')
                ->paginate($perPage);

            $paginated->through(fn(Province $province) => [
                'id' => $province->id,
                'nama' => $province->name,
                'ump' => $province->activeUmp
                    ? $this->formatUmp($province->activeUmp)
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

    // ── Level 2: List Kota/Kabupaten ─────────────────────────────────────────

    public function getKotaList(int $provinceId, int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        try {
            $query = City::active()
                ->byProvince($provinceId)
                ->with('activeUmk');

            if ($search !== null && $search !== '') {
                $query->where('name', 'like', "%{$search}%");
            }

            $paginated = $query
                ->orderBy('name')
                ->paginate($perPage);

            $paginated->through(fn(City $city) => [
                'id' => $city->id,
                'kode' => $city->kode,
                'nama' => $city->name,
                'umk' => $city->activeUmk
                    ? $this->formatUmk($city->activeUmk)
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

    // ── Level 2: List UMP Provinsi (riwayat) ─────────────────────────────────

    public function getUmpListByProvince(int $provinceId, int $perPage = 15): LengthAwarePaginator
    {
        try {
            $paginated = Ump::byProvince($provinceId)
                ->withTrashed()
                ->orderByDesc('tgl_berlaku')
                ->paginate($perPage);

            $paginated->through(fn(Ump $ump) => [
                ...$this->formatUmp($ump),
                'is_aktif' => $ump->is_aktif,
                'created_by' => $ump->created_by,
                'deleted_at' => $ump->deleted_at?->format('Y-m-d'),
            ]);

            return $paginated;

        } catch (QueryException $e) {
            Log::error('[UpahService::getUmpListByProvince] Query error', [
                'province_id' => $provinceId,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil daftar UMP provinsi.', previous: $e);

        } catch (\Throwable $e) {
            Log::error('[UpahService::getUmpListByProvince] Unexpected error', [
                'province_id' => $provinceId,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Terjadi kesalahan tak terduga saat mengambil UMP.', previous: $e);
        }
    }

    // ── Level 2: List UMSP Provinsi (riwayat, bisa filter sektor) ────────────

    public function getUmspListByProvince(int $provinceId, int $perPage = 15, ?string $sektor = null): LengthAwarePaginator
    {
        try {
            $query = Umsp::byProvince($provinceId)->withTrashed();

            if ($sektor !== null && $sektor !== '') {
                $query->where('sektor', 'like', "%{$sektor}%");
            }

            $paginated = $query
                ->orderBy('sektor')
                ->orderByDesc('tgl_berlaku')
                ->paginate($perPage);

            $paginated->through(fn(Umsp $umsp) => [
                ...$this->formatUmsp($umsp),
                'is_aktif' => $umsp->is_aktif,
                'created_by' => $umsp->created_by,
                'deleted_at' => $umsp->deleted_at?->format('Y-m-d'),
            ]);

            return $paginated;

        } catch (QueryException $e) {
            Log::error('[UpahService::getUmspListByProvince] Query error', [
                'province_id' => $provinceId,
                'sektor' => $sektor,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil daftar UMSP provinsi.', previous: $e);

        } catch (\Throwable $e) {
            Log::error('[UpahService::getUmspListByProvince] Unexpected error', [
                'province_id' => $provinceId,
                'sektor' => $sektor,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Terjadi kesalahan tak terduga saat mengambil UMSP.', previous: $e);
        }
    }

    // ── Level 3: Detail Kota ─────────────────────────────────────────────────

    public function getDetailKota(int $cityId): array
    {
        try {
            $city = City::active()
                ->with('province')
                ->findOrFail($cityId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('[UpahService::getDetailKota] Query error loading city', [
                'city_id' => $cityId,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil data kota. Periksa koneksi database.', previous: $e);
        }

        // ── Load UMK aktif ────────────────────────────────────────────────────
        try {
            $activeUmk = $city->activeUmk;
        } catch (QueryException $e) {
            Log::error('[UpahService::getDetailKota] Query error loading active UMK', [
                'city_id' => $cityId,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil UMK aktif.', previous: $e);
        }

        // ── Load UMSK aktif per sektor ─────────────────────────────────────────
        try {
            $activeUmsks = $city->activeUmsks; // Hanya yang is_aktif = true
        } catch (QueryException $e) {
            Log::error('[UpahService::getDetailKota] Query error loading active UMSK', [
                'city_id' => $cityId,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil UMSK aktif.', previous: $e);
        }

        // ── Load riwayat UMK (tetap dipertahankan) ──────────────────────────────
        try {
            $umkHistory = Umk::byCity($cityId)
                ->withTrashed()
                ->orderByDesc('tgl_berlaku')
                ->get();
        } catch (QueryException $e) {
            Log::error('[UpahService::getDetailKota] Query error loading UMK history', [
                'city_id' => $cityId,
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);
            throw new \RuntimeException('Gagal mengambil riwayat UMK.', previous: $e);
        }

        // ── HAPUS umsk_history ──────────────────────────────────────────────────
        // Tidak ada query umsk_history lagi.

        return [
            'kota' => [
                'id' => $city->id,
                'kode' => $city->kode,
                'nama' => $city->name,
                'provinsi' => $city->province
                    ? ['id' => $city->province->id, 'nama' => $city->province->name]
                    : null,
                'umk_aktif' => $activeUmk ? $this->formatUmk($activeUmk) : null,
                'umsk_aktif' => $activeUmsks
                    ->map(fn(Umsk $umsk) => $this->formatUmsk($umsk))
                    ->values(),
            ],
            'umk_history' => $umkHistory
                ->map(fn(Umk $umk) => [
                    ...$this->formatUmk($umk),
                    'is_aktif' => $umk->is_aktif,
                    'created_by' => $umk->created_by,
                    'deleted_at' => $umk->deleted_at?->format('Y-m-d'),
                ])
                ->values(),
            // umsk_history dihilangkan
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
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException(
                        "Gagal menonaktifkan UMP lama untuk province_id {$validated['province_id']}.",
                        previous: $e,
                    );
                }

                try {
                    return Ump::create([
                        ...$validated,
                        'is_aktif' => true,
                        'created_by' => $actor,
                        'updated_by' => $actor,
                    ]);
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmp] Gagal insert UMP baru', [
                        'province_id' => $validated['province_id'],
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

    // ── Store UMSP ────────────────────────────────────────────────────────────

    public function storeUmsp(array $validated, string $actor): Umsp
    {
        try {
            return DB::transaction(function () use ($validated, $actor): Umsp {
                try {
                    Umsp::deactivatePreviousBySector(
                        provinceId: $validated['province_id'],
                        sektor: $validated['sektor'],
                        updatedBy: $actor,
                    );
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmsp] Gagal menonaktifkan UMSP lama', [
                        'province_id' => $validated['province_id'],
                        'sektor' => $validated['sektor'],
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException(
                        "Gagal menonaktifkan UMSP lama untuk sektor '{$validated['sektor']}'.",
                        previous: $e,
                    );
                }

                try {
                    return Umsp::create([
                        ...$validated,
                        'is_aktif' => true,
                        'created_by' => $actor,
                        'updated_by' => $actor,
                    ]);
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmsp] Gagal insert UMSP baru', [
                        'province_id' => $validated['province_id'],
                        'sektor' => $validated['sektor'],
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException('Gagal menyimpan data UMSP baru.', previous: $e);
                }
            });
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[UpahService::storeUmsp] Unexpected error', [
                'province_id' => $validated['province_id'] ?? null,
                'sektor' => $validated['sektor'] ?? null,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Terjadi kesalahan tak terduga saat menyimpan UMSP.', previous: $e);
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
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException(
                        "Gagal menonaktifkan UMK lama untuk city_id {$validated['city_id']}.",
                        previous: $e,
                    );
                }

                try {
                    return Umk::create([
                        ...$validated,
                        'is_aktif' => true,
                        'created_by' => $actor,
                        'updated_by' => $actor,
                    ]);
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmk] Gagal insert UMK baru', [
                        'city_id' => $validated['city_id'],
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
                        cityId: $validated['city_id'],
                        sektor: $validated['sektor'],
                        updatedBy: $actor,
                    );
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmsk] Gagal menonaktifkan UMSK lama', [
                        'city_id' => $validated['city_id'],
                        'sektor' => $validated['sektor'],
                        'message' => $e->getMessage(),
                        'sql' => $e->getSql(),
                    ]);
                    throw new \RuntimeException(
                        "Gagal menonaktifkan UMSK lama untuk sektor '{$validated['sektor']}'.",
                        previous: $e,
                    );
                }

                try {
                    return Umsk::create([
                        ...$validated,
                        'is_aktif' => true,
                        'created_by' => $actor,
                        'updated_by' => $actor,
                    ]);
                } catch (QueryException $e) {
                    Log::error('[UpahService::storeUmsk] Gagal insert UMSK baru', [
                        'city_id' => $validated['city_id'],
                        'sektor' => $validated['sektor'],
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
                'sektor' => $validated['sektor'] ?? null,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Terjadi kesalahan tak terduga saat menyimpan UMSK.', previous: $e);
        }
    }

    // ── Private Formatters ────────────────────────────────────────────────────

    private function formatUmp(Ump $ump): array
    {
        return [
            'id' => $ump->id,
            'nilai' => (float) $ump->ump,
            'formatted' => $ump->formatump(),
            'tgl_berlaku' => $ump->tgl_berlaku,
            'sumber' => $ump->sumber,
        ];
    }

    private function formatUmsp(Umsp $umsp): array
    {
        return [
            'id' => $umsp->id,
            'sektor' => $umsp->sektor,
            'nilai' => (float) $umsp->umsp,
            'formatted' => $umsp->formatumsp(),
            'tgl_berlaku' => $umsp->tgl_berlaku,
            'sumber' => $umsp->sumber,
        ];
    }

    private function formatUmk(Umk $umk): array
    {
        return [
            'id' => $umk->id,
            'nilai' => (float) $umk->umk,
            'formatted' => $umk->formatumk(),
            'tgl_berlaku' => $umk->tgl_berlaku,
            'sumber' => $umk->sumber,
        ];
    }

    private function formatUmsk(Umsk $umsk): array
    {
        return [
            'id' => $umsk->id,
            'sektor' => $umsk->sektor,
            'nilai' => (float) $umsk->umsk,
            'formatted' => $umsk->formatumsk(),
            'tgl_berlaku' => $umsk->tgl_berlaku,
            'sumber' => $umsk->sumber,
        ];
    }
}