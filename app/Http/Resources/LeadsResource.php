<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nomor' => $this->nomor,
            'wilayah' => $this->branch->name ?? '-',
            'wilayah_id' => $this->branch_id,
            'tgl_leads' => Carbon::parse($this->tgl_leads)->isoFormat('D MMMM Y'),
            'sales' => $this->timSalesD->nama ?? '-',
            'nama_perusahaan' => $this->nama_perusahaan,
            'telp_perusahaan' => $this->telp_perusahaan,
            'provinsi' => $this->provinsi,
            'kota' => $this->kota,
            'no_telp' => $this->no_telp,
            'email' => $this->email,
            'status_leads' => $this->statusLeads->nama ?? '-',
            'status_leads_id' => $this->status_leads_id,
            'sumber_leads' => $this->platform->nama ?? '-',
            'sumber_leads_id' => $this->platform_id,
            'created_by' => $this->created_by,
            'notes' => $this->notes,
            'kebutuhan' => $this->leadsKebutuhan->map(fn($lk) => [
                'id' => $lk->kebutuhan_id,
                'nama' => $lk->kebutuhan->nama ?? '-',
                'tim_sales_d_id' => $lk->tim_sales_d_id,
                'sales_name' => $lk->timSalesD->nama ?? '-'
            ])
        ];
    }
}
