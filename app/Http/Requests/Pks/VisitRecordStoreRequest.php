<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;
use App\Models\Pks;
use App\Models\Site;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\FluentRule;

class VisitRecordStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Auto-derive leads_id — client tidak perlu mengirimnya.
     * Prioritas: sl_site.leads_id (site menyimpan leads-nya), fallback sl_pks.leads_id.
     * User hanya mengirim: pks_id, site_id, role, tgl_visit_aktual, hasil_visit, catatan, fotos.
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
            'schedule_id'      => FluentRule::integer()->nullable()->exists('sl_pks_visit_schedule', 'id'),
            'pks_id'           => FluentRule::integer()->required()->exists('sl_pks', 'id'),
            'site_id'          => FluentRule::integer()->required()->exists('sl_site', 'id'),
            'role'             => FluentRule::string()->required()->in(['operasional', 'crm']),
            'tgl_visit_aktual' => FluentRule::date()->required(),
            'hasil_visit'      => FluentRule::string()->required()->in(['selesai', 'ada_kendala', 'ditunda']),
            'catatan'          => FluentRule::string()->required()->min(10),
            'fotos'            => FluentRule::array()->required()->min(1)->max(3),
            'fotos.*'          => FluentRule::file()->required()->mimes('jpg', 'jpeg', 'png')->max(5120),
            // Auto-derived — tidak wajib dari client
            'leads_id'         => FluentRule::integer()->nullable(),
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_id.exists'           => 'Schedule visit tidak ditemukan.',
            'pks_id.required'              => 'PKS wajib dipilih.',
            'pks_id.exists'                => 'PKS tidak ditemukan.',
            'site_id.required'             => 'Site wajib dipilih.',
            'site_id.exists'               => 'Site tidak ditemukan.',
            'role.required'                => 'Role wajib dipilih.',
            'role.in'                      => 'Role harus operasional atau crm.',
            'tgl_visit_aktual.required'    => 'Tanggal visit aktual wajib diisi.',
            'hasil_visit.required'         => 'Hasil visit wajib dipilih.',
            'hasil_visit.in'               => 'Hasil visit harus selesai, ada_kendala, atau ditunda.',
            'catatan.required'             => 'Catatan wajib diisi.',
            'catatan.min'                  => 'Catatan minimal 10 karakter.',
            'fotos.required'               => 'Foto wajib diunggah.',
            'fotos.min'                    => 'Minimal 1 foto wajib diunggah.',
            'fotos.max'                    => 'Maksimal 3 foto.',
            'fotos.*.required'             => 'Foto wajib diunggah.',
            'fotos.*.mimes'                => 'Foto harus berformat jpg, jpeg, atau png.',
            'fotos.*.max'                  => 'Ukuran foto maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'schedule_id'      => 'Schedule',
            'pks_id'           => 'PKS',
            'site_id'          => 'Site',
            'leads_id'         => 'Leads',
            'role'             => 'Role',
            'tgl_visit_aktual' => 'Tanggal Visit Aktual',
            'hasil_visit'      => 'Hasil Visit',
            'catatan'          => 'Catatan',
            'fotos'            => 'Foto',
            'fotos.*'          => 'Foto',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Optional: validasi sisa target visit role > 0
            // Dapat ditangani di service level bila diperlukan
        });
    }
}
