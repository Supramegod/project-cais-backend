<?php

namespace App\Services\Spk;

use App\Models\Quotation;
use App\Models\QuotationPic;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Handles SPK checklist submission and PIC management.
 */
class SpkChecklistService
{
    /**
     * Submit checklist untuk quotation terkait SPK.
     */
    public function submitChecklist(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            $user = Auth::user();
            $currentDateTime = Carbon::now()->toDateTimeString();

            $quotation = Quotation::notDeleted()->findOrFail($id);

            // Logika untuk status serikat
            $statusSerikat = $data['status_serikat'];
            if (($data['ada_serikat'] ?? null) === 'Tidak Ada') {
                $statusSerikat = 'Tidak Ada';
            }

            // Update quotation data
            $quotation->update([
                'npwp' => $data['npwp'],
                'alamat_npwp' => $data['alamat_npwp'],
                'pic_invoice' => $data['pic_invoice'] ?? null,
                'telp_pic_invoice' => $data['telp_pic_invoice'] ?? null,
                'email_pic_invoice' => $data['email_pic_invoice'] ?? null,
                'materai' => $data['materai'],
                'joker_reliever' => $data['joker_reliever'],
                'syarat_invoice' => $data['syarat_invoice'],
                'alamat_penagihan_invoice' => $data['alamat_penagihan_invoice'],
                'catatan_site' => $data['catatan_site'] ?? null,
                'status_serikat' => $statusSerikat,
                'updated_at' => $currentDateTime,
                'updated_by' => $user->full_name,
            ]);

            // Tambah PICs jika ada
            $picsAdded = 0;
            if (! empty($data['pics']) && is_array($data['pics'])) {
                foreach ($data['pics'] as $picData) {
                    $this->addDetailPic($quotation, $picData, $currentDateTime);
                    $picsAdded++;
                }
            }

            return [
                'id' => $quotation->id,
                'npwp' => $quotation->npwp,
                'pic_invoice' => $quotation->pic_invoice,
                'pics_added' => $picsAdded,
            ];
        });
    }

    private function addDetailPic($quotation, array $picData, string $currentDateTime): void
    {
        $user = Auth::user();

        QuotationPic::create([
            'quotation_id' => $quotation->id,
            'nama' => $picData['nama'],
            'jabatan_id' => $picData['jabatan'] ?? null,
            'no_telp' => $picData['no_telp'] ?? null,
            'email' => $picData['email'] ?? null,
            'leads_id' => $quotation->leads_id,
            'is_kuasa' => 0,
            'created_at' => $currentDateTime,
            'created_by' => $user->full_name,
            'created_by_user_id' => $user->id,
        ]);
    }
}
