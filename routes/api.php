<?php

use App\Http\Controllers\CompanyGroupController;
use App\Http\Controllers\CustomerActivityController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\PksController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationStepController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SpkController;
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
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

Route::middleware(['auth:sanctum', 'token.expiry'])->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);


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
        Route::post('/add', 'add');
        Route::get('/view/{id}', 'view');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
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
        Route::get('/available', 'availableLeads');
    });

    // Company Group Routes - UPDATED
    Route::prefix('company-group')->controller(CompanyGroupController::class)->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/create', 'create');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');

        // Company Management
        Route::get('/available-companies/{groupId}', 'getAvailableCompanies');
        Route::get('/companies/{groupId}', 'getCompaniesInGroup');
        Route::post('/bulk-assign', 'bulkAssign');
        Route::delete('/remove-company/{groupId}/{companyId}', 'removeCompany');
        Route::delete('/bulk-remove-companies', 'bulkRemoveCompanies');

        // Statistics & Recommendations
        Route::get('/statistics', 'getStatistics');
        Route::get('/recommendations', 'getRecommendations');
    });

    // Leads Routes
    Route::prefix('leads')->controller(LeadsController::class)->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::put('/assign-sales/{id}', 'assignSales');
        Route::delete('/delete/{id}', 'delete');
        Route::delete('/remove-sales/{id}', 'removeSales');
        Route::post('/restore/{id}', 'restore');

        // Additional endpoints
        Route::get('/available-sales/{id}', 'availableSales');
        Route::get('/sales-kebutuhan/{id}', 'getSalesKebutuhan');
        Route::get('/deleted', 'listTerhapus');
        Route::get('/child/{id}', 'childLeads');
        Route::post('/child/{id}', 'saveChildLeads');
        Route::get('/belum-aktif', 'leadsBelumAktif');
        Route::get('/available-quotation', 'availableQuotation');
        Route::post('/activate/{id}', 'activateLead');
        Route::post('/import', 'import');
        Route::get('/export', 'exportExcel');
        Route::get('/template-import', 'templateImport');
        Route::post('/generate-null-kode', 'generateNullKode');
        Route::get('/spk/{id}', 'getSpkByLead');
        Route::get('/pks/{id}', 'getPksByLead');
    });
    Route::prefix('customer')->controller(CustomerController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::get('/available', 'availableCustomer')->name('customer.available');
    });
    // SPK Routes
    Route::prefix('spk')->controller(SpkController::class)->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/list-terhapus', 'listTerhapus');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add');
        Route::put('/delete-site/{id}', 'deleteSite');
        Route::delete('/delete/{id}', 'delete');

        // Cetak SPK
        Route::get('/cetak/{id}', 'cetakSpk');

        // File Upload
        Route::post('/upload/{id}', 'uploadSpk');

        // Ajukan Ulang Quotation
        Route::post('/ajukan-ulang/{spkId}', 'ajukanUlangQuotation');

        // Available Resources
        Route::get('/available-quotation', 'availableQuotation');
        Route::get('/available-leads', 'availableLeads');
        Route::get('/available-sites/{leadsId}', 'getSiteAvailableList');

        // Site Management
        Route::get('/site-list/{id}', 'getSiteList');
        Route::get('/spk/deleted-sites/{spkId}', 'getDeletedSpkSites');
    });
    // Role Management
    Route::prefix('roles')->controller(RoleController::class)->group(function () {
        Route::get('/list', 'index');
        Route::get('/view/{id}', 'show');
        Route::get('/permissions', 'menuPermissions');
        Route::post('/{id}/update-permissions', 'updatePermissions');
    });
    // PKS Management - TAMBAHKAN BLOK INI
    Route::prefix('pks')->controller(PksController::class)->group(function () {
        // Basic CRUD
        Route::get('/list', 'index');
        Route::get('/view/{id}', 'show');
        Route::post('/add', 'store');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'destroy');

        // Approval & Activation
        Route::post('/{id}/approve', 'approve');
        Route::post('/{id}/activate', 'activate');

        // Template Data
        Route::get('/{id}/perjanjian', 'getPerjanjianTemplateData');

        // Available Resources
        Route::get('/available-leads', 'getAvailableLeads');
        Route::get('/available-sites/{leadsId}', 'getAvailableSites');
    });
    // Quotation Management
    Route::prefix('quotations')->controller(QuotationController::class)->group(function () {
        Route::get('/list', 'index');
        Route::post('/add/{tipe_quotation}', 'store');
        Route::get('/view/{id}', 'show');
        Route::delete('/delete/{id}', 'destroy');
        Route::post('/{sourceId}/copy/{targetId}', 'copy');
        Route::post('/{id}/resubmit', 'resubmit');
        Route::post('/{id}/submit-approval', 'submitForApproval');
        Route::get('/{id}/calculate', 'calculate');
        Route::get('/{id}/export-pdf', 'exportPdf');
        Route::get('/{id}/status', 'getStatus');
        Route::get('/available-leads/{tipe_quotation}', 'availableLeads');
        Route::get('/reference/{leads_id}', 'getReferenceQuotations');
        Route::get('/hc-high-cost', 'getSitesWithHighHcAndCost');
    });

    // Quotation Step Management
    Route::prefix('quotations-step')->controller(QuotationStepController::class)->group(function () {
        Route::get('/{id}/step/{step}', 'getStep');
        Route::post('/{id}/step/{step}', 'updateStep');
    });

    // Options/Master Data
    Route::prefix('options')->controller(OptionController::class)->group(function () {
        Route::get('/branches', 'getBranches');
        Route::get('/users', 'getUsers');
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

    });

});