<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step2Request extends BaseRequest
{
    public function rules(): array
    {
        $excludedRoles = [53, 54, 55, 56, 2];
        $userRole = auth()->user()?->cais_role_id;

        $rules = [
            'edit' => FluentRule::boolean()->sometimes(),
            'mulai_kontrak' => FluentRule::date()
                ->when(! in_array($userRole, $excludedRoles), fn ($r) => $r->required()->rule('after_or_equal:today')),
            'kontrak_selesai' => FluentRule::date()
                ->when(! in_array($userRole, $excludedRoles), fn ($r) => $r->required()->rule('after_or_equal:mulai_kontrak')),
            'tgl_penempatan' => FluentRule::date()
                ->when(! in_array($userRole, $excludedRoles), fn ($r) => $r->required()),
            'top' => FluentRule::string()->required()->in(['Non TOP', 'Kurang Dari 7 Hari', 'Lebih Dari 7 Hari']),
            'salary_rule' => FluentRule::integer()->required()->exists('m_salary_rule', 'id'),
            'jumlah_hari_invoice' => FluentRule::integer()->requiredIf('top', 'Lebih Dari 7 Hari')->min(1),
            'tipe_hari_invoice' => FluentRule::string()->requiredIf('top', 'Lebih Dari 7 Hari')->in(['Kerja', 'Kalender']),
            'evaluasi_kontrak' => FluentRule::string()->required(),
            'durasi_kerjasama' => FluentRule::string()->required(),
            'durasi_karyawan' => FluentRule::string()->required(),
            'evaluasi_karyawan' => FluentRule::string()->required(),
            'ada_cuti' => FluentRule::string()->required()->in(['Ada', 'Tidak Ada']),
            'cuti' => FluentRule::array()->requiredIf('ada_cuti', 'Ada')->children([
                '*' => FluentRule::string()->sometimes()->in([
                    'Cuti Tahunan', 'Cuti Melahirkan', 'Cuti Kematian',
                    'Istri Melahirkan', 'Cuti Menikah', 'Cuti Roster', 'Tidak Ada',
                ]),
            ]),
            'gaji_saat_cuti' => FluentRule::string()->sometimes()->in(['No Work No Pay', 'Prorate']),
            'prorate' => FluentRule::integer()->requiredIf('gaji_saat_cuti', 'Prorate')->min(0),
            'shift_kerja' => FluentRule::string()->sometimes(),
            'hari_kerja' => FluentRule::string()->required(),
            'jam_kerja' => FluentRule::string()->required(),
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'mulai_kontrak.required' => 'Mulai kontrak harus diisi',
            'mulai_kontrak.after_or_equal' => 'Tanggal mulai kontrak tidak boleh kurang dari hari ini.',
            'mulai_kontrak.date' => 'Mulai kontrak harus berupa tanggal yang valid',
            'kontrak_selesai.required' => 'Kontrak selesai harus diisi',
            'kontrak_selesai.date' => 'Kontrak selesai harus berupa tanggal yang valid',
            'kontrak_selesai.after_or_equal' => 'Kontrak selesai harus setelah atau sama dengan mulai kontrak',
            'tgl_penempatan.required' => 'Tanggal penempatan harus diisi',
            'tgl_penempatan.date' => 'Tanggal penempatan harus berupa tanggal yang valid',
            'top.required' => 'TOP harus diisi',
            'top.in' => 'TOP harus salah satu dari: Non TOP, Kurang Dari 7 Hari, Lebih Dari 7 Hari',
            'salary_rule.required' => 'Salary rule harus diisi',
            'salary_rule.exists' => 'Salary rule tidak valid',
            'jumlah_hari_invoice.required_if' => 'Jumlah hari invoice harus diisi ketika TOP adalah Lebih Dari 7 Hari',
            'jumlah_hari_invoice.integer' => 'Jumlah hari invoice harus berupa angka',
            'jumlah_hari_invoice.min' => 'Jumlah hari invoice minimal 1',
            'tipe_hari_invoice.required_if' => 'Tipe hari invoice harus diisi ketika TOP adalah Lebih Dari 7 Hari',
            'tipe_hari_invoice.in' => 'Tipe hari invoice harus salah satu dari: Kerja, Kalender',
            'evaluasi_kontrak.required' => 'Evaluasi kontrak harus diisi',
            'durasi_kerjasama.required' => 'Durasi kerjasama harus diisi',
            'durasi_karyawan.required' => 'Durasi karyawan harus diisi',
            'evaluasi_karyawan.required' => 'Evaluasi karyawan harus diisi',
            'hari_kerja.required' => 'Hari kerja harus diisi',
            'jam_kerja.required' => 'Jam kerja harus diisi',
            'ada_cuti.required' => 'Status cuti harus dipilih',
            'ada_cuti.in' => 'Status cuti harus Ada atau Tidak Ada',
            'cuti.required_if' => 'Jenis cuti harus dipilih ketika memilih ada cuti',
            'cuti.*.in' => 'Jenis cuti tidak valid',
            'gaji_saat_cuti.in' => 'Gaji saat cuti harus salah satu dari: No Work No Pay, Prorate',
            'prorate.required_if' => 'Prorate harus diisi ketika memilih gaji saat cuti Prorate',
            'prorate.min' => 'Prorate tidak boleh kurang dari 0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $excludedRoles = [53, 54, 55, 56, 2];
            $userRole = auth()->user()->cais_role_id ?? null;

            if (! in_array($userRole, $excludedRoles)) {
                if ($this->mulai_kontrak && $this->kontrak_selesai) {
                    if ($this->mulai_kontrak > $this->kontrak_selesai) {
                        $validator->errors()->add('mulai_kontrak', 'Mulai Kontrak tidak boleh lebih dari Kontrak Selesai');
                    }
                    if ($this->tgl_penempatan < $this->mulai_kontrak) {
                        $validator->errors()->add('tgl_penempatan', 'Tanggal Penempatan tidak boleh kurang dari Mulai Kontrak');
                    }
                    if ($this->tgl_penempatan > $this->kontrak_selesai) {
                        $validator->errors()->add('tgl_penempatan', 'Tanggal Penempatan tidak boleh lebih dari Kontrak Selesai');
                    }
                }
            }

            if ($this->ada_cuti === 'Ada' && is_array($this->cuti) && in_array('Cuti Melahirkan', $this->cuti) && empty($this->gaji_saat_cuti)) {
                $validator->errors()->add('gaji_saat_cuti', 'Gaji saat cuti harus diisi ketika memilih Cuti Melahirkan.');
            }
        });
    }
}
