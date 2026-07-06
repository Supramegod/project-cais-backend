# Rencana Refactoring Quotation Flow

## Ringkasan

Dokumen ini berisi rencana perbaikan menyeluruh untuk flow pembuatan quotation, mulai dari `QuotationController@store` hingga `updateStep12` dan `QuotationStepService` (4498 baris). Target utama: memecah file besar, menghilangkan duplikasi, memperbaiki N+1, dan mengurangi beban komputasi berulang.

---

## ⚠️ SYARAT MUTLAK: Zero Payload Change

**Tidak boleh ada perubahan response payload** antara sebelum vs sesudah refactoring. Setiap perubahan harus diverifikasi dengan:
1. Diff code per-step antara resource vs controller
2. Test assertion yang capture exact JSON response
3. UAT dengan response actual dari endpoint

---

## Prioritas 0 — Bersihkan QuotationStepResource (1010 baris)

### Status Saat Ini

| Komponen | Status |
|----------|--------|
| `QuotationStepResource::getStepSpecificData()` (583 baris) | ✅ **Tidak dipakai** — logic sudah dipindah ke `QuotationStepController::buildStepDataStep*()` |
| `QuotationStepResource::getAdditionalData()` (218 baris) | ✅ **Tidak dipakai** — logic sudah dipindah ke `QuotationStepController::buildAdditionalDataStep*()` |
| `QuotationStepResource` via `new QuotationStepResource(...)` di `updateStep()` response | ❌ **Masih dipakai** — baris 282 |

### Hasil Diff Payload Per Step

Dari analisa menyeluruh, ditemukan perbedaan antara output `QuotationStepResource` (lama) vs `QuotationStepController` (baru):

| Step | `step_data` sama? | `additional_data` sama? | Catatan |
|------|-------------------|------------------------|---------|
| **1** | ✅ Identik | ✅ Identik | Aman |
| **2** | ✅ Identik | ✅ Identik | Aman |
| **3** | ❌ **BERBEDA** | ❌ **BERBEDA** | Resource: detail punya `requirements` + `tunjangans` + fallback query. Controller: tanpa requirements/tunjangans. Additional: Resource punya `provinsi`, `kota`, `penempatan` di site — controller hanya `id`, `nama_site` |
| **4** | ❌ **BERBEDA** | ✅ Identik | Controller punya `management_fee_components` extra di `global_data` |
| **5** | ✅ Identik | ✅ Identik | Aman |
| **6** | ✅ Identik | ✅ Identik | Aman |
| **7** | ✅ Identik | ✅ Identik | Aman |
| **8** | ✅ Identik | ✅ Identik | Aman |
| **9** | ✅ Identik | ✅ Identik | Aman |
| **10** | ✅ Identik | ✅ Identik | Aman |
| **11** | ❌ **BERBEDA** | ✅ Identik | Resource: `coss` selalu ada untuk semua detail. Controller: `coss` di-skip untuk RO (is_ro). `penagihan`/`nama_perusahaan` fallback berbeda |
| **12** | ❌ **BERBEDA** | ✅ Identik | Resource: pakai `$calculatedQuotation->calculation_summary`. Controller: **bug** — panggil `$summary` undefined (`$summary` tidak di-set) |

### Strategi Cleanup

Karena ada perbedaan payload, pendekatan amannya:

#### Step 3, 11, 12 — Sinkronisasi Dulu

Sebelum resource dihapus, **pastikan controller menghasilkan payload yang identik** dengan resource (kecuali perbaikan memang disengaja):

1. **Step 3:** Tambah `requirements` + `tunjangans` di `buildStepDataStep3()` controller — ikuti pola resource (dengan try/catch fallback).
2. **Step 11:** Pastikan `coss` di-resource dan controller konsisten (baik selalu ada atau conditional RO).
3. **Step 12:** Fix bug `$summary` undefined di controller — ambil dari `$calculatedQuotation->calculation_summary`.
4. **Step 4:** Jika `management_fee_components` adalah tambahan baru, pastikan frontend sudah handle field baru ini.

#### Setelah Sinkron — Penggantian

Di `QuotationStepController::updateStep()` baris 280-285:

```php
// SEBELUM
'data' => new QuotationStepResource(Quotation::notDeleted()->findOrFail($id), $step),

// SESUDAH
'data' => $this->prepareStepData(
    Quotation::notDeleted()->findOrFail($id),
    $step
),
```

Kedua response punya struktur JSON yang identik (hanya beda urutan key — tidak relevan untuk JSON).

#### Setelah Penggantian — Hapus Resource

1. Hapus `use App\Http\Resources\QuotationStepResource;` dari controller
2. Hapus file `app/Http/Resources/QuotationStepResource.php`
3. Verifikasi tidak ada import lain yang refer file ini (`grep -r "QuotationStepResource"`)

**Estimasi:** 1 hari (termasuk sinkronisasi diff)

---

## Prioritas 1 — Pecah QuotationStepService (4498 baris)

**Masalah:** Satu file menampung semua step logic, helper, dan utilitas. Setiap perubahan berisiko tinggi.

**Langkah:**
1. Buat direktori `app/Services/Steps/`
2. Pindahkan masing-masing `updateStep1()` s.d. `updateStep12()` ke class terpisah:

| File Baru | Method | Baris |
|-----------|--------|-------|
| `app/Services/Steps/Step1Service.php` | `execute()` | 366-405 |
| `app/Services/Steps/Step2Service.php` | `execute()` | 406-464 |
| `app/Services/Steps/Step3Service.php` | `execute()` + `syncDetailHCFromArray()` | 465-528 + 1865-2022 |
| `app/Services/Steps/Step4Service.php` | `execute()` + `updateUpahPerPosition()` | 529-617 + 1787-1814 |
| `app/Services/Steps/Step5Service.php` | `execute()` | 618-704 |
| `app/Services/Steps/Step6Service.php` | `execute()` | 706-842 |
| `app/Services/Steps/Step7Service.php` | `execute()` | 843-880 |
| `app/Services/Steps/Step8Service.php` | `execute()` | 881-918 |
| `app/Services/Steps/Step9Service.php` | `execute()` | 919-965 |
| `app/Services/Steps/Step10Service.php` | `execute()` | 966-1020 |
| `app/Services/Steps/Step11Service.php` | `execute()` → delegasi ke `updateAllQuotationData()` | 1021-1025 + 3036-3117 |
| `app/Services/Steps/Step12Service.php` | `execute()` | 1028-1098 |

3. Helper method milik bersama (`updateKerjasamaData`, `softDeleteQuotationDetail`, `syncTunjanganData`, dll.) pindah ke `app/Services/Steps/Traits/StepHelperTrait.php` atau `app/Services/QuotationStepHelperService.php`
4. `QuotationStepService` lama disederhanakan menjadi **router** / **facade** yang mapping `updateStep1` → `Step1Service::execute()`

**Estimasi:** 2-3 hari

---

## Prioritas 2 — Hapus Dead Code

### 2a. `QuotationStepService::prepareStepData()` (baris 137-360)

- Method ini sudah tidak dipanggil — controller punya `prepareStepData()` sendiri.
- **Tindakan:** Hapus method. Jika ada caller (cek grep dulu), redirect ke controller method.

### 2b. Cek seluruh `QuotationStepService` untuk method yang tak terpakai

- `grep -r "function [a-zA-Z]*" app/Services/QuotationStepService.php | grep -v "updateStep"`
- Cari caller masing-masing method. Hapus yang unreachable.

**Estimasi:** 0.5 hari

---

## Prioritas 3 — Perbaiki N+1

### 3a. N+1 UMK/UMP per site di Additional Data Step 4

**Lokasi:** `QuotationStepController::buildAdditionalDataStep4()` (baris 897-993)

**Sekarang:**
```php
foreach ($sites as $site) {
    $umk = Umk::byCity($site->kota_id)->active()->first();   // 1 query
    $ump = Ump::byCity($site->kota_id)->active()->first();   // 1 query
    $umsk = Umsk::byCity($site->kota_id)->active()->first(); // 1 query
    $umps = UmpProvince::byProvince($site->provinsi_id)->active()->first(); // 1 query
}
// 4 query × jumlah site
```

**Perbaikan:**
```php
$kotaIds = $sites->pluck('kota_id')->unique()->toArray();
$provinsiIds = $sites->pluck('provinsi_id')->unique()->toArray();

$umkList = Umk::whereIn('kota_id', $kotaIds)->active()->get()->keyBy('kota_id');
$umpList = Ump::whereIn('kota_id', $kotaIds)->active()->get()->keyBy('kota_id');
$umskList = Umsk::whereIn('kota_id', $kotaIds)->active()->get()->keyBy('kota_id');
$umpsList = UmpProvince::whereIn('provinsi_id', $provinsiIds)->active()->get()->keyBy('provinsi_id');

foreach ($sites as $site) {
    $umk = $umkList->get($site->kota_id);
    $ump = $umpList->get($site->kota_id);
    // ...
}
```

### 3b. Kaporlap/Devices N+1

**Lokasi:** `QuotationStepResource::getKaporlapData()` (baris 884-929) dan `getDevicesData()` (931-971)

**Catatan:** Method ini sudah tidak dipakai karena resource akan dihapus (Prioritas 0).

**Estimasi:** 0.5 hari (hanya untuk 3a)

---

## Prioritas 4 — Step 11: Cache Hasil Kalkulasi

**Masalah:** Setiap `getStep(step=11)` memicu `calculateQuotation()` penuh, padahal data tidak berubah.

**Langkah:**
1. Buat kolom `cached_calculation` (JSON) atau `calculated_at` (timestamp) di tabel `sl_quotation`.
2. Di `updateAllQuotationData()` (Step 11 write), setelah kalkulasi selesai, simpan hasil kalkulasi ke `cached_calculation`.
3. Di `getStep(step=11)`, jika `calculated_at > updated_at` (atau pakai dirty flag), return cached.
4. Di `updateStep` step lain (1-10), set `calculated_at = null` (invalidate cache).

**Estimasi:** 0.5 hari

---

## Prioritas 5 — Bulk Update HPP / COSS

**Lokasi:** `syncDetailHCFromArray()`, `updateBpjsKsNominal()`, `saveAllCalculationResults()`

**Sekarang (loop + single update):**
```php
foreach ($hppUpdate as $data) {
    QuotationDetailHpp::where('id', $data['id'])->update($data);
}
```

**Perbaikan:** Pakai `upsert()` atau `update()` dengan `whereIn()`:

```php
QuotationDetailHpp::upsert($hppUpdate, ['id'], ['jumlah_hc', 'nominal_upah', ...]);
```

**Estimasi:** 1 hari

---

## Prioritas 6 — Step 11 Reset + Recalculate

**Masalah:** `resetAllCalculatedValues()` null-kan 20+ field setiap kali Step 11 di-update, lalu kalkulasi dari 0. Riskan data loss.

**Langkah:**
1. Alternatif 1 (dianjurkan): Hanya reset field yang dipengaruhi oleh perubahan terakhir. Track field mana yang diubah di request, hanya recalculate field terkait.
2. Alternatif 2: Simpan snapshot sebelum reset, restore jika kalkulasi gagal. Sebagian sudah ada (`preResetHppMap`) tapi belum sempurna.
3. Tambahkan validation di awal `updateAllQuotationData()` — pastikan semua data step 1-10 sudah lengkap sebelum reset.

**Estimasi:** 1 hari

---

## Prioritas 7 — Eliminasi Duplikasi Kerjasama

**Lokasi:**
- `QuotationStepService::syncKerjasamaData()` (baris ~2392)
- `ProcessQuotationFinalization::syncKerjasamaData()` (baris ~109)

**Langkah:**
1. Pindahkan logic ke service class terpisah: `app/Services/KerjasamaService.php`
2. Panggil dari kedua tempat
3. Pastikan behaviour identik

**Estimasi:** 0.5 hari

---

## Prioritas 8 — Konsistensi Soft Delete

**Lokasi:** `QuotationStepService::softDeleteQuotationDetail()` (baris ~2468)

**Sekarang:** Ada yang pake `$model->delete()`, ada yang `DB::table(...)->update(['deleted_at' => now()])`.

**Perbaikan:** Standardisasi ke model `->delete()` agar model events tetap jalan.

**Estimasi:** 0.5 hari

---

## Prioritas 9 — Validasi Urutan Step

**Masalah:** Controller tidak memvalidasi urutan step. User bisa POST `updateStep(step=11)` padahal step 2-10 belum lengkap.

**Langkah:**
Di `QuotationStepController` atau `QuotationStepRequest`:
1. Ambil `$quotation->step` saat ini
2. Jika `$step > $quotation->step + 1`, return 422 dengan pesan step sebelumnya harus diisi

**Pengecualian:** Admin panel routes — mungkin tidak kena validasi ini.

**Estimasi:** 0.5 hari

---

## Ringkasan Timeline

| Prioritas | Item | Estimasi |
|-----------|------|----------|
| P0 | **Bersihkan QuotationStepResource** (termasuk sinkronisasi diff step 3, 4, 11, 12) | **1 hari** |
| P1 | Pecah QuotationStepService | 2-3 hari |
| P2 | Hapus dead code | 0.5 hari |
| P3 | Perbaiki N+1 | 0.5 hari |
| P4 | Cache hasil kalkulasi Step 11 | 0.5 hari |
| P5 | Bulk update HPP/COSS | 1 hari |
| P6 | Step 11 reset risk mitigation | 1 hari |
| P7 | Kerjasama duplication | 0.5 hari |
| P8 | Soft delete consistency | 0.5 hari |
| P9 | Step sequence validation | 0.5 hari |
| **Total** | | **~8-10 hari** |

---

## Catatan Penting

1. **Test dulu, baru refactor.** Tulis test coverage untuk tiap endpoint sebelum menyentuh.
2. **Step 12 bug di controller** (`$summary` undefined) — harus difix sebagai bagian dari Prioritas 0.
3. **Step 3 diff** — jika `requirements` dan `tunjangans` di step_data sudah tidak diperlukan frontend, boleh tidak ditambahkan. Tapi pastikan ini sudah dikomunikasikan.
4. **Step 11 diff (RO)** — pastikan frontend sudah siap dengan `coss` yang hilang untuk RO. Jika belum, samakan dulu behaviour-nya.
5. **Step 4 extra field** `management_fee_components` — pastikan tidak nge-break frontend.
