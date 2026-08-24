<?php

namespace App\Traits;

use App\Models\Pks;

/**
 * Gerbang status PKS untuk aksi tulis item fulfillment.
 *
 * Dipakai pada request barang (single & bulk) dan koreksi fulfillment, TIDAK
 * pada penerimaan barang: receive hanya menutup barang yang sudah terlanjur
 * dikirim, jadi memblokirnya akan membuat barang fisik yang sudah sampai site
 * tidak bisa dibukukan. Gerbang dipasang di pintu masuk, bukan di pintu keluar.
 */
trait ValidatesPksAktif
{
    protected const PESAN_PKS_BELUM_AKTIF = 'Fulfillment barang hanya bisa dilakukan pada PKS berstatus Kontrak Aktif.';

    /**
     * True bila PKS ada tapi statusnya bukan Kontrak Aktif. PKS yang tidak
     * ditemukan bukan urusan method ini — sudah ditangani rule required/exists.
     */
    protected function pksBelumAktif(?Pks $pks): bool
    {
        return $pks !== null && ! $pks->isAktif();
    }
}
