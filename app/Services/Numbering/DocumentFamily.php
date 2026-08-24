<?php

namespace App\Services\Numbering;

use Illuminate\Database\Eloquent\Model;

/**
 * Kumpulkan seluruh nomor dalam satu "keluarga" dokumen — dari root ke bawah,
 * termasuk baris yang sudah di-soft-delete.
 *
 * Counter versi harus dihitung terhadap keluarga, bukan terhadap parent
 * langsung. Query lama:
 *
 *     ->where(fn($q) => $q->where('quotation_referensi_id', $id)->orWhere('id', $id))
 *     ->where('tipe_quotation', $tipe)->count() + 1
 *
 * menghitung dokumen referensi sebagai anggotanya sendiri, sehingga merevisi
 * sebuah revisi selalu menghasilkan `count = 1` → counter `02`, selamanya.
 * Itulah kenapa nomor rusak yang dilaporkan berbunyi `-V02-V02-V02-V02`.
 */
class DocumentFamily
{
    /** Batas iterasi, penjaga terhadap data lama yang relasinya melingkar. */
    private const MAX_DEPTH = 20;

    /**
     * @param  class-string<Model> $modelClass
     * @param  string              $parentColumn mis. 'quotation_referensi_id' | 'pks_induk_id'
     * @return string[]            daftar nomor (sudah dibuang yang kosong)
     */
    public static function nomorOf(string $modelClass, string $parentColumn, int $memberId): array
    {
        $root = self::resolveRoot($modelClass, $parentColumn, $memberId);

        if (!$root) {
            return [];
        }

        $visited = [$root->id => true];
        $nomor = [$root->nomor];
        $frontier = [$root->id];
        $depth = 0;

        while ($frontier !== [] && $depth++ < self::MAX_DEPTH) {
            $children = $modelClass::withTrashed()
                ->whereIn($parentColumn, $frontier)
                ->get(['id', 'nomor']);

            $frontier = [];

            foreach ($children as $child) {
                if (isset($visited[$child->id])) {
                    continue;
                }

                $visited[$child->id] = true;
                $nomor[] = $child->nomor;
                $frontier[] = $child->id;
            }
        }

        return array_values(array_filter($nomor, fn ($n) => $n !== null && $n !== ''));
    }

    /**
     * Telusuri ke atas sampai dokumen yang tidak punya induk.
     *
     * @param  class-string<Model> $modelClass
     */
    public static function resolveRoot(string $modelClass, string $parentColumn, int $memberId): ?Model
    {
        $current = $modelClass::withTrashed()->find($memberId);

        if (!$current) {
            return null;
        }

        $visited = [$current->id => true];
        $depth = 0;

        while ($current->{$parentColumn} && $depth++ < self::MAX_DEPTH) {
            $parent = $modelClass::withTrashed()->find($current->{$parentColumn});

            // Induk hilang, atau relasi melingkar — berhenti di sini.
            if (!$parent || isset($visited[$parent->id])) {
                break;
            }

            $visited[$parent->id] = true;
            $current = $parent;
        }

        return $current;
    }
}
