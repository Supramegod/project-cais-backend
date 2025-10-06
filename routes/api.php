<?php

use App\Http\Controllers\CompanyGroupController;
use App\Http\Controllers\CustomerActivityController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TimSalesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JenisPerusahaanController;
use App\Http\Controllers\KebutuhanController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\KaporlapController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\OhcController;
use App\Http\Controllers\ChemicalController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\JenisBarangController;
use App\Http\Controllers\ManagementFeeController;
use App\Http\Controllers\TopController;
use App\Http\Controllers\SalaryRuleController;
use App\Http\Controllers\TunjanganController;
use App\Http\Controllers\UmpController;
use App\Http\Controllers\UmkController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SiteController;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'token.expiry'])->group(function () {
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Branches & Users
    Route::get('/branches', [TimSalesController::class, 'getBranches']);
    Route::get('/users', [TimSalesController::class, 'getUsers']);

    // Site Management
    Route::prefix('site')->controller(SiteController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::get('/available-customer', 'availableCustomer');
    });

    // Jenis Perusahaan
    Route::prefix('jenis-perusahaan')->controller(JenisPerusahaanController::class)->group(function () {
        Route::get('/list', 'list');
        Route::post('/save', 'save');
        Route::get('/view/{id}', 'view');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // Jenis Barang
    Route::prefix('jenis-barang')->controller(JenisBarangController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::get('/list-detail/{id}', 'listdetail');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // Tim Sales
    Route::prefix('tim-sales')->controller(TimSalesController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/show/{id}', 'show');
        Route::post('/store', 'store');
        Route::put('/update/{id}', 'update');
        Route::delete('/destroy/{id}', 'destroy');
        Route::get('/getMembers/{id}', 'getMembers');
        Route::post('/addMember/{id}', 'addMember');
        Route::delete('/removeMember/{id}/{memberId}', 'removeMember');
        Route::put('/setLeader/{id}', 'setLeader');
        Route::get('/getAvailableUsers/{id}', 'getAvailableUsers');
        Route::post('/bulkAddMembers/{id}', 'bulkAddMembers');
        Route::get('/getStatistics', 'getStatistics');
    });

    // Kebutuhan
    Route::prefix('kebutuhan')->controller(KebutuhanController::class)->group(function () {
        Route::get('/list', 'list');
        // detail
        Route::get('/list-detail/{id}', 'listDetail');
        //Detail Tunjangan
        Route::get('/list-detail-tunjangan/{id}', 'listDetailTunjangan');
        Route::post('/add-detail-tunjangan', 'addDetailTunjangan');
        Route::delete('/delete-detail-tunjangan/{id}', 'deleteDetailTunjangan');
        //Detail Requirement
        Route::get('/list-detail-requirement/{id}', 'listDetailRequirement');
        Route::post('/add-detail-requirement', 'addDetailRequirement');
        Route::delete('/delete-detail-requirement/{id}', 'deleteDetailRequirement');
    });

    // Training
    Route::prefix('training')->controller(TrainingController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // Position
    Route::prefix('position')->controller(PositionController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/entitas-list', 'listEntitas');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'save');
        Route::put('/edit/{id}', 'edit');
        Route::delete('/delete/{id}', 'delete');

        // Position Requirements
        Route::get('/requirement/list/{position_id}', 'requirementList');
        Route::post('/requirement/add', 'addRequirement');
        Route::put('/requirement/edit', 'requirementEdit');
        Route::delete('/requirement/delete/{id}', 'requirementDelete');
    });

    // Management Fee
    Route::prefix('management-fee')->controller(ManagementFeeController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/list-all', 'listAll');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // TOP (Terms of Payment)
    Route::prefix('top')->controller(TopController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/list-all', 'listAll');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // Salary Rule
    Route::prefix('salary-rule')->controller(SalaryRuleController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/list-all', 'listAll');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // Tunjangan Posisi
    Route::prefix('tunjangan')->controller(TunjanganController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // UMP (Upah Minimum Provinsi)
    Route::prefix('ump')->controller(UmpController::class)->group(function () {
        Route::get('/list', 'index');
        Route::get('/list-all', 'listAll');
        Route::get('/view/{id}', 'view');
        Route::get('/province/{provinceId}', 'listUmp');
        Route::post('/add', 'add');
    });

    // UMK (Upah Minimum Kabupaten/Kota)
    Route::prefix('umk')->controller(UmkController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::get('/city/{cityId}', 'listUmk');
        Route::post('/add', 'add');
    });

    // Supplier
    Route::prefix('supplier')->controller(SupplierController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // Barang Utama
    Route::prefix('barang')->controller(BarangController::class)->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');

        // Default Quantity Management
        Route::get('/default-qty/{id}', 'getDefaultQty');
        Route::get('/{barangId}/default-qty/{layananId}', 'getDefaultQtyByLayanan');
        Route::post('/default-qty/save', 'saveDefaultQty');
        Route::post('/default-qty/bulk-save', 'bulkSaveDefaultQty');
        Route::delete('/default-qty/delete/{id}', 'deleteDefaultQty');
    });
    // Kaporlap (hanya index)
    Route::prefix('kaporlap')->controller(KaporlapController::class)->group(function () {
        Route::get('/list', 'list');
    });

    // Devices (hanya index)
    Route::prefix('devices')->controller(DevicesController::class)->group(function () {
        Route::get('/list', 'list');
    });
    // Menu Management
    Route::prefix('menu')->controller(MenuController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/by-role', 'getMenusByRole');
        Route::get('/permissions', 'getUserPermissions');
        Route::get('/all-permissions', 'getAllPermissions');
        Route::post('/add', 'add');
        Route::get('/view/{id}', 'view');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
        Route::get('/listRole/{id}', 'listRole');
        Route::post('/addrole/{id}', 'addrole');
    });

    // OHC (hanya index)
    Route::prefix('ohc')->controller(OhcController::class)->group(function () {
        Route::get('/list', 'list');
    });

    // Chemical (hanya index)
    Route::prefix('chemical')->controller(ChemicalController::class)->group(function () {
        Route::get('/list', 'list');
    });

    Route::prefix('customer-activities')->controller(CustomerActivityController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
        Route::get('/leads/{leadsId}/track', 'trackActivity');
    });
    // Company Group Routes
    Route::prefix('company-group')->controller(CompanyGroupController::class)->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');

        // Company Management
        Route::get('/get-available-companies/{groupId}', 'getAvailableCompanies');
        Route::get('/get-companies-in-group/{groupId}', 'getCompaniesInGroup');
        Route::post('/bulk-assign', 'bulkAssign');
        Route::delete('/remove-company/{groupId}/{companyId}', 'removeCompany');
        Route::delete('/bulk-remove-companies', 'bulkRemoveCompanies');

        // Statistics & Filtering
        Route::get('/statistics', 'getStatistics');
        Route::get('/filter-rekomendasi', 'filterRekomendasi');
        Route::post('/groupkan', 'groupkan');
    });
    // Leads Routes
    Route::prefix('leads')->controller(LeadsController::class)->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
        Route::post('/restore/{id}', 'restore');

        // Additional endpoints
        Route::get('/deleted', 'listTerhapus');
        Route::get('/child/{id}', 'childLeads');
        Route::post('/child/{id}', 'saveChildLeads');
        Route::get('/belum-aktif', 'leadsBelumAktif');
        Route::get('/available', 'availableLeads');
        Route::get('/available-quotation', 'availableQuotation');
        Route::post('/activate/{id}', 'activateLead');
        Route::post('/import', 'import');
        Route::get('/export', 'exportExcel');
        Route::get('/template-import', 'templateImport');
        Route::post('/generate-null-kode', 'generateNullKode');

        // Location data endpoints
        Route::get('/provinsi', 'getProvinsi');
        Route::get('/kota/{provinsiId}', 'getKota');
        Route::get('/kecamatan/{kotaId}', 'getKecamatan');
        Route::get('/kelurahan/{kecamatanId}', 'getKelurahan');
        Route::get('/negara/{benuaId}', 'getNegara');
    });
    Route::prefix('customer')->controller(CustomerController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::get('/available', 'availableCustomer')->name('customer.available');
    });
});