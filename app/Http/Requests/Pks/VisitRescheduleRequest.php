<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;
use App\Models\PksVisitSchedule;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\FluentRule;

class VisitRescheduleRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tgl_jadwal' => FluentRule::date()->required(),
            'alasan'     => FluentRule::string()->required()->min(10),
        ];
    }

    public function messages(): array
    {
        return [
            'tgl_jadwal.required' => 'Tanggal jadwal baru wajib diisi.',
            'alasan.required'     => 'Alasan reschedule wajib diisi.',
            'alasan.min'         => 'Alasan minimal 10 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tgl_jadwal' => 'Tanggal Jadwal',
            'alasan'     => 'Alasan',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $schedule = $this->route('schedule');

            if (!$schedule instanceof PksVisitSchedule) {
                return;
            }

            $pks = $schedule->pks;

            if (!$pks) {
                return;
            }

            $tglJadwalBaru = $this->tgl_jadwal;
            $kontrakAwal = $pks->kontrak_awal instanceof \Carbon\Carbon
                ? $pks->kontrak_awal
                : \Carbon\Carbon::parse($pks->kontrak_awal);
            $kontrakAkhir = $pks->kontrak_akhir instanceof \Carbon\Carbon
                ? $pks->kontrak_akhir
                : \Carbon\Carbon::parse($pks->kontrak_akhir);

            // Validasi tgl_jadwal baru dalam rentang kontrak
            if ($kontrakAwal && $tglJadwalBaru < $kontrakAwal->toDateString()) {
                $validator->errors()->add(
                    'tgl_jadwal',
                    "Tanggal jadwal tidak boleh sebelum kontrak awal ({$kontrakAwal->toDateString()})."
                );
            }

            if ($kontrakAkhir && $tglJadwalBaru > $kontrakAkhir->toDateString()) {
                $validator->errors()->add(
                    'tgl_jadwal',
                    "Tanggal jadwal tidak boleh setelah kontrak akhir ({$kontrakAkhir->toDateString()})."
                );
            }

            // Pastikan tgl_jadwal baru tidak sama dengan yang saat ini
            $tglJadwalSekarang = $schedule->tgl_jadwal instanceof \Carbon\Carbon
                ? $schedule->tgl_jadwal->toDateString()
                : (string) $schedule->tgl_jadwal;

            if ($schedule->tgl_jadwal && $tglJadwalBaru === $tglJadwalSekarang) {
                $validator->errors()->add(
                    'tgl_jadwal',
                    'Tanggal jadwal baru tidak boleh sama dengan jadwal saat ini.'
                );
            }

            // Cek tidak bentrok dengan jadwal lain (customer + role + tanggal sama, exclude self)
            $conflict = PksVisitSchedule::where('leads_id', $schedule->leads_id)
                ->where('tgl_jadwal', $tglJadwalBaru)
                ->where('role', $schedule->role)
                ->where('id', '!=', $schedule->id)
                ->exists();

            if ($conflict) {
                $validator->errors()->add(
                    'tgl_jadwal',
                    'Sudah ada jadwal visit untuk customer, tanggal, dan role yang sama.'
                );
            }
        });
    }
}
