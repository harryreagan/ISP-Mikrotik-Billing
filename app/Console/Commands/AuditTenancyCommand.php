<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditTenancyCommand extends Command
{
    protected $signature = 'tenancy:audit';
    protected $description = 'Audit all database tables and models for multi-tenant isolation and tenant_id column';

    public function handle(): int
    {
        $this->info("=== MULTI-TENANT ISOLATION AUDIT ===");

        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $tableKey = 'Tables_in_' . $dbName;

        $results = [];

        foreach ($tables as $t) {
            $tableName = $t->$tableKey ?? reset($t);
            $hasTenantId = Schema::hasColumn($tableName, 'tenant_id');
            $rowCount = DB::table($tableName)->count();

            $tenantBreakdown = [];
            if ($hasTenantId && $rowCount > 0) {
                try {
                    $tenantBreakdown = DB::table($tableName)
                        ->selectRaw('tenant_id, count(*) as cnt')
                        ->groupBy('tenant_id')
                        ->pluck('cnt', 'tenant_id')
                        ->toArray();
                } catch (\Throwable $e) {}
            }

            $results[] = [
                'table' => $tableName,
                'has_tenant_id' => $hasTenantId ? 'YES' : 'NO',
                'rows' => $rowCount,
                'breakdown' => json_encode($tenantBreakdown),
            ];
        }

        $this->table(['Table', 'Has tenant_id', 'Rows', 'Tenant Breakdown'], $results);

        return 0;
    }
}
