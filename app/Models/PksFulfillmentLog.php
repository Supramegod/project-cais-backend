<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Log aktivitas modul fulfillment, dikelompokkan per PKS (append-only, immutable).
 *
 * Satu tabel untuk seluruh modul fulfillment (item, visit, dst) — dibedakan lewat
 * kolom `jenis`. Tidak global lintas aplikasi.
 *  - pks_id       : PKS pemilik log (kunci pengelompokan)
 *  - jenis        : sub-aktivitas fulfillment, mis. PksFulfillmentLog::JENIS_ITEM
 *  - reference_id : id record di tabel sub-modul (mis. sl_pks_item_fulfillment.id)
 *  - batch_id     : UUID satu kelompok pengiriman (endpoint bulk). NULL untuk
 *                   pengiriman satuan.
 *  - batch_ke     : nomor urut batch dalam satu PKS (1, 2, 3, ...) — versi
 *                   terbaca manusia dari batch_id. Dihitung per jenis + aksi,
 *                   jadi "kirim batch ke-3" dan "terima batch ke-3" berbeda.
 *  - meta         : payload spesifik sub-modul (JSON), mis. item simpan
 *                   {qty_sesi_ini, remaining_sebelum, remaining_sesudah}
 *
 * @property int $id
 * @property int $pks_id
 * @property int|null $site_id
 * @property string $jenis
 * @property int $reference_id
 * @property string|null $batch_id
 * @property int|null $batch_ke
 * @property string $aksi
 * @property string|null $catatan
 * @property array|null $meta
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Pks $pks
 */
class PksFulfillmentLog extends Model
{
    // Append-only, immutable — no SoftDeletes, no updated_at
    const UPDATED_AT = null;

    // Jenis sub-aktivitas fulfillment. Tambah konstanta saat sub-modul lain ikut log.
    const JENIS_ITEM = 'item';

    const JENIS_VISIT = 'visit';

    // Aksi log jenis item. Deret batch_ke dihitung terpisah per aksi.
    /** Barang dikirim, menunggu penerimaan. */
    const AKSI_REQUEST = 'request';

    /** Penerimaan barang di site dicatat. */
    const AKSI_RECEIVE = 'receive';

    /** Koreksi jumlah yang diterima. */
    const AKSI_EDIT = 'edit';

    /** Aksi log lama sebelum alur dua tahap — dibaca sebagai request. */
    const AKSI_CREATE_LEGACY = 'create';

    protected $table = 'sl_pks_fulfillment_log';

    protected $fillable = [
        'pks_id',
        'site_id',
        'jenis',
        'reference_id',
        'batch_id',
        'batch_ke',
        'aksi',
        'catatan',
        'meta',
        'created_by',
        'created_by_user_id',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function pks(): BelongsTo
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    /**
     * Semua log satu PKS (lintas sub-modul fulfillment).
     */
    public function scopeForPks($query, int $pksId)
    {
        return $query->where('pks_id', $pksId);
    }

    /**
     * Log satu site dalam PKS.
     */
    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Satu kelompok pengiriman bulk.
     */
    public function scopeForBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Batch ke-n satu jenis dalam satu PKS.
     */
    public function scopeForBatchKe($query, string $jenis, int $batchKe)
    {
        return $query->where('jenis', $jenis)->where('batch_ke', $batchKe);
    }

    // ─── Penomoran batch ──────────────────────────────────────────

    /**
     * Penanda batch baru: UUID untuk mesin, nomor urut untuk manusia.
     *
     * WAJIB dipanggil di dalam transaksi — nomor diambil dengan lockForUpdate,
     * dan kuncinya baru lepas saat transaksi commit.
     *
     * @return array{batch_id: string, batch_ke: int}
     */
    public static function newBatch(int $pksId, string $jenis, ?string $aksi = null): array
    {
        return [
            'batch_id' => (string) Str::uuid(),
            'batch_ke' => self::nextBatchKe($pksId, $jenis, $aksi),
        ];
    }

    /**
     * Nomor batch berikutnya, dihitung per PKS per jenis — item punya urutan
     * sendiri, visit punya urutan sendiri.
     *
     * $aksi mempersempit deret sekali lagi: pengiriman barang (request) dan
     * penerimaannya (receive) masing-masing mulai dari 1, supaya "kirim batch
     * ke-3" tidak berlubang gara-gara nomornya dipakai batch penerimaan.
     */
    public static function nextBatchKe(int $pksId, string $jenis, ?string $aksi = null): int
    {
        $last = self::query()
            ->where('pks_id', $pksId)
            ->where('jenis', $jenis)
            ->when($aksi !== null, fn ($q) => $q->where('aksi', $aksi))
            ->whereNotNull('batch_ke')
            ->lockForUpdate()
            ->max('batch_ke');

        return (int) $last + 1;
    }

    /**
     * Log milik satu record sub-modul.
     */
    public function scopeForRef($query, string $jenis, int $referenceId)
    {
        return $query->where('jenis', $jenis)->where('reference_id', $referenceId);
    }
}
