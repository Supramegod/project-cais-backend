<?php

namespace App\Services\Quotation;

use App\Models\City;
use App\Models\Province;
use App\Models\Quotation;
use App\Models\QuotationSite;
use App\Models\Umk;
use App\Models\Ump;
use App\Models\Umsk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuotationSiteService
{
    public function createQuotationSites(Quotation $quotation, Request $request, string $createdBy): void
    {
        if ($request->jumlah_site == 'Multi Site') {
            foreach ($request->multisite as $key => $value) {
                $this->createQuotationSite($quotation, $request, $key, true, $createdBy);
            }
        } else {
            $this->createQuotationSite($quotation, $request, null, false, $createdBy);
        }
    }

    public function createQuotationSite(Quotation $quotation, Request $request, ?int $index, bool $isMulti, string $createdBy): void
    {
        $provinceId = $isMulti ? $request->provinsi_multi[$index] : $request->provinsi;
        $cityId = $isMulti ? $request->kota_multi[$index] : $request->kota;

        $province = Province::findOrFail($provinceId);
        $city = City::findOrFail($cityId);

        $ump = Ump::where('province_id', $province->id)->active()->first();
        $umk = Umk::where('city_id', $city->id)->active()->first();
        $umsk = Umsk::where('city_id', $city->id)->active()->first();

        QuotationSite::create([
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'nama_site' => $isMulti ? $request->multisite[$index] : $request->nama_site,
            'provinsi_id' => $provinceId,
            'provinsi' => $province->name,
            'kota_id' => $cityId,
            'kota' => $city->name,
            'ump' => $ump ? $ump->ump : 0,
            'umk' => $umk ? $umk->umk : 0,
            'umsk' => $umsk ? $umsk->umsk : 0,
            'penempatan' => $isMulti ? $request->penempatan_multi[$index] : $request->penempatan,
            'created_by' => $createdBy,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    public function createQuotationSiteFromReference(Quotation $quotation, QuotationSite $refSite, string $createdBy): QuotationSite
    {
        $province = Province::findOrFail($refSite->provinsi_id);
        $city = City::findOrFail($refSite->kota_id);

        $ump = Ump::where('province_id', $province->id)->active()->first();
        $umk = Umk::where('city_id', $city->id)->active()->first();
        $umsk = Umsk::where('city_id', $city->id)->active()->first();

        return QuotationSite::create([
            'quotation_id' => $quotation->id, 'leads_id' => $quotation->leads_id,
            'nama_site' => $refSite->nama_site, 'provinsi_id' => $refSite->provinsi_id,
            'provinsi' => $province->name, 'kota_id' => $refSite->kota_id,
            'kota' => $city->name, 'ump' => $ump ? $ump->ump : 0,
            'umk' => $umk ? $umk->umk : 0, 'umsk' => $umsk ? $umsk->umsk : 0,
            'penempatan' => $refSite->penempatan, 'created_by' => $createdBy,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    public function checkSiteExists(int $leadsId, string $namaSite, int $provinsiId, int $kotaId): bool
    {
        return QuotationSite::where('leads_id', $leadsId)
            ->whereRaw('LOWER(TRIM(nama_site)) = ?', [strtolower(trim($namaSite))])
            ->where('provinsi_id', $provinsiId)->where('kota_id', $kotaId)
            ->whereNull('deleted_at')->exists();
    }

    public function createNewSitesOnly(Quotation $quotation, Request $request, string $createdBy): void
    {
        if ($request->jumlah_site == 'Multi Site') {
            foreach ($request->multisite as $key => $value) {
                if (!$this->checkSiteExists($request->perusahaan_id, $value, $request->provinsi_multi[$key], $request->kota_multi[$key])) {
                    $this->createQuotationSite($quotation, $request, $key, true, $createdBy);
                }
            }
        } else {
            if (!$this->checkSiteExists($request->perusahaan_id, $request->nama_site, $request->provinsi, $request->kota)) {
                $this->createQuotationSite($quotation, $request, null, false, $createdBy);
            }
        }
    }

    public function validateMultiSiteData(Request $request): void
    {
        if ($request->jumlah_site == 'Multi Site') {
            $counts = [
                count($request->multisite ?? []), count($request->provinsi_multi ?? []),
                count($request->kota_multi ?? []), count($request->penempatan_multi ?? []),
            ];
            if (count(array_unique($counts)) > 1) {
                throw new \Exception('Jumlah data multisite, provinsi, kota, dan penempatan harus sama');
            }
        }
    }
}
