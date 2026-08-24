<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesActivityController;
use App\Http\Controllers\SalesRevenueController;
use App\Http\Controllers\SalesTargetController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SystemAnnouncementController;
use App\Http\Controllers\SystemAnnouncementV2Controller;
use App\Http\Controllers\UserEmailConfigController;

/*
|--------------------------------------------------------------------------
| Penegakan permission menu
|--------------------------------------------------------------------------
|
| Route yang punya menu di `sysmenu` dipecah ke routes/modules/{modul}.php,
| satu file per menu, mengikuti pola routes/modules/spk.php. Di dalamnya
| middleware `menu:{modul}` (default is_view) dipasang di level grup dan route
| yang mengubah data menambahkan field-nya sendiri (is_add/is_edit/is_delete).
| Peta modul -> sysmenu.id ada di config/menu_permissions.php.
|
| Yang tertinggal di file ini adalah route yang sengaja TIDAK digerbang:
|   - auth/*           login, logout, refresh, profil sendiri.
|   - site/*           helper read-only lintas modul (SPK, PKS, quotation).
|   - options/*        dropdown yang dipakai hampir semua layar; menggerbangnya
|                      mematikan seluruh form meski menunya terbuka.
|   - user/*           konfigurasi email pribadi, bukan menu.
|   - sales-activity, sales-report, sales-revenue, sales-target,
|     system-announcements, v2/system-announcements
|                      belum punya baris di `sysmenu`, jadi tidak ada yang bisa
|                      dijadikan acuan. Fail-closed akan menolak semua orang
|                      kalau dipaksa digerbang sekarang.
|
*/

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::get('/admin-panel/consultations', [AdminPanelController::class, 'getConsultations']);
Route::post('/admin-panel/consultations', [AdminPanelController::class, 'storeConsultation']);

Route::middleware(['auth:sanctum,web', 'token.expiry'])->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    /*
    |----------------------------------------------------------------------
    | Modul bergerbang — satu file per menu di `sysmenu`
    |----------------------------------------------------------------------
    */

    require __DIR__.'/modules/dashboard.php';
    require __DIR__.'/modules/leads_customer.php';
    require __DIR__.'/modules/customer_activity.php';
    require __DIR__.'/modules/quotation.php';
    require __DIR__.'/modules/spk.php';
    require __DIR__.'/modules/pks.php';
    require __DIR__.'/modules/training.php';
    require __DIR__.'/modules/master_data.php';
    require __DIR__.'/modules/master_barang.php';
    require __DIR__.'/modules/master_keuangan.php';
    require __DIR__.'/modules/role_access.php';
    require __DIR__.'/modules/menu_manager.php';

    /*
    |----------------------------------------------------------------------
    | Tanpa gerbang — lihat alasan per prefix di blok komentar paling atas
    |----------------------------------------------------------------------
    */

    // Site Management — helper read-only lintas modul.
    Route::prefix('site')->controller(SiteController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::get('/available-customer', 'availableCustomer');
    });

    // Options/Master Data — dropdown lintas layar.
    Route::prefix('options')->controller(OptionController::class)->group(function () {
        Route::get('/branches', 'getBranches');
        Route::get('/users', 'getUsers');
        Route::get('/list-user', 'getListUser');
        Route::get('/list-jenis-visit', 'getListJenisVisit');
        Route::get('/platforms', 'getPlatforms');
        Route::get('/status-leads', 'getStatusLeads');
        Route::get('/benua', 'getBenua');
        Route::get('/jabatan-pic', 'getJabatanPic');
        Route::get('/bidang-perusahaan', 'getBidangPerusahaan');
        Route::get('/entitas', 'listEntitas');
        Route::get('/entitas/{layanan_id}', 'getEntitas');
        Route::get('/status-quotation', 'getStatusQuotation');
        Route::get('/branches/{provinceId}', 'getBranchesByProvince');
        // Location data endpoints
        Route::get('/provinsi', 'getProvinsi');
        Route::get('/kota/{provinsiId}', 'getKota');
        Route::get('/kecamatan/{kotaId}', 'getKecamatan');
        Route::get('/kelurahan/{kecamatanId}', 'getKelurahan');
        Route::get('/negara/{benuaId}', 'getNegara');
        Route::get('/loyalty', 'loyaltylist');
        Route::get('/kategori-sesuai-hc', 'kategorusesuaihc');
        Route::get('/rule-thr', 'rulethr');
        Route::get('/salary-rule', 'salaryrule');
        Route::get('/status-pks', 'statuspks');
        Route::get('/status-spk', 'statusspk');

    });

    // User Email Config — pengaturan pribadi, bukan menu.
    Route::prefix('user')->controller(UserEmailConfigController::class)->group(function () {
        Route::get('/list', 'getConfig');
        Route::post('/add', 'saveConfig');
        Route::post('/test', 'testConnection');
    });

    // Sales Activity Routes — belum ada baris di `sysmenu`.
    Route::prefix('sales-activity')->controller(SalesActivityController::class)->group(function () {
        Route::get('/available-leads', 'getAvailableLeads');
        Route::get('/list', 'index');
        Route::post('/add', 'store');
        Route::get('/view/{id}', 'show');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'destroy');
        Route::get('/kebutuhan/{leadsId}', 'getKebutuhanByLeads');
        Route::get('/stats', 'getStats');
    });

    // Sales Revenue Management — belum ada baris di `sysmenu`.
    Route::prefix('sales-revenue')->controller(SalesRevenueController::class)->group(function () {
        Route::get('/list', 'getMonthlyRevenue');
        Route::get('/summary', 'getRevenueSummary');
        Route::get('/by-user', 'getRevenueByUser');
        Route::get('/by-month', 'getRevenueByMonth');
        Route::get('/kpi', 'getkpi');

    });

    Route::apiResource('sales-target', SalesTargetController::class)->only([
        'index',
        'store',
        'show',
        'update',
        'destroy',
    ]);

    // System Announcements — belum ada baris di `sysmenu`.
    Route::prefix('system-announcements')->controller(SystemAnnouncementController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // System Announcements V2 — rich content, image upload, file attachment
    Route::prefix('v2/system-announcements')->controller(SystemAnnouncementV2Controller::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::post('/upload-image', 'uploadImage');
        Route::post('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
        Route::delete('/delete-file/{fileId}', 'deleteFile');
    });

    // Sales Report Routes — belum ada baris di `sysmenu`.
    Route::prefix('sales-report')->controller(ReportController::class)->group(function () {
        Route::get('/monthly', 'monthly');
        Route::get('/weekly', 'weekly');
        Route::get('/activity-detail/{user_id}', 'activityDetail');
        Route::get('/monthly/tele', 'monthlyRole30');
        Route::get('/weekly/tele', 'weeklyRole30');
        Route::get('/activity-detail/tele/{user_id}', 'activityDetailTele');

    });

});
