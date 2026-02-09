<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Quotation;
use App\Models\QuotationChemical;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\QuotationOhc;
use App\Models\BarangDefaultQty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuotationBarangService
{
    /**
     * Sync data barang dengan pola seragam - untuk semua jenis barang
     */
    public function syncBarangData($quotation, string $jenisBarang, array $barangData, array $config = []): array
    {
        try {
            DB::beginTransaction();

            $modelConfig = $this->getModelConfig($jenisBarang, $config);
            $modelClass = $modelConfig['model'];
            $jenisBarangIds = $modelConfig['jenis_barang_ids'];
            $useDetailId = $modelConfig['use_detail_id'];
            $useSiteId = $modelConfig['use_site_id'];

            // 1. FIRST, collect all incoming keys (barang_id + identifier)
            $incomingKeys = collect($barangData)
                ->map(function ($data) use ($useDetailId, $useSiteId) {
                    // Untuk custom barang tanpa barang_id, gunakan nama sebagai identifier
                    if (isset($data['is_custom']) && $data['is_custom'] && !isset($data['barang_id'])) {
                        $key = 'custom_' . md5($data['nama'] ?? 'unknown');
                    } else {
                        $key = (string) ($data['barang_id'] ?? 'unknown');
                    }

                    if ($useDetailId && isset($data['quotation_detail_id'])) {
                        $key .= '_detail_' . (int) $data['quotation_detail_id'];
                    }

                    if ($useSiteId && isset($data['quotation_site_id'])) {
                        $key .= '_site_' . (int) $data['quotation_site_id'];
                    }

                    return $key;
                })
                ->unique()
                ->toArray();

            // 2. SOFT DELETE only items that are NOT in the incoming data
            $deleteQuery = $modelClass::where('quotation_id', $quotation->id);

            // Jika ada data incoming, soft delete yang tidak ada
            if (!empty($incomingKeys)) {
                // Collect existing items to compare
                $existingItems = $modelClass::where('quotation_id', $quotation->id)->get();
                $itemsToDelete = [];

                foreach ($existingItems as $existing) {
                    $existingKey = $this->generateItemKey($existing, $useDetailId, $useSiteId);

                    if (!in_array($existingKey, $incomingKeys)) {
                        $itemsToDelete[] = $existing->id;
                    }
                }

                if (!empty($itemsToDelete)) {
                    $modelClass::whereIn('id', $itemsToDelete)
                        ->update([
                            'deleted_at' => now(),
                            'deleted_by' => Auth::user()->full_name
                        ]);
                }
            }



            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;

            // Load relasi yang diperlukan sebelum processing
            if ($useDetailId && !$quotation->relationLoaded('quotationDetails')) {
                $quotation->load('quotationDetails');
            }

            if ($useSiteId && !$quotation->relationLoaded('quotationSites')) {
                $quotation->load('quotationSites');
            }

            // 3. PROCESS each incoming item
            foreach ($barangData as $data) {
                $result = $this->processBarangItem($quotation, $jenisBarang, $data, $modelClass, $jenisBarangIds, $useDetailId, $useSiteId);

                if ($result['success']) {
                    if ($result['action'] === 'created') {
                        $createdCount++;
                    } else {
                        $updatedCount++;
                    }
                } else {
                    $skippedCount++;
                    \Log::warning("Skipped barang item", [
                        'quotation_id' => $quotation->id,
                        'jenis_barang' => $jenisBarang,
                        'data' => $data,
                        'reason' => $result['reason']
                    ]);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'jenis_barang' => $jenisBarang,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'deleted' => count($incomingKeys), // items that were soft deleted
                'skipped' => $skippedCount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error syncing barang data", [
                'quotation_id' => $quotation->id,
                'jenis_barang' => $jenisBarang,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Generate key for existing item to match with incoming keys
     */
    private function generateItemKey($item, bool $useDetailId, bool $useSiteId): string
    {
        // Untuk custom barang tanpa barang_id
        if (is_null($item->barang_id)) {
            $key = 'custom_' . md5($item->nama ?? 'unknown');
        } else {
            $key = (string) $item->barang_id;
        }

        if ($useDetailId && $item->quotation_detail_id) {
            $key .= '_detail_' . $item->quotation_detail_id;
        }

        if ($useSiteId && $item->quotation_site_id) {
            $key .= '_site_' . $item->quotation_site_id;
        }

        return $key;
    }

    /**
     * Process individual barang item
     */
    private function processBarangItem($quotation, string $jenisBarang, array $data, string $modelClass, array $jenisBarangIds, bool $useDetailId, bool $useSiteId): array
    {
        // Validasi data minimal
        if (!isset($data['jumlah'])) {
            return ['success' => false, 'reason' => 'missing_required_fields'];
        }

        $jumlah = (int) $data['jumlah'];

        // Skip jika jumlah 0 atau negatif
        if ($jumlah <= 0) {
            return ['success' => false, 'reason' => 'zero_quantity'];
        }

        // CASE 1: Barang custom
        if (isset($data['is_custom']) && $data['is_custom']) {
            // Validasi field yang diperlukan untuk barang custom
            if (!isset($data['nama']) || !isset($data['harga'])) {
                return ['success' => false, 'reason' => 'missing_custom_barang_fields'];
            }

            // Jika ada barang_id dari FE, gunakan itu. Jika tidak, set null
            $barang_id = isset($data['barang_id']) ? (int) $data['barang_id'] : null;
            $nama = $data['nama'];
            $jenis_barang_id = $data['jenis_barang_id'] ?? null;
            $jenis_barang = $data['jenis_barang'] ?? 'Custom';

            $harga = $data['harga'];
            if (is_string($harga)) {
                $harga = (float) str_replace(['.', ','], ['', '.'], $harga);
            } else {
                $harga = (float) $harga;
            }
        }
        // CASE 2: Barang dari tabel barang (existing logic)
        else {
            if (!isset($data['barang_id'])) {
                return ['success' => false, 'reason' => 'missing_barang_id'];
            }

            $barang_id = (int) $data['barang_id'];

            // Cari barang
            $barang = Barang::where('id', $barang_id)
                ->whereIn('jenis_barang_id', $jenisBarangIds)
                ->first();

            if (!$barang) {
                return ['success' => false, 'reason' => 'barang_not_found'];
            }

            $nama = $barang->nama;
            $jenis_barang_id = $barang->jenis_barang_id;
            $jenis_barang = $barang->jenis_barang;

            $harga = $barang->harga;
            if (isset($data['harga'])) {
                if (is_string($data['harga'])) {
                    $harga = (float) str_replace(['.', ','], ['', '.'], $data['harga']);
                } else {
                    $harga = (float) $data['harga'];
                }
            }
        }

        // Validasi identifier berdasarkan jenis barang
        $quotation_detail_id = null;
        $quotation_site_id = null;

        if ($useDetailId) {
            if (!isset($data['quotation_detail_id'])) {
                return ['success' => false, 'reason' => 'missing_quotation_detail_id'];
            }

            $quotation_detail_id = (int) $data['quotation_detail_id'];
            $detail = $quotation->quotationDetails->firstWhere('id', $quotation_detail_id);

            if (!$detail) {
                return ['success' => false, 'reason' => 'quotation_detail_not_found'];
            }
        }

        if ($useSiteId) {
            if (!isset($data['quotation_site_id'])) {
                return ['success' => false, 'reason' => 'missing_quotation_site_id'];
            }

            $quotation_site_id = (int) $data['quotation_site_id'];
            $site = $quotation->quotationSites->firstWhere('id', $quotation_site_id);

            if (!$site) {
                return ['success' => false, 'reason' => 'quotation_site_not_found'];
            }
        }

        // Siapkan data dasar terlebih dahulu
        $createData = [
            'quotation_id' => $quotation->id,
            'barang_id' => $barang_id,
            'jumlah' => $jumlah,
            'harga' => $harga,
            'nama' => $nama,
            'jenis_barang_id' => $jenis_barang_id,
            'jenis_barang' => $jenis_barang,
            'updated_by' => Auth::user()->full_name
        ];

        // Handle masa pakai berdasarkan jenis barang
        if ($jenisBarang === 'chemicals') {
            // Untuk chemicals, ambil dari request atau gunakan default 12
            $masa_pakai = isset($data['masa_pakai']) && $data['masa_pakai'] > 0
                ? (int) $data['masa_pakai']
                : 12;

            \Log::info('Chemical masa_pakai', [
                'barang_id' => $barang_id ?? 'custom',
                'nama' => $nama,
                'masa_pakai_from_request' => $data['masa_pakai'] ?? 'not_set',
                'masa_pakai_final' => $masa_pakai
            ]);
        } else {
            // Untuk non-chemical, gunakan 1
            $masa_pakai = 1;
        }

        // Tambahkan masa_pakai ke createData
        $createData['masa_pakai'] = $masa_pakai;

        if ($useDetailId) {
            $createData['quotation_detail_id'] = $quotation_detail_id;
        }

        if ($useSiteId) {
            $createData['quotation_site_id'] = $quotation_site_id;
        }

        // Cari data existing untuk update
        $existingQuery = $modelClass::where('quotation_id', $quotation->id);

        // Untuk custom barang, cari berdasarkan barang_id (jika ada) atau nama
        if (isset($data['is_custom']) && $data['is_custom']) {
            if ($barang_id !== null) {
                $existingQuery->where('barang_id', $barang_id);
            } else {
                $existingQuery->where('nama', $nama)->whereNull('barang_id');
            }
        } else {
            $existingQuery->where('barang_id', $barang_id);
        }

        if ($useDetailId) {
            $existingQuery->where('quotation_detail_id', $quotation_detail_id);
        }

        if ($useSiteId) {
            $existingQuery->where('quotation_site_id', $quotation_site_id);
        }

        $existing = $existingQuery->first();

        if ($existing) {
            // UPDATE data existing
            $existing->update($createData);
            return ['success' => true, 'action' => 'updated'];
        } else {
            // CREATE data baru
            $createData['created_by'] = Auth::user()->full_name;
            $modelClass::create($createData);
            return ['success' => true, 'action' => 'created'];
        }
    }

    /**
     * Get model configuration for different barang types
     */
    private function getModelConfig(string $jenisBarang, array $config = []): array
    {
        $defaultConfig = [
            'chemicals' => [
                'model' => QuotationChemical::class,
                'jenis_barang_ids' => [13, 14, 15, 16, 18, 19],
                'use_detail_id' => false,   // Tidak menggunakan detail_id
                'use_site_id' => true,      // Menggunakan site_id
                'default_masa_pakai' => 12
            ],
            'kaporlap' => [
                'model' => QuotationKaporlap::class,
                'jenis_barang_ids' => [1, 2, 3, 4, 5],
                'use_detail_id' => true,    // Tetap menggunakan detail_id
                'use_site_id' => false,     // Tidak menggunakan site_id
                'default_masa_pakai' => 12
            ],
            'devices' => [
                'model' => QuotationDevices::class,
                'jenis_barang_ids' => [8, 9, 10, 11, 12, 17],
                'use_detail_id' => false,   // Tidak menggunakan detail_id
                'use_site_id' => true,      // Menggunakan site_id
                'default_masa_pakai' => 12
            ],
            'ohc' => [
                'model' => QuotationOhc::class,
                'jenis_barang_ids' => [6, 7, 8],
                'use_detail_id' => false,   // Tidak menggunakan detail_id
                'use_site_id' => true,      // Menggunakan site_id
                'default_masa_pakai' => 12
            ]
        ];

        $config = array_merge($defaultConfig[$jenisBarang] ?? $defaultConfig['chemicals'], $config);

        if (isset($config['custom_jenis_barang_ids'])) {
            $config['jenis_barang_ids'] = $config['custom_jenis_barang_ids'];
        }

        return $config;
    }

    /**
     * Get default masa pakai based on barang type
     */
    private function getDefaultMasaPakai(string $jenisBarang): int
    {
        $defaults = [
            'chemicals' => 12,
            'kaporlap' => 12,
            'devices' => 24,
            'ohc' => 12
        ];

        return $defaults[$jenisBarang] ?? 12;
    }

    /**
     * Process legacy format (dynamic field names) untuk backward compatibility
     * Diubah untuk mendukung kedua format (detail_id dan site_id)
     */
    public function processLegacyFormat($quotation, Request $request, string $jenisBarang): array
    {
        $modelConfig = $this->getModelConfig($jenisBarang);
        $useDetailId = $modelConfig['use_detail_id'];
        $useSiteId = $modelConfig['use_site_id'];

        $barangData = [];

        foreach ($request->all() as $fieldName => $jumlah) {
            if (strpos($fieldName, 'jumlah_') !== 0 || is_null($jumlah)) {
                continue;
            }

            $parts = explode('_', $fieldName);

            // Format: jumlah_barangId_detailId (untuk kaporlap)
            if ($useDetailId && count($parts) >= 3) {
                $barang_id = (int) $parts[1];
                $quotation_detail_id = (int) $parts[2];

                $barangData[] = [
                    'barang_id' => $barang_id,
                    'quotation_detail_id' => $quotation_detail_id,
                    'jumlah' => (int) $jumlah
                ];
            }
            // Format: jumlah_barangId_siteId (untuk chemicals, devices, ohc)
            elseif ($useSiteId && count($parts) >= 3) {
                $barang_id = (int) $parts[1];
                $quotation_site_id = (int) $parts[2];

                $barangData[] = [
                    'barang_id' => $barang_id,
                    'quotation_site_id' => $quotation_site_id,
                    'jumlah' => (int) $jumlah
                ];
            }
            // Format lama tanpa identifier
            elseif (count($parts) >= 2) {
                $barang_id = (int) $parts[1];

                $barangData[] = [
                    'barang_id' => $barang_id,
                    'jumlah' => (int) $jumlah
                ];
            }
        }

        return $barangData;
    }

    /**
     * Prepare data for display
     */
    public function prepareBarangData($quotation, string $jenisBarang)
    {
        $modelConfig = $this->getModelConfig($jenisBarang);
        $relationName = $this->getRelationName($jenisBarang);
        $useDetailId = $modelConfig['use_detail_id'];
        $useSiteId = $modelConfig['use_site_id'];

        $barangData = [];
        $totalAll = 0;
        $jumlah_item = 0;

        // Load relasi yang diperlukan berdasarkan jenis barang
        if ($useDetailId && !$quotation->relationLoaded('quotationDetails')) {
            $quotation->load('quotationDetails');
        }

        if ($useSiteId && !$quotation->relationLoaded('quotationSites')) {
            $quotation->load('quotationSites');
        }

        // Buat mapping data berdasarkan kebutuhan
        $quotationDetailsMap = [];
        if ($useDetailId && $quotation->relationLoaded('quotationDetails')) {
            foreach ($quotation->quotationDetails as $detail) {
                $quotationDetailsMap[$detail->id] = [
                    'jabatan_kebutuhan' => $detail->jabatan_kebutuhan,
                    'quotation_site_id' => $detail->quotation_site_id,
                    'jumlah_hc' => $detail->jumlah_hc,
                    'nama_site' => $detail->nama_site,
                    'position_id' => $detail->position_id
                ];
            }
        }

        $quotationSitesMap = [];
        if ($useSiteId && $quotation->relationLoaded('quotationSites')) {
            foreach ($quotation->quotationSites as $site) {
                $quotationSitesMap[$site->id] = [
                    'leads_id' => $site->leads_id,
                    'nama_site' => $site->nama_site,
                ];
            }
        }

        // Cek apakah relasi barang dimuat
        if (!$quotation->relationLoaded($relationName)) {
            return [
                'data' => [],
                'total' => [
                    'jumlah_item' => 0,
                    'total_all' => 0,
                    'total_formatted' => "Rp 0",
                ]
            ];
        }

        $items = $quotation->$relationName;

        foreach ($items as $item) {
            // HANYA chemical yang menggunakan masa_pakai dalam perhitungan
            if ($jenisBarang === 'chemicals') {
                $masa_pakai = (int) $item->masa_pakai;
                if ($masa_pakai <= 0) {
                    $masa_pakai = 1;
                }

                $jumlah_pertahun = (int) $item->jumlah / $masa_pakai * 12;
                $total_per_item = $item->harga * $item->jumlah / $masa_pakai;

                $itemData = [
                    'id' => $item->id,
                    'barang_id' => $item->barang_id,
                    'jumlah' => $item->jumlah,
                    'harga' => $item->harga,
                    'masa_pakai' => $item->masa_pakai,
                    'masa_pakai_formatted' => $item->masa_pakai . " Bulan",
                    'jumlah_pertahun' => $jumlah_pertahun,
                    'total_per_item' => $total_per_item,
                    'jenis_barang_id' => $item->jenis_barang_id,
                    'jenis_barang' => $item->jenis_barang,
                    'nama' => $item->nama,
                ];

                $totalAll += $total_per_item;
            } else {
                // UNTUK NON-CHEMICAL
                $total_per_item = $item->harga * $item->jumlah;

                $itemData = [
                    'id' => $item->id,
                    'barang_id' => $item->barang_id,
                    'jumlah' => $item->jumlah,
                    'harga' => $item->harga,
                    'jenis_barang_id' => $item->jenis_barang_id,
                    'jenis_barang' => $item->jenis_barang,
                    'nama' => $item->nama,
                    'total_per_item' => $total_per_item,
                ];

                $totalAll += $total_per_item;
            }

            // LOGIC untuk KAPORLAP (menggunakan detail_id)
            if ($useDetailId && isset($item->quotation_detail_id) && $item->quotation_detail_id) {
                $detailId = $item->quotation_detail_id;

                if (isset($quotationDetailsMap[$detailId])) {
                    $detailData = $quotationDetailsMap[$detailId];
                    $itemData['jabatan_kebutuhan'] = $detailData['jabatan_kebutuhan'];
                    $itemData['jumlah_hc'] = $detailData['jumlah_hc'];
                    $itemData['nama_site'] = $detailData['nama_site'];
                    $itemData['position_id'] = $detailData['position_id'];
                    $itemData['quotation_detail_id'] = $detailId;

                    // Jika ada quotation_site_id dari detail, tambahkan info site
                    if ($detailData['quotation_site_id'] && isset($quotationSitesMap[$detailData['quotation_site_id']])) {
                        $siteData = $quotationSitesMap[$detailData['quotation_site_id']];
                        $itemData['leads_id'] = $siteData['leads_id'];
                        $itemData['nama_site'] = $siteData['nama_site'] ?? $detailData['nama_site'];
                        $itemData['provinsi'] = $siteData['provinsi'];
                        $itemData['kota'] = $siteData['kota'];
                        $itemData['quotation_site_id'] = $detailData['quotation_site_id'];
                    }
                } else {
                    $itemData['jabatan_kebutuhan'] = null;
                    $itemData['jumlah_hc'] = null;
                    $itemData['nama_site'] = null;
                    $itemData['position_id'] = null;
                    $itemData['quotation_detail_id'] = $detailId;
                }
            }
            // LOGIC untuk CHEMICALS, DEVICES, OHC (menggunakan site_id)
            elseif ($useSiteId && isset($item->quotation_site_id) && $item->quotation_site_id) {
                $siteId = $item->quotation_site_id;

                if (isset($quotationSitesMap[$siteId])) {
                    $siteData = $quotationSitesMap[$siteId];
                    $itemData['leads_id'] = $siteData['leads_id'];
                    $itemData['nama_site'] = $siteData['nama_site'];
                    $itemData['quotation_site_id'] = $siteId;
                } else {
                    $itemData['leads_id'] = null;
                    $itemData['nama_site'] = null;
                    $itemData['quotation_site_id'] = $siteId;
                }
            }
            // Tidak ada identifier
            else {
                if ($useSiteId) {
                    $itemData['leads_id'] = null;
                    $itemData['nama_site'] = null;
                    $itemData['quotation_site_id'] = null;
                }

                if ($useDetailId) {
                    $itemData['jabatan_kebutuhan'] = null;
                    $itemData['jumlah_hc'] = null;
                    $itemData['nama_site'] = null;
                    $itemData['position_id'] = null;
                    $itemData['quotation_detail_id'] = null;
                }
            }

            $jumlah_item += $item->jumlah;
            $barangData[] = $itemData;
        }

        return [
            'data' => $barangData,
            'total' => [
                'jumlah_item' => $jumlah_item,
                'total_all' => $totalAll,
                'total_formatted' => "Rp " . number_format($totalAll, 0, ",", "."),
            ]
        ];
    }

    /**
     * Get relation name for different barang types
     */
    private function getRelationName(string $jenisBarang): string
    {
        $relations = [
            'chemicals' => 'quotationChemicals',
            'kaporlap' => 'quotationKaporlaps',
            'devices' => 'quotationDevices',
            'ohc' => 'quotationOhcs'
        ];

        return $relations[$jenisBarang] ?? 'quotationChemicals';
    }

    /**
     * Get default quantity for barang based on layanan
     */
    public function getDefaultQty($barang_id, $layanan_id)
    {
        $qtyDefault = BarangDefaultQty::where('layanan_id', $layanan_id)
            ->where('barang_id', $barang_id)
            ->first();

        return $qtyDefault ? $qtyDefault->qty_default : 0;
    }
}