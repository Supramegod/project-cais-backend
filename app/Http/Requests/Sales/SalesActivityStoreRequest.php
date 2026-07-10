<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class SalesActivityStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leads_id'           => FluentRule::integer('Leads ID')->required()->exists('sl_leads', 'id'),
            'leads_kebutuhan_id' => FluentRule::integer('Leads Kebutuhan ID')->required()->exists('sl_leads_kebutuhan', 'id'),
            'tgl_activity'       => FluentRule::date('Tanggal activity')->required(),
            'jenis_activity'     => FluentRule::string('Jenis activity')->required()->max(255),
            'notulen'            => FluentRule::string('Notulen')->required(),
            'files'              => FluentRule::array(label: 'Files')->nullable()->each(
                FluentRule::file()->nullable()->mimes('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png')->max(10240)
            ),
        ];
    }

    public function messages(): array
    {
        return [
            'leads_id.required'           => 'Leads ID wajib diisi',
            'leads_id.exists'             => 'Leads tidak ditemukan',
            'leads_kebutuhan_id.required' => 'Leads Kebutuhan ID wajib diisi',
            'leads_kebutuhan_id.exists'   => 'Leads Kebutuhan tidak ditemukan',
            'tgl_activity.required'       => 'Tanggal activity wajib diisi',
            'tgl_activity.date'           => 'Format tanggal tidak valid',
            'jenis_activity.required'     => 'Jenis activity wajib diisi',
            'notulen.required'            => 'Notulen wajib diisi',
            'files.*.file'                => 'File yang diupload harus berupa file',
            'files.*.mimes'               => 'File harus berformat: pdf, doc, docx, jpg, jpeg, atau png',
            'files.*.max'                 => 'Ukuran file maksimal 10MB',
        ];
    }
}
