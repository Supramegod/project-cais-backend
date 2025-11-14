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

            // Hapus semua data existing untuk quotation ini dan jenis barang ini
            $deletedCount = $modelClass::where('quotation_id', $quotation->id)->delete();

            $createdCount = 0;
            $skippedCount = 0;

            // Insert data baru hanya untuk jumlah > 0
            foreach ($barangData as $data) {
                $result = $this->processBarangItem($quotation, $jenisBarang, $data, $modelClass, $jenisBarangIds, $useDetailId);

                if ($result['success']) {
                    $createdCount++;
                } else {
                    $skippedCount++;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'jenis_barang' => $jenisBarang,
                'created' => $createdCount,
                'deleted' => $deletedCount,
                'skipped' => $skippedCount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    /**
     * Process individual barang item
     */
    private function processBarangItem($quotation, string $jenisBarang, array $data, string $modelClass, array $jenisBarangIds, bool $useDetailId): array
    {
        // Validasi data minimal
        if (!isset($data['barang_id']) || !isset($data['jumlah'])) {
            return ['success' => false, 'reason' => 'missing_required_fields'];
        }

        $barang_id = (int) $data['barang_id'];
        $jumlah = (int) $data['jumlah'];

        // Skip jika jumlah 0 atau negatif
        if ($jumlah <= 0) {
            return ['success' => false, 'reason' => 'zero_quantity'];
        }

        // Cari barang
        $barang = Barang::where('id', $barang_id)
            ->whereIn('jenis_barang_id', $jenisBarangIds)
            ->first();

        if (!$barang) {
            return ['success' => false, 'reason' => 'barang_not_found'];
        }

        // Validasi quotation_detail_id jika diperlukan
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

        // Handle masa pakai - hanya untuk chemical
        $masa_pakai = 1;
        if ($jenisBarang === 'chemicals') {
            $masa_pakai = isset($data['masa_pakai']) ? (int) $data['masa_pakai'] : ($barang->masa_pakai ?? 12);
            if ($masa_pakai <= 0) {
                $masa_pakai = 12;
            }
        }

        $harga = $barang->harga;
        if (isset($data['harga'])) {
            if (is_string($data['harga'])) {
                $harga = (float) str_replace(['.', ','], ['', '.'], $data['harga']);
            } else {
                $harga = (float) $data['harga'];
            }
        }

        $createData = [
            'quotation_id' => $quotation->id,
            'barang_id' => $barang_id,
            'jumlah' => $jumlah,
            'harga' => $harga,
            'nama' => $barang->nama,
            'jenis_barang_id' => $barang->jenis_barang_id,
            'jenis_barang' => $barang->jenis_barang,
            'masa_pakai' => $masa_pakai,
            'created_by' => Auth::user()->full_name
        ];

        if ($useDetailId) {
            $createData['quotation_detail_id'] = $quotation_detail_id;
        }

        $modelClass::create($createData);

        return ['success' => true];
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
                'use_detail_id' => true,
                'default_masa_pakai' => 12
            ],
            'kaporlap' => [
                'model' => QuotationKaporlap::class,
                'jenis_barang_ids' => [1, 2, 3, 4, 5],
                'use_detail_id' => true,
                'default_masa_pakai' => 12
            ],
            'devices' => [
                'model' => QuotationDevices::class,
                'jenis_barang_ids' => [8, 9, 10, 11, 12, 17],
                'use_detail_id' => true,
                'default_masa_pakai' => 12
            ],
            'ohc' => [
                'model' => QuotationOhc::class,
                'jenis_barang_ids' => [6, 7, 8],
                'use_detail_id' => true,
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
     */
    public function processLegacyFormat($quotation, Request $request, string $jenisBarang): array
    {
        $modelConfig = $this->getModelConfig($jenisBarang);
        $useDetailId = $modelConfig['use_detail_id'];

        $barangData = [];

        foreach ($request->all() as $fieldName => $jumlah) {
            if (strpos($fieldName, 'jumlah_') !== 0 || is_null($jumlah)) {
                continue;
            }

            $parts = explode('_', $fieldName);

            if ($useDetailId && count($parts) >= 3) {
                $barang_id = (int) $parts[1];
                $quotation_detail_id = (int) $parts[2];

                $barangData[] = [
                    'barang_id' => $barang_id,
                    'quotation_detail_id' => $quotation_detail_id,
                    'jumlah' => (int) $jumlah
                ];
            } elseif (!$useDetailId && count($parts) >= 2) {
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

        $barangData = [];
        $totalAll = 0;
        $jumlah_item = 0;

        // SELALU load quotationDetails untuk semua jenis barang
        if (!$quotation->relationLoaded('quotationDetails')) {
            $quotation->load('quotationDetails');
        }

        // Buat mapping data dari quotation_details
        $quotationDetailsMap = [];
        if ($quotation->relationLoaded('quotationDetails')) {
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
                    'harga_formatted' => "Rp " . number_format($item->harga, 0, ",", "."),
                    'masa_pakai' => $item->masa_pakai,
                    'masa_pakai_formatted' => $item->masa_pakai . " Bulan",
                    'jumlah_pertahun' => $jumlah_pertahun,
                    'total_per_item' => $total_per_item,
                    'total_formatted' => "Rp " . number_format($total_per_item, 0, ",", "."),
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
                    'harga_formatted' => "Rp " . number_format($item->harga, 0, ",", "."),
                    'jenis_barang_id' => $item->jenis_barang_id,
                    'jenis_barang' => $item->jenis_barang,
                    'nama' => $item->nama,
                    'total_per_item' => $total_per_item,
                    'total_formatted' => "Rp " . number_format($total_per_item, 0, ",", "."),
                ];

                $totalAll += $total_per_item;
            }

            // LOGIC BARU: Cek quotation_detail_id tanpa peduli useDetailId
            if (isset($item->quotation_detail_id) && $item->quotation_detail_id) {
                $detailId = $item->quotation_detail_id;

                if (isset($quotationDetailsMap[$detailId])) {
                    $detailData = $quotationDetailsMap[$detailId];
                    $itemData['jabatan_kebutuhan'] = $detailData['jabatan_kebutuhan'];
                    $itemData['quotation_site_id'] = $detailData['quotation_site_id'];
                    $itemData['jumlah_hc'] = $detailData['jumlah_hc'];
                    $itemData['nama_site'] = $detailData['nama_site'];
                    $itemData['position_id'] = $detailData['position_id'];
                    $itemData['quotation_detail_id'] = $detailId;
                } else {
                    $itemData['jabatan_kebutuhan'] = null;
                    $itemData['quotation_site_id'] = null;
                    $itemData['jumlah_hc'] = null;
                    $itemData['nama_site'] = null;
                    $itemData['position_id'] = null;
                    $itemData['quotation_detail_id'] = $detailId;
                }
            } else {
                // Item tidak punya quotation_detail_id
                // UNTUK CHEMICALS tanpa detail_id, ambil dari detail pertama (fallback)
                if ($jenisBarang === 'chemicals' && !empty($quotationDetailsMap)) {
                    $firstDetail = reset($quotationDetailsMap);
                    $itemData['jabatan_kebutuhan'] = null;
                    $itemData['quotation_site_id'] = $firstDetail['quotation_site_id'];
                    $itemData['jumlah_hc'] = null;
                    $itemData['nama_site'] = $firstDetail['nama_site'];
                    $itemData['position_id'] = null;
                    $itemData['quotation_detail_id'] = null;
                } else {
                    $itemData['jabatan_kebutuhan'] = null;
                    $itemData['quotation_site_id'] = null;
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