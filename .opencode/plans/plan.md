# Plan: `created_by_user_id` Migration

## Latar Belakang

Saat ini `created_by` di ~48 tabel menyimpan **nama string** (`Auth::user()->full_name`). Ketika nama user berubah di `mysqlhris.m_user`, seluruh histori aktivitas user tersebut **tidak terlihat** di report karena filter pakai nama tidak match.

**Pemicu:** User ID 17019 (ELVIN SETIA PARSANGAPAN SIMANJUNTAK) — nama diubah 2026-06-23 dari `"ELVIN SETIA PARSANGAPAN S"` jadi `"ELVIN SETIA PARSANGAPAN SIMANJUNTAK"`. 508 record aktivitas historis jadi tidak terbaca.

## Solusi

Tambah kolom **`created_by_user_id`** (unsigned bigint, nullable) di semua tabel yang `created_by`-nya nyimpen nama string. Kolom `created_by` tetap dipertahankan untuk display/backward compatibility.

---

## Phase 0: Discovery & Audit (SELESAI)

| Item | Status |
|---|---|
| Identifikasi semua tabel dgn `created_by` varchar | ✅ |
| Identifikasi pattern data (nama vs ID) | ✅ |
| Identifikasi ~150 lokasi insert `created_by` | ✅ |
| Identifikasi 2 Eloquent relationship putus | ✅ |
| Identifikasi semua filter/groupBy di ReportController | ✅ |

---

## Phase 1: Migration Database

### 1A. Tabel Target (51 tabel)

Prioritas berdasarkan dampak bisnis:

#### P0 — Critical (dampak report, relationship putus)

| # | Tabel | Database | Catatan |
|---|-------|----------|---------|
| 1 | **sl_activity_sales** | shelter3_cais | Report monthly/weekly utama |
| 2 | **sl_customer_activity** | shelter3_cais | Report monthly/weekly role30. **SUDAH punya `user_id`** |
| 3 | **log_approval** | shelter3_cais | Relationship `belongsTo(User, 'created_by', 'full_name')` PUTUS |
| 4 | **log_notification** | shelter3_cais | Relationship `belongsTo(User, 'created_by', 'full_name')` PUTUS |

#### P1 — High (core bisnis)

| # | Tabel | Database | Catatan |
|---|-------|----------|---------|
| 5 | **sl_leads** | shelter3_cais | |
| 6 | **sl_leads_pic** | shelter3_cais | |
| 7 | **sl_quotation** | shelter3_cais | |
| 8 | **sl_quotation_site** | shelter3_cais | |
| 9 | **sl_quotation_detail** | shelter3_cais | |
| 10 | **sl_quotation_detail_wages** | shelter3_cais | |
| 11 | **sl_quotation_detail_hpp** | shelter3_cais | |
| 12 | **sl_quotation_detail_coss** | shelter3_cais | |
| 13 | **sl_quotation_detail_requirement** | shelter3_cais | |
| 14 | **sl_quotation_detail_tunjangan** | shelter3_cais | |
| 15 | **sl_quotation_aplikasi** | shelter3_cais | |
| 16 | **sl_quotation_chemical** | shelter3_cais | |
| 17 | **sl_quotation_devices** | shelter3_cais | |
| 18 | **sl_quotation_kaporlap** | shelter3_cais | |
| 19 | **sl_quotation_kerjasama** | shelter3_cais | |
| 20 | **sl_quotation_margin** | shelter3_cais | |
| 21 | **sl_quotation_ohc** | shelter3_cais | |
| 22 | **sl_quotation_pic** | shelter3_cais | |
| 23 | **sl_quotation_training** | shelter3_cais | |
| 24 | **sl_spk** | shelter3_cais | |
| 25 | **sl_spk_site** | shelter3_cais | |
| 26 | **sl_pks** | shelter3_cais | |
| 27 | **sl_pks_perjanjian** | shelter3_cais | |
| 28 | **sl_pks_import** | shelter3_cais | |
| 29 | **sl_site** | shelter3_cais | |
| 30 | **sl_putus_kontrak** | shelter3_cais | |
| 31 | **sl_perusahaan_groups** | shelter3_cais | |
| 32 | **sl_perusahaan_groups_d** | shelter3_cais | |
| 33 | **sl_issue** | shelter3_cais | |
| 34 | **sl_activity_sales_file** | shelter3_cais | |

#### P2 — Medium (master data, transaksi pendukung)

| # | Tabel | Database | Catatan |
|---|-------|----------|---------|
| 35 | **sl_submission** | shelter3_cais | Data "webhook" |
| 36 | **sl_submission_v2** | shelter3_cais | Data "sync" |
| 37 | **sl_good_receipt** | shelter3_cais | |
| 38 | **sl_good_receipt_d** | shelter3_cais | |
| 39 | **sl_purchase_order** | shelter3_cais | |
| 40 | **sl_purchase_order_d** | shelter3_cais | |
| 41 | **sl_purchase_request** | shelter3_cais | |
| 42 | **sl_purchase_request_d** | shelter3_cais | |
| 43 | **sl_receiving_notes** | shelter3_cais | |
| 44 | **sl_receiving_notes_d** | shelter3_cais | |
| 45 | **sl_customer** | shelter3_cais | |
| 46 | **log_error** | shelter3_cais | |

#### P3 — Low (master data, mostly kosong)

| # | Tabel | Database | Catatan |
|---|-------|----------|---------|
| 47 | **sysmenu** | shelter3_cais | |
| 48 | **sysmenu_role** | shelter3_cais | |
| 49–61 | **m_barang, m_barang_default_qty, m_barang_import, m_jenis_barang, m_jenis_visit, m_kebutuhan, m_kebutuhan_detail, m_kebutuhan_detail_requirement, m_kebutuhan_detail_tunjangan, m_management_fee, m_requirement_posisi, m_salary_rule, m_tim_sales, m_tim_sales_d, m_tunjangan, m_tunjangan_posisi, m_training, m_top, m_ump, m_umk, m_umsk, m_umsp, m_platform, m_status_leads, m_status_pks, m_status_quotation, m_status_spk, m_aplikasi_pendukung, m_bidang_perusahaan, m_jabatan_pic, m_kategori_sesuai_hc, m_loyalty, m_rule_thr** | shelter3_cais | Master data, mayoritas kosong/null |
| 62+ | **m_district, m_village, dll** | shelter3_hris | HRIS, mostly system/empty |

#### ⚠️ Skip: Tabel dengan `created_by` integer (sudah pake ID)

| Tabel | Database | Type |
|-------|----------|------|
| m_branch | shelter3_cais | bigint |
| m_company | shelter3_cais | bigint |
| issue, issue_file, issue_timeline_activity | shelter3_cais | bigint unsigned |
| m_bentuk_usaha | shelter3_cais | bigint unsigned |
| system_announcements, system_announcement_files | shelter3_cais | bigint unsigned |
| Semua tabel shelter3_hris dengan `created_by` bigint/int | shelter3_hris | bigint/int |

### 1B. Migration File

Buat 1 migration file:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $tables = [
        // P0
        'sl_activity_sales',
        'log_approval',
        'log_notification',
        // P1
        'sl_leads',
        'sl_leads_pic',
        'sl_quotation',
        'sl_quotation_site',
        'sl_quotation_detail',
        'sl_quotation_detail_wages',
        'sl_quotation_detail_hpp',
        'sl_quotation_detail_coss',
        'sl_quotation_detail_requirement',
        'sl_quotation_detail_tunjangan',
        'sl_quotation_aplikasi',
        'sl_quotation_chemical',
        'sl_quotation_devices',
        'sl_quotation_kaporlap',
        'sl_quotation_kerjasama',
        'sl_quotation_margin',
        'sl_quotation_ohc',
        'sl_quotation_pic',
        'sl_quotation_training',
        'sl_spk',
        'sl_spk_site',
        'sl_pks',
        'sl_pks_perjanjian',
        'sl_pks_import',
        'sl_site',
        'sl_putus_kontrak',
        'sl_perusahaan_groups',
        'sl_perusahaan_groups_d',
        'sl_issue',
        'sl_activity_sales_file',
        // P2
        'sl_submission',
        'sl_submission_v2',
        'sl_good_receipt',
        'sl_good_receipt_d',
        'sl_purchase_order',
        'sl_purchase_order_d',
        'sl_purchase_request',
        'sl_purchase_request_d',
        'sl_receiving_notes',
        'sl_receiving_notes_d',
        'sl_customer',
        'log_error',
        // P3
        'sysmenu',
        'sysmenu_role',
        // m_* tables (varchar, name strings)
        'm_barang', 'm_barang_default_qty', 'm_barang_import',
        'm_jenis_barang', 'm_jenis_visit',
        'm_kebutuhan', 'm_kebutuhan_detail',
        'm_kebutuhan_detail_requirement', 'm_kebutuhan_detail_tunjangan',
        'm_management_fee', 'm_requirement_posisi', 'm_salary_rule',
        'm_tim_sales', 'm_tim_sales_d', 'm_tunjangan', 'm_tunjangan_posisi',
        'm_training', 'm_top', 'm_ump', 'm_umk', 'm_umsk', 'm_umsp',
        'm_platform', 'm_status_leads', 'm_status_pks',
        'm_status_quotation', 'm_status_spk',
        'm_aplikasi_pendukung', 'm_bidang_perusahaan', 'm_jabatan_pic',
        'm_kategori_sesuai_hc', 'm_loyalty', 'm_rule_thr',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasColumn($table, 'created_by_user_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('created_by_user_id')
                      ->nullable()
                      ->after('created_by');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'created_by_user_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('created_by_user_id');
                });
            }
        }
    }
};
```

### 1C. Catatan: `sl_customer_activity`

Tabel ini **sudah punya** kolom `user_id`. Dua opsi:
- **A**) Tetap tambah `created_by_user_id` biar konsisten
- **B**) Manfaatin `user_id` yang sudah ada, ganti semua filter query pake `user_id`

**Rekomendasi: B** — lebih hemat, `sl_customer_activity` sudah selalu diisi `user_id` di setiap insert (kecuali 1 bug di PksController:3399).

---

## Phase 2: Backfill Seeder

### 2A. Strategi Chunk (Minimalkan Table Lock)

Gunakan `chunkById()` — bukan satu UPDATE besar — untuk menghindari lock berkepanjangan:

| Approach | Risiko |
|----------|--------|
| ❌ `UPDATE table SET ... WHERE ...` (satu query besar) | Table lock lama, bisa menit, berpotensi deadlock |
| ✅ `chunkById(500)` loop + batch update | Lock per 500 baris ~milidetik, aman untuk production |

### 2B. Seeder: `BackfillCreatedByUserIdSeeder`

```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackfillCreatedByUserIdSeeder extends Seeder
{
    /**
     * Daftar tabel yang akan di-backfill.
     * Urutkan dari P0 (critical) → P3 (low).
     */
    protected array $tables = [
        // P0 — Critical (report & relationship)
        'sl_activity_sales',
        'sl_customer_activity', // sudah punya user_id, tapi diisi juga created_by_user_id
        'log_approval',
        'log_notification',
        // P1 — Core bisnis
        'sl_leads', 'sl_leads_pic',
        'sl_quotation', 'sl_quotation_site', 'sl_quotation_detail',
        'sl_quotation_detail_wages', 'sl_quotation_detail_hpp',
        'sl_quotation_detail_coss', 'sl_quotation_detail_requirement',
        'sl_quotation_detail_tunjangan', 'sl_quotation_aplikasi',
        'sl_quotation_chemical', 'sl_quotation_devices',
        'sl_quotation_kaporlap', 'sl_quotation_kerjasama',
        'sl_quotation_margin', 'sl_quotation_ohc', 'sl_quotation_pic',
        'sl_quotation_training',
        'sl_spk', 'sl_spk_site',
        'sl_pks', 'sl_pks_perjanjian', 'sl_pks_import',
        'sl_site', 'sl_putus_kontrak',
        'sl_perusahaan_groups', 'sl_perusahaan_groups_d',
        'sl_issue', 'sl_activity_sales_file',
        // P2 — Transaksi pendukung
        'sl_submission', 'sl_submission_v2',
        'sl_good_receipt', 'sl_good_receipt_d',
        'sl_purchase_order', 'sl_purchase_order_d',
        'sl_purchase_request', 'sl_purchase_request_d',
        'sl_receiving_notes', 'sl_receiving_notes_d',
        'sl_customer', 'log_error',
        // P3 — Master data
        'm_barang', 'm_barang_default_qty', 'm_barang_import',
        'm_jenis_barang', 'm_jenis_visit',
        'm_kebutuhan', 'm_kebutuhan_detail',
        'm_kebutuhan_detail_requirement', 'm_kebutuhan_detail_tunjangan',
        'm_management_fee', 'm_requirement_posisi', 'm_salary_rule',
        'm_tim_sales', 'm_tim_sales_d', 'm_tunjangan', 'm_tunjangan_posisi',
        'm_training', 'm_top', 'm_ump', 'm_umk', 'm_umsk', 'm_umsp',
        'm_platform', 'm_status_leads', 'm_status_pks',
        'm_status_quotation', 'm_status_spk',
        'm_aplikasi_pendukung', 'm_bidang_perusahaan', 'm_jabatan_pic',
        'm_kategori_sesuai_hc', 'm_loyalty', 'm_rule_thr',
        'sysmenu', 'sysmenu_role',
    ];

    /**
     * Chunk size — 500 baris per batch.
     * Lebih kecil = lebih aman, lebih besar = lebih cepat.
     */
    protected int $chunkSize = 500;

    public function run(): void
    {
        foreach ($this->tables as $table) {
            $this->processTable($table);
        }

        $this->command->info(PHP_EOL . '✅ All tables processed.');
        $this->outputSummary();
    }

    protected function processTable(string $table): void
    {
        // Hitung total yang perlu di-update
        $totalPending = DB::table($table)
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->whereNull('created_by_user_id')
            ->count();

        if ($totalPending === 0) {
            $this->command->warn("  ⏭  {$table}: nothing to backfill");
            return;
        }

        $progress = $this->command->getOutput()->createProgressBar($totalPending);
        $progress->setFormat("  %message%: %current%/%max% [%bar%] %percent:3s%%");
        $progress->setMessage($table);
        $progress->start();

        $updated = 0;
        $skipped = 0;

        // chunkById = SELECT id, created_by WHERE ... ORDER BY id LIMIT 500
        // Proses per batch, jeda alami antar batch
        DB::table($table)
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->whereNull('created_by_user_id')
            ->orderBy('id')
            ->chunkById($this->chunkSize, function ($rows) use ($table, $progress, &$updated, &$skipped) {
                // 1. Kumpulkan semua nama unik dalam batch ini
                $names = $rows->pluck('created_by')->unique()->values()->toArray();

                // 2. Satu query batch ke mysqlhris — bukan N+1
                $userMap = DB::connection('mysqlhris')
                    ->table('m_user')
                    ->whereIn('full_name', $names)
                    ->pluck('id', 'full_name');

                // 3. Update per row dalam batch
                foreach ($rows as $row) {
                    if (isset($userMap[$row->created_by])) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['created_by_user_id' => $userMap[$row->created_by]]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    $progress->advance();
                }
            });

        $progress->finish();
        $this->command->info('');
        $this->command->line("     ✓ {$updated} updated, {$skipped} skipped (unmatched names)");
    }

    protected function outputSummary(): void
    {
        $this->command->info(PHP_EOL . '=== Tables with NULL created_by_user_id (unmatched names) ===');
        foreach ($this->tables as $table) {
            $nulls = DB::table($table)
                ->whereNotNull('created_by')
                ->where('created_by', '!=', '')
                ->whereNull('created_by_user_id')
                ->count();
            if ($nulls > 0) {
                $this->command->line("  {$table}: {$nulls} rows");
            }
        }
    }
}
```

### 2C. Alur Kerja Chunk

```
┌──────────────────────────────────────────────────────┐
│  foreach table:                                       │
│    hitung total pending                               │
│    chunkById(500) → SELECT id, created_by             │
│     ↓                                                 │
│    kumpulkan unique names dari 500 baris              │
│     ↓                                                 │
│    batch query ke mysqlhris.m_user (1 query)          │
│     ↓                                                 │
│    loop 500 baris → UPDATE per id                     │
│     ↓                                                 │
│    LANJUT ke 500 berikutnya (chunkById otomatis)      │
│     ↓                                                 │
│    selesai, lanjut tabel berikutnya                   │
└──────────────────────────────────────────────────────┘
```

Keuntungan:
- ✅ **Minim lock** — UPDATE hanya 1 baris per statement, diproses 500 baris per chunk
- ✅ **Anti N+1** — unique names dikumpulin, 1 query batch ke m_user per chunk
- ✅ **Progress bar** — tahu persis berapa persen selesai
- ✅ **Resumable** — kalau terputus, tinggal jalanin lagi (WHERE `created_by_user_id IS NULL`)

### 2D. Edge Cases

| Kasus | Penanganan |
|-------|-----------|
| `created_by` = `'System'`, `'Superadmin IT'`, `'Super Admin'` | Tidak match → `created_by_user_id` = NULL, di-skip |
| `created_by` = nama yang sudah diubah (ELVIN case) | JOIN gagal → di-skip, perlu manual fix |
| Duplicate `full_name` di m_user | `pluck('id', 'full_name')` ambil yang terakhir, kemungkinan duplicate name kecil |
| `created_by` = integer string (`"1"`) di varchar column | JOIN ke full_name → tidak match → NULL |
| `created_by` kosong / `''` | Skip (WHERE `!= ''`) |
| Backfill terputus di tengah | Jalanin ulang — WHERE `created_by_user_id IS NULL` pastiin idempotent |

### 2E. Verifikasi

```sql
-- Cek total yang belum match per tabel
SELECT COUNT(*) as total_null
FROM sl_activity_sales
WHERE created_by IS NOT NULL AND created_by != '' AND created_by_user_id IS NULL;

-- Cek isi nama yang tidak match
SELECT DISTINCT created_by
FROM sl_activity_sales
WHERE created_by IS NOT NULL AND created_by != '' AND created_by_user_id IS NULL;
```

---

## Phase 3: Update Insert Code (~150 lokasi)

### Pattern yang harus diubah

**Sebelum:**
```php
'created_by' => Auth::user()->full_name,
```

**Sesudah:**
```php
'created_by' => Auth::user()->full_name,
'created_by_user_id' => Auth::id(),
```

### Daftar file yang kena insert (per controller):

| # | File | Perkiraan lokasi | Prioritas |
|---|------|------------------|-----------|
| 1 | `app/Http/Controllers/LeadsController.php` | ~6 lokasi | P1 |
| 2 | `app/Http/Controllers/QuotationController.php` | ~5 lokasi | P1 |
| 3 | `app/Http/Controllers/SpkController.php` | ~14 lokasi | P1 |
| 4 | `app/Http/Controllers/PksController.php` | ~13 lokasi | P1 |
| 5 | `app/Http/Controllers/CustomerActivityController.php` | ~7 lokasi | P0 |
| 6 | `app/Http/Controllers/SalesActivityController.php` | ~2 lokasi | P0 |
| 7 | `app/Http/Controllers/CompanyGroupController.php` | ~3 lokasi | P1 |
| 8 | `app/Services/QuotationBusinessService.php` | ~1 lokasi | P1 |
| 9 | `app/Services/QuotationStepService.php` | ~20 lokasi | P1 |
| 10 | `app/Services/QuotationService.php` | ~2 lokasi | P1 |
| 11 | `app/Services/AddendumService.php` | ~2 lokasi | P1 |
| 12 | `app/Services/UpahService.php` | ~4 lokasi | P3 |
| 13 | `app/Services/SubmissionV2Controller.php` | ~1 lokasi | P2 |
| 14 | `app/Http/Controllers/SubmissionController.php` | ~1 lokasi | P2 |
| 15 | `app/Http/Controllers/BarangController.php` | ~3 lokasi | P3 |
| 16 | `app/Http/Controllers/TimSalesController.php` | ~2 lokasi | P3 |
| 17 | `app/Http/Controllers/KebutuhanController.php` | ~2 lokasi | P3 |
| 18 | `app/Http/Controllers/TrainingController.php` | ~1 lokasi | P3 |
| 19 | `app/Http/Controllers/TopController.php` | ~1 lokasi | P3 |
| 20 | `app/Http/Controllers/UmpController.php` | ~1 lokasi | P3 |
| 21 | `app/Http/Controllers/UmkController.php` | ~1 lokasi | P3 |
| 22 | `app/Http/Controllers/SupplierController.php` | ~1 lokasi | P3 |
| 23 | `app/Http/Controllers/SalaryRuleController.php` | ~1 lokasi | P3 |
| 24 | `app/Http/Controllers/ManagementFeeController.php` | ~1 lokasi | P3 |
| 25 | `app/Http/Controllers/MenuController.php` | ~1 lokasi | P3 |
| 26 | `app/Http/Controllers/JenisBarangController.php` | ~1 lokasi | P3 |
| 27 | `app/Models/LogNotification.php` | ~1 lokasi (boot) | P0 |
| 28 | `app/Models/Kebutuhan.php` | ~1 lokasi (boot) | P3 |
| 29 | `app/Models/KebutuhanDetailRequirement.php` | ~1 lokasi (boot) | P3 |
| 30 | `app/Models/KebutuhanDetailTunjangan.php` | ~1 lokasi (boot) | P3 |
| 31 | `app/Models/RequirementPosisi.php` | ~1 lokasi (boot) | P3 |
| 32 | `app/Models/TunjanganPosisi.php` | ~1 lokasi (boot) | P3 |
| 33 | `app/Models/SalesActivity.php` | ~1 lokasi (boot) | P0 |

### Bug fix khusus

**PksController.php:3399** — `logPerjanjianChange()` insert ke `sl_customer_activity` tanpa `user_id`:
```php
// Ada kemungkinan juga kurang created_by_user_id setelah migration
```

### Catatan Penting

- Jangan lupa update **QuotationDuplicationService.php** — saat duplikasi quotation, `created_by` di-copy dari quotation lama. Perlu juga copy `created_by_user_id`.
- Model boot events (`creating()` callback) — beberapa model auto-set `created_by` via boot, perlu tambah `created_by_user_id`.

---

## Phase 4: Update ReportController

### 4A. Helper Methods

**`getSalesNames()` dan `getSalesNamesRole30()`** — sudah return `user_id`. Tidak perlu diubah.

### 4B. Ubah Parameter Filter

Kirim `$userIds` sebagai pengganti/gandengan `$salesNames`:

```php
// Sebelum (baris 131):
$salesNames = $salesData->pluck('nama_sales')->toArray();

// Sesudah:
$salesNames = $salesData->pluck('nama_sales')->toArray();
$userIds = $salesData->pluck('user_id')->filter()->values()->toArray();
```

### 4C. Ubah Query Filter (6 titik)

#### 1. `getMonthlyAggregation()` — line 1591-1607
```php
// SEBELUM:
return DB::table('sl_activity_sales')
    ->select('created_by', ...)
    ->whereBetween('tgl_activity', [$start, $end])
    ->whereIn('created_by', $salesNames)
    ->groupBy('created_by')
    ->get();

// SESUDAH:
return DB::table('sl_activity_sales')
    ->select('created_by', ...)
    ->whereBetween('tgl_activity', [$start, $end])
    ->whereIn('created_by_user_id', $userIds)
    ->groupBy('created_by_user_id')
    ->get();
```

#### 2. `weekly()` query (sl_activity_sales) — line 279-309
```php
// whereIn('created_by', $salesNames) → whereIn('created_by_user_id', $userIds)
// groupBy('created_by') → groupBy('created_by_user_id')
```

#### 3. `getRole30MonthlyAggregation()` — line 1688-1713
```php
// whereIn('sa.created_by', $salesNames) → whereIn('sa.user_id', $userIds)
// (pakai user_id yang sudah ada, bukan created_by_user_id)
```

#### 4. `weeklyRole30()` query (sl_customer_activity) — line 852-949
```php
// whereIn('sa.created_by', $salesNames) → whereIn('sa.user_id', $userIds)
```

#### 5. `activityDetail()` — line 1270-1271
```php
// SEBELUM:
->where('sa.created_by', $salesName)
// SESUDAH:
->where('sa.created_by_user_id', $userId)
```

#### 6. `activityDetailTele()` — line 1494-1495
```php
// SEBELUM:
->where('sa.created_by', $salesName)
// SESUDAH:
->where('sa.user_id', $userId)
// (sl_customer_activity sudah punya user_id)
```

### 4D. Ubah `firstWhere` Lookup (4 titik)

```php
// SEBELUM:
$aggThisMonth->firstWhere('created_by', $nama);
$weeklyActivity->firstWhere('created_by', $nama);
$aggData->firstWhere('created_by', $nama);

// SESUDAH:
$aggThisMonth->firstWhere('created_by_user_id', $sales->user_id);
$weeklyActivity->firstWhere('created_by_user_id', $sales->user_id);
$aggData->firstWhere('user_id', $sales->user_id); // sl_customer_activity
```

### 4E. Perubahan Response

Response masih bisa return `created_by` (nama string) untuk display. Tidak perlu diubah.

---

## Phase 5: Fix Eloquent Relationships

### LogApproval (app/Models/LogApproval.php:64)
```php
// SEBELUM:
return $this->belongsTo(User::class, 'created_by', 'full_name');

// SESUDAH:
return $this->belongsTo(User::class, 'created_by_user_id', 'id');
```

### LogNotification (app/Models/LogNotification.php:72)
```php
// SEBELUM:
return $this->belongsTo(User::class, 'created_by', 'full_name');

// SESUDAH:
return $this->belongsTo(User::class, 'created_by_user_id', 'id');
```

### SalesActivity (app/Models/SalesActivity.php:61)
```php
// SEBELUM: belongsTo(User::class, 'created_by') — broken, join nama ke id
// SESUDAH:
return $this->belongsTo(User::class, 'created_by_user_id', 'id');
```

---

## Phase 6: Testing

### 6A. Unit Test
```bash
composer test
```

### 6B. Manual QA Checklist

| No | Test Case | Expected |
|----|-----------|----------|
| 1 | GET `/api/sales-report/monthly?month=6&year=2026` | User 17019 muncul dengan data > 0 |
| 2 | GET `/api/sales-report/weekly?month=6&year=2026` | User 17019 muncul dengan data > 0 |
| 3 | GET `/api/sales-report/activity-detail/17019?month=6&year=2026` | Histori aktivitas muncul |
| 4 | Buat activity baru, cek `created_by_user_id` terisi | ID benar |
| 5 | Buat quotation baru, cek `created_by_user_id` | ID benar |
| 6 | Buat SPK baru, cek `created_by_user_id` | ID benar |
| 7 | Buat PKS baru, cek `created_by_user_id` | ID benar |

### 6C. Rollback Plan

```bash
php artisan migrate:rollback --step=1
# Ulang seeder dengan data backup
```

---

## Timeline Estimasi

| Phase | Estimasi | Ketergantungan |
|-------|----------|----------------|
| P1: Migration + Seeder | 1 jam | - |
| P2: Insert code (~150 lokasi) | 4-6 jam | P1 selesai |
| P3: ReportController (~6 query) | 1 jam | P1 selesai |
| P4: Eloquent relationships (3 file) | 30 menit | P1 selesai |
| P5: Testing | 2 jam | Semua selesai |
| **Total** | **~10-12 jam** | |

---

## Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| Migration fail di production | Downtime | Backup DB dulu, jalankan di jam sepi |
| Nama tidak match di backfill | `created_by_user_id` = NULL | Query verifikasi, manual fix untuk nama-nama tertentu |
| Ada insert yang kelewat ditambah `created_by_user_id` | Kolom NULL untuk record baru | Code review + grep `'created_by' =>` cari semua lokasi |
| Report jadi lambat karena kolom baru belum di-index | Query lambat | Tambah index di `created_by_user_id` |
| Salah satu query di ReportController kelewat diubah | Report masih pake nama | Code review checklist |
