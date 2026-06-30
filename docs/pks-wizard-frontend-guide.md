# PKS Wizard Frontend Guide

Dokumen ini menjelaskan seluruh endpoint `PKS Wizard` yang saat ini tersedia di backend, alur pemakaian dari awal sampai finalize, serta arti setiap field request body yang perlu dipakai frontend.

Dokumen ini dibuat berdasarkan implementasi backend saat ini, bukan sekadar plan. Jadi isi di bawah mengikuti route, request validation, dan behavior API yang benar-benar aktif.

## Ringkasan Flow

Flow umum frontend:

1. Panggil `initialize` untuk membuat draft PKS wizard dan mendapatkan `pksId`.
2. Ambil data step dengan `GET /step/{step}`.
3. Simpan data per step dengan `POST /step/{step}`.
4. Pada step pasal, generate preview pasal dengan `POST /preview-pasal`.
5. Jika user mengedit isi pasal, simpan dengan `PUT /preview-pasal/{pasalKey}`.
6. Setelah semua step selesai, panggil `POST /finalize`.

## Base Info

- Base path: `/api/pks-wizard`
- Auth: semua endpoint butuh auth route group yang sama dengan endpoint API lain
- Response shape utama:

```json
{
  "success": true,
  "data": {},
  "message": "..."
}
```

- Validation error request class memakai format:

```json
{
  "message": {
    "field": ["error message"]
  }
}
```

## Tipe PKS

Nilai `tipe` yang didukung pada wizard:

- `baru`
- `rekontrak`
- `addendum`

Perbedaan penting:

- `baru`
: sumber utama site berasal dari `SPK Site`, dan `spk_id` wajib saat initialize.

- `rekontrak`
: sumber utama site berasal dari `Quotation Site`, dan `quotation_id` wajib saat initialize.

- `addendum`
: tidak membuat site baru saat finalize, wajib memiliki `pks_induk_id`, dan preview pasal bisa diisi manual lewat `additional_articles`.

## Status Wizard

Nilai `wizard_status_id`:

- `1` = Initialized
- `2` = In Progress
- `3` = Ready To Finalize
- `4` = Finalized
- `5` = Cancelled

Catatan untuk frontend:

- PKS dengan status `1`, `2`, `3` masih dianggap draft wizard.
- Endpoint approval, upload, dan activate pada modul PKS lama akan ditolak jika `wizard_status_id != 4`.

## Endpoint 1: Initialize PKS Wizard

**Endpoint**

```text
POST /api/pks-wizard/initialize/{tipe}
```

`{tipe}` harus salah satu dari `baru`, `rekontrak`, atau `addendum`.

### Tujuan

- Membuat row draft di `sl_pks`
- Menghasilkan `pksId`
- Menyimpan source awal ke `wizard_payload.source`
- Mengisi nomor draft dengan prefix `draft/`

### Request Body: Tipe `baru`

```json
{
  "leads_id": 123,
  "company_id": 13,
  "quotation_id": 456,
  "spk_id": 789
}
```

### Field Description: Tipe `baru`

- `leads_id`
: ID leads yang menjadi sumber utama PKS. Wajib. Harus valid di `sl_leads`.

- `company_id`
: ID entitas/perusahaan Shelter yang akan dipakai untuk PKS dan template pasal. Wajib. Harus valid di `mysqlhris.m_company`.

- `quotation_id`
: ID quotation terkait. Optional pada `baru`, tetapi disarankan diisi jika memang sumber quotation sudah ada.

- `spk_id`
: ID SPK sumber. Wajib untuk `baru`. Harus valid di `sl_spk`.

### Request Body: Tipe `rekontrak`

```json
{
  "leads_id": 123,
  "company_id": 13,
  "quotation_id": 456,
  "spk_id": null
}
```

### Field Description: Tipe `rekontrak`

- `leads_id`
: ID leads sumber. Wajib.

- `company_id`
: ID company/template yang dipakai di PKS. Wajib.

- `quotation_id`
: ID quotation sumber rekontrak. Wajib.

- `spk_id`
: Tidak wajib untuk rekontrak. Bisa `null`.

### Request Body: Tipe `addendum`

```json
{
  "leads_id": 123,
  "pks_induk_id": 22,
  "quotation_id": 456,
  "company_id": 13
}
```

### Field Description: Tipe `addendum`

- `leads_id`
: ID leads sumber. Wajib.

- `pks_induk_id`
: ID PKS induk yang akan ditambahi addendum. Wajib. Harus valid di `sl_pks`.

- `quotation_id`
: Optional. Jika ada quotation addendum yang terkait, boleh diisi.

- `company_id`
: Optional. Jika tidak diisi, backend akan mencoba memakai `company_id` dari PKS induk.

### Response Sukses

```json
{
  "success": true,
  "data": {
    "pks_id": 99,
    "nomor": "draft/PKS/SIG/LDS001-062026-00001",
    "wizard_status_id": 1,
    "wizard_current_step": 1
  },
  "message": "PKS wizard initialized successfully"
}
```

### Frontend Note

- Simpan `pks_id` hasil initialize.
- Semua endpoint step, preview, dan finalize berikutnya memakai `pks_id` ini.

## Endpoint 2: Get Step Data

**Endpoint**

```text
GET /api/pks-wizard/{pksId}/step/{step}
```

### Tujuan

- Mengambil data step yang sudah tersimpan
- Mengambil `additional_data` yang dibutuhkan frontend untuk render form, pilihan dropdown, atau informasi read-only

### Path Param

- `pksId`
: ID draft PKS wizard.

- `step`
: Nomor step. Saat ini valid `1` sampai `7`.

### Response Shape

```json
{
  "success": true,
  "data": {
    "pks_id": 99,
    "step": 2,
    "wizard_status_id": 2,
    "wizard_status": "In Progress",
    "wizard_current_step": 3,
    "wizard_completed_steps": [1, 2],
    "nomor": "draft/PKS/SIG/LDS001-062026-00001",
    "tipe_pks": "baru",
    "step_data": {},
    "additional_data": {}
  },
  "message": "Step data retrieved successfully"
}
```

### Penjelasan Field Response

- `step_data`
: data yang sebelumnya disimpan khusus untuk step itu.

- `additional_data`
: data tambahan dari backend untuk bantu frontend render UI.

### Additional Data per Step

#### Step 1

- `source_summary`
: ringkasan source yang disimpan saat initialize.

- `available_leads`
: daftar leads yang tersedia sesuai akses user.

- `available_sites`
: daftar site kandidat jika tipe memungkinkan.

- `is_read_only`
: source step bersifat read-only.

#### Step 2

- `company_options`
: pilihan entitas/company.

- `salary_rule_options`
: pilihan salary rule.

- `rule_thr_options`
: pilihan rule THR.

- `kategori_hc_options`
: pilihan kategori HC.

- `loyalty_options`
: pilihan loyalty.

- `readonly_source_fields`
: field dari source yang tidak boleh diedit di wizard.

#### Step 3

- `available_sites`
: daftar site yang valid untuk dipilih.

- `selection_mode`
: memberi tahu frontend field mana yang dipakai.
Nilai bisa:
`site_ids`, `quotation_site_ids`, atau `skipped`.

- `is_read_only`
: `true` untuk addendum, karena addendum tidak membuat site baru.

#### Step 4

- `contact_defaults`
: default PIC dari data leads.

#### Step 5

- `commercial_snapshot`
: snapshot read-only hasil quotation calculation.

- `is_read_only`
: selalu `true`.

#### Step 6

- `template_payload`
: payload yang menjadi basis template pasal.

- `pasal_preview_payload`
: preview pasal yang sudah pernah digenerate, jika ada.

- `preview_status`
: hint dari backend untuk frontend.

#### Step 7

- `review_summary`
: rangkuman payload wizard, template, dan preview pasal.

## Endpoint 3: Update Step Data

**Endpoint**

```text
POST /api/pks-wizard/{pksId}/step/{step}
```

### Tujuan

- Menyimpan payload per step ke `wizard_payload`
- Mengupdate `wizard_current_step`
- Mengupdate `wizard_completed_steps`
- Mengubah status wizard menjadi `in_progress` atau `ready_to_finalize`

### Request Field Umum

- `mark_as_complete`
: boolean. Jika `true`, backend menandai step ini selesai.

- `step_data`
: object payload step yang sedang disimpan.

## Detail Step Request

### Step 1: Review Sumber PKS

**Contoh Body**

```json
{
  "mark_as_complete": true,
  "step_data": {
    "confirmed": true,
    "notes": "Source reviewed and accepted"
  }
}
```

**Field**

- `confirmed`
: boolean penanda bahwa user sudah mengonfirmasi source.

- `notes`
: catatan opsional dari user/frontend. Maksimal 1000 karakter.

### Step 2: Data Header PKS

**Contoh Body**

```json
{
  "mark_as_complete": true,
  "step_data": {
    "tanggal_pks": "2026-07-01",
    "tanggal_awal_kontrak": "2026-07-01",
    "tanggal_akhir_kontrak": "2027-06-30",
    "company_id": 13,
    "salary_rule_id": 1,
    "rule_thr_id": 2,
    "kategori_sesuai_hc_id": 1,
    "loyalty_id": 1
  }
}
```

**Field**

- `tanggal_pks`
: tanggal dokumen PKS. Format `Y-m-d`. Wajib.

- `tanggal_awal_kontrak`
: tanggal mulai kontrak. Format `Y-m-d`. Wajib.

- `tanggal_akhir_kontrak`
: tanggal akhir kontrak. Format `Y-m-d`. Wajib. Harus `>= tanggal_awal_kontrak`.

- `company_id`
: entitas/perusahaan yang akan dipakai di PKS dan template. Wajib.

- `salary_rule_id`
: salary rule yang dipakai untuk schedule invoice/payroll. Wajib.

- `rule_thr_id`
: rule THR yang dipakai template. Wajib.

- `kategori_sesuai_hc_id`
: kategori HC. Optional.

- `loyalty_id`
: loyalty. Optional.

### Step 3: Pilih Site

#### Untuk `baru`

**Contoh Body**

```json
{
  "mark_as_complete": true,
  "step_data": {
    "site_ids": [11, 12]
  }
}
```

**Field**

- `site_ids`
: array ID dari `sl_spk_site`. Wajib minimal 1 item untuk tipe `baru`.

#### Untuk `rekontrak`

**Contoh Body**

```json
{
  "mark_as_complete": true,
  "step_data": {
    "quotation_site_ids": [21, 22]
  }
}
```

**Field**

- `quotation_site_ids`
: array ID dari `sl_quotation_site`. Wajib minimal 1 item untuk tipe `rekontrak`.

#### Untuk `addendum`

**Contoh Body**

```json
{
  "mark_as_complete": true,
  "step_data": {
    "skipped": true,
    "reason": "Addendum does not create new sites"
  }
}
```

**Field**

- `skipped`
: harus `true` untuk addendum.

- `reason`
: alasan skip step site. Optional.

### Step 4: PIC, Kontak, dan Operasional

**Contoh Body**

```json
{
  "mark_as_complete": true,
  "step_data": {
    "pic_1": "Budi Santoso",
    "jabatan_pic_1": "Procurement Manager",
    "email_pic_1": "budi@example.com",
    "telp_pic_1": "081234567890",
    "pic_2": "Siti Aminah",
    "jabatan_pic_2": "Finance Supervisor",
    "email_pic_2": "siti@example.com",
    "telp_pic_2": "081298765432"
  }
}
```

**Field**

- `pic_1`
: nama PIC utama. Wajib.

- `jabatan_pic_1`
: jabatan PIC utama. Optional.

- `email_pic_1`
: email PIC utama. Optional, tapi harus format email valid jika diisi.

- `telp_pic_1`
: nomor telepon PIC utama. Optional.

- `pic_2`, `jabatan_pic_2`, `email_pic_2`, `telp_pic_2`
: data PIC kedua. Optional.

- `pic_3`, `jabatan_pic_3`, `email_pic_3`, `telp_pic_3`
: data PIC ketiga. Optional.

### Step 5: Komersial dan Penagihan

**Contoh Body**

```json
{
  "mark_as_complete": true,
  "step_data": {
    "total_sebelum_pajak": 10000000,
    "dasar_pengenaan_pajak": 10000000,
    "ppn": 1100000,
    "pph": 200000,
    "total_invoice": 10900000,
    "persen_mf": 12,
    "nominal_mf": 1200000,
    "persen_bpjs_tk": 5.54,
    "nominal_bpjs_tk": 554000,
    "persen_bpjs_ks": 4,
    "nominal_bpjs_ks": 400000,
    "tgl_kirim_invoice": "25",
    "jumlah_hari_top": 30,
    "tipe_hari_top": "calendar",
    "tgl_gaji": "28"
  }
}
```

**Catatan Penting**

- Step 5 bersifat read-only di backend.
- Body di atas ada sebagai referensi contoh/fallback testing, tetapi backend saat ini akan membangun snapshot komersial dari quotation calculation saat step ini disimpan.
- Frontend sebaiknya menganggap step ini non-editable.

### Step 6: Template Input / Pasal Draft Context

**Contoh Body**

```json
{
  "mark_as_complete": true,
  "step_data": {
    "regenerate_preview": false,
    "manual_notes": "Initial template inputs saved, then call preview endpoint"
  }
}
```

**Field**

- `regenerate_preview`
: boolean penanda dari frontend apakah user berniat regenerate preview setelah perubahan sebelumnya.

- `manual_notes`
: catatan opsional internal frontend/user. Maksimal 1000 karakter.

### Step 7: Review

**Contoh Body**

```json
{
  "mark_as_complete": true,
  "step_data": {
    "ready_for_finalize": true,
    "review_notes": "All current steps have been reviewed"
  }
}
```

**Field**

- `ready_for_finalize`
: boolean penanda bahwa frontend/user sudah siap finalize.

- `review_notes`
: catatan review. Optional.

## Endpoint 4: Generate Preview Pasal

**Endpoint**

```text
POST /api/pks-wizard/{pksId}/preview-pasal
```

### Tujuan

- Untuk `baru` dan `rekontrak`
: generate preview pasal dari template existing berdasarkan `template_payload`.

- Untuk `addendum`
: bisa generate preview dari input `additional_articles`.

### Skenario A: Generate Template Preview Biasa

**Contoh Body**

```json
{
  "regenerate": true
}
```

**Field**

- `regenerate`
: kalau `true`, backend regenerate preview dari template saat ini.

### Skenario B: Generate Preview Addendum Manual

**Contoh Body**

```json
{
  "regenerate": true,
  "additional_articles": [
    {
      "pasal": "Addendum 1",
      "judul": "PENAMBAHAN RUANG LINGKUP",
      "raw_text": "<p>PIHAK PERTAMA dan PIHAK KEDUA sepakat menambahkan ruang lingkup pekerjaan untuk site baru.</p>"
    },
    {
      "pasal": "Addendum 2",
      "judul": "PENYESUAIAN NILAI",
      "raw_text": "<p>Nilai tagihan mengikuti quotation addendum yang disetujui.</p>"
    }
  ]
}
```

### Field

- `additional_articles`
: array pasal tambahan untuk addendum.

- `additional_articles[].pasal`
: label pasal/addendum. Wajib.

- `additional_articles[].judul`
: judul section. Wajib.

- `additional_articles[].raw_text`
: isi HTML/text pasal. Wajib.

### Response

Response `data` adalah array preview section seperti:

```json
[
  {
    "key": "section_0",
    "pasal": "Pasal 1",
    "judul": "RUANG LINGKUP PERJANJIAN",
    "raw_text": "<p>...</p>",
    "is_edited": false
  }
]
```

### Field Response Preview

- `key`
: ID section yang dipakai untuk endpoint update preview. Simpan ini di frontend.

- `pasal`
: nama pasal.

- `judul`
: judul pasal.

- `raw_text`
: isi HTML pasal.

- `is_edited`
: apakah section pernah diedit manual.

## Endpoint 5: Update Preview Pasal

**Endpoint**

```text
PUT /api/pks-wizard/{pksId}/preview-pasal/{pasalKey}
```

### Tujuan

- Menyimpan hasil edit raw text per section preview sebelum finalize.

### Path Param

- `pasalKey`
: key section dari response preview, misalnya `section_0`, `section_1`, dan seterusnya.

### Contoh Body

```json
{
  "pasal": "Pasal 1",
  "judul": "RUANG LINGKUP PERJANJIAN",
  "raw_text": "<p>Isi pasal hasil edit manual user sebelum finalize.</p>"
}
```

### Field

- `pasal`
: optional. Kalau ingin update label pasal.

- `judul`
: optional. Kalau ingin update judul.

- `raw_text`
: wajib. Isi HTML terbaru setelah diedit.

### Frontend Note

- Setelah user edit di rich text editor, kirim `raw_text` terbaru ke endpoint ini.
- Backend akan menandai section tersebut sebagai `is_edited = true`.

## Endpoint 6: Finalize PKS Wizard

**Endpoint**

```text
POST /api/pks-wizard/{pksId}/finalize
```

### Tujuan

- Mengubah PKS draft wizard menjadi finalized
- Menjalankan seluruh side effect final

### Contoh Body

```json
{
  "confirm_finalize": true
}
```

### Field

- `confirm_finalize`
: boolean konfirmasi dari frontend/user bahwa finalize memang dijalankan.

### Apa yang dilakukan backend saat finalize

- remove prefix `draft/` dari nomor PKS
- simpan header final ke `sl_pks`
- set `wizard_status_id = 4`
- set `finalized_at`
- generate `sl_site` final untuk `baru` dan `rekontrak`
- skip generate site untuk `addendum`
- insert `sl_pks_perjanjian` dari `pasal_preview_payload`
- update status SPK
- update status quotation
- update status leads
- create activity final

### Response Contoh

```json
{
  "success": true,
  "data": {
    "id": 99,
    "nomor": "PKS/SIG/LDS001-062026-00001",
    "wizard_status_id": 4,
    "finalized_at": "2026-06-30T12:00:00.000000Z",
    "sites_count": 2,
    "perjanjian_count": 10
  },
  "message": "PKS wizard finalized successfully"
}
```

## Error Cases yang Perlu Diperhatikan Frontend

### 1. Step belum selesai saat finalize

Contoh message:

```json
{
  "success": false,
  "message": "Step 6 belum selesai"
}
```

Frontend sebaiknya arahkan user balik ke step terkait.

### 2. Pasal preview belum tersedia

Contoh message:

```json
{
  "success": false,
  "message": "Pasal preview belum tersedia"
}
```

Frontend harus pastikan endpoint generate preview sudah dipanggil.

### 3. PKS wizard belum finalized di endpoint PKS lama

Jika frontend salah memanggil approval/upload/activate terlalu cepat, backend akan balikin:

```json
{
  "success": false,
  "message": "PKS wizard belum finalized"
}
```

### 4. Invalid step

```json
{
  "success": false,
  "message": "Step wizard tidak valid"
}
```

## Urutan Implementasi Frontend yang Disarankan

### Flow `baru`

1. `POST /initialize/baru`
2. `GET /step/1`
3. `POST /step/1`
4. `GET /step/2`
5. `POST /step/2`
6. `GET /step/3`
7. `POST /step/3` dengan `site_ids`
8. `POST /step/4`
9. `GET /step/5`
10. `POST /step/5`
11. `POST /step/6`
12. `POST /preview-pasal`
13. `PUT /preview-pasal/{pasalKey}` jika user edit pasal
14. `POST /step/7`
15. `POST /finalize`

### Flow `rekontrak`

Sama seperti `baru`, tetapi step 3 memakai `quotation_site_ids`.

### Flow `addendum`

1. `POST /initialize/addendum`
2. `POST /step/1`
3. `POST /step/2`
4. skip step site secara payload dengan `skipped=true`
5. `POST /step/4`
6. `POST /step/5`
7. `POST /step/6`
8. `POST /preview-pasal` dengan `additional_articles`
9. edit preview jika perlu
10. `POST /step/7`
11. `POST /finalize`

## File Referensi Contoh Payload

Untuk payload contoh yang sinkron dengan backend, lihat juga:

- `tests/Fixtures/pks-wizard-request-bodies.json`

File itu cocok dipakai sebagai:

- sumber example frontend
- data uji manual
- dasar pembuatan automated test di frontend atau backend

## Catatan Implementasi Penting

- Step 5 harus dianggap read-only oleh frontend.
- Frontend jangan membuat asumsi bahwa semua data sudah ada di step initialize; beberapa field final baru valid setelah step 2.
- `pasalKey` untuk endpoint update preview harus diambil dari response generate preview, jangan dibuat sendiri.
- PKS wizard draft tetap bisa muncul di list PKS, jadi frontend management perlu siap menampilkan status wizard dan progress-nya.
- Endpoint approval/upload/activate baru boleh dipakai setelah finalize sukses.
