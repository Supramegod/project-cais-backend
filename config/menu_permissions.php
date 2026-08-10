<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pemetaan Modul -> sysmenu.id
    |--------------------------------------------------------------------------
    |
    | Middleware `menu:{modul},{field}` memakai peta ini untuk tahu baris
    | `sysmenu_role` mana yang harus dicek. Kuncinya adalah `sysmenu.id`
    | karena itu kolom yang dirujuk FK `sysmenu_role.sysmenu_id`, dan satu-
    | satunya pengenal menu yang tidak bisa diubah lewat UI admin (`nama` dan
    | `url` bisa, lihat App\Http\Requests\Menu\MenuUpdateRequest).
    |
    | Kalau id menu berbeda antar environment, override lewat variabel env
    | yang tercantum di sini.
    |
    */

    'spk' => (int) env('MENU_ID_SPK', 45),

];
