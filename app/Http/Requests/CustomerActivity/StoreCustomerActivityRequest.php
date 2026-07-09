<?php

namespace App\Http\Requests\CustomerActivity;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi untuk CustomerActivityController@add (create).
 *
 * Menerjemahkan getValidationRules(false) + aturan file multipart yang
 * sebelumnya digabung inline di controller. FluentRule style.
 */
class StoreCustomerActivityRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leads_id'        => FluentRule::integer()->required()->exists('sl_leads', 'id'),
            'tgl_activity'    => FluentRule::date()->required(),
            'tipe'            => FluentRule::string()->required()->in(['Telepon', 'Email', 'Meeting', 'Visit', 'Online Meeting']),
            'notes'           => FluentRule::string()->nullable(),
            'notes_tipe'      => FluentRule::string()->nullable(),
            'tim_sales_id'    => FluentRule::integer()->nullable()->exists('m_tim_sales', 'id'),
            'tim_sales_d_id'  => FluentRule::integer()->nullable()->exists('m_tim_sales_d', 'id'),
            'status_leads_id' => FluentRule::integer()->nullable()->exists('m_status_leads', 'id'),
            'start'           => FluentRule::string()->nullable()->dateFormat('H:i'),
            'end'             => FluentRule::string()->nullable()->dateFormat('H:i')->rule('after:start'),
            'durasi'          => FluentRule::integer()->nullable()->min(0),
            'tgl_realisasi'   => FluentRule::date()->nullable()->afterOrEqual('tgl_activity'),
            'jam_realisasi'   => FluentRule::string()->nullable()->dateFormat('H:i'),
            'jenis_visit_id'  => FluentRule::integer()->nullable(),
            'jenis_visit'     => FluentRule::string()->nullable(),
            'notulen'         => FluentRule::string()->nullable(),
            'email'           => FluentRule::email()->nullable(),
            'penerima'        => FluentRule::string()->nullable(),
            'link_bukti_foto' => FluentRule::url()->nullable(),

            'files'           => FluentRule::array()->nullable(),
            'files.*'         => FluentRule::file()->nullable()->mimes('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png')->max(1024),
        ];
    }

    public function messages(): array
    {
        return [
            'leads_id.required'              => 'Leads wajib dipilih.',
            'leads_id.exists'               => 'Leads yang dipilih tidak valid.',
            'tgl_activity.required'         => 'Tanggal activity wajib diisi.',
            'tgl_activity.date'             => 'Format tanggal activity tidak valid.',
            'tipe.required'                 => 'Tipe activity wajib dipilih.',
            'tipe.in'                       => 'Tipe activity harus salah satu dari: Telepon, Email, Meeting, Visit, atau Online Meeting.',
            'tim_sales_id.exists'           => 'Tim Sales yang dipilih tidak valid.',
            'tim_sales_d_id.exists'         => 'Sales yang dipilih tidak valid.',
            'status_leads_id.exists'        => 'Status leads yang dipilih tidak valid.',
            'start.date_format'             => 'Format jam mulai harus HH:MM (contoh: 09:00).',
            'end.date_format'               => 'Format jam selesai harus HH:MM (contoh: 10:00).',
            'end.after'                     => 'Jam selesai harus lebih besar dari jam mulai.',
            'jam_realisasi.date_format'     => 'Format jam realisasi harus HH:MM (contoh: 09:00).',
            'durasi.integer'                => 'Durasi harus berupa angka.',
            'durasi.min'                    => 'Durasi tidak boleh kurang dari 0.',
            'tgl_realisasi.date'            => 'Format tanggal realisasi tidak valid.',
            'tgl_realisasi.after_or_equal'  => 'Tanggal realisasi tidak boleh sebelum tanggal activity.',
            'email.email'                   => 'Format email tidak valid.',
            'link_bukti_foto.url'           => 'Format link bukti foto tidak valid.',
            'files.array'                   => 'Format files harus berupa array.',
            'files.*.file'                  => 'File yang diupload harus berupa file.',
            'files.*.mimes'                 => 'File harus berformat: pdf, doc, docx, jpg, jpeg, atau png.',
            'files.*.max'                   => 'Ukuran file maksimal 1MB.',
        ];
    }
}
