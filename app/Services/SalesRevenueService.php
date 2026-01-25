<?php

namespace App\Services;

use App\Models\Pks;
use App\Models\Quotation;
use App\Models\QuotationDetailCoss;
use App\Models\User;
use App\Models\Spk;
use App\Models\SpkSite;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesRevenueService
{
    /**
     * Menghitung akumulasi total invoice per sales per bulan
     * Versi yang sudah diperbaiki dengan multiple paths
     */
    public function calculateMonthlyRevenue(array $filters = [])
    {
        Log::info('Starting calculateMonthlyRevenue with filters:', $filters);

        // 1. Ambil semua user dengan role sales
        $salesUsers = User::whereIn('role_id', [29, 30, 31, 32, 33])
            ->when(isset($filters['user_id']), function ($query) use ($filters) {
                return $query->where('id', $filters['user_id']);
            })
            ->get(['id', 'full_name', 'role_id']);

        Log::info('Sales users found:', ['count' => $salesUsers->count()]);

        $result = [];

        foreach ($salesUsers as $user) {
            Log::info('Processing user:', ['user_id' => $user->id, 'name' => $user->full_name]);

            // 2. Ambil PKS yang terkait dengan user ini
            $pksList = $this->getPksBySalesUser($user, $filters);

            Log::info('PKS found for user:', ['user_id' => $user->id, 'count' => $pksList->count()]);

            $userMonthlyRevenue = [];

            foreach ($pksList as $pks) {
                Log::info('Processing PKS:', [
                    'pks_id' => $pks->id,
                    'nomor' => $pks->nomor,
                    'leads_id' => $pks->leads_id,
                    'quotation_id' => $pks->quotation_id,
                    'spk_id' => $pks->spk_id
                ]);

                // 3. Hitung total invoice dari quotationdetailcoss untuk PKS ini
                $totalInvoice = $this->calculateTotalInvoiceForPks($pks);

                Log::info('Total invoice for PKS:', ['pks_id' => $pks->id, 'total_invoice' => $totalInvoice]);

                if ($totalInvoice <= 0) {
                    Log::info('Skipping PKS - no invoice', ['pks_id' => $pks->id]);
                    continue;
                }

                // 4. Hitung durasi kontrak dalam bulan
                $contractDuration = $this->calculateContractDuration($pks);

                Log::info('Contract duration:', ['pks_id' => $pks->id, 'duration' => $contractDuration]);

                if ($contractDuration <= 0) {
                    Log::info('Skipping PKS - invalid duration', ['pks_id' => $pks->id]);
                    continue;
                }

                // 5. Hitung perolehan per bulan
                $monthlyRevenue = $totalInvoice / $contractDuration;

                Log::info('Monthly revenue:', ['pks_id' => $pks->id, 'monthly_revenue' => $monthlyRevenue]);

                // 6. Generate bulan-bulan dalam masa kontrak
                $monthlyBreakdown = $this->generateMonthlyBreakdown(
                    $pks->kontrak_awal,
                    $pks->kontrak_akhir,
                    $monthlyRevenue,
                    $filters
                );

                Log::info('Monthly breakdown:', ['pks_id' => $pks->id, 'breakdown_count' => count($monthlyBreakdown)]);

                // 7. Akumulasikan ke hasil
                foreach ($monthlyBreakdown as $month => $revenue) {
                    if (!isset($userMonthlyRevenue[$month])) {
                        $userMonthlyRevenue[$month] = 0;
                    }
                    $userMonthlyRevenue[$month] += $revenue;
                }
            }

            if (!empty($userMonthlyRevenue)) {
                $result[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->full_name,
                    'user_role' => $user->role_id,
                    'monthly_revenue' => $userMonthlyRevenue,
                    'total_revenue' => array_sum($userMonthlyRevenue),
                ];

                Log::info('User revenue calculated:', [
                    'user_id' => $user->id,
                    'months_count' => count($userMonthlyRevenue),
                    'total_revenue' => array_sum($userMonthlyRevenue)
                ]);
            } else {
                Log::info('No revenue data for user:', ['user_id' => $user->id]);
            }
        }

        // 8. Format dan sort hasil
        $formattedResult = $this->formatResult($result, $filters);

        Log::info('Final formatted result count:', ['count' => count($formattedResult)]);

        return $formattedResult;
    }

    /**
     * Mendapatkan PKS berdasarkan sales user dengan multiple paths
     */
    private function getPksBySalesUser(User $user, array $filters = [])
    {
        Log::info('Getting PKS for user:', ['user_id' => $user->id]);

        // Query builder untuk mendapatkan PKS yang terkait dengan sales
        $query = Pks::query()
            ->with(['leads', 'quotations', 'spk'])
            ->where('is_aktif', 1) // Hanya PKS aktif
            ->where(function ($query) use ($user) {
                // Path 1: Melalui leads langsung (tim_sales_d_id)
                $query->whereHas('leads', function ($q) use ($user) {
                    $q->whereHas('timSalesD', function ($subQ) use ($user) {
                        $subQ->where('user_id', $user->id);
                    });
                })
                    // Path 2: Melalui leads_kebutuhan (jika ada relasi ini)
                    ->orWhereHas('leads.leadsKebutuhan.timSalesD', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            });

        // Apply filters
        if (isset($filters['year'])) {
            $query->whereYear('kontrak_awal', '<=', $filters['year'])
                ->whereYear('kontrak_akhir', '>=', $filters['year']);
        }

        if (isset($filters['month'])) {
            $year = $filters['year'] ?? date('Y');
            $date = Carbon::create($year, $filters['month'], 1);
            $query->where('kontrak_awal', '<=', $date->endOfMonth())
                ->where('kontrak_akhir', '>=', $date->startOfMonth());
        }

        if (isset($filters['start_date'])) {
            $query->where('kontrak_akhir', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('kontrak_awal', '<=', $filters['end_date']);
        }

        $result = $query->orderBy('kontrak_awal', 'desc')->get();

        Log::info('PKS query result:', [
            'count' => $result->count(),
            'user_id' => $user->id,
            'filters' => $filters
        ]);

        // Debug: Log semua PKS yang ditemukan
        foreach ($result as $pks) {
            Log::debug('PKS found:', [
                'id' => $pks->id,
                'nomor' => $pks->nomor,
                'kontrak_awal' => $pks->kontrak_awal,
                'kontrak_akhir' => $pks->kontrak_akhir,
                'leads_id' => $pks->leads_id,
                'quotation_id' => $pks->quotation_id,
                'spk_id' => $pks->spk_id,
                'is_aktif' => $pks->is_aktif
            ]);
        }

        return $result;
    }

    /**
     * Menghitung total invoice dari quotationdetailcoss untuk PKS tertentu
     * PERBAIKAN: Mencari melalui SPK dan SpkSite
     */
    private function calculateTotalInvoiceForPks(Pks $pks): float
    {
        Log::debug('SalesRevenueService: Calculating total invoice for PKS', [
            'pks_id' => $pks->id,
            'leads_id' => $pks->leads_id,
            'quotation_id' => $pks->quotation_id,
            'spk_id' => $pks->spk_id
        ]);

        $totalInvoice = 0;
        $quotationIds = [];

        // PATH 1: Jika PKS memiliki quotation_id langsung
        // if ($pks->quotation_id) {
        //     $quotationIds[] = $pks->quotation_id;
        //     Log::debug('SalesRevenueService: Path 1 - Direct quotation from PKS', [
        //         'pks_id' => $pks->id,
        //         'quotation_id' => $pks->quotation_id
        //     ]);
        // }

        // PATH 2: Jika PKS memiliki spk_id, cari melalui SPK dan SpkSite
        if ($pks->spk_id) {
            Log::debug('SalesRevenueService: Path 2 - Looking through SPK', [
                'pks_id' => $pks->id,
                'spk_id' => $pks->spk_id
            ]);

            // 2a: Cari quotation langsung dari SPK (jika ada relasi langsung)
            $spkQuotations = Quotation::where('id', $pks->spk_id)->pluck('id');
            if ($spkQuotations->isNotEmpty()) {
                $quotationIds = array_merge($quotationIds, $spkQuotations->toArray());
                Log::debug('SalesRevenueService: Found quotations directly from SPK', [
                    'pks_id' => $pks->id,
                    'quotation_ids' => $spkQuotations->toArray()
                ]);
            }

            // 2b: Cari melalui SpkSite
            $spkSiteQuotations = SpkSite::where('spk_id', $pks->spk_id)
                ->whereNotNull('quotation_id')
                ->pluck('quotation_id');

            if ($spkSiteQuotations->isNotEmpty()) {
                $quotationIds = array_merge($quotationIds, $spkSiteQuotations->toArray());
                Log::debug('SalesRevenueService: Found quotations through SpkSite', [
                    'pks_id' => $pks->id,
                    'quotation_ids' => $spkSiteQuotations->toArray()
                ]);
            }

            // 2c: Cari SPK yang terkait, lalu cari quotation dari SPK tersebut
            $spk = Spk::find($pks->spk_id);
            if ($spk && $spk->quotation_id) {
                $quotationIds[] = $spk->quotation_id;
                Log::debug('SalesRevenueService: Found quotation from SPK model', [
                    'pks_id' => $pks->id,
                    'spk_id' => $spk->id,
                    'quotation_id' => $spk->quotation_id
                ]);
            }
        }

        // PATH 3: Cari melalui leads_id (quotation dengan leads yang sama)
        if ($pks->leads_id) {
            Log::debug('SalesRevenueService: Path 3 - Looking through leads', [
                'pks_id' => $pks->id,
                'leads_id' => $pks->leads_id
            ]);

            $leadsQuotations = Quotation::where('leads_id', $pks->leads_id)
                ->pluck('id');

            if ($leadsQuotations->isNotEmpty()) {
                $quotationIds = array_merge($quotationIds, $leadsQuotations->toArray());
                Log::debug('SalesRevenueService: Found quotations through leads', [
                    'pks_id' => $pks->id,
                    'quotation_ids' => $leadsQuotations->toArray()
                ]);
            }
        }

        // PATH 4: Cari melalui SPK yang terkait dengan leads yang sama (jika ada spk_id di PKS)
        if ($pks->leads_id && !$pks->spk_id) {
            // Cari SPK yang terkait dengan leads ini
            $relatedSpks = Spk::where('leads_id', $pks->leads_id)->pluck('id');

            if ($relatedSpks->isNotEmpty()) {
                foreach ($relatedSpks as $spkId) {
                    // Cari quotation dari SpkSite
                    $spkSiteQuotations = SpkSite::where('spk_id', $spkId)
                        ->whereNotNull('quotation_id')
                        ->pluck('quotation_id');

                    if ($spkSiteQuotations->isNotEmpty()) {
                        $quotationIds = array_merge($quotationIds, $spkSiteQuotations->toArray());
                        Log::debug('SalesRevenueService: Found quotations through related SPKs', [
                            'pks_id' => $pks->id,
                            'spk_id' => $spkId,
                            'quotation_ids' => $spkSiteQuotations->toArray()
                        ]);
                    }

                    // Cari quotation langsung dari SPK
                    $spk = Spk::find($spkId);
                    if ($spk && $spk->quotation_id) {
                        $quotationIds[] = $spk->quotation_id;
                    }
                }
            }
        }

        // Remove duplicates and empty values
        $quotationIds = array_unique(array_filter($quotationIds));

        Log::debug('SalesRevenueService: Final quotation IDs to check', [
            'pks_id' => $pks->id,
            'quotation_ids' => $quotationIds,
            'count' => count($quotationIds)
        ]);

        if (empty($quotationIds)) {
            Log::debug('SalesRevenueService: No quotation IDs found for PKS', ['pks_id' => $pks->id]);
            return 0.0;
        }

        // Hitung total invoice dari semua quotation yang ditemukan
        $totalInvoice = QuotationDetailCoss::whereIn('quotation_id', $quotationIds)
            ->sum('total_invoice');

        Log::debug('SalesRevenueService: Total invoice calculated', [
            'pks_id' => $pks->id,
            'quotation_ids' => $quotationIds,
            'total_invoice' => $totalInvoice
        ]);

        return (float) $totalInvoice;
    }

    /**
     * Versi simplified dari calculateTotalInvoiceForPks untuk debugging
     */
    private function calculateTotalInvoiceForPksSimple(Pks $pks): float
    {
        Log::debug('SalesRevenueService: Simplified calculation for PKS', [
            'pks_id' => $pks->id,
            'leads_id' => $pks->leads_id,
            'quotation_id' => $pks->quotation_id,
            'spk_id' => $pks->spk_id
        ]);

        // Coba semua kemungkinan path secara berurutan
        $paths = [];

        // Path 1: Direct quotation_id
        if ($pks->quotation_id) {
            $invoice = QuotationDetailCoss::where('quotation_id', $pks->quotation_id)
                ->sum('total_invoice');
            if ($invoice > 0) {
                Log::debug('SalesRevenueService: Path 1 success', [
                    'pks_id' => $pks->id,
                    'quotation_id' => $pks->quotation_id,
                    'invoice' => $invoice
                ]);
                return (float) $invoice;
            }
            $paths[] = ['path' => 'direct_quotation', 'invoice' => $invoice];
        }

        // Path 2: Through SpkSite
        if ($pks->spk_id) {
            $quotationIds = SpkSite::where('spk_id', $pks->spk_id)
                ->whereNotNull('quotation_id')
                ->pluck('quotation_id');

            if ($quotationIds->isNotEmpty()) {
                $invoice = QuotationDetailCoss::whereIn('quotation_id', $quotationIds)
                    ->sum('total_invoice');
                if ($invoice > 0) {
                    Log::debug('SalesRevenueService: Path 2 success', [
                        'pks_id' => $pks->id,
                        'spk_id' => $pks->spk_id,
                        'quotation_ids' => $quotationIds->toArray(),
                        'invoice' => $invoice
                    ]);
                    return (float) $invoice;
                }
                $paths[] = ['path' => 'spk_site', 'invoice' => $invoice, 'quotation_ids' => $quotationIds->toArray()];
            }
        }

        // Path 3: Through leads
        if ($pks->leads_id) {
            $quotationIds = Quotation::where('leads_id', $pks->leads_id)
                ->pluck('id');

            if ($quotationIds->isNotEmpty()) {
                $invoice = QuotationDetailCoss::whereIn('quotation_id', $quotationIds)
                    ->sum('total_invoice');
                if ($invoice > 0) {
                    Log::debug('SalesRevenueService: Path 3 success', [
                        'pks_id' => $pks->id,
                        'leads_id' => $pks->leads_id,
                        'quotation_ids' => $quotationIds->toArray(),
                        'invoice' => $invoice
                    ]);
                    return (float) $invoice;
                }
                $paths[] = ['path' => 'leads', 'invoice' => $invoice, 'quotation_ids' => $quotationIds->toArray()];
            }
        }

        // Path 4: Find SPK from leads and then SpkSite
        if ($pks->leads_id) {
            // Cari SPK yang terkait dengan leads ini
            $spkIds = Spk::where('leads_id', $pks->leads_id)->pluck('id');

            if ($spkIds->isNotEmpty()) {
                $allQuotationIds = [];
                foreach ($spkIds as $spkId) {
                    $siteQuotationIds = SpkSite::where('spk_id', $spkId)
                        ->whereNotNull('quotation_id')
                        ->pluck('quotation_id');
                    $allQuotationIds = array_merge($allQuotationIds, $siteQuotationIds->toArray());
                }

                $allQuotationIds = array_unique(array_filter($allQuotationIds));

                if (!empty($allQuotationIds)) {
                    $invoice = QuotationDetailCoss::whereIn('quotation_id', $allQuotationIds)
                        ->sum('total_invoice');
                    if ($invoice > 0) {
                        Log::debug('SalesRevenueService: Path 4 success', [
                            'pks_id' => $pks->id,
                            'leads_id' => $pks->leads_id,
                            'spk_ids' => $spkIds->toArray(),
                            'quotation_ids' => $allQuotationIds,
                            'invoice' => $invoice
                        ]);
                        return (float) $invoice;
                    }
                    $paths[] = ['path' => 'leads_to_spk_to_site', 'invoice' => $invoice, 'quotation_ids' => $allQuotationIds];
                }
            }
        }

        Log::debug('SalesRevenueService: All paths failed', [
            'pks_id' => $pks->id,
            'paths_tried' => $paths
        ]);

        return 0.0;
    }

    /**
     * Menghitung durasi kontrak dalam bulan
     */
    private function calculateContractDuration(Pks $pks): int
    {
        if (!$pks->kontrak_awal || !$pks->kontrak_akhir) {
            return 0;
        }

        try {
            $start = Carbon::parse($pks->kontrak_awal);
            $end = Carbon::parse($pks->kontrak_akhir);

            // Hitung selisih bulan
            $duration = $start->diffInMonths($end);

            // Jika tanggal akhir lebih besar dari tanggal awal dalam bulan yang sama,
            // tambahkan 1 bulan untuk bulan pertama
            if ($start->day > 1 || $end->day > $start->day) {
                $duration += 1;
            }

            return max(1, $duration); // Minimal 1 bulan
        } catch (\Exception $e) {
            Log::error('Error calculating contract duration:', [
                'pks_id' => $pks->id,
                'kontrak_awal' => $pks->kontrak_awal,
                'kontrak_akhir' => $pks->kontrak_akhir,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Generate breakdown per bulan selama masa kontrak
     */
    private function generateMonthlyBreakdown(
        $contractStart,
        $contractEnd,
        float $monthlyRevenue,
        array $filters = []
    ): array {
        if (!$contractStart || !$contractEnd) {
            return [];
        }

        try {
            $start = Carbon::parse($contractStart)->startOfMonth();
            $end = Carbon::parse($contractEnd)->endOfMonth();

            $period = CarbonPeriod::create($start, '1 month', $end);
            $breakdown = [];

            foreach ($period as $date) {
                $monthKey = $date->format('Y-m');

                // Apply filters jika ada
                $includeMonth = true;

                if (isset($filters['year']) && $date->year != $filters['year']) {
                    $includeMonth = false;
                }
                if (isset($filters['month']) && $date->month != $filters['month']) {
                    $includeMonth = false;
                }
                if (isset($filters['start_date']) && $date->format('Y-m-d') < $filters['start_date']) {
                    $includeMonth = false;
                }
                if (isset($filters['end_date']) && $date->format('Y-m-d') > $filters['end_date']) {
                    $includeMonth = false;
                }

                if ($includeMonth) {
                    $breakdown[$monthKey] = $monthlyRevenue;
                }
            }

            return $breakdown;
        } catch (\Exception $e) {
            Log::error('Error generating monthly breakdown:', [
                'contractStart' => $contractStart,
                'contractEnd' => $contractEnd,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Format hasil akhir
     */
    private function formatResult(array $result, array $filters = []): array
    {
        $formatted = [];

        foreach ($result as $userData) {
            foreach ($userData['monthly_revenue'] as $month => $revenue) {
                $formatted[] = [
                    'user_id' => $userData['user_id'],
                    'user_name' => $userData['user_name'],
                    'user_role' => $userData['user_role'],
                    'month' => $month,
                    'month_name' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                    'revenue' => round($revenue, 2),
                    'revenue_formatted' => 'Rp ' . number_format($revenue, 0, ',', '.'),
                ];
            }
        }

        // Sort by user_id dan bulan
        usort($formatted, function ($a, $b) {
            if ($a['user_id'] == $b['user_id']) {
                return $a['month'] <=> $b['month'];
            }
            return $a['user_id'] <=> $b['user_id'];
        });

        return $formatted;
    }

    /**
     * Versi optimized menggunakan Query Builder langsung
     */
    public function calculateMonthlyRevenueOptimized(array $filters = [])
    {
        Log::info('Starting calculateMonthlyRevenueOptimized with filters:', $filters);

        $query = DB::table('sl_pks as p')
            ->select([
                'u.id as user_id',
                'u.name as user_name',
                'u.role_id',
                DB::raw('DATE_FORMAT(p.kontrak_awal, "%Y-%m") as month_start'),
                DB::raw('DATE_FORMAT(p.kontrak_akhir, "%Y-%m") as month_end'),
                DB::raw('TIMESTAMPDIFF(MONTH, p.kontrak_awal, p.kontrak_akhir) + 1 as contract_months'),
                DB::raw('COALESCE(SUM(qdc.total_invoice), 0) as total_invoice')
            ])
            ->join('sl_leads as l', 'p.leads_id', '=', 'l.id')
            ->leftJoin('sl_tim_sales_details as tsd', 'l.tim_sales_d_id', '=', 'tsd.id')
            ->leftJoin('users as u', 'tsd.user_id', '=', 'u.id')
            ->leftJoin('sl_quotation as q', function ($join) {
                $join->on('p.quotation_id', '=', 'q.id')
                    ->orOn('q.leads_id', '=', 'p.leads_id');
            })
            ->leftJoin('sl_quotation_detail_coss as qdc', 'q.id', '=', 'qdc.quotation_id')
            ->where('p.is_aktif', 1)
            ->whereIn('u.role_id', [29, 30, 31, 32, 33])
            ->whereNotNull('p.kontrak_awal')
            ->whereNotNull('p.kontrak_akhir')
            ->where('qdc.total_invoice', '>', 0)
            ->groupBy('u.id', 'p.id', 'p.kontrak_awal', 'p.kontrak_akhir');

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('u.id', $filters['user_id']);
        }

        if (isset($filters['year'])) {
            $query->whereYear('p.kontrak_awal', '<=', $filters['year'])
                ->whereYear('p.kontrak_akhir', '>=', $filters['year']);
        }

        if (isset($filters['month'])) {
            $year = $filters['year'] ?? date('Y');
            $date = Carbon::create($year, $filters['month'], 1);
            $query->where('p.kontrak_awal', '<=', $date->endOfMonth())
                ->where('p.kontrak_akhir', '>=', $date->startOfMonth());
        }

        $results = $query->get();

        Log::info('Optimized query results count:', ['count' => $results->count()]);

        $result = [];

        foreach ($results as $row) {
            $contractMonths = max(1, $row->contract_months);
            $monthlyRevenue = $row->total_invoice / $contractMonths;

            // Generate monthly breakdown
            $start = Carbon::parse($row->month_start . '-01');
            $end = Carbon::parse($row->month_end . '-01');

            $period = CarbonPeriod::create($start, '1 month', $end);

            foreach ($period as $date) {
                $monthKey = $date->format('Y-m');

                if (!isset($result[$row->user_id][$monthKey])) {
                    $result[$row->user_id][$monthKey] = 0;
                }

                $result[$row->user_id][$monthKey] += $monthlyRevenue;
            }
        }

        // Format result
        $formatted = [];
        foreach ($result as $userId => $monthlyData) {
            $user = User::find($userId);

            foreach ($monthlyData as $month => $revenue) {
                $formatted[] = [
                    'user_id' => $userId,
                    'user_name' => $user->full_name ?? 'Unknown',
                    'user_role' => $user->role_id ?? 0,
                    'month' => $month,
                    'month_name' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                    'revenue' => round($revenue, 2),
                    'revenue_formatted' => 'Rp ' . number_format($revenue, 0, ',', '.'),
                ];
            }
        }

        Log::info('Optimized calculation complete:', ['result_count' => count($formatted)]);

        return $formatted;
    }

    /**
     * Mendapatkan summary revenue per sales
     */
    public function getSalesRevenueSummary(array $filters = []): array
    {
        Log::info('Getting sales revenue summary with filters:', $filters);

        $monthlyData = $this->calculateMonthlyRevenue($filters);

        $summary = [];
        foreach ($monthlyData as $data) {
            $userId = $data['user_id'];

            if (!isset($summary[$userId])) {
                $summary[$userId] = [
                    'user_id' => $userId,
                    'user_name' => $data['user_name'],
                    'total_revenue' => 0,
                    'month_count' => 0,
                    'months' => [],
                ];
            }

            $summary[$userId]['total_revenue'] += $data['revenue'];
            $summary[$userId]['month_count']++;
            $summary[$userId]['months'][$data['month']] = $data['revenue'];
        }

        // Format summary
        foreach ($summary as &$userSummary) {
            $userSummary['total_revenue_formatted'] = 'Rp ' . number_format($userSummary['total_revenue'], 0, ',', '.');
            $userSummary['average_monthly'] = $userSummary['month_count'] > 0
                ? $userSummary['total_revenue'] / $userSummary['month_count']
                : 0;
            $userSummary['average_monthly_formatted'] = 'Rp ' . number_format($userSummary['average_monthly'], 0, ',', '.');
        }

        Log::info('Sales revenue summary calculated:', ['user_count' => count($summary)]);

        return array_values($summary);
    }
}