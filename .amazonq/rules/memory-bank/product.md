# Product Overview

## Project Purpose
CAIS Backend is a comprehensive business management system for outsourcing services companies. It manages the complete sales lifecycle from lead generation through quotation, contract management (PKS/SPK), and revenue tracking for workforce outsourcing services.

## Core Value Proposition
- Streamlines quotation creation with multi-step calculation workflows for labor costs, overhead, and pricing
- Automates contract lifecycle management from initial quotation to final agreement
- Provides real-time approval workflows and notifications for business processes
- Tracks sales activities, customer interactions, and revenue performance
- Manages complex pricing calculations including regional minimum wages (UMP/UMK), allowances, and management fees

## Key Features

### Lead & Customer Management
- Lead tracking with status progression and sales assignment
- Customer activity logging with email notifications
- Multi-site customer support for enterprise clients
- Company grouping for related business entities
- Sales team management with member assignments and statistics

### Quotation System
- Multi-step quotation workflow (12 steps) covering:
  - Basic information and site details
  - Human capital (HC) calculations
  - Position requirements and allowances
  - Equipment and supplies (Kaporlap, Devices, Chemicals)
  - Overhead costs (OHC) and pricing
- Support for quotation types: new (baru), revision (revisi), renewal (rekontrak), addendum
- Copy/duplicate quotations between sites
- PDF export and calculation summaries
- Approval workflow with multi-level authorization

### Contract Management
- PKS (Perjanjian Kerja Sama) - Partnership agreements
- SPK (Surat Perintah Kerja) - Work orders
- Contract templates with dynamic data population
- Document upload and storage
- Checklist submission for contract completion
- Contract activation and approval workflows

### Master Data Management
- Position and service type (Kebutuhan) configuration
- Regional wage data (UMP/UMK) by province/city
- Allowances (Tunjangan) and salary rules
- Equipment, chemicals, and supplies catalog
- Supplier and training provider management
- Management fee and payment terms (TOP) configuration

### Sales & Revenue Tracking
- Sales activity logging with visit types
- Monthly revenue reporting by user and period
- Target setting and achievement tracking
- Dashboard with approval notifications
- Performance statistics and analytics

### User & Access Control
- Role-based access control (RBAC) with menu permissions
- Multi-company/entity support (ION entities)
- User-specific email configuration for notifications
- Token-based authentication with refresh mechanism

## Target Users

### Primary Users
- **Sales Team**: Create and manage leads, quotations, and customer activities
- **Sales Managers**: Review quotations, approve contracts, track team performance
- **Finance Team**: Configure pricing rules, management fees, and payment terms
- **Operations Team**: Manage master data, positions, and service configurations

### User Roles
- Sales representatives with territory/customer assignments
- Team leaders managing sales teams
- Approvers for quotation and contract workflows
- Administrators for system configuration and master data

## Use Cases

### Quotation Creation Flow
1. Sales receives lead from potential customer
2. Create quotation with customer and site details
3. Configure positions and headcount requirements
4. System calculates labor costs based on regional wages
5. Add equipment, supplies, and overhead costs
6. Apply management fee and profit margins
7. Submit for approval through workflow
8. Generate PDF quotation for customer presentation

### Contract Management Flow
1. Customer accepts quotation
2. Create SPK (work order) from approved quotation
3. Submit checklist and required documents
4. Obtain approvals from authorized personnel
5. Upload signed contract documents
6. Activate contract for service delivery
7. Track contract status and renewals

### Sales Performance Tracking
1. Log daily sales activities and customer visits
2. Track quotation conversion rates
3. Monitor monthly revenue by sales person
4. Compare actual vs target achievement
5. Generate performance reports for management
