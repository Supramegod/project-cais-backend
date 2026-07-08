<?php

namespace App\Services\PksTemplate\Concerns;

use Illuminate\Support\Carbon;

/**
 * Helper pengisian data dinamis untuk template PKS.
 *
 * Dipakai bersama oleh semua template company (SIG/GSU/RCI/ION) dan fallback,
 * agar placeholder "titik-titik" pada dokumen perjanjian terisi dari data yang
 * sudah tersedia (leads, kebutuhan, salary rule, rule THR, tanggal kontrak PKS,
 * dan persentase management fee). Semua getter aman terhadap nilai null.
 *
 * Class pemakai diasumsikan punya properti: $leads, $kebutuhan, $salaryRule,
 * $ruleThr, $pks (nullable), $persentase (nullable).
 */
trait FillsPksTemplateData
{
    /** Placeholder netral saat data belum tersedia (bukan "titik-titik"). */
    private function tplBlank(string $value = ''): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : '-';
    }

    // ── PIHAK PERTAMA (leads) ─────────────────────────────────────────────
    private function tplAlamatPihakPertama(): string
    {
        return $this->tplBlank($this->leads->alamat ?? '');
    }

    private function tplKotaPihakPertama(): string
    {
        return $this->tplBlank($this->leads->kota ?? '');
    }

    private function tplProvinsiPihakPertama(): string
    {
        return $this->tplBlank($this->leads->provinsi ?? '');
    }

    private function tplPicPihakPertama(): string
    {
        return strtoupper($this->tplBlank($this->leads->pic ?? ''));
    }

    // ── Ruang lingkup / jenis pekerjaan ───────────────────────────────────
    private function tplJenisPekerjaan(): string
    {
        return $this->tplBlank($this->kebutuhan->nama ?? '');
    }

    // ── Jadwal penagihan & penggajian (salary rule) ───────────────────────
    private function tplCutoff(): string
    {
        return $this->tplBlank($this->salaryRule->cutoff ?? '');
    }

    private function tplCrosscheckAbsen(): string
    {
        return $this->tplBlank($this->salaryRule->crosscheck_absen ?? '');
    }

    private function tplPengirimanInvoice(): string
    {
        return $this->tplBlank($this->salaryRule->pengiriman_invoice ?? '');
    }

    private function tplPembayaranInvoice(): string
    {
        return $this->tplBlank($this->salaryRule->pembayaran_invoice ?? '');
    }

    private function tplRilisPayroll(): string
    {
        return $this->tplBlank($this->salaryRule->rilis_payroll ?? '');
    }

    // ── Jadwal THR (rule THR) ─────────────────────────────────────────────
    private function tplThrPenagihan(): string
    {
        return $this->tplBlank($this->ruleThr->hari_penagihan_invoice ?? '');
    }

    private function tplThrPembayaran(): string
    {
        return $this->tplBlank($this->ruleThr->hari_pembayaran_invoice ?? '');
    }

    private function tplThrRilis(): string
    {
        return $this->tplBlank($this->ruleThr->hari_rilis_thr ?? '');
    }

    // ── Jangka waktu perjanjian (tanggal kontrak PKS) ─────────────────────
    private function tplKontrakAwal(): string
    {
        return $this->formatTanggalIndo($this->rawPksDate('kontrak_awal'));
    }

    private function tplKontrakAkhir(): string
    {
        return $this->formatTanggalIndo($this->rawPksDate('kontrak_akhir'));
    }

    private function rawPksDate(string $column): ?string
    {
        if (! isset($this->pks) || $this->pks === null) {
            return null;
        }

        // Pks model memformat tanggal via accessor; ambil nilai MENTAH.
        // getRawOriginal untuk model tersimpan; fallback ke getAttributes
        // (raw, bypass accessor) agar tetap benar untuk model belum tersimpan.
        $raw = method_exists($this->pks, 'getRawOriginal')
            ? $this->pks->getRawOriginal($column)
            : null;

        if (empty($raw)) {
            $raw = $this->pks->getAttributes()[$column] ?? null;
        }

        return $raw;
    }

    private function formatTanggalIndo(?string $date): string
    {
        if (empty($date)) {
            return '-';
        }

        try {
            return Carbon::parse($date)->locale('id')->isoFormat('D MMMM Y');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    // ── Management fee (persentase quotation) ─────────────────────────────
    private function tplManagementFeePersen(): string
    {
        if (! isset($this->persentase) || $this->persentase === null || $this->persentase === '') {
            return '-';
        }

        // Buang trailing nol (10.00 -> 10, 12.50 -> 12,5).
        $num = (float) $this->persentase;
        $formatted = rtrim(rtrim(number_format($num, 2, ',', '.'), '0'), ',');

        return $formatted . '%';
    }
}
