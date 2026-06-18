# CAIS BACKEND — FULL SYSTEM ARCHITECTURE

> **Project**: Laravel 12 | PHP ^8.2 | Multi-tenant business management (Sales, HR, Quotation, PKS, SPK)  
> **Models**: 94 | **Controllers**: 46 | **Services**: 13 | **DTOs**: 3 | **Resources**: 3 | **Requests**: 14  
> **Database**: MySQL `shelter3_cais` + MySQL `shelter3_hris` (dual connection)

---

## 1. INFRASTRUCTURE & CONFIG

### Database Dual Connection

```mermaid
graph LR
    subgraph APP["Laravel App"]
        DB1["DB_CONNECTION=mysql<br/>shelter3_cais"]
        DB2["DB_CONNECTION_2=mysqlhris<br/>shelter3_hris"]
    end
    
    DB1 --> MYSQL["MySQL 31.97.48.106:3306"]
    DB2 --> MYSQL
    
    subgraph CAIS["shelter3_cais"]
        CAIS_TABLES["sl_* (bisnis)<br/>m_* (master)<br/>log_*, personal_access_tokens<br/>consultations, refresh_tokens<br/>sysmenu, sysmenu_*, user_email_configs"]
    end
    
    subgraph HRIS["shelter3_hris"]
        HRIS_TABLES["m_user, m_role<br/>m_branch, m_company<br/>m_position<br/>m_province, m_city<br/>m_district, m_village<br/>m_benua, m_negara<br/>m_tim_sales, m_tim_sales_detail"]
    end
```

### Auth Stack

```mermaid
flowchart TD
    LOGIN["POST /auth/login
    → User::where('email', $request->email)
    → md5(password) verification (legacy)"] --> TOKEN["Sanctum Token Created
    → HrisPersonalAccessToken (extends Sanctum)
    → expires_at = 24 hours
    → Return { access_token, refresh_token }"]
    TOKEN --> MID["CheckTokenExpiry middleware
    → Alias: token.expiry
    → Cek expires_at
    → 401 jika expired"]
    
    REFRESH["POST /auth/refresh
    → RefreshTokens model
    → Token rotation"] --> NEWTOKEN["New access_token + refresh_token"]
    
    SESSION["WebAuthController (session-based)
    → Login form
    → Dummy tokens + cookie refresh"]
```

### Middleware Stack (bootstrap/app.php)

```
API Group:
  → ApiResponseMiddleware (set Accept: application/json)
  → EncryptCookies
  → StartSession
  → ShareErrorsFromSession
  → SubstituteBindings
  → CheckTokenExpiry (alias: token.expiry)
  → auth:sanctum,web (route-level)
```

---

## 2. FULL ROUTE MAP

```mermaid
graph LR
    subgraph PUBLIC["Public (No Auth)"]
        AUTH_LOGIN["POST /auth/login<br/>POST /auth/refresh"]
        CONSULT["GET/POST /admin-panel/consultations"]
    end

    subgraph AUTH["Authenticated (40+ route groups)"]
        AUTH_ME["POST /auth/logout<br/>GET /auth/user"]
        
        MASTER_A["Master Data A:<br/>jenis-perusahaan<br/>bentuk-usaha<br/>jenis-barang<br/>barang<br/>kebutuhan<br/>position<br/>training"]
        MASTER_B["Master Data B:<br/>management-fee<br/>top<br/>salary-rule<br/>tunjangan<br/>supplier<br/>menu"]
        WAGE["Wage Data:<br/>ump, umk, upah<br/>(UMP/UMK/UMSK/UMSP)"]
        
        LEADS["/leads<br/>✓ Full CRUD<br/>✓ assignSales, removeSales<br/>✓ childLeads<br/>✓ import/export excel<br/>✓ getPksByLead, getSpkByLead<br/>✓ availableQuotation"]
        CUSTOMER["/customer<br/>✓ list, view, available"]
        CUST_ACT["/customer-activities<br/>✓ CRUD + track + sendEmail"]
        SALES_ACT["/sales-activity<br/>✓ CRUD + stats"]
        TIM_SALES["/tim-sales<br/>✓ CRUD + members + stats"]
        SALES_TARGET["/sales-targets<br/>✓ API Resource"]
        SALES_REV["/sales-revenue<br/>✓ monthly, summary, kpi"]
        
        QUOTATION["/quotations<br/>✓ CRUD + copy/resubmit<br/>✓ submit-approval + reset-approval<br/>✓ calculate + export-pdf<br/>✓ available-leads + reference"]
        QUOTATION_STEP["/quotations-step<br/>✓ getStep + updateStep (12 steps)"]
        
        PKS["/pks<br/>✓ CRUD + approve + activate<br/>✓ perjanjian (template/history/compare)<br/>✓ upload + submit-checklist<br/>✓ storePasal, destroyPasal"]
        SPK["/spk<br/>✓ CRUD + cetak + upload<br/>✓ ajukan-ulang<br/>✓ site management"]
        SITE["/site<br/>✓ list, view, available-customer"]
        
        DASHBOARD["/dashboard-approval<br/>/dashboard-pks"]
        REPORT["/sales-report<br/>✓ monthly, weekly, activity-detail"]
        
        COMPANY_GROUP["/company-group<br/>✓ CRUD + bulk assign"]
        SUBMISSION["/submission<br/>/submission-v2"]
        ANNOUNCEMENT["/system-announcements<br/>/v2/system-announcements"]
        
        ADMIN["/admin-panel<br/>✓ Step data + bypass updates"]
        OPTIONS["/options<br/>✓ 20+ master data endpoints"]
    end

    PUBLIC --> AUTH
```

---

## 3. MODULE MAP — DATABASE RELATIONSHIPS

```mermaid
erDiagram
    %% LEADS MODULE
    sl_leads ||--o{ sl_leads_kebutuhan : "has"
    sl_leads ||--o{ sl_quotation : "has"
    sl_leads ||--o{ sl_pks : "has"
    sl_leads ||--o{ sl_spk : "has"
    sl_leads ||--o{ sl_customer_activity : "has"
    sl_leads ||--o{ sl_activity_sales : "has"
    sl_leads ||--o{ sl_quotation_site : "has"
    sl_leads ||--o{ sl_submission : "has"
    sl_leads }o--|| m_status_leads : "status"
    sl_leads }o--|| m_branch : "branch"
    sl_leads }o--|? m_platform : "platform"
    sl_leads }o--|? sl_customers : "customer"
    
    %% SALES TEAM
    m_tim_sales ||--o{ m_tim_sales_detail : "has"
    m_tim_sales_detail }o--|| m_user : "user"
    sl_leads_kebutuhan }o--|? m_tim_sales_detail : "tim_sales_d"
    sl_leads_kebutuhan }o--|| m_kebutuhan : "kebutuhan"
    
    %% QUOTATION MODULE
    sl_quotation ||--o{ sl_quotation_detail : "has"
    sl_quotation ||--o{ sl_quotation_site : "has"
    sl_quotation ||--o{ sl_site : "has (via sites)"
    sl_quotation ||--o{ sl_quotation_pic : "has"
    sl_quotation ||--o{ sl_quotation_kaporlap : "has"
    sl_quotation ||--o{ sl_quotation_devices : "has"
    sl_quotation ||--o{ sl_quotation_chemical : "has"
    sl_quotation ||--o{ sl_quotation_ohc : "has"
    sl_quotation ||--o{ sl_quotation_aplikasi : "has"
    sl_quotation ||--o{ sl_quotation_kerjasama : "has"
    sl_quotation ||--o{ sl_quotation_training : "has"
    sl_quotation ||--o{ sl_quotation_management_fee : "has"
    sl_quotation }o--|| m_status_quotation : "status"
    sl_quotation }o--|| m_company : "company"
    sl_quotation }o--|| m_kebutuhan : "kebutuhan"
    sl_quotation }o--|| m_management_fee : "management_fee"
    sl_quotation }o--|| m_salary_rule : "salary_rule"
    
    sl_quotation_detail ||--o{ sl_quotation_detail_hpp : "has"
    sl_quotation_detail ||--o{ sl_quotation_detail_coss : "has"
    sl_quotation_detail ||--o{ sl_quotation_detail_wage : "has"
    sl_quotation_detail ||--o{ sl_quotation_detail_tunjangan : "has"
    sl_quotation_detail ||--o{ sl_quotation_detail_requirement : "has"
    sl_quotation_detail }o--|| m_position : "position"
    
    %% PKS MODULE
    sl_pks ||--o{ sl_pks_perjanjian : "has"
    sl_pks_perjanjian ||--o{ sl_pks_perjanjian_history : "has"
    sl_pks ||--o{ sl_site : "has"
    sl_pks ||--o{ log_approval : "has (polymorphic)"
    sl_pks }o--|| m_status_pks : "status"
    
    %% SPK MODULE
    sl_spk ||--o{ sl_spk_site : "has"
    sl_spk_site }o--|| sl_quotation_site : "quotation_site"
    sl_spk }o--|| m_status_spk : "status"
    
    %% SITE
    sl_site }o--|| sl_quotation : "quotation"
    sl_site }o--|| sl_pks : "pks"
    sl_site }o--|| sl_spk : "spk"
    
    %% LOGGING
    log_approval }o--|| sl_quotation : "doc_id (tabel=quotation)"
    log_approval }o--|| sl_pks : "doc_id (tabel=pks)"
    log_notification }o--|| m_user : "user_id"
    
    %% REFERENCE / MASTER
    m_kebutuhan ||--o{ m_kebutuhan_detail_tunjangan : "has"
    m_kebutuhan ||--o{ m_kebutuhan_detail_requirement : "has"
    m_kebutuhan ||--o{ m_position : "has"
    m_barang ||--o{ m_barang_default_qty : "has"
    m_barang }o--|| m_jenis_barang : "jenis"
    m_province ||--o{ m_city : "has"
    m_province ||--|| m_ump : "has active"
    m_city ||--|| m_umk : "has active"
    m_user }o--|| m_role : "cais_role"
    m_user }o--|| m_branch : "branch"
    m_user ||--o{ personal_access_tokens : "has"
    personal_access_tokens ||--|| refresh_tokens : "has"
```

---

## 4. DIRECTORY STRUCTURE

```
project-cais-backend/
├── app/
│   ├── Console/
│   │   └── Commands/          # (Custom commands - currently empty)
│   ├── DTO/                   # 3 Data Transfer Objects
│   │   ├── CalculationSummary.php
│   │   ├── DetailCalculation.php
│   │   └── QuotationCalculationResult.php
│   ├── Enums/
│   │   └── ProvinceDetailType.php
│   ├── Events/
│   │   └── QuotationCreated.php
│   ├── Exceptions/            # (Custom exceptions)
│   ├── Http/
│   │   ├── Controllers/       # 46 controllers
│   │   ├── Middleware/        # 2 middleware
│   │   ├── Requests/          # 14 form requests
│   │   └── Resources/         # 3 API resources
│   ├── Jobs/
│   │   └── EscalateQuotationJob.php
│   ├── Listeners/
│   │   └── ProcessQuotationDuplication.php
│   ├── Mail/                  # 3 mailables
│   ├── Models/                # 94 models
│   ├── Providers/             # 4 service providers
│   └── Services/              # 13 services
│       ├── AddendumService.php
│       ├── DocumentCompressionService.php
│       ├── DynamicMailerService.php
│       ├── PksPerjanjianTemplateService.php
│       ├── QuotationBarangService.php
│       ├── QuotationBusinessService.php
│       ├── QuotationDuplicationService.php
│       ├── QuotationNotificationService.php
│       ├── QuotationService.php
│       ├── QuotationStepService.php
│       ├── SalesRevenueService.php
│       └── UpahService.php
├── bootstrap/
│   └── app.php                # Middleware + Sanctum config
├── config/
│   ├── app.php, auth.php, cache.php, cors.php
│   ├── database.php, filesystems.php, logging.php
│   ├── l5-swagger.php, mail.php, queue.php
│   ├── sanctum.php, services.php, session.php
│   └── telescope.php
├── database/
│   ├── factories/
│   ├── migrations/            # 13 migration files
│   └── seeders/
├── docs/                      # (Documentation)
├── resources/
│   └── views/                 # (Blade templates)
├── routes/
│   ├── api.php                # 576 lines - all API routes
│   ├── console.php
│   └── web.php
├── storage/
├── tests/
│   ├── Feature/
│   └── Unit/
├── .env                       # DB credentials + config
├── AGENTS.md                  # Project conventions
└── composer.json
```

---

## 5. CORE MODULE: QUOTATION — FULL DATAFLOW

```mermaid
flowchart TD
    subgraph FRONTEND["Frontend"]
        FE["React/Vue App"]
    end
    
    subgraph API["API Layer"]
        QC["QuotationController"]
        QSC["QuotationStepController"]
        ADM["AdminPanelController"]
    end
    
    subgraph VALIDATION["Validation"]
        QSR["QuotationStoreRequest<br/>- tipe_quotation validation<br/>- site rules (single/multi)<br/>- revision/rekontrak ref<br/>- after-validation callbacks"]
        QSTR["QuotationStepRequest<br/>- step-specific rules"]
    end
    
    subgraph BUSINESS["Business Services"]
        QBS["QuotationBusinessService<br/>- prepareData, createSites<br/>- generateNomor, activity log<br/>- softDeleteRelations"]
        QDS["QuotationDuplicationService<br/>- duplicate with site mapping<br/>- copy Details/HPP/COSS<br/>- copy Tunjangan, Pig, Items"]
        QSS["QuotationStepService<br/>- updateStep1 ~ 12<br/>- prepareStepData<br/>- syncDetail, validateStep<br/>- saveManagementFeeConfig<br/>- generateKerjasamaContent<br/>- calculateFinalStatus"]
        QS["QuotationService<br/>- calculateQuotation()<br/>- loadQuotationData()<br/>- processAllDetails()<br/>- calculateFinancials()"]
        QBS2["QuotationBarangService<br/>- syncBarangData (uniform)<br/>- processLegacyFormat<br/>- prepareBarangData"]
        QNS["QuotationNotificationService<br/>- sendApprovalEmail<br/>- DirSales / DirKeuangan"]
    end
    
    subgraph QUEUE["Queue / Events"]
        EVENT["QuotationCreated Event"]
        LISTENER["ProcessQuotationDuplication<br/>(ShouldQueue)"]
        JOB["EscalateQuotationJob<br/>(delayed 1 day)"]
    end
    
    subgraph DTO["Data Transfer Objects"]
        QCR["QuotationCalculationResult<br/>- quotation (model)<br/>- calculation_summary<br/>- detail_calculations[]"]
        CS["CalculationSummary<br/>- total_sebelum_mf<br/>- nominal_mf<br/>- grand_total, ppn, pph<br/>- margin, gpm<br/>- versi HPP + COSS"]
        DC["DetailCalculation<br/>- detail_id<br/>- hpp_data[]<br/>- coss_data[]"]
    end
    
    subgraph RESPONSE["Response / Resources"]
        QRES["QuotationResource<br/>- toArray() full structure<br/>- resolveMfConfig()<br/>- calculateApprovalHighlights()"]
        QSRES["QuotationStepResource<br/>- step-specific data<br/>- additional_data"]
        QCOLL["QuotationCollection"]
    end
    
    FE --> API
    API --> VALIDATION
    VALIDATION --> BUSINESS
    BUSINESS --> QUEUE
    BUSINESS --> DTO
    DTO --> RESPONSE
    RESPONSE --> FE
    
    EVENT --> LISTENER
    LISTENER --> QBS
    LISTENER --> QDS
    
    QBC["Quotation Event Bus"] -.->|dispatches| EVENT
    QSS -.->|dispatches after step 12| JOB
```

---

## 6. CALCULATION ENGINE — DETAILED SEQUENCE

```mermaid
sequenceDiagram
    participant FE as Frontend
    participant QC as QuotationController
    participant QS as QuotationService
    participant QDetail as QuotationDetail
    participant DB as Database
    
    FE->>QC: GET /quotations/view/{id}
    QC->>QS: calculateQuotation(quotation)
    
    Note over QS: === INIT ===
    QS->>QS: loadQuotationData()
    QS->>DB: SELECT ... FROM sl_quotation_detail WHERE quotation_id = ?
    QS->>DB: SELECT ... FROM sl_quotation_site WHERE quotation_id = ?
    QS->>DB: SELECT ... FROM sl_quotation_detail_hpp WHERE detail_id IN (...)
    QS->>DB: SELECT ... FROM sl_quotation_detail_coss WHERE detail_id IN (...)
    QS->>DB: SELECT items (kaporlap, devices, ohc, chemical) - 4 queries
    QS-->>QS: Preload _hpp_map, _coss_map, _sites_map, _items

    QS->>QS: ensureAllWagesExist()
    Note over QS: Batch insert + reload jika ada detail tanpa wage
    
    QS->>QS: initializeAllDetails()
    Note over QS: Set jumlah_hc_hpp, jumlah_hc_original
    
    Note over QS: === FIRST PASS ===
    QS->>QS: processAllDetails()
    
    loop For each quotation_detail
        QS->>QS: initializeDetail()
        Note over QS: nominal_upah, umk, ump, wage defaults, normalizeUpahForKontrak
        
        QS->>QS: calculateTunjangan()
        Note over QS: Loop daftar_tunjangan, ambil nominal dari collection
        
        QS->>QS: calculateBpjs()
        Note over QS: BPU? → 0. Cek program_bpjs. Base = max(upah, ump/umk). Hitung persentase. Cek opt-out.
        
        QS->>QS: calculateExtras()
        Note over QS: THR, Kompensasi, Tunjangan Holiday, Lembur, Insentif
        
        QS->>QS: calculateAllItems()
        Note over QS: Precompute _site_hc_cache. 4 items (kaporlap, devices, ohc, chemical). Cek manual HPP/COSS.
        
        QS->>QS: calculateFinalTotals()
        Note over QS: total_base_manpower, total_personil, sub_total_personil. RO? coss = 0.
        
        QS->>QS: populateDetailCalculation()
        Note over QS: → detail_calculations[detail_id] = hpp_data[], coss_data[]
    end
    
    QS->>QS: calculateFinancials(HPP)
    Note over QS: calculateBaseTotals() → calculateManagementFee() → calculateTaxes() → finalizeCalculations()
    QS->>QS: calculateFinancials(COSS)
    Note over QS: Sama, filter RO, suffix _coss
    
    Note over QS: === SECOND PASS (GROSS UP) ===
    QS->>QS: calculateBankInterestAndIncentive()
    Note over QS: bunga_bank_total, insentif_total dari summary
    
    QS->>QS: updateDetailsWithGrossUp()
    Note over QS: Set bunga_bank & insentif per detail. Recalculate calculateFinalTotals.
    
    QS->>QS: calculateFinancials(HPP) # second pass
    QS->>QS: calculateFinancials(COSS) # second pass
    
    QS-->>QC: return QuotationCalculationResult
    QC-->>FE: QuotationResource JSON
    
    Note over QS: TOTAL: ~12 batch queries + loop O(n) in-memory = ZERO N+1
```

---

## 7. STEP UPDATE SEQUENCE

```mermaid
sequenceDiagram
    participant FE as Frontend
    participant QSC as QuotationStepController
    participant QSS as QuotationStepService
    participant DB as Database
    
    FE->>QSC: POST /quotations-step/{id}/step/{step}
    
    Note over QSC: DB::transaction()
    QSC->>QSS: updateStep{step}(quotation, request)
    
    alt Step 1
        QSS->>DB: UPDATE sl_quotation SET jenis_kontrak
    else Step 2
        QSS->>DB: UPDATE sl_quotation SET contract + cuti data
    else Step 3
        QSS->>DB: Sync quotation_detail (bulk insert/update/soft-delete)
    else Step 4
        QSS->>DB: UPDATE global (mf_id, persentase, ppn)
        QSS->>DB: UPDATE position_data per detail
        QSS->>DB: UPSERT sl_quotation_management_fee (flags)
    else Step 5
        QSS->>DB: UPDATE sl_quotation_detail (bpjs flags)
        QSS->>DB: UPDATE sl_quotation (company, program_bpjs)
    else Step 6
        QSS->>DB: Sync sl_quotation_aplikasi
        QSS->>DB: Sync sl_quotation_devices (aplikasi items)
    else Step 7~9
        QSS->>QBS2: syncBarangData(kaporlap/devices/chemical)
    else Step 10
        QSS->>QBS2: syncBarangData(ohc)
        QSS->>DB: Sync training, update kunjungan
    else Step 11
        QSS->>QSS: updateAllQuotationData()
        Note over QSS: Bulk update HPP, COSS, Items, MF config
    else Step 12
        QSS->>QS: calculateQuotation() (final check)
        QSS->>DB: UPDATE step=100, status_quotation_id
        QSS->>DB: Insert requirements
        QSS->>DB: Notify dir sales if needed
        QSS->>DB: Soft delete old quotation if revisi
    end
    
    QSS-->>QSC: done
    QSC-->>FE: QuotationStepResource JSON
```

---

## 8. LEADS MODULE (30+ endpoints)

```mermaid
flowchart LR
    subgraph LEADS_ENDPOINTS["/leads endpoints"]
        CRUD["CRUD:<br/>list, view, add, update, delete, restore"]
        SALES["Sales:<br/>assignSales, removeSales<br/>availableSales, getSalesKebutuhan"]
        CHILD["Child:<br/>childLeads, saveChildLeads"]
        REL["Related:<br/>getQuotationByLead<br/>getPksByLead, getSpkByLead<br/>getCustomerActivityByLead"]
        IMPORT["Import/Export:<br/>import, exportExcel<br/>templateImport"]
        OTHER["Other:<br/>activateLead, leadsBelumAktif<br/>listTerhapus, availableQuotation<br/>generateNullKode"]
    end
    
    subgraph LEADS_SCOPE["Role-Based Filtering"]
        SUPER["Superadmin (role_id=2)<br/>→ Semua leads"]
        SALES_D["Sales D (role_id=29)<br/>→ Filter by tim_sales_d_id<br/>via sl_leads_kebutuhan"]
        SALES_L["Sales Leader (role_id=31)<br/>→ Filter by team members<br/>via tim_sales_detail"]
        BRANCH["Branch (role_id=4,5,6,8)<br/>→ Filter by branch_id"]
        CRM["CRM (role_id=54,55,56)<br/>→ Semua leads"]
    end
```

---

## 9. PKS MODULE

```mermaid
flowchart TD
    PKS_CREATE["PKS Created<br/>from Quotation"] --> PKS_DRAFT["status_pks_id = 1 (Draft)"]
    PKS_DRAFT --> PKS_APPROVED["status_pks_id = 2 (Approved)"]
    PKS_APPROVED --> PKS_ACTIVE["status_pks_id = 7 (Active)<br/>is_aktif = 1"]
    PKS_ACTIVE --> PKS_EXPIRED["status_pks_id = 3 (Expired)"]
    
    PKS_ACTIVE -->|Rekontrak| QUOT_NEW["New Quotation (rekontrak)<br/>→ Duplikasi data<br/>→ Site matching by name"]
    PKS_ACTIVE -->|Addendum| ADDENDUM["AddendumService<br/>→ Add new sites to existing PKS"]
    
    subgraph PKS_FEATURES["PKS Features"]
        PERJANJIAN["Perjanjian Management<br/>- Template generation<br/>- Version history<br/>- Compare revisions"]
        UPLOAD["Upload PKS document<br/>PDF, images"]
        APPROVAL["Approval workflow<br/>→ log_approval"]
        CHECKLIST["Submit checklist<br/>Operational readiness"]
    end
```

---

## 10. SPK MODULE

```mermaid
flowchart LR
    SPK_DRAFT["Draft"] --> SPK_OT1["OT1 Approve"]
    SPK_OT1 --> SPK_OT2["OT2 Approve"]
    SPK_OT2 --> SPK_OT3["OT3 Approve"]
    SPK_OT3 --> SPK_ACTIVE["Active"]
    
    subgraph SPK_SITES["Site Management"]
        SITE_LIST["getSiteAvailableList<br/>getSiteList<br/>getDeletedSpkSites"]
        SITE_DEL["deleteSite"]
    end
    
    subgraph SPK_REF["Reference"]
        AVAIL_QUOT["availableQuotation<br/>availableLeads"]
    end
```

---

## 11. SALES MODULE

```mermaid
flowchart LR
    subgraph SALES_TEAM["Tim Sales"]
        TS_CRUD["CRUD: list, show, store, update, destroy"]
        TS_MEMBERS["Members: getMembers, addMember<br/>removeMember, setLeader<br/>bulkAddMembers, getAvailableUsers"]
        TS_STATS["Statistics: getStatistics"]
    end
    
    subgraph SALES_ACTIVITY["Sales Activity"]
        SA_CRUD["CRUD: index, store, show, update, destroy"]
        SA_LEADS["getAvailableLeads, getKebutuhanByLeads"]
        SA_STATS["Stats: getStats"]
    end
    
    subgraph SALES_REVENUE["Sales Revenue"]
        SR_REV["Monthly Revenue, Summary<br/>By User, By Month"]
        SR_KPI["KPI Dashboard"]
    end
    
    subgraph SALES_TARGET["Sales Target"]
        ST_CRUD["API Resource (full CRUD)"]
    end
```

---

## 12. BUSINESS SUMMARY: 4 CORE CYCLES

```mermaid
flowchart TD
    subgraph CYCLE_1["① Lead → Quotation → PKS"]
        L1["LEADS<br/>(prospek customer)"] --> Q1["QUOTATION<br/>(penawaran harga)"]
        Q1 -->|Approved| P1["PKS<br/>(kontrak)"]
        P1 -->|Active| S1["SITE<br/>(operasional)"]
        S1 -->|Kontrak berakhir| L1
        S1 -->|Need new contract| Q1
    end
    
    subgraph CYCLE_2["② Quotation → SPK"]
        Q2["QUOTATION"] --> SPK["SPK<br/>(surat perintah kerja)"]
        SPK --> S2["SITE<br/>(pelaksanaan)"]
    end
    
    subgraph CYCLE_3["③ Sales Activity Pipeline"]
        SA1["LEADS → New"] --> SA2["Activity Tracking<br/>(call/meeting/followup)"]
        SA2 --> SA3["Quotation → Deal"]
        SA3 --> SA4["PKS → Customer"]
        SA4 --> SA5["Revenue Recognition"]
    end
    
    subgraph CYCLE_4["④ Approval & Notification"]
        EVENT["QuotationCreated"] --> DUPLICATE["ProcessQuotationDuplication<br/>(queue)"]
        DUPLICATE --> NOTIFY["QuotationNotificationService<br/>→ Email to Dir. Sales"]
        NOTIFY --> APPROVE["Approval (OT1 → OT2 → OT3)"]
        APPROVE -->|Overdue| ESCALATE["EscalateQuotationJob<br/>(delayed 1 day)"]
        APPROVE -->|Done| PKS["PKS Creation"]

        NOTIFY --> NOTIF_DB["LogNotification<br/>(in-app notification)"]
        APPROVE --> LOG_APP["LogApproval<br/>(approval trail)"]
    end
    
    CYCLE_1 --> CYCLE_4
    CYCLE_3 --> CYCLE_1
```

---

## 13. KEY NUMBERS SUMMARY

| Module | Controllers | Models | Services | Endpoints (≈) |
|--------|-------------|--------|----------|----------------|
| **Auth** | 2 | 5 | 0 | 6 |
| **Leads** | 1 | 10+ | 0 | 30+ |
| **Quotation** | 2 | 20+ | 6 | 25+ |
| **PKS** | 1 | 5+ | 1 | 25+ |
| **SPK** | 1 | 4+ | 0 | 15+ |
| **Sales** | 5 | 8+ | 1 | 30+ |
| **Customer** | 2 | 4+ | 0 | 8+ |
| **Master Data** | 18 | 30+ | 1 | 80+ |
| **Dashboard/Report** | 3 | 0 | 0 | 12+ |
| **System** | 4 | 8+ | 3 | 20+ |
| **Admin Panel** | 1 | 1 | 0 | 8+ |
| **Options** | 1 | 0 | 0 | 20+ |
| **Total** | **46** | **94** | **13** | **280+** |

---

## 14. APPROVAL FLOW HIERARCHY

```mermaid
flowchart LR
    subgraph LEVELS["Approval Levels"]
        OT1["OT1<br/>→ role_id 97 (SPV/Manager)"]
        OT2["OT2<br/>→ role_id 96 (GM/Direktur Sales)"]
        OT3["OT3<br/>→ role_id 40 (Finance)"]
    end
    
    subgraph CONDITIONS["Auto-OT2 triggers"]
        C1["BPJS tidak lengkap"]
        C2["Kompensasi/THR Tidak Ada"]
        C3["Upah custom < 85% UMK"]
        C4["TOP > 7 hari"]
        C5["Management Fee < threshold<br/>(kebutuhan1=7%, lainnya=6%)"]
        C6["Company ID = 17 (PT Indah Optima)"]
        C7["Total HC < minimum<br/>(kebutuhan1=5, kebutuhan2=10, kebutuhan3=5)"]
    end

    OT1 -->|Normal flow| OT2
    OT1 -->|Any condition matched| OT2_ESCALATED["OT2 (mandatory)"]
    OT2_ESCALATED --> OT2
    
    OT2 -->|Approved| OT3
    OT3 -->|Approved| DONE["PKS Ready"]
    
    OT2 -.->|24 hours no response| ESCALATE["Escalation to<br/>Direktur Utama"]
```

---

## 15. QUILL — KEY CONVENTIONS (from AGENTS.md)

| Convention | Rule |
|------------|------|
| **Controllers** | Thin, HTTP concerns only → delegate to Services |
| **Services** | Business logic, transactions, return DTOs/arrays |
| **Resources (3)** | `QuotationResource`, `QuotationCollection`, `QuotationStepResource` |
| **Requests** | Extend `BaseRequest` (not `FormRequest`) → consistent 422 format |
| **API Response** | `{ "success": true, "data": {}, "message": "..." }` |
| **SoftDeletes** | Every model |
| **Audit** | `created_by`, `updated_by`, `deleted_by` on every model |
| **Table prefix** | `sl_*` for business tables |
| **LeadsKebutuhan** | Use `updateOrCreate`, NOT `firstOrCreate` |
| **Quotation Multi-Site** | `$request->jumlah_site == "Single Site"` — exact case-sensitive match |
