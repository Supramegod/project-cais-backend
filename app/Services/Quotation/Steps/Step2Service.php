<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use App\Models\Top;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class Step2Service
{
    public function execute(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            Log::info('Starting updateDetailKontrak (Step2Service)', [
                'quotation_id' => $quotation->id,
                'version' => $quotation->version,
                'request_data' => $request->all(),
            ]);

            if ($quotation->version === 2) {
                $this->validateStep2V2($request);
                $updateData = [
                    'hari_kerja' => $request->hari_kerja,
                    'hari_off' => $request->hari_off,
                    'jam_kerja' => $request->jam_kerja,
                    'shift_kerja' => $request->shift_kerja,
                    'jam_lembur' => $request->jam_lembur,
                    'cuti_izin' => $request->cuti_izin,
                    'status_rekrutmen' => $request->status_rekrutmen,
                    'pendaftaran_pkwt' => $request->pendaftaran_pkwt,
                    'jaminan' => $request->jaminan,
                    'penanggung_jawab_aset' => $request->penanggung_jawab_aset,
                    'detail_penanggung_jawab_aset' => $request->detail_penanggung_jawab_aset,
                    'calculated_at' => null,
                    'updated_by' => Auth::user()->full_name,
                ];
            } else {
                $this->validateStep2V1($request);

                $cutiData = $this->prepareCutiData($request);

                $top = Top::where('nama', $request->jumlah_hari_invoice)->first();
                $persenBungaBank = $top ? ($top->persentase ?? 0) : 0;

                $updateData = array_merge([
                    'mulai_kontrak' => $request->mulai_kontrak,
                    'kontrak_selesai' => $request->kontrak_selesai,
                    'tgl_penempatan' => $request->tgl_penempatan,
                    'salary_rule_id' => $request->salary_rule,
                    'pengiriman_invoice' => $request->pengiriman_invoice,
                    'top' => $request->top,
                    'jumlah_hari_invoice' => $request->jumlah_hari_invoice,
                    'tipe_hari_invoice' => $request->tipe_hari_invoice,
                    'evaluasi_kontrak' => $request->evaluasi_kontrak,
                    'durasi_kerjasama' => $request->durasi_kerjasama,
                    'durasi_karyawan' => $request->durasi_karyawan,
                    'evaluasi_karyawan' => $request->evaluasi_karyawan,
                    'hari_kerja' => $request->hari_kerja,
                    'shift_kerja' => $request->shift_kerja,
                    'jam_kerja' => $request->jam_kerja,
                    'persen_bunga_bank' => $persenBungaBank,
                    'calculated_at' => null,
                    'updated_by' => Auth::user()->full_name,
                ], $cutiData);
            }

            Log::debug('Final data to update quotation:', $updateData);

            $quotation->update($updateData);

            DB::commit();

            Log::info('Step 2 updated successfully', [
                'quotation_id' => $quotation->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateStep2', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function validateStep2V2(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'hari_kerja' => 'required|string',
            'hari_off' => 'required|string',
            'jam_kerja' => 'required|string',
            'shift_kerja' => 'required|string',
            'jam_lembur' => 'required|string',
            'cuti_izin' => 'required|string',
            'status_rekrutmen' => 'required|string',
            'pendaftaran_pkwt' => 'required|string',
            'jaminan' => 'required|string',
            'penanggung_jawab_aset' => 'required|string',
            'detail_penanggung_jawab_aset' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }
    }

    private function validateStep2V1(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'mulai_kontrak' => 'required|date',
            'kontrak_selesai' => 'required|date|after_or_equal:mulai_kontrak',
            'tgl_penempatan' => 'required|date',
            'top' => 'required|string',
            'salary_rule' => 'required|exists:m_salary_rule,id',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        if ($request->tgl_penempatan < $request->mulai_kontrak) {
            throw new \Exception('Tanggal Penempatan tidak boleh kurang dari Kontrak Awal');
        }

        if ($request->tgl_penempatan > $request->kontrak_selesai) {
            throw new \Exception('Tanggal Penempatan tidak boleh lebih dari Kontrak Selesai');
        }
    }

    private function prepareCutiData(Request $request): array
    {
        $data = [];
        if ($request->ada_cuti == 'Tidak Ada') {
            $data['cuti'] = 'Tidak Ada';
            $data['gaji_saat_cuti'] = null;
            $data['prorate'] = null;
            $data['hari_cuti_kematian'] = null;
            $data['hari_istri_melahirkan'] = null;
            $data['hari_cuti_menikah'] = null;
        } else {
            $cuti = $request->cuti;

            if (is_null($cuti)) {
                $cuti = [];
            } elseif (! is_array($cuti)) {
                $cuti = [$cuti];
            }

            $data['cuti'] = ! empty($cuti) ? implode(',', $cuti) : null;

            if (in_array('Cuti Melahirkan', $cuti)) {
                if ($request->gaji_saat_cuti != 'Prorate') {
                    $data['prorate'] = null;
                } else {
                    $data['prorate'] = $request->prorate;
                }
                $data['gaji_saat_cuti'] = $request->gaji_saat_cuti;
            } else {
                $data['gaji_saat_cuti'] = $request->gaji_saat_cuti;
                $data['prorate'] = $request->prorate;
            }
        }

        return $data;
    }
}
