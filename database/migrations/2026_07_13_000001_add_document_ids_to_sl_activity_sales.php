<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regex untuk mengambil nomor dokumen dari teks notulen lama, dipakai
     * untuk backfill quotation_id/spk_id/pks_id baris sl_activity_sales
     * yang sudah terlanjur ada sebelum kolom ini ditambahkan.
     */
    protected array $notulenPatterns = [
        'quotation_id' => ['table' => 'sl_quotation', 'jenis' => ['Quotation'], 'regex' => '/Quotation\s+baru\s+(\S+)\s+dibuat/i'],
        'spk_id'       => ['table' => 'sl_spk', 'jenis' => ['spk', 'SPK'], 'regex' => '/SPK\s+baru\s+(\S+)\s+dibuat/i'],
        'pks_id'       => ['table' => 'sl_pks', 'jenis' => ['PKS'], 'regex' => '/pks\s*(?:baru\s*)?(\S+)\s*(?:dibuat|finalized)/i'],
    ];

    public function up(): void
    {
        Schema::table('sl_activity_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sl_activity_sales', 'quotation_id')) {
                $table->unsignedBigInteger('quotation_id')->nullable()->after('leads_kebutuhan_id')->index();
            }
            if (!Schema::hasColumn('sl_activity_sales', 'spk_id')) {
                $table->unsignedBigInteger('spk_id')->nullable()->after('quotation_id')->index();
            }
            if (!Schema::hasColumn('sl_activity_sales', 'pks_id')) {
                $table->unsignedBigInteger('pks_id')->nullable()->after('spk_id')->index();
            }
        });

        $this->backfillFromNotulen();
    }

    public function down(): void
    {
        Schema::table('sl_activity_sales', function (Blueprint $table) {
            foreach (['quotation_id', 'spk_id', 'pks_id'] as $column) {
                if (Schema::hasColumn('sl_activity_sales', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Best-effort backfill: parse nomor dokumen dari notulen baris lama,
     * lalu cari id-nya di tabel dokumen terkait berdasarkan nomor.
     * Baris yang nomornya tidak ketemu (mis. dokumennya sudah dihapus)
     * dibiarkan null.
     */
    protected function backfillFromNotulen(): void
    {
        foreach ($this->notulenPatterns as $column => $config) {
            DB::table('sl_activity_sales')
                ->whereIn('jenis_activity', $config['jenis'])
                ->whereNull($column)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($column, $config) {
                    $nomorToId = null;

                    foreach ($rows as $row) {
                        if (!preg_match($config['regex'], (string) $row->notulen, $matches)) {
                            continue;
                        }

                        $nomor = $matches[1];

                        if ($nomorToId === null || !isset($nomorToId)) {
                            $nomorToId = [];
                        }

                        if (!array_key_exists($nomor, $nomorToId)) {
                            $nomorToId[$nomor] = DB::table($config['table'])
                                ->where('nomor', $nomor)
                                ->value('id');
                        }

                        if ($nomorToId[$nomor]) {
                            DB::table('sl_activity_sales')
                                ->where('id', $row->id)
                                ->update([$column => $nomorToId[$nomor]]);
                        }
                    }
                });
        }
    }
};
