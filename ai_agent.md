# AI Agent Team — CAIS Backend Project

## Hirarki Tim

```
                    ┌──────────────────────────────────┐
                    │  KAMU (Owner / Product Owner)    │
                    └──────────────┬───────────────────┘
                                   │
                    ┌──────────────▼───────────────────┐
                    │  AI PROJECT ORCHESTRATOR         │
                    │  .opencode/agents/orchestrator.md │
                    │  (saya / LLM utama)              │
                    │  Full codebase access             │
                    └──────────────┬───────────────────┘
                                   │
         ┌─────────────────────────┼──────────────────────────┐
         │                         │                          │
         ▼                         ▼                          ▼
   ┌─────────────┐          ┌─────────────┐           ┌─────────────┐
   │ Koordinator │          │ Koordinator │           │ Koordinator │
   │ Backend     │          │ Feature     │           │ Auth &      │
   │ Core        │          │ Domain      │           │ Security    │
   └──────┬──────┘          └──────┬──────┘           └──────┬──────┘
          │                        │                          │
   ┌──────┴──────┐          ┌──────┴──────┐           ┌──────┴──────┐
   │ Agent: DB   │          │ Agent:      │           │ Agent: Auth │
   │ Schema &    │          │ Leads Mgmt  │           │ & Token     │
   │ Migration   │          │             │           │             │
   ├─────────────┤          ├─────────────┤           ├─────────────┤
   │ Agent: API  │          │ Agent:      │           │ Agent: RBAC │
   │ Routes &    │          │ Quotation   │           │ & Permission│
   │ Controller  │          │ Engine      │           │             │
   ├─────────────┤          ├─────────────┤           └─────────────┘
   │ Agent:      │          │ Agent:      │
   │ Model &     │          │ PKS / SPK   │
   │ Eloquent    │          │             │
   ├─────────────┤          ├─────────────┤
   │ Agent:      │          │ Agent:      │
   │ Service     │          │ Sales &     │
   │ Layer       │          │ Customer    │
   ├─────────────┤          └─────────────┘
   │ Agent:      │
   │ Validation  │
   │ & Request   │
   └─────────────┘

         ┌─────────────────────┬──────────────────────┐
         │                     │                       │
         ▼                     ▼                       ▼
   ┌─────────────┐     ┌─────────────┐        ┌─────────────┐
   │ Koordinator │     │ Koordinator │        │ Koordinator │
   │ Testing     │     │ DevOps &    │        │ Dokumen &   │
   │             │     │ Deployment  │        │ Quality     │
   └──────┬──────┘     └──────┬──────┘        └──────┬──────┘
          │                   │                       │
   ┌──────┴──────┐     ┌──────┴──────┐        ┌──────┴──────┐
   │ Agent:      │     │ Agent:      │        │ Agent:      │
   │ Unit Test   │     │ Docker &    │        │ Swagger     │
   │             │     │ Container   │        │ /OpenAPI    │
   ├─────────────┤     ├─────────────┤        ├─────────────┤
   │ Agent:      │     │ Agent:      │        │ Agent: Code │
   │ Feature     │     │ CI/CD       │        │ Quality &   │
   │ Test        │     │ Pipeline    │        │ Refactoring │
   └─────────────┘     └─────────────┘        └─────────────┘
```

## Daftar Agent & File

### Backend Core
| Agent | File | Fokus |
|-------|------|-------|
| **Database Schema & Migration** | `.opencode/agents/db-schema-migration.md` | Migrations, indexes, seeders, multi-connection, query optimization |
| **API Routes & Controller** | `.opencode/agents/api-routes-controller.md` | Routes, controllers, middleware, response formatting |
| **Model & Eloquent** | `.opencode/agents/model-eloquent.md` | 96 models, relationships, scopes, eager loading |
| **Service Layer** | `.opencode/agents/service-layer.md` | 21 services, business logic extraction, refactoring |
| **Validation & Request** | `.opencode/agents/validation-request.md` | 19 form requests, FluentRule, custom validation |

### Feature Domain
| Agent | File | Fokus |
|-------|------|-------|
| **Leads Management** | `.opencode/agents/leads-management.md` | Leads CRUD, assign sales, import/export, kebutuhan |
| **Quotation Engine** | `.opencode/agents/quotation-engine.md` | Step wizard, calculation, HPP/COSS, approval, PDF |
| **PKS / SPK** | `.opencode/agents/pks-spk.md` | Contract wizard, template system, perjanjian, SPK |
| **Sales & Customer** | `.opencode/agents/sales-customer.md` | Activities, targets, revenue, teams, submissions |

### Auth & Security
| Agent | File | Fokus |
|-------|------|-------|
| **Auth & Token** | `.opencode/agents/auth-security.md` | Sanctum tokens, login/refresh, token expiry |
| **RBAC & Permission** | `.opencode/agents/auth-security.md` | cais_role_id, menu-permission, role management |

### Testing
| Agent | File | Fokus |
|-------|------|-------|
| **Backend Test** | `.opencode/agents/backend-test.md` | PHPUnit, feature tests, unit tests, fixtures |

### DevOps & Deployment
| Agent | File | Fokus |
|-------|------|-------|
| **DevOps & Deployment** | `.opencode/agents/devops-deployment.md` | Docker, docker-compose, GitLab CI, deploy scripts |

### Documentation & Quality
| Agent | File | Fokus |
|-------|------|-------|
| **Swagger / OpenAPI** | `.opencode/agents/swagger-docs.md` | l5-swagger annotations, doc generation |
| **Code Quality & Refactoring** | `.opencode/agents/code-quality-refactor.md` | Pint, refactoring, N+1 fixes, dead code removal |

## Cara Pakai

Saya (Orchestrator) adalah entry point. Untuk task spesifik, saya panggil agent:

```
task:
  description: "..."
  subagent_type: "quotation-engine"   # nama agent
  prompt: "detail task..."
```

Agent akan mengerjakan task sesuai domain-nya, lalu lapor hasil.

## Catatan
- Project ini **API-only backend** (Laravel 12). Frontend (React/Vue) dan Mobile (Flutter) ada di repo terpisah — tidak perlu agent untuk itu di sini.
- Semua agent punya akses `edit: allow` dan `bash: allow` — sesuaikan permission di frontmatter jika perlu dibatasi.
- Lihat `plan_quotation.md` untuk rencana refactoring spesifik quotation flow.
