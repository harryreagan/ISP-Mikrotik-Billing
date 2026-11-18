<?php

namespace App\Console\Commands;

use App\Services\IspRevenueService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileRevenueCommand extends Command
{
    protected $signature = 'isp:reconcile-revenue {--year=} {--tenant=}';
    protected $description = 'Audit and reconcile Hotspot, PPPoE and Subscription revenues across all tenants from raw payment ledgers';

    public function handle(): int
    {
        $timezone = config('app.timezone') ?: 'Africa/Nairobi';
        $year = (int) ($this->option('year') ?: Carbon::now()->timezone($timezone)->year);
        $reqTenant = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;

        $this->info("=== HSmart ISP Revenue Ledger Audit (Year: {$year}, Timezone: {$timezone}) ===");

        if ($reqTenant !== null) {
            $report = IspRevenueService::getRevenueReport($reqTenant, $year);
            $this->renderReportTable("Tenant #{$reqTenant}", $report);
            return 0;
        }

        // 1. Super Admin Global Report
        $globalReport = IspRevenueService::getRevenueReport(null, $year);
        $this->renderReportTable("Global Platform Overview (All Tenants)", $globalReport);

        // 2. Tenant-by-Tenant Breakdown & Reconciliation Verification
        $this->newLine();
        $this->info("--- Tenant Isolation & Cross-Check ---");

        $tenants = Schema::hasTable('tenants') ? DB::table('tenants')->get(['id', 'name']) : collect();
        $tenantSums = [
            'hotspot' => 0.0,
            'pppoe'   => 0.0,
            'total'   => 0.0,
        ];

        $tenantRows = [];
        foreach ($tenants as $t) {
            $tReport = IspRevenueService::getRevenueReport((int) $t->id, $year);
            $tenantSums['hotspot'] += $tReport['hotspot_revenue'];
            $tenantSums['pppoe'] += $tReport['pppoe_revenue'];
            $tenantSums['total'] += $tReport['total_revenue'];

            $tenantRows[] = [
                'Tenant ID'   => $t->id,
                'ISP Name'    => $t->name,
                'Hotspot'     => 'KES ' . number_format($tReport['hotspot_revenue'], 2),
                'PPPoE'       => 'KES ' . number_format($tReport['pppoe_revenue'], 2),
                'Year Total'  => 'KES ' . number_format($tReport['total_revenue'], 2),
                'Reconciled?' => $tReport['reconciled'] ? '✓ YES' : '✗ MISMATCH',
            ];
        }

        $this->table(['Tenant ID', 'ISP Name', 'Hotspot', 'PPPoE', 'Year Total', 'Reconciled?'], $tenantRows);

        // 3. Mathematical Verification: Sum of Tenants vs Global Total
        $this->newLine();
        $this->info("--- Mathematical Integrity Check ---");
        $hotspotDiff = abs($globalReport['hotspot_revenue'] - $tenantSums['hotspot']);
        $pppoeDiff   = abs($globalReport['pppoe_revenue'] - $tenantSums['pppoe']);

        $this->line("Sum of Tenants Hotspot: KES " . number_format($tenantSums['hotspot'], 2) . " | Global Hotspot: KES " . number_format($globalReport['hotspot_revenue'], 2) . " [Diff: {$hotspotDiff}]");
        $this->line("Sum of Tenants PPPoE:   KES " . number_format($tenantSums['pppoe'], 2) . " | Global PPPoE:   KES " . number_format($globalReport['pppoe_revenue'], 2) . " [Diff: {$pppoeDiff}]");

        if ($hotspotDiff < 0.01 && $pppoeDiff < 0.01) {
            $this->info("✓ PERFECT RECONCILIATION: Sum of all tenants strictly equals global platform revenue totals.");
        } else {
            $this->warn("Note: Small variance detected (could be unassigned orphan records). Review raw sales.");
        }

        return 0;
    }

    protected function renderReportTable(string $title, array $report): void
    {
        $this->info("\n>> {$title}");
        $this->line("Total Revenue:  KES " . number_format($report['total_revenue'], 2));
        $this->line("Hotspot:        KES " . number_format($report['hotspot_revenue'], 2));
        $this->line("PPPoE:          KES " . number_format($report['pppoe_revenue'], 2));
        if ($report['is_super_admin']) {
            $this->line("Subscriptions:  KES " . number_format($report['subscription_revenue'], 2));
        }

        $headers = ['Month', 'Hotspot (KES)', 'PPPoE (KES)'];
        if ($report['is_super_admin']) {
            $headers[] = 'Subscriptions (KES)';
        }
        $headers[] = 'Month Total (KES)';

        $rows = [];
        foreach ($report['monthly'] as $m) {
            $r = [
                $m['name'],
                number_format($m['hotspot'], 2),
                number_format($m['pppoe'], 2),
            ];
            if ($report['is_super_admin']) {
                $r[] = number_format($m['subscriptions'], 2);
            }
            $r[] = number_format($m['total'], 2);
            $rows[] = $r;
        }

        // Summary footer row
        $footer = ['TOTAL (' . $report['year'] . ')', number_format($report['hotspot_revenue'], 2), number_format($report['pppoe_revenue'], 2)];
        if ($report['is_super_admin']) {
            $footer[] = number_format($report['subscription_revenue'], 2);
        }
        $footer[] = number_format($report['total_revenue'], 2);
        $rows[] = $footer;

        $this->table($headers, $rows);
    }
}
