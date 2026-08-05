<?php

namespace App\Http\Requests\Quotation;

use App\Http\Requests\BaseRequest;

use App\Models\Position;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationSite;
use App\Models\Umk;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class QuotationStepRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $step = $this->route('step');
        $id = $this->route('id') ?? $this->route('quotation') ?? $this->route('quotation_id') ?? $this->segment(3);
        $quotation = \App\Models\Quotation::find($id);

        $logicalStepName = \App\Services\Quotation\Steps\StepMapper::resolveUpdateMethod(
            $quotation ? $quotation->version : 1,
            (int)$step
        );

        $rules = [
            'edit' => FluentRule::boolean()->sometimes(),
        ];

        switch ($logicalStepName) {
            case 'updateDataSite':
                $rules['nama_perusahaan'] = FluentRule::string()->required();
                $rules['kota'] = FluentRule::string()->required();
                $rules['cabang'] = FluentRule::string()->required();
                $rules['jenis_perusahaan'] = FluentRule::string()->required();
                $rules['status_gedung'] = FluentRule::string()->required();
                $rules['alamat_lengkap'] = FluentRule::string()->required();
                $rules['hari_operasional'] = FluentRule::string()->required();
                break;

            case 'updateJenisKontrak':
                $rules['jenis_kontrak'] = FluentRule::string()->required()->in([
                    'Reguler',
                    'Event Gaji Harian',
                    'PKHL',
                    'Borongan',
                    'General Cleaning'
                ]);
                break;

            case 'updateDetailKontrak':
                $excludedRoles = [53, 54, 55, 56, 2];
                /** @var \App\Models\User|null $user */
                $user = \Illuminate\Support\Facades\Auth::user();
                $userRole = $user?->cais_role_id;

                // Aturan dasar selalu: date (jika ada input)
                $rules['mulai_kontrak'] = FluentRule::date()
                    ->when(!in_array($userRole, $excludedRoles), fn($r) => $r->required()->rule('after_or_equal:today'));
                $rules['kontrak_selesai'] = FluentRule::date()
                    ->when(!in_array($userRole, $excludedRoles), fn($r) => $r->required()->rule('after_or_equal:mulai_kontrak'));
                $rules['tgl_penempatan'] = FluentRule::date()
                    ->when(!in_array($userRole, $excludedRoles), fn($r) => $r->required());

                $rules['top'] = FluentRule::string()->required()->in(['Non TOP', 'Kurang Dari 7 Hari', 'Lebih Dari 7 Hari']);
                $rules['salary_rule'] = FluentRule::integer()->required()->exists('m_salary_rule', 'id');
                $rules['jumlah_hari_invoice'] = FluentRule::integer()->requiredIf('top', 'Lebih Dari 7 Hari')->min(1);
                $rules['tipe_hari_invoice'] = FluentRule::string()->requiredIf('top', 'Lebih Dari 7 Hari')->in(['Kerja', 'Kalender']);
                $rules['evaluasi_kontrak'] = FluentRule::string()->required();
                $rules['durasi_kerjasama'] = FluentRule::string()->required();
                $rules['durasi_karyawan'] = FluentRule::string()->required();
                $rules['evaluasi_karyawan'] = FluentRule::string()->required();
                $rules['ada_cuti'] = FluentRule::string()->required()->in(['Ada', 'Tidak Ada']);
                $rules['cuti'] = FluentRule::array()->requiredIf('ada_cuti', 'Ada')->children([
                    '*' => FluentRule::string()->sometimes()->in([
                        'Cuti Tahunan',
                        'Cuti Melahirkan',
                        'Cuti Kematian',
                        'Istri Melahirkan',
                        'Cuti Menikah',
                        'Cuti Roster',
                        'Tidak Ada'
                    ]),
                ]);
                $rules['gaji_saat_cuti'] = FluentRule::string()->sometimes()->in(['No Work No Pay', 'Prorate']);
                $rules['prorate'] = FluentRule::integer()->requiredIf('gaji_saat_cuti', 'Prorate')->min(0);
                $rules['shift_kerja'] = FluentRule::string()->sometimes();
                $rules['hari_kerja'] = FluentRule::string()->required();
                $rules['jam_kerja'] = FluentRule::string()->required();
                break;

            case 'updateHeadcount':
                $rules['headCountData'] = FluentRule::array()->required()->each([
                    'quotation_site_id' => FluentRule::integer()->required(),
                    'position_id' => FluentRule::integer()->required(),
                    'jumlah_hc' => FluentRule::integer()->required()->min(1),
                    'jabatan_kebutuhan' => FluentRule::string()->required(),
                    'nama_site' => FluentRule::string()->required(),
                ]);
                break;

            case 'updateCosting':
                $rules['is_ppn'] = FluentRule::integer()->required()->in([0, 1]);
                $rules['ppn_pph_dipotong'] = FluentRule::string()->required()->in(['Total Invoice', 'Management Fee']);
                $rules['management_fee_id'] = FluentRule::integer()->required()->exists('m_management_fee', 'id');
                $rules['persentase'] = FluentRule::numeric()->required()->min(0)->max(100);
                $rules['position_data'] = FluentRule::array()->required()->min(1)->each([
                    'quotation_detail_id' => FluentRule::integer()->required()->exists('sl_quotation_detail', 'id'),
                    'upah' => FluentRule::string()->required()->in(['UMP', 'UMK', 'Custom']),
                    'hitungan_upah' => FluentRule::string()->requiredIf('upah', 'Custom')->in(['Per Bulan', 'Per Hari', 'Per Jam']),
                    'nominal_upah' => FluentRule::numeric()->requiredIf('upah', 'Custom')->min(0),
                    'lembur' => FluentRule::string()->sometimes()->in(['Flat', 'Tidak Ada', 'Normatif']),
                    'nominal_lembur' => FluentRule::numeric()->requiredIf('lembur', 'Flat')->min(0),
                    'jenis_bayar_lembur' => FluentRule::string()->requiredIf('lembur', 'Flat')->in(['Per Bulan', 'Per Hari', 'Per Jam']),
                    'jam_per_bulan_lembur' => FluentRule::integer()->requiredIf('jenis_bayar_lembur', 'Per Jam')->min(0),
                    'lembur_ditagihkan' => FluentRule::string()->rule('required_if:lembur,Flat,Normatif')->in(['Ditagihkan', 'Ditagihkan Terpisah']),
                    'kompensasi' => FluentRule::string()->sometimes()->in(['Diprovisikan', 'Diberikan Langsung','Ditagihkan', 'Tidak Ada']),
                    'thr' => FluentRule::string()->sometimes()->in(['Diprovisikan', 'Ditagihkan', 'Diberikan Langsung', 'Tidak Ada']),
                    'tunjangan_holiday' => FluentRule::string()->sometimes()->in(['Flat', 'Tidak Ada', 'Normatif']),
                    'nominal_tunjangan_holiday' => FluentRule::numeric()->requiredIf('tunjangan_holiday', 'Flat')->min(0),
                    'jenis_bayar_tunjangan_holiday' => FluentRule::string()->requiredIf('tunjangan_holiday', 'Flat')->in(['Per Bulan', 'Per Hari', 'Per Jam']),
                ]);
                break;

            case 'updateBpjs':
                $rules['jenis-perusahaan'] = FluentRule::integer()->required()->exists('m_jenis_perusahaan', 'id');
                $rules['bidang-perusahaan'] = FluentRule::integer()->required()->exists('m_bidang_perusahaan', 'id');
                $rules['resiko'] = FluentRule::string()->required();
                $rules['program-bpjs'] = FluentRule::string()->required();
                $rules['penjamin'] = FluentRule::array()->sometimes()->children([
                    '*' => FluentRule::string()->sometimes(),
                ]);
                $rules['jkk'] = FluentRule::array()->sometimes()->children([
                    '*' => FluentRule::boolean()->sometimes(),
                ]);
                $rules['jkm'] = FluentRule::array()->sometimes()->children([
                    '*' => FluentRule::boolean()->sometimes(),
                ]);
                $rules['jht'] = FluentRule::array()->sometimes()->children([
                    '*' => FluentRule::boolean()->sometimes(),
                ]);
                $rules['jp'] = FluentRule::array()->sometimes()->children([
                    '*' => FluentRule::boolean()->sometimes(),
                ]);
                $rules['nominal_takaful'] = FluentRule::array()->sometimes()->children([
                    '*' => FluentRule::numeric()->sometimes()->min(0),
                ]);
                break;

            case 'updateAplikasiPendukung':
                $rules['aplikasi_pendukung'] = FluentRule::array()->sometimes()->children([
                    '*' => FluentRule::integer()->exists('m_aplikasi_pendukung', 'id'),
                ]);
                break;

            case 'updateChemical':
                $rules['barang_id'] = FluentRule::integer()->sometimes()->requiredWithout('chemicals')->exists('m_barang', 'id');
                $rules['jumlah'] = FluentRule::integer()->sometimes()->requiredWithout('chemicals')->min(0);
                $rules['masa_pakai'] = FluentRule::integer()->sometimes()->min(1);
                $rules['harga'] = FluentRule::numeric()->sometimes()->min(0);

                $rules['chemicals'] = FluentRule::array()->sometimes()->each([
                    'barang_id' => FluentRule::integer()->requiredWith('chemicals')->exists('m_barang', 'id'),
                    'jumlah' => FluentRule::integer()->requiredWith('chemicals')->min(0),
                    'masa_pakai' => FluentRule::integer()->sometimes()->min(1),
                    'harga' => FluentRule::numeric()->sometimes()->min(0),
                ]);
                break;

            case 'updateOperasional':
                $rules['jumlah_kunjungan_operasional'] = FluentRule::integer()->required()->min(0);
                $rules['bulan_tahun_kunjungan_operasional'] = FluentRule::string()->required()->in(['Bulan', 'Tahun']);
                $rules['jumlah_kunjungan_tim_crm'] = FluentRule::integer()->required()->min(0);
                $rules['bulan_tahun_kunjungan_tim_crm'] = FluentRule::string()->required()->in(['Bulan', 'Tahun']);
                $rules['keterangan_kunjungan_operasional'] = FluentRule::string()->sometimes();
                $rules['keterangan_kunjungan_tim_crm'] = FluentRule::string()->sometimes();
                $rules['ada_training'] = FluentRule::string()->sometimes()->in(['Ada', 'Tidak Ada']);
                $rules['training'] = FluentRule::string()->sometimes();
                $rules['persen_bunga_bank'] = FluentRule::numeric()->sometimes()->min(0);
                break;

            case 'updatePricing':
                $rules['penagihan'] = FluentRule::string()->required();
                $rules['tunjangan_data'] = FluentRule::array()->sometimes()->children([
                    '*' => FluentRule::array()->sometimes()->each([   // ← pakai each() karena array numerik
                        'nama_tunjangan' => FluentRule::string()->required()->max(255),
                        'nominal' => FluentRule::numeric()->required()->min(0),
                    ]),
                ]);
                break;

            case 'updateFinalization':
                break;
        }

        return $rules;
    }


    public function messages(): array
    {
        return [
            // Step 1 Messages
            'jenis_kontrak.required' => 'Jenis kontrak harus diisi',
            'jenis_kontrak.string' => 'Jenis kontrak harus berupa teks',
            'jenis_kontrak.in' => 'Jenis kontrak harus salah satu dari: Reguler, Event Gaji Harian, PKHL, Borongan',

            // Step 2 Messages
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
            'tipe_hari_invoice.string' => 'Tipe hari invoice harus berupa teks',
            'tipe_hari_invoice.in' => 'Tipe hari invoice harus salah satu dari: Kerja, Kalender',
            'ada_cuti.required' => 'Status cuti harus dipilih',
            'ada_cuti.in' => 'Status cuti harus Ada atau Tidak Ada',
            'cuti.required_if' => 'Jenis cuti harus dipilih ketika memilih ada cuti',
            'cuti.array' => 'Jenis cuti harus berupa array',
            'cuti.*.in' => 'Jenis cuti tidak valid',
            'gaji_saat_cuti.in' => 'Gaji saat cuti harus salah satu dari: No Work No Pay, Prorate',
            'prorate.required_if' => 'Prorate harus diisi ketika memilih gaji saat cuti Prorate',
            'prorate.integer' => 'Prorate harus berupa angka',
            'prorate.min' => 'Prorate tidak boleh kurang dari 0',
            'evaluasi_kontrak.string' => 'Evaluasi kontrak harus berupa teks',
            'durasi_kerjasama.string' => 'Durasi kerjasama harus berupa teks',
            'durasi_karyawan.string' => 'Durasi karyawan harus berupa teks',
            'evaluasi_karyawan.string' => 'Evaluasi karyawan harus berupa teks',
            'shift_kerja.string' => 'Shift kerja harus berupa teks',
            'hari_kerja.string' => 'Hari kerja harus berupa teks',
            'jam_kerja.string' => 'Jam kerja harus berupa teks',
            'hari_kerja.required' => 'Hari kerja harus diisi',
            'jam_kerja.required' => 'Jam kerja harus diisi',
            'evaluasi_kontrak.required' => 'Evaluasi kontrak harus diisi',
            'durasi_kerjasama.required' => 'Durasi kerjasama harus diisi',
            'durasi_karyawan.required' => 'Durasi karyawan harus diisi',
            'evaluasi_karyawan.required' => 'Evaluasi karyawan harus diisi',

            // Step 3 Messages
            'headCountData.required' => 'Data headcount harus diisi',
            'headCountData.array' => 'Data headcount harus berupa array',
            'headCountData.*.quotation_site_id.required' => 'Site ID harus diisi',
            'headCountData.*.quotation_site_id.integer' => 'Site ID harus berupa angka',
            'headCountData.*.position_id.required' => 'Position ID harus diisi',
            'headCountData.*.position_id.integer' => 'Position ID harus berupa angka',
            'headCountData.*.jumlah_hc.required' => 'Jumlah HC harus diisi',
            'headCountData.*.jumlah_hc.integer' => 'Jumlah HC harus berupa angka',
            'headCountData.*.jumlah_hc.min' => 'Jumlah HC minimal 1',
            'headCountData.*.jabatan_kebutuhan.required' => 'Jabatan kebutuhan harus diisi',
            'headCountData.*.jabatan_kebutuhan.string' => 'Jabatan kebutuhan harus berupa teks',
            'headCountData.*.nama_site.required' => 'Nama site harus diisi',
            'headCountData.*.nama_site.string' => 'Nama site harus berupa teks',

            // Step 4 Messages - GLOBAL DATA
            'is_ppn.in' => 'Status PPN harus 0 atau 1',
            'ppn_pph_dipotong.in' => 'PPN PPH dipotong harus salah satu dari: Total Invoice, Management Fee',
            'management_fee_id.exists' => 'Management fee tidak valid',
            'persentase.numeric' => 'Persentase harus berupa angka',
            'persentase.min' => 'Persentase tidak boleh kurang dari 0',
            'persentase.required' => 'Persentase harus diisi',
            'management_fee_id.required' => 'Management fee harus diisi',
            'is_ppn.required' => 'Status PPN harus diisi',
            'ppn_pph_dipotong.required' => 'PPN PPH dipotong harus diisi',
            'persentase.max' => 'Persentase tidak boleh lebih dari 100',

            // Step 4 Messages - POSITION DATA
            'position_data.required' => 'Data posisi harus diisi',
            'position_data.array' => 'Data posisi harus berupa array',
            'position_data.min' => 'Minimal satu data posisi harus dikirim',
            'position_data.*.quotation_detail_id.required' => 'Quotation detail ID harus diisi',
            'position_data.*.quotation_detail_id.exists' => 'Quotation detail ID tidak valid',
            'position_data.*.upah.required' => 'Jenis upah harus dipilih',
            'position_data.*.upah.in' => 'Jenis upah harus salah satu dari: UMP, UMK, Custom',
            'position_data.*.hitungan_upah.required_if' => 'Hitungan upah harus diisi ketika memilih upah custom',
            'position_data.*.hitungan_upah.in' => 'Hitungan upah harus salah satu dari: Per Bulan, Per Hari, Per Jam',
            'position_data.*.nominal_upah.required_if' => 'Nominal upah custom harus diisi ketika memilih upah custom',
            'position_data.*.nominal_upah.numeric' => 'Nominal upah harus berupa angka',
            'position_data.*.nominal_upah.min' => 'Nominal upah tidak boleh kurang dari 0',
            'position_data.*.lembur.in' => 'Lembur harus salah satu dari: Flat, Tidak Ada, Normatif',
            'position_data.*.nominal_lembur.required_if' => 'Nominal lembur harus diisi ketika memilih lembur Flat',
            'position_data.*.nominal_lembur.numeric' => 'Nominal lembur harus berupa angka',
            'position_data.*.nominal_lembur.min' => 'Nominal lembur tidak boleh kurang dari 0',
            'position_data.*.jenis_bayar_lembur.required_if' => 'Jenis bayar lembur harus diisi ketika memilih lembur Flat',
            'position_data.*.jenis_bayar_lembur.in' => 'Jenis bayar lembur harus salah satu dari: Per Bulan, Per Hari, Per Jam',
            'position_data.*.jam_per_bulan_lembur.required_if' => 'Jam per bulan lembur harus diisi ketika jenis bayar lembur Per Jam',
            'position_data.*.jam_per_bulan_lembur.integer' => 'Jam per bulan lembur harus berupa angka',
            'position_data.*.jam_per_bulan_lembur.min' => 'Jam per bulan lembur tidak boleh kurang dari 0',
            'position_data.*.lembur_ditagihkan.required_if' => 'Lembur ditagihkan harus diisi ketika memilih lembur Flat atau Normatif',
            'position_data.*.lembur_ditagihkan.in' => 'Lembur ditagihkan harus salah satu dari: Ditagihkan, Ditagihkan Terpisah',
            // 'position_data.*.kompensasi.required' => 'Kompensasi harus diisi',
            // 'position_data.*.kompensasi.in' => 'Kompensasi harus salah satu dari: Diprovisikan, Ditagihkan, Tidak Ada',
            // 'position_data.*.thr.required' => 'THR (tunjangan hari raya) harus diisi',
            // 'position_data.*.thr.in' => 'THR harus salah satu dari: Diprovisikan, Ditagihkan, Diberikan Langsung, Tidak Ada',
            'position_data.*.tunjangan_holiday.in' => 'Tunjangan holiday harus salah satu dari: Flat, Tidak Ada, Normatif',
            'position_data.*.nominal_tunjangan_holiday.required_if' => 'Nominal tunjangan holiday harus diisi ketika memilih tunjangan holiday Flat',
            'position_data.*.nominal_tunjangan_holiday.numeric' => 'Nominal tunjangan holiday harus berupa angka',
            'position_data.*.nominal_tunjangan_holiday.min' => 'Nominal tunjangan holiday tidak boleh kurang dari 0',
            'position_data.*.jenis_bayar_tunjangan_holiday.required_if' => 'Jenis bayar tunjangan holiday harus diisi ketika memilih tunjangan holiday Flat',
            'position_data.*.jenis_bayar_tunjangan_holiday.in' => 'Jenis bayar tunjangan holiday harus salah satu dari: Per Bulan, Per Hari, Per Jam',

            // Step 5 Messages
            'jenis-perusahaan.required' => 'Jenis perusahaan harus dipilih',
            'jenis-perusahaan.exists' => 'Jenis perusahaan tidak valid',
            'bidang-perusahaan.required' => 'Bidang perusahaan harus dipilih',
            'bidang-perusahaan.exists' => 'Bidang perusahaan tidak valid',
            'resiko.required' => 'Resiko harus diisi',
            'resiko.string' => 'Resiko harus berupa teks',
            'program-bpjs.required' => 'Program BPJS harus diisi',
            'program-bpjs.string' => 'Program BPJS harus berupa teks',
            'penjamin.array' => 'Penjamin harus berupa array',
            'penjamin.*.string' => 'Penjamin harus berupa teks',
            'jkk.array' => 'JKK harus berupa array',
            'jkk.*.boolean' => 'JKK harus berupa boolean (true/false)',
            'jkm.array' => 'JKM harus berupa array',
            'jkm.*.boolean' => 'JKM harus berupa boolean (true/false)',
            'jht.array' => 'JHT harus berupa array',
            'jht.*.boolean' => 'JHT harus berupa boolean (true/false)',
            'jp.array' => 'JP harus berupa array',
            'jp.*.boolean' => 'JP harus berupa boolean (true/false)',
            'nominal_takaful.array' => 'Nominal takaful harus berupa array',
            'nominal_takaful.*.numeric' => 'Nominal takaful harus berupa angka',
            'nominal_takaful.*.min' => 'Nominal takaful tidak boleh kurang dari 0',

            // Step 6 Messages
            'aplikasi_pendukung.array' => 'Aplikasi pendukung harus berupa array',
            'aplikasi_pendukung.*.exists' => 'Aplikasi pendukung tidak valid',

            // Step 9 Messages
            'barang_id.required_without' => 'Barang ID harus diisi',
            'barang_id.exists' => 'Barang tidak valid',
            'jumlah.required_without' => 'Jumlah harus diisi',
            'jumlah.integer' => 'Jumlah harus berupa angka',
            'jumlah.min' => 'Jumlah tidak boleh kurang dari 0',
            'masa_pakai.integer' => 'Masa pakai harus berupa angka',
            'masa_pakai.min' => 'Masa pakai minimal 1',
            'harga.numeric' => 'Harga harus berupa angka',
            'harga.min' => 'Harga tidak boleh kurang dari 0',
            'chemicals.array' => 'Chemicals harus berupa array',
            'chemicals.*.barang_id.required_with' => 'Barang ID harus diisi',
            'chemicals.*.barang_id.exists' => 'Barang tidak valid',
            'chemicals.*.jumlah.required_with' => 'Jumlah harus diisi',
            'chemicals.*.jumlah.integer' => 'Jumlah harus berupa angka',
            'chemicals.*.jumlah.min' => 'Jumlah tidak boleh kurang dari 0',
            'chemicals.*.masa_pakai.integer' => 'Masa pakai harus berupa angka',
            'chemicals.*.masa_pakai.min' => 'Masa pakai minimal 1',
            'chemicals.*.harga.numeric' => 'Harga harus berupa angka',
            'chemicals.*.harga.min' => 'Harga tidak boleh kurang dari 0',

            // Step 10 Messages
            'jumlah_kunjungan_operasional.required' => 'Jumlah kunjungan operasional harus diisi',
            'jumlah_kunjungan_operasional.integer' => 'Jumlah kunjungan operasional harus berupa angka',
            'jumlah_kunjungan_operasional.min' => 'Jumlah kunjungan operasional tidak boleh kurang dari 0',
            'bulan_tahun_kunjungan_operasional.required' => 'Periode kunjungan operasional harus dipilih',
            'bulan_tahun_kunjungan_operasional.string' => 'Periode kunjungan operasional harus berupa teks',
            'bulan_tahun_kunjungan_operasional.in' => 'Periode kunjungan operasional harus Bulan atau Tahun',
            'jumlah_kunjungan_tim_crm.required' => 'Jumlah kunjungan tim CRM harus diisi',
            'jumlah_kunjungan_tim_crm.integer' => 'Jumlah kunjungan tim CRM harus berupa angka',
            'jumlah_kunjungan_tim_crm.min' => 'Jumlah kunjungan tim CRM tidak boleh kurang dari 0',
            'bulan_tahun_kunjungan_tim_crm.required' => 'Periode kunjungan tim CRM harus dipilih',
            'bulan_tahun_kunjungan_tim_crm.string' => 'Periode kunjungan tim CRM harus berupa teks',
            'bulan_tahun_kunjungan_tim_crm.in' => 'Periode kunjungan tim CRM harus Bulan atau Tahun',
            'keterangan_kunjungan_operasional.string' => 'Keterangan kunjungan operasional harus berupa teks',
            'keterangan_kunjungan_tim_crm.string' => 'Keterangan kunjungan tim CRM harus berupa teks',
            'ada_training.string' => 'Ada training harus berupa teks',
            'ada_training.in' => 'Ada training harus salah satu dari: Ada, Tidak Ada',
            'training.string' => 'Training harus berupa teks',
            'persen_bunga_bank.numeric' => 'Persen bunga bank harus berupa angka',
            'persen_bunga_bank.min' => 'Persen bunga bank tidak boleh kurang dari 0',

            // Step 11 Messages
            'penagihan.required' => 'Metode penagihan harus diisi',
            'penagihan.string' => 'Metode penagihan harus berupa teks',
            'tunjangan_data.array' => 'Data tunjangan harus berupa array',
            'tunjangan_data.*.array' => 'Data tunjangan per detail harus berupa array',
            'tunjangan_data.*.*.nama_tunjangan.required' => 'Nama tunjangan harus diisi',
            'tunjangan_data.*.*.nama_tunjangan.string' => 'Nama tunjangan harus berupa teks',
            'tunjangan_data.*.*.nama_tunjangan.max' => 'Nama tunjangan maksimal 255 karakter',
            'tunjangan_data.*.*.nominal.required' => 'Nominal tunjangan harus diisi',
            'tunjangan_data.*.*.nominal.numeric' => 'Nominal tunjangan harus berupa angka',
            'tunjangan_data.*.*.nominal.min' => 'Nominal tunjangan tidak boleh kurang dari 0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $step = $this->route('step');
            $id = $this->route('id') ?? $this->route('quotation') ?? $this->route('quotation_id') ?? $this->segment(3);
            $quotation = \App\Models\Quotation::find($id);
            $logicalStepName = \App\Services\Quotation\Steps\StepMapper::resolveUpdateMethod(
                $quotation ? $quotation->version : 1,
                (int)$step
            );

            // Validasi custom untuk updateDetailKontrak
            if ($logicalStepName === 'updateDetailKontrak') {
                // Daftar role_id yang boleh melewati validasi (CRM)
                $excludedRoles = [53, 54, 55, 56, 2];

                // Ambil role dari user yang sedang login
                /** @var \App\Models\User|null $user */
                $user = \Illuminate\Support\Facades\Auth::user();
                $userRole = $user->cais_role_id ?? null;

                // Hanya jalankan validasi jika role_id TIDAK ada di dalam list pengecualian
                if (!in_array($userRole, $excludedRoles)) {
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

                // Validasi khusus: gaji_saat_cuti hanya wajib jika ada Cuti Melahirkan
                if (
                    $this->ada_cuti === 'Ada' &&
                    in_array('Cuti Melahirkan', $this->cuti) &&
                    empty($this->gaji_saat_cuti)
                ) {
                    $validator->errors()->add('gaji_saat_cuti', 'Gaji saat cuti harus diisi ketika memilih Cuti Melahirkan.');
                }
            }

            // Validasi custom untuk updateHeadcount
            if ($logicalStepName === 'updateHeadcount') {
                \Log::info('=== STEP 3 VALIDATION START ===', [
                    'all_route_parameters' => $this->route()->parameters(),
                    'step' => $step,
                    'has_headCountData' => $this->has('headCountData'),
                    'headCountData_count' => is_array($this->headCountData) ? count($this->headCountData) : 0
                ]);

                // CARA 1: Coba ambil quotation_id dari berbagai kemungkinan nama parameter
                $quotationId = null;

                // Cek semua parameter route yang tersedia
                $routeParameters = $this->route()->parameters();
                \Log::info('Available route parameters:', array_keys($routeParameters));

                // Coba dari parameter yang umum digunakan
                if (isset($routeParameters['quotation'])) {
                    $quotationId = $routeParameters['quotation'];
                } elseif (isset($routeParameters['id'])) {
                    $quotationId = $routeParameters['id'];
                } elseif (isset($routeParameters['quotation_id'])) {
                    $quotationId = $routeParameters['quotation_id'];
                }

                // Jika masih null, coba ambil dari URL segment
                if (!$quotationId) {
                    // Pattern: /api/quotation/{id}/step/{step}
                    $path = $this->path();
                    \Log::info('Request path:', ['path' => $path]);

                    // Ekstrak ID dari path
                    if (preg_match('/quotation\/(\d+)\/step/', $path, $matches)) {
                        $quotationId = $matches[1];
                        \Log::info('Extracted quotation ID from path:', ['id' => $quotationId]);
                    }
                }

                if (!$quotationId) {
                    \Log::error('Cannot determine quotation ID for validation');
                    // Skip validasi custom jika tidak bisa dapat quotation ID
                    return;
                }

                \Log::info('Quotation ID for validation:', ['id' => $quotationId]);

                $quotation = Quotation::with('quotationSites')->find($quotationId);

                if (!$quotation) {
                    \Log::warning('Quotation not found', ['quotation_id' => $quotationId]);
                    $validator->errors()->add('headCountData', 'Quotation tidak ditemukan.');
                    return;
                }

                \Log::info('Quotation details', [
                    'quotation_id' => $quotation->id,
                    'site_count' => $quotation->quotationSites->count(),
                    'site_ids' => $quotation->quotationSites->pluck('id')->toArray(),
                    'site_names' => $quotation->quotationSites->pluck('nama_site')->toArray()
                ]);

                // Validasi 1: Setiap site di quotation harus ada di headCountData
                $siteIdsInQuotation = $quotation->quotationSites->pluck('id')->toArray();
                $siteIdsInRequest = [];

                if (is_array($this->headCountData)) {
                    $siteIdsInRequest = collect($this->headCountData)
                        ->pluck('quotation_site_id')
                        ->unique()
                        ->toArray();
                }

                \Log::info('Site comparison', [
                    'siteIdsInQuotation' => $siteIdsInQuotation,
                    'siteIdsInRequest' => $siteIdsInRequest
                ]);

                $missingSites = array_diff($siteIdsInQuotation, $siteIdsInRequest);

                \Log::info('Missing sites calculation', [
                    'missingSites' => $missingSites,
                    'count' => count($missingSites)
                ]);

                if (!empty($missingSites)) {
                    $missingSiteNames = $quotation->quotationSites
                        ->whereIn('id', $missingSites)
                        ->pluck('nama_site')
                        ->toArray();

                    \Log::info('Missing site names', ['missingSiteNames' => $missingSiteNames]);

                    $validator->errors()->add(
                        'headCountData.missing_sites',
                        'Setiap site harus memiliki minimal satu headcount. Site berikut belum memiliki headcount: ' .
                        implode(', ', $missingSiteNames)
                    );
                } else {
                    \Log::info('No missing sites detected');
                }

                // Validasi 2: Setiap site_id di request harus ada di quotation
                $invalidSites = array_diff($siteIdsInRequest, $siteIdsInQuotation);
                if (!empty($invalidSites)) {
                    $validator->errors()->add(
                        'headCountData.invalid_sites',
                        'Site dengan ID berikut tidak valid untuk quotation ini: ' .
                        implode(', ', $invalidSites)
                    );
                }

                // Validasi 3: Setiap position_id harus sesuai dengan layanan (kebutuhan_id) dari quotation
                if (is_array($this->headCountData) && count($this->headCountData) > 0) {
                    $validPositionIds = Position::where('layanan_id', $quotation->kebutuhan_id)
                        ->pluck('id')
                        ->toArray();

                    \Log::info('Position validation', [
                        'kebutuhan_id' => $quotation->kebutuhan_id,
                        'validPositionIds' => $validPositionIds
                    ]);

                    $invalidPositions = [];

                    foreach ($this->headCountData as $index => $data) {
                        if (!in_array($data['position_id'], $validPositionIds)) {
                            $invalidPositions[] = $data['position_id'];
                        }
                    }

                    if (!empty($invalidPositions)) {
                        $validator->errors()->add(
                            'headCountData.invalid_positions',
                            'Position ID: ' . implode(', ', array_unique($invalidPositions)) .
                            ' tidak valid untuk layanan ini.'
                        );
                    }
                }

                \Log::info('=== STEP 3 VALIDATION END ===');
            }

            // Validasi custom untuk step 4
            // if ($logicalStepName === 'updateCosting') {
            //     $hasGlobalData = $this->hasAny(['is_ppn', 'ppn_pph_dipotong', 'management_fee_id', 'persentase']);
            //     $hasPositionData = $this->has('position_data') && !empty($this->position_data);

            //     if (!$hasGlobalData && !$hasPositionData) {
            //         $validator->errors()->add('base', 'Minimal satu data (global data atau position data) harus dikirim untuk step 4.');
            //     }

            //     // ✅ VALIDASI BARU: Nominal upah custom minimal 85% dari UMK
            //     if ($hasPositionData) {
            //         foreach ($this->position_data as $index => $positionData) {
            //             // Skip jika bukan custom upah
            //             if (($positionData['upah'] ?? null) !== 'Custom') {
            //                 continue;
            //             }

            //             $nominalUpah = $positionData['nominal_upah'] ?? 0;

            //             // Convert string to numeric if needed
            //             if (is_string($nominalUpah)) {
            //                 $nominalUpah = (int) str_replace('.', '', $nominalUpah);
            //             }

            //             // Get detail untuk ambil site_id
            //             $detailId = $positionData['quotation_detail_id'] ?? null;
            //             if (!$detailId) {
            //                 continue;
            //             }

            //             $detail = QuotationDetail::find($detailId);
            //             if (!$detail || !$detail->quotation_site_id) {
            //                 continue;
            //             }

            //             // Get UMK dari site
            //             $site = QuotationSite::find($detail->quotation_site_id);
            //             if (!$site || !$site->kota_id) {
            //                 continue;
            //             }

            //             $umk = Umk::byCity($site->kota_id)
            //                 ->active()
            //                 ->first();

            //             if (!$umk) {
            //                 continue;
            //             }

            //             // Hitung minimal 85% dari UMK
            //             $minimalUpah = $umk->umk * 0.85;

            //             // Validasi
            //             if ($nominalUpah < $minimalUpah) {
            //                 $validator->errors()->add(
            //                     "position_data.{$index}.nominal_upah",
            //                     sprintf(
            //                         'Nominal upah custom minimal 85%% dari UMK (Rp %s). Minimal: Rp %s',
            //                         number_format((float) $umk->umk, 0, ',', '.'),
            //                         number_format($minimalUpah, 0, ',', '.')
            //                     )
            //                 );

            //                 \Log::warning("Nominal upah custom kurang dari 85% UMK", [
            //                     'detail_id'          => $detailId,
            //                     'nominal_upah'       => $nominalUpah,
            //                     'umk'                => $umk->umk,
            //                     'minimal_85_persen'  => $minimalUpah,
            //                     'site_id'            => $site->id,
            //                     'city_id'            => $site->kota_id
            //                 ]);
            //             }
            //         }
            //     }
            // }
        });
    }

    /**
     * Prepare the data for validation.
     * Untuk handle field dengan nama yang berbeda di frontend vs backend
     */
    protected function prepareForValidation()
    {
        $step = $this->route('step');

        // Untuk step 4, handle field management_fee_id yang mungkin dikirim sebagai manajemen_fee
        $id = $this->route('id') ?? $this->route('quotation') ?? $this->route('quotation_id') ?? $this->segment(3);
        $quotation = \App\Models\Quotation::find($id);
        $logicalStepName = \App\Services\Quotation\Steps\StepMapper::resolveUpdateMethod(
            $quotation ? $quotation->version : 1,
            (int)$step
        );

        if ($logicalStepName === 'updateCosting' && $this->has('manajemen_fee')) {
            $this->merge([
                'management_fee_id' => $this->manajemen_fee
            ]);
        }

        // Untuk step 5, handle field dengan dash
        if ($logicalStepName === 'updateBpjs') {
            $this->merge([
                'jenis_perusahaan_id' => $this->input('jenis-perusahaan'),
                'bidang_perusahaan_id' => $this->input('bidang-perusahaan')
            ]);
        }

        // Convert empty strings to null untuk field optional
        $this->merge([
            'is_ppn' => $this->is_ppn ?? null,
            'ppn_pph_dipotong' => $this->ppn_pph_dipotong ?? null,
            'management_fee_id' => $this->management_fee_id ?? null,
            'persentase' => $this->persentase ?? null,
        ]);
    }
}
