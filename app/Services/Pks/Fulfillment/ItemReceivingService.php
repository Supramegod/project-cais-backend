<?php

namespace App\Services\Pks\Fulfillment;

use App\Models\PksFulfillmentLog;
use App\Models\PksItemFulfillment;
use App\Models\PksItemRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 2 — penerimaan barang di site.
 *
 * Dipisah dari ItemFulfillmentService (yang mengurus tahap request) karena
 * aturannya berbeda: di sini yang naik qty_terpenuhi, dan baris permintaan
 * ditutup satu per satu.
 *
 * Barang boleh diterima kurang dari yang dikirim. Baris permintaan yang
 * tersentuh selalu ditutup — kekurangannya tidak menggantung sebagai "masih di
 * jalan", melainkan langsung kembali menjadi sisa yang boleh di-request ulang
 * lewat batch baru.
 */
class ItemReceivingService
{
    /**
     * Catat penerimaan sekelompok barang. All-or-nothing seperti bulk request:
     * satu item gagal, seluruh panggilan dibatalkan, supaya client tidak perlu
     * menebak bagian mana yang sudah masuk.
     *
     * @param  array<int, array{fulfillment_id: int, qty: int, catatan?: string|null}>  $items
     * @param  string|null  $requestBatchId  batasi penutupan ke satu batch request saja;
     *                                       null berarti ambil baris open paling lama dulu (FIFO)
     * @return array{batch_id: string, batch_ke: int, items: array<int, array<string, mixed>>}
     */
    public function receive(array $items, User $user, ?string $requestBatchId = null, ?string $catatan = null): array
    {
        return DB::transaction(function () use ($items, $user, $requestBatchId, $catatan) {
            $results = [];
            $batch = null;

            foreach ($items as $index => $item) {
                try {
                    $hasil = $this->receiveOne(
                        (int) $item['fulfillment_id'],
                        (int) $item['qty'],
                        $user,
                        $requestBatchId,
                        $batch,
                        $item['catatan'] ?? $catatan
                    );
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException('Item ke-'.($index + 1).': '.$e->getMessage(), 0, $e);
                }

                $batch ??= $hasil['batch'];
                $results[] = $hasil['item'];
            }

            return [
                'batch_id' => $batch['batch_id'],
                'batch_ke' => $batch['batch_ke'],
                'items' => $results,
            ];
        });
    }

    /**
     * Penerimaan satu item. Batch log dibuat pada item pertama saja supaya satu
     * panggilan penerimaan = satu batch, sama seperti bulk request.
     *
     * @param  array{batch_id: string, batch_ke: int}|null  $batch
     * @return array{batch: array{batch_id: string, batch_ke: int}, item: array<string, mixed>}
     */
    private function receiveOne(
        int $fulfillmentId,
        int $qty,
        User $user,
        ?string $requestBatchId,
        ?array $batch,
        ?string $catatan
    ): array {
        $fulfillment = PksItemFulfillment::whereKey($fulfillmentId)->lockForUpdate()->first();

        if (! $fulfillment) {
            throw new \RuntimeException('Data fulfillment tidak ditemukan.');
        }

        $openRequests = $this->openRequests($fulfillmentId, $requestBatchId);

        if ($openRequests->isEmpty()) {
            throw new \RuntimeException('Tidak ada permintaan barang yang menunggu penerimaan.');
        }

        $totalOpen = (int) $openRequests->sum('qty_request');

        if ($qty > $totalOpen) {
            throw new \RuntimeException(
                "Qty diterima ({$qty}) melebihi yang dikirim dan belum diterima ({$totalOpen})."
            );
        }

        // Sabuk pengaman untuk baris yang invariannya sudah terlanjur rusak
        // sebelum editFulfillment dijaga. Data seperti itu tidak boleh diperparah
        // menjadi qty_terpenuhi > qty_diminta — lebih baik gagal dan diperiksa.
        $sudahDiterima = (int) $fulfillment->qty_terpenuhi;

        if ($sudahDiterima + $qty > (int) $fulfillment->qty_diminta) {
            throw new \RuntimeException(
                "Qty diterima ({$qty}) melebihi kebutuhan: {$fulfillment->qty_diminta} diminta, ".
                "{$sudahDiterima} sudah diterima."
            );
        }

        $batch ??= PksFulfillmentLog::newBatch(
            (int) $fulfillment->pks_id,
            PksFulfillmentLog::JENIS_ITEM,
            PksFulfillmentLog::AKSI_RECEIVE
        );

        $terpenuhiSebelum = (int) $fulfillment->qty_terpenuhi;
        $sisa = $qty;
        $requestDitutup = 0;
        $requestIds = [];

        // FIFO: pengiriman paling lama ditutup lebih dulu.
        foreach ($openRequests as $request) {
            if ($sisa <= 0) {
                break;
            }

            $diterima = min($sisa, (int) $request->qty_request);
            $sisa -= $diterima;

            $request->qty_diterima = $diterima;
            $request->status = $diterima === (int) $request->qty_request
                ? PksItemRequest::STATUS_RECEIVED
                : PksItemRequest::STATUS_SHORT;
            $request->received_at = now();
            // Tautan balik ke batch penerimaan. Tanpa ini baris yang sudah
            // 'received' hanya mengenal batch pengirimannya, sehingga dari
            // daftar permintaan tidak ada jalan menuju batch yang menutupnya.
            $request->received_batch_id = $batch['batch_id'];
            $request->received_batch_ke = $batch['batch_ke'];
            $request->updated_by = $user->full_name;
            $request->save();

            $requestDitutup += (int) $request->qty_request;
            $requestIds[] = $request->id;
        }

        // qty_request turun sebesar seluruh isi baris yang ditutup — termasuk
        // bagian yang tidak jadi diterima, karena bagian itu bukan lagi "di
        // jalan" melainkan kekurangan yang boleh dikirim ulang.
        $fulfillment->qty_terpenuhi = $terpenuhiSebelum + $qty;
        $fulfillment->qty_request = max(0, (int) $fulfillment->qty_request - $requestDitutup);
        $fulfillment->status = $fulfillment->resolveStatus();
        $fulfillment->updated_by = $user->full_name;
        $fulfillment->save();

        PksFulfillmentLog::create([
            'pks_id' => $fulfillment->pks_id,
            'site_id' => $fulfillment->site_id,
            'jenis' => PksFulfillmentLog::JENIS_ITEM,
            'reference_id' => $fulfillment->id,
            'batch_id' => $batch['batch_id'],
            'batch_ke' => $batch['batch_ke'],
            'aksi' => PksFulfillmentLog::AKSI_RECEIVE,
            'catatan' => $catatan,
            'meta' => [
                'qty_diterima' => $qty,
                'qty_request_ditutup' => $requestDitutup,
                'kurang' => $requestDitutup - $qty,
                'request_batch_id' => $requestBatchId,
                'request_ids' => $requestIds,
                'qty_terpenuhi_sebelum' => $terpenuhiSebelum,
                'qty_terpenuhi_sesudah' => (int) $fulfillment->qty_terpenuhi,
            ],
            'created_by' => $user->full_name,
            'created_by_user_id' => $user->id,
        ]);

        return [
            'batch' => $batch,
            'item' => [
                'fulfillment_id' => $fulfillment->id,
                'site_id' => $fulfillment->site_id,
                'item_type' => $fulfillment->item_type,
                'item_id' => $fulfillment->item_id,
                'qty_diterima' => $qty,
                'qty_request_ditutup' => $requestDitutup,
                'kurang' => $requestDitutup - $qty,
                'request_ids' => $requestIds,
                'qty_diminta' => (int) $fulfillment->qty_diminta,
                'qty_request' => (int) $fulfillment->qty_request,
                'qty_terpenuhi' => (int) $fulfillment->qty_terpenuhi,
                'boleh_direquest' => $fulfillment->boleh_direquest,
                'status' => $fulfillment->status,
            ],
        ];
    }

    /**
     * Baris permintaan yang masih menunggu, urut paling lama dulu. Dikunci
     * supaya dua penerimaan bersamaan tidak menutup baris yang sama.
     *
     * @return Collection<int, PksItemRequest>
     */
    private function openRequests(int $fulfillmentId, ?string $requestBatchId): Collection
    {
        return PksItemRequest::query()
            ->forFulfillment($fulfillmentId)
            ->open()
            ->when($requestBatchId !== null, fn ($q) => $q->forBatch($requestBatchId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Daftar permintaan barang satu PKS — sumber data form penerimaan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPksRequests(int $pksId, ?int $siteId = null, ?string $status = PksItemRequest::STATUS_OPEN): array
    {
        $requests = PksItemRequest::query()
            ->forPks($pksId)
            ->when($siteId !== null, fn ($q) => $q->where('site_id', $siteId))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderBy('id', 'desc')
            ->get();

        // Nama barang: satu query per jenis, bukan per baris.
        $names = FulfillmentLogService::itemNames($requests);

        return $requests
            ->map(fn (PksItemRequest $request) => [
                'id' => $request->id,
                'fulfillment_id' => $request->fulfillment_id,
                'site_id' => $request->site_id,
                // batch_id/batch_ke dipertahankan demi klien lama, tapi namanya
                // menyesatkan: isinya batch PENGIRIMAN. Pakai pasangan
                // request_*/received_* di bawah untuk navigasi ke batch detail.
                'batch_id' => $request->batch_id,
                'batch_ke' => $request->batch_ke,
                'request_batch_id' => $request->batch_id,
                'request_batch_ke' => $request->batch_ke,
                'received_batch_id' => $request->received_batch_id,
                'received_batch_ke' => $request->received_batch_ke,
                'item_type' => $request->item_type,
                'item_id' => $request->item_id,
                'nama' => $names[$request->item_type][$request->item_id] ?? null,
                'qty_request' => (int) $request->qty_request,
                'qty_diterima' => (int) $request->qty_diterima,
                'kurang' => $request->kurang,
                'status' => $request->status,
                'received_at' => $request->received_at,
                'created_by' => $request->created_by,
                'created_at' => $request->created_at,
            ])
            ->all();
    }
}
