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
    | Satu modul boleh dipakai beberapa prefix route sekaligus, karena satu
    | menu di sidebar memang menaungi beberapa layar (mis. Master Barang
    | menaungi barang, kaporlap, devices, ohc, chemical, supplier).
    |
    */

    'dashboard' => (int) env('MENU_ID_DASHBOARD', 2),

    'leads_customer' => (int) env('MENU_ID_LEADS_CUSTOMER', 40),

    'customer_activity' => (int) env('MENU_ID_CUSTOMER_ACTIVITY', 43),

    'quotation' => (int) env('MENU_ID_QUOTATION', 44),

    'spk' => (int) env('MENU_ID_SPK', 45),

    'pks' => (int) env('MENU_ID_PKS', 46),

    'training' => (int) env('MENU_ID_TRAINING', 50),

    'master_data' => (int) env('MENU_ID_MASTER_DATA', 61),

    'master_barang' => (int) env('MENU_ID_MASTER_BARANG', 69),

    'master_keuangan' => (int) env('MENU_ID_MASTER_KEUANGAN', 84),

    /*
    | Dua modul di bawah menunjuk ke anak dari "Apps Manager" (107), bukan ke
    | induknya, karena keduanya layar terpisah di sidebar dan bisa saja seorang
    | admin hanya boleh memegang salah satunya.
    */

    'role_access' => (int) env('MENU_ID_ROLE_ACCESS', 110),

    'menu_manager' => (int) env('MENU_ID_MENU_MANAGER', 111),

];
