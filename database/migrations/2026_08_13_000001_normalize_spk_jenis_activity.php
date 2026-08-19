<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyamakan casing jenis_activity SPK pada sl_activity_sales.
 *
 * Tiga penulis SPK (SpkController, SpkCommandService, SpkService) menulis 'spk'
 * huruf kecil, sementara laporan mencocokkan 'SPK' — persis seperti Quotation
 * dan PKS yang sudah benar. Akibatnya kolom `aksi` pada detail aktivitas sales
 * selalu null untuk baris SPK, jadi tautan ke dokumennya hilang.
 *
 * Sisi tulis sudah diperbaiki di rilis yang sama; migrasi ini merapikan 43 baris
 * lama yang bercasing 'spk' (2026-02-18 s/d 2026-07-15). 221 baris SPK lainnya
 * sudah bercasing benar sejak awal — masalahnya di sana spk_id yang kosong,
 * ditangani BackfillActivitySpkIdSeeder.
 *
 * Perbandingan sengaja pakai BINARY: collation MySQL default tidak case
 * sensitive, tanpa itu baris yang sudah 'SPK' ikut tersentuh percuma.
 * updated_at ditulis ulang ke nilainya sendiri supaya kolom
 * `on update CURRENT_TIMESTAMP` tidak bergeser — ini koreksi teknis, bukan
 * perubahan data oleh pengguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sl_activity_sales')) {
            return;
        }

        $isMysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

        DB::table('sl_activity_sales')
            ->when(
                $isMysql,
                fn ($query) => $query->whereRaw("BINARY jenis_activity = 'spk'"),
                fn ($query) => $query->where('jenis_activity', 'spk'),
            )
            ->update([
                'jenis_activity' => 'SPK',
                'updated_at' => DB::raw('updated_at'),
            ]);
    }

    /**
     * Sengaja no-op: mengembalikan casing yang rusak bukan rollback yang berguna.
     * Jalur baca sudah memakai strtoupper(), jadi kedua casing tetap terbaca.
     */
    public function down(): void {}
};
