<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;
use App\Models\Pks;
use App\Models\PksVisitSchedule;
use App\Models\Site;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\FluentRule;

class VisitScheduleManualStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Auto-derive leads_id — client tidak perlu mengirimnya.
     * Prioritas: sl_site.leads_id, fallback sl_pks.leads_id.
     */
    protected function prepareForValidation(): void
    {
        $leadsId = null;

        if ($this->site_id) {
            $leadsId = Site::whereKey($this->site_id)->value('leads_id');
        }

        if (! $leadsId && $this->pks_id) {
            $leadsId = Pks::whereKey($this->pks_id)->value('leads_id');
        }

        if ($leadsId) {
            $this->merge(['leads_id' => $leadsId]);
        }
    }

    public function rules(): array
    {
        return [
            'pks_id'      => FluentRule::integer()->required()->exists('sl_pks', 'id'),
            'site_id'     => FluentRule::integer()->required()->exists('sl_site', 'id'),
            'role'        => FluentRule::string()->required()->in(['operasional', 'crm']),
            'pic_user_id' => FluentRule::integer()->required()->exists('mysqlhris.m_user', 'id'),
            'tgl_jadwal'  => FluentRule::date()->required(),
            // Auto-derived
            'leads_id'    => FluentRule::integer()->nullable(),
        ];
    }

    public function messages(): array
    {
        return [
            'pks_id.required'      => 'PKS wajib dipilih.',
            'pks_id.exists'        => 'PKS tidak ditemukan.',
            'site_id.required'     => 'Site wajib dipilih.',
            'site_id.exists'       => 'Site tidak ditemukan.',
            'leads_id.required'    => 'Leads wajib dipilih.',
            'leads_id.exists'      => 'Leads tidak ditemukan.',
            'role.required'        => 'Role wajib dipilih.',
            'role.in'              => 'Role harus operasional atau crm.',
            'pic_user_id.required' => 'PIC wajib dipilih.',
            'pic_user_id.exists'   => 'PIC tidak ditemukan.',
            'tgl_jadwal.required'  => 'Tanggal jadwal wajib diisi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'pks_id'      => 'PKS',
            'site_id'     => 'Site',
            'leads_id'    => 'Leads',
            'role'        => 'Role',
            'pic_user_id' => 'PIC',
            'tgl_jadwal'  => 'Tanggal Jadwal',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $pks = Pks::find($this->pks_id);

            if (!$pks) {
                $validator->errors()->add('pks_id', 'PKS tidak ditemukan.');

                return;
            }

            // Validasi tgl_jadwal berada dalam rentang kontrak
            $tglJadwal = $this->tgl_jadwal;
            $kontrakAwal = $pks->kontrak_awal instanceof \Carbon\Carbon
                ? $pks->kontrak_awal
                : \Carbon\Carbon::parse($pks->kontrak_awal);
            $kontrakAkhir = $pks->kontrak_akhir instanceof \Carbon\Carbon
                ? $pks->kontrak_akhir
                : \Carbon\Carbon::parse($pks->kontrak_akhir);

            if ($kontrakAwal && $tglJadwal < $kontrakAwal->toDateString()) {
                $validator->errors()->add(
                    'tgl_jadwal',
                    "Tanggal jadwal tidak boleh sebelum kontrak awal ({$kontrakAwal->toDateString()})."
                );
            }

            if ($kontrakAkhir && $tglJadwal > $kontrakAkhir->toDateString()) {
                $validator->errors()->add(
                    'tgl_jadwal',
                    "Tanggal jadwal tidak boleh setelah kontrak akhir ({$kontrakAkhir->toDateString()})."
                );
            }

            // Cek tidak duplikat: customer (leads_id) + tanggal + role
            $duplicate = PksVisitSchedule::where('leads_id', $this->leads_id)
                ->where('tgl_jadwal', $this->tgl_jadwal)
                ->where('role', $this->role)
                ->exists();

            if ($duplicate) {
                $validator->errors()->add(
                    'tgl_jadwal',
                    "Sudah ada jadwal visit untuk customer, tanggal, dan role yang sama."
                );
            }
        });
    }
}
