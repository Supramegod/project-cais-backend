<?php

namespace App\Services\Option;

use App\Models\Benua;
use App\Models\BidangPerusahaan;
use App\Models\Branch;
use App\Models\City;
use App\Models\Company;
use App\Models\District;
use App\Models\JabatanPic;
use App\Models\JenisVisit;
use App\Models\KategoriSesuaiHc;
use App\Models\Loyalty;
use App\Models\Negara;
use App\Models\Platform;
use App\Models\Province;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Models\StatusLeads;
use App\Models\StatusPks;
use App\Models\StatusQuotation;
use App\Models\StatusSpk;
use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class OptionService
{
    /**
     * Get active companies (id, name, code) ordered by name.
     */
    public function getEntitas(): Collection
    {
        return Company::where('is_active', true)
            ->select(['id', 'name', 'code'])
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get bidang perusahaan excluding soft-deleted, ordered by name.
     */
    public function getBidangPerusahaan(): Collection
    {
        return BidangPerusahaan::whereNull('deleted_at')
            ->orderBy('nama', 'asc')
            ->get();
    }

    /**
     * Get all platforms.
     */
    public function getPlatforms(): Collection
    {
        return Platform::all();
    }

    /**
     * Get all status leads.
     */
    public function getStatusLeads(): Collection
    {
        return StatusLeads::all();
    }

    /**
     * Get all benua.
     */
    public function getBenua(): Collection
    {
        return Benua::all();
    }

    /**
     * Get jabatan PIC excluding soft-deleted.
     */
    public function getJabatanPic(): Collection
    {
        return JabatanPic::whereNull('deleted_at')->get();
    }

    /**
     * Get active branches by province, with city relationship.
     */
    public function getBranchesByProvince(int $provinceId): Collection
    {
        return Branch::where('is_active', 1)
            ->byProvince($provinceId)
            ->with(['city:id,name,province_id'])
            ->select('id', 'name', 'description', 'city_id', 'is_active')
            ->get();
    }

    /**
     * Get active branches, optionally filtered by province_id.
     */
    public function getBranches(?int $provinceId = null): Collection
    {
        $query = Branch::where('is_active', 1);

        if ($provinceId !== null) {
            $query->byProvince($provinceId);
        }

        return $query->select('id', 'name', 'description', 'city_id', 'is_active')
            ->get();
    }

    /**
     * Get active users with sales roles, filtered by branch.
     */
    public function getUsers(int $branchId): Collection
    {
        return User::where('is_active', 1)
            ->whereIn('cais_role_id', [29, 31, 32, 33])
            ->where('branch_id', $branchId)
            ->select('id', 'full_name', 'username', 'email', 'cais_role_id', 'branch_id')
            ->get();
    }

    /**
     * Get status quotation excluding soft-deleted.
     */
    public function getStatusQuotation(): Collection
    {
        return StatusQuotation::whereNull('deleted_at')->get();
    }

    /**
     * Get companies filtered by layanan_id.
     * Returns a Collection. The calling controller decides whether to push the IONS company.
     *
     * When an unrecognised layanan_id is given, the method returns an empty Collection
     * so the controller can produce the bespoke {success,message,data,total} envelope.
     */
    public function getEntitasByLayanan(int $layananId): Collection
    {
        $query = Company::where('is_active', true)
            ->select(['id', 'name', 'code'])
            ->orderBy('name', 'asc');

        switch ($layananId) {
            case 1:
                $query->where(function ($q) {
                    $q->where('code', 'GSU')
                        ->orWhere('code', 'SN');
                });
                break;

            case 2:
            case 4:
                $query->where(function ($q) {
                    $q->where('code', 'SIG')
                        ->orWhere('code', 'SNI');
                });
                break;

            case 3:
                $query->where(function ($q) {
                    $q->where('code', 'RCI')
                        ->orWhere('code', 'SNI');
                });
                break;

            default:
                return new Collection();
        }

        $data = $query->get();

        // Tambahkan opsi IONS secara manual (sesuai dengan JavaScript)
        // Pastikan IONS belum ada dalam hasil query sebelum menambahkannya
        $ionsExists = $data->contains('id', 17);
        if (!$ionsExists) {
            $ionsCompany = [
                'id' => 17,
                'name' => 'PT. Indah Optimal Nusantara',
                'code' => 'IONS',
            ];
            $data->push($ionsCompany);
        }

        return $data;
    }

    /**
     * Get all provinces.
     */
    public function getProvinsi(): Collection
    {
        return Province::all();
    }

    /**
     * Get cities by province ID.
     */
    public function getKota(int $provinsiId): Collection
    {
        return City::where('province_id', $provinsiId)->get();
    }

    /**
     * Get districts by city ID.
     */
    public function getKecamatan(int $kotaId): Collection
    {
        return District::where('city_id', $kotaId)->get();
    }

    /**
     * Get villages by district ID.
     */
    public function getKelurahan(int $kecamatanId): Collection
    {
        return Village::where('district_id', $kecamatanId)->get();
    }

    /**
     * Get countries by benua ID.
     */
    public function getNegara(int $benuaId): Collection
    {
        return Negara::where('id_benua', $benuaId)->get();
    }

    /**
     * Get all loyalty options.
     */
    public function getLoyalty(): Collection
    {
        return Loyalty::get();
    }

    /**
     * Get all kategori sesuai HC.
     */
    public function getKategoriSesuaiHc(): Collection
    {
        return KategoriSesuaiHc::get();
    }

    /**
     * Get all rule THR.
     */
    public function getRuleThr(): Collection
    {
        return RuleThr::get();
    }

    /**
     * Get all salary rules.
     */
    public function getSalaryRule(): Collection
    {
        return SalaryRule::get();
    }

    /**
     * Get all status SPK.
     */
    public function getStatusSpk(): Collection
    {
        return StatusSpk::get();
    }

    /**
     * Get all status PKS.
     */
    public function getStatusPks(): Collection
    {
        return StatusPks::get();
    }

    /**
     * Get active users with specific roles for customer activity filter.
     */
    public function getListUser(): Collection
    {
        return User::where('is_active', 1)
            ->whereIn('cais_role_id', [4, 5, 29, 30, 31, 54])
            ->select('id', 'full_name', 'username', 'email')
            ->orderBy('full_name', 'asc')
            ->get();
    }

    /**
     * Get jenis visit excluding soft-deleted, ordered by name.
     */
    public function getListJenisVisit(): Collection
    {
        return JenisVisit::whereNull('deleted_at')
            ->select('id', 'nama')
            ->orderBy('nama', 'asc')
            ->get();
    }
}
