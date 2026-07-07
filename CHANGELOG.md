# Changelog

## [Unreleased] — 2026-06-24

### Added

#### Migration: `add_created_by_user_id_to_tables`
- Kolom `created_by_user_id` (unsignedBigInteger, nullable, after `created_by`) di 52 tabel
- **P0 (4):** `sl_activity_sales`, `sl_customer_activity`, `log_approval`, `log_notification`
- **P1 (30):** `sl_leads`, `sl_leads_pic`, `sl_quotation`, `sl_quotation_site`, `sl_quotation_detail`, `sl_quotation_detail_wages`, `sl_quotation_detail_hpp`, `sl_quotation_detail_coss`, `sl_quotation_detail_requirement`, `sl_quotation_detail_tunjangan`, `sl_quotation_aplikasi`, `sl_quotation_chemical`, `sl_quotation_devices`, `sl_quotation_kaporlap`, `sl_quotation_kerjasama`, `sl_quotation_margin`, `sl_quotation_ohc`, `sl_quotation_pic`, `sl_quotation_training`, `sl_spk`, `sl_spk_site`, `sl_pks`, `sl_pks_perjanjian`, `sl_pks_import`, `sl_site`, `sl_putus_kontrak`, `sl_perusahaan_groups`, `sl_perusahaan_groups_d`, `sl_issue`, `sl_activity_sales_file`
- **P2 (12):** `sl_submission`, `sl_submission_v2`, `sl_good_receipt`, `sl_good_receipt_d`, `sl_purchase_order`, `sl_purchase_order_d`, `sl_purchase_request`, `sl_purchase_request_d`, `sl_receiving_notes`, `sl_receiving_notes_d`, `sl_customer`, `log_error`
- **P3 (6+):** `sysmenu`, `sysmenu_role`, `m_barang`, `m_barang_default_qty`, `m_barang_import`, `m_jenis_barang`, `m_jenis_visit`, `m_kebutuhan`, `m_kebutuhan_detail`, `m_kebutuhan_detail_requirement`, `m_kebutuhan_detail_tunjangan`, `m_management_fee`, `m_requirement_posisi`, `m_salary_rule`, `m_tim_sales`, `m_tim_sales_d`, `m_tunjangan`, `m_tunjangan_posisi`, `m_training`, `m_top`, `m_ump`, `m_umk`, `m_umsk`, `m_umsp`, `m_platform`, `m_status_leads`, `m_status_pks`, `m_status_quotation`, `m_status_spk`, `m_aplikasi_pendukung`, `m_bidang_perusahaan`, `m_jabatan_pic`, `m_kategori_sesuai_hc`, `m_loyalty`, `m_rule_thr`

#### Seeder: `BackfillCreatedByUserIdSeeder`
- Chunk: `chunkById(500)` — minim lock, resumable
- Filter: hanya data `created_at >= 2026` (kalo tabel punya kolom `created_at`)
- Batch query: kumpulin unique names → 1 query ke `mysqlhris.m_user` per chunk (anti N+1)
- Progress bar + summary output (nampilin sisa nama yang ga match)
- Idempotent: `WHERE created_by_user_id IS NULL` → aman jalan ulang
- Tabel tanpa `created_at` (master `m_*`): diproses semua (tanpa filter tahun)

#### Insert Code: `'created_by_user_id' => ...` (45 files)
| Kelompok | File | Lokasi |
|----------|------|--------|
| P0 | `SalesActivityController.php` | 2 |
| P0 | `CustomerActivityController.php` | 6 |
| P0 | `LogNotification.php` (model) | 2 |
| P1 | `LeadsController.php` | 7 |
| P1 | `QuotationController.php` | 4 |
| P1 | `SpkController.php` | 12 |
| P1 | `PksController.php` | 16 |
| P1 | `QuotationBusinessService.php` | 5 |
| P1 | `QuotationStepService.php` | 20 |
| P1 | `QuotationService.php` | 1 |
| P1 | `AddendumService.php` | 2 |
| P1 | `QuotationDuplicationService.php` | 16 |
| P1 | `CompanyGroupController.php` | 3 |
| P2 | `SubmissionController.php` | 2 |
| P2 | `SubmissionV2Controller.php` | 3 |
| P2 | `ProcessQuotationFinalization.php` (Job) | 2 |
| P3 | `BarangController.php` | 3 |
| P3 | `KebutuhanController.php` | 2 |
| P3 | `TimSalesController.php` | 2 |
| P3 | `JenisBarangController.php` | 1 |
| P3 | `ManagementFeeController.php` | 1 |
| P3 | `MenuController.php` | 1 |
| P3 | `SalaryRuleController.php` | 1 |
| P3 | `SupplierController.php` | 1 |
| P3 | `TopController.php` | 1 |
| P3 | `TrainingController.php` | 1 |
| P3 | `UmkController.php` | 1 |
| P3 | `UmpController.php` | 1 |
| P3 | `UpahService.php` | 9 |
| P3 | PKS Template Services (5 files) | 5 |

#### ReportController: Filter Query
| Method | Sebelum | Sesudah |
|--------|---------|---------|
| `getMonthlyAggregation()` | `whereIn('created_by', $salesNames)` `groupBy('created_by')` | `whereIn('created_by_user_id', $userIds)` `groupBy('created_by_user_id')` |
| `weekly()` | `whereIn('created_by', $salesNames)` `groupBy('created_by')` | `whereIn('created_by_user_id', $userIds)` `groupBy('created_by_user_id')` |
| `getRole30MonthlyAggregation()` | `whereIn('sa.created_by', $salesNames)` `groupBy('sa.created_by')` | `whereIn('sa.user_id', $userIds)` `groupBy('sa.user_id')` |
| `weeklyRole30()` | `whereIn('sa.created_by', $salesNames)` `groupBy('sa.created_by')` | `whereIn('sa.user_id', $userIds)` `groupBy('sa.user_id')` |
| `activityDetail()` | `where('sa.created_by', $salesName)` | `where('sa.created_by_user_id', $userId)` |
| `activityDetailTele()` | `where('sa.created_by', $salesName)` | `where('sa.user_id', $userId)` |
| 4x `firstWhere()` | `firstWhere('created_by', $nama)` | `firstWhere('created_by_user_id', $sales->user_id)` / `firstWhere('user_id', $sales->user_id)` |

### Changed

#### Eloquent Relationships (3 models)
| Model | Sebelum | Sesudah |
|-------|---------|---------|
| `LogApproval.php:64` | `belongsTo(User::class, 'created_by', 'full_name')` | `belongsTo(User::class, 'created_by_user_id', 'id')` |
| `LogNotification.php:72` | `belongsTo(User::class, 'created_by', 'full_name')` | `belongsTo(User::class, 'created_by_user_id', 'id')` |
| `SalesActivity.php:61` | `belongsTo(User::class, 'created_by')` | `belongsTo(User::class, 'created_by_user_id', 'id')` |

#### Model Boot Events (5 models)
- `Kebutuhan.php`, `KebutuhanDetailRequirement.php`, `KebutuhanDetailTunjangan.php`, `RequirementPosisi.php`, `TunjanganPosisi.php`
- Tambah `$model->created_by_user_id = auth()->id();` di `static::creating()` callback

#### Model $fillable (7 models)
- `CustomerActivity.php`, `LogApproval.php`, `LogNotification.php`, `SalesActivity.php`, `Leads.php`, `Quotation.php`, `Kebutuhan.php`
- Tambah `'created_by_user_id'` ke `$fillable` array

### Fixed

#### SpkController: missing brace (syntax error)
- Di `createResubmissionActivities()`, closing `}` dari `if (!empty($deletedSpkSiteIds))` kehapus waktu edit task agent
- Akibat: PHP parse error `unexpected token "private"` di line 2215
- Fix: tambah `}` balik, rapiin indentasi array

#### LeadsController: syntax `];` vs `])` (pre-existing bug)
- `response()->json([...]);` pake `];` buat nutup function call — harusnya `]);`
- Fix: ganti `];` → `]);`

#### ReportController: undefined variable `$nama`
- Di `monthlyRole30()`, variabel `$nama = $sales->nama_sales` kehapus pas edit `firstWhere`
- Akibat: error undefined variable `$nama` di response array
- Fix: tambah `$nama = $sales->nama_sales;` balik

### Notes
- **Butuh `php artisan migrate`** sebelum seeder jalan
- **Seeder optional** — bisa jalan kapan aja, idempotent, aman diulang
- **Commit:** `e058619c` (48 files, +1205 -80)

---

## Garis Besar

**Masalah:** `created_by` di ~48 tabel nyimpen **nama string** (`Auth::user()->full_name`). Pas nama user berubah (trigger: User ID 17019 ELVIN, nama diubah 2026-06-23), **508 record aktivitas historis jadi ga terbaca** di report karena filter pake nama ga match.

**Solusi:** Kolom baru `created_by_user_id` (unsignedBigInteger) di semua tabel — nyimpen **ID user**, bukan nama. `created_by` (string) tetap dipertahankan buat display/backward compatibility.

| Layer | Apa yang berubah |
|-------|-----------------|
| **Database** | 52 tabel dapet kolom `created_by_user_id` |
| **Insert code** | 45 file, ~150 lokasi — tiap `'created_by' => ...` ditambah `'created_by_user_id' => Auth::id()` |
| **Report** | 6 query report + 4 lookup — filter pake `created_by_user_id` / `user_id`, bukan `created_by` (string) |
| **Relationship** | 3 model (LogApproval, LogNotification, SalesActivity) — join pake ID, bukan full_name |
| **Model events** | 5 model auto-set `created_by_user_id` di boot `creating()` |
| **Backfill** | Seeder idempotent, chunkById 500, hanya data `>= 2026` |
