<?php

namespace App\Http\Resources\Quotation;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for quotation list/index views (~50 lines).
 * Only exposes fields needed for table/list display.
 */
class QuotationListResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nomor' => $this->nomor,
            'tgl_quotation' => $this->tgl_quotation,
            'tgl_quotation_formatted' => $this->tgl_quotation ? Carbon::parse($this->tgl_quotation)->isoFormat('D MMMM Y') : null,
            'nama_perusahaan' => $this->nama_perusahaan,
            'kebutuhan' => $this->kebutuhan,
            'kebutuhan_id' => $this->kebutuhan_id,
            'company' => $this->company,
            'company_id' => $this->company_id,
            'jumlah_site' => $this->jumlah_site,
            'step' => $this->step,
            'is_aktif' => $this->is_aktif,
            'status_quotation_id' => $this->status_quotation_id,
            'revisi' => $this->revisi,
            'alasan_revisi' => $this->alasan_revisi,
            'quotation_asal_id' => $this->quotation_asal_id,
            'created_at' => $this->created_at,
            'created_at_formatted' => $this->created_at ? Carbon::parse($this->created_at)->isoFormat('D MMMM Y') : null,
            'created_by' => $this->created_by,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'status_quotation' => $this->whenLoaded('statusQuotation', function () {
                return [
                    'id' => $this->statusQuotation->id,
                    'nama' => $this->statusQuotation->nama,
                ];
            }),
            'leads' => $this->whenLoaded('leads', function () {
                return [
                    'id' => $this->leads->id,
                    'nomor' => $this->leads->nomor,
                    'nama_perusahaan' => $this->leads->nama_perusahaan,
                    'pic' => $this->leads->pic,
                    'branch' => $this->leads->branch ? $this->leads->branch->name : null,
                ];
            }),
            'can_create_spk' => $this->is_aktif == 1,
        ];
    }
}
