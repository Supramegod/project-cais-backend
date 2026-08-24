<?php

namespace App\Services\Pks\Fulfillment;

use App\Models\Branch;
use App\Models\Pks;
use App\Models\Site;

class BranchResolutionService
{
    public function resolveSiteAnchor(Pks $pks): ?Site
    {
        // Step 1: Cek flag manual is_visit_anchor = 1
        $manualAnchor = $pks->sites()
            ->where('is_visit_anchor', 1)
            ->select('id', 'pks_id', 'kota_id', 'nama_site', 'is_visit_anchor')
            ->first();
        if ($manualAnchor) {
            return $manualAnchor;
        }

        // Step 2: Ambil semua site PKS — cuma kolom yg dipakai
        $sites = $pks->sites()
            ->select('id', 'pks_id', 'kota_id', 'nama_site', 'is_visit_anchor')
            ->get();
        $kotaIds = $sites->pluck('kota_id')->unique()->filter()->toArray();

        if (empty($kotaIds)) {
            return $sites->first();
        }

        // Step 3: Query mysqlhris — Branch where city_id IN kotaIds. Cuma perlu city_id
        $branchCityIds = Branch::whereIn('city_id', $kotaIds)
            ->select('city_id')
            ->get()
            ->pluck('city_id')
            ->unique()
            ->toArray();

        // Step 4: Cocokkan site.kota_id = branch.city_id
        $matchedSites = $sites->filter(function ($site) use ($branchCityIds) {
            return in_array($site->kota_id, $branchCityIds);
        });

        if ($matchedSites->count() === 1) {
            return $matchedSites->first();
        }

        if ($matchedSites->count() > 1) {
            // >1 match: cek lagi is_visit_anchor
            $flagged = $matchedSites->where('is_visit_anchor', 1)->first();

            return $flagged ?: $matchedSites->first();
        }

        // Step 5: Tidak ada match (0) → fallback ke site pertama
        $flagged = $sites->where('is_visit_anchor', 1)->first();

        return $flagged ?: $sites->first();
    }
}
