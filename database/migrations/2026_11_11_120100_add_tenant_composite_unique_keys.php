<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function hasIndex(string $table, string $indexName): bool
    {
        try {
            $database = DB::getDatabaseName();
            $result = DB::select(
                "SELECT COUNT(*) as cnt FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$database, $table, $indexName]
            );
            return ($result[0]->cnt ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function up(): void
    {
        // 1. routers: unique (tenant_id, name)
        if (Schema::hasTable('routers') && Schema::hasColumn('routers', 'tenant_id')) {
            if ($this->hasIndex('routers', 'routers_name_unique')) {
                Schema::table('routers', fn (Blueprint $table) => $table->dropUnique('routers_name_unique'));
            }
            if (!$this->hasIndex('routers', 'routers_tenant_name_unique')) {
                Schema::table('routers', fn (Blueprint $table) => $table->unique(['tenant_id', 'name'], 'routers_tenant_name_unique'));
            }
        }

        // 2. package_lists: unique (tenant_id, package)
        if (Schema::hasTable('package_lists') && Schema::hasColumn('package_lists', 'tenant_id')) {
            if ($this->hasIndex('package_lists', 'package_lists_package_unique')) {
                Schema::table('package_lists', fn (Blueprint $table) => $table->dropUnique('package_lists_package_unique'));
            }
            if (!$this->hasIndex('package_lists', 'packages_tenant_name_unique')) {
                Schema::table('package_lists', fn (Blueprint $table) => $table->unique(['tenant_id', 'package'], 'packages_tenant_name_unique'));
            }
        }

        // 3. p_p_p_secrets: unique (tenant_id, username)
        if (Schema::hasTable('p_p_p_secrets') && Schema::hasColumn('p_p_p_secrets', 'tenant_id')) {
            if ($this->hasIndex('p_p_p_secrets', 'p_p_p_secrets_username_unique')) {
                Schema::table('p_p_p_secrets', fn (Blueprint $table) => $table->dropUnique('p_p_p_secrets_username_unique'));
            }
            if (!$this->hasIndex('p_p_p_secrets', 'ppp_secrets_tenant_username_unique')) {
                Schema::table('p_p_p_secrets', fn (Blueprint $table) => $table->unique(['tenant_id', 'username'], 'ppp_secrets_tenant_username_unique'));
            }
        }

        // 4. hotspot_vouchers: unique (tenant_id, code)
        if (Schema::hasTable('hotspot_vouchers') && Schema::hasColumn('hotspot_vouchers', 'tenant_id')) {
            if ($this->hasIndex('hotspot_vouchers', 'hotspot_vouchers_code_unique')) {
                Schema::table('hotspot_vouchers', fn (Blueprint $table) => $table->dropUnique('hotspot_vouchers_code_unique'));
            }
            if (!$this->hasIndex('hotspot_vouchers', 'vouchers_tenant_code_unique')) {
                Schema::table('hotspot_vouchers', fn (Blueprint $table) => $table->unique(['tenant_id', 'code'], 'vouchers_tenant_code_unique'));
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('routers') && $this->hasIndex('routers', 'routers_tenant_name_unique')) {
            Schema::table('routers', fn (Blueprint $table) => $table->dropUnique('routers_tenant_name_unique'));
        }
        if (Schema::hasTable('package_lists') && $this->hasIndex('package_lists', 'packages_tenant_name_unique')) {
            Schema::table('package_lists', fn (Blueprint $table) => $table->dropUnique('packages_tenant_name_unique'));
        }
        if (Schema::hasTable('p_p_p_secrets') && $this->hasIndex('p_p_p_secrets', 'ppp_secrets_tenant_username_unique')) {
            Schema::table('p_p_p_secrets', fn (Blueprint $table) => $table->dropUnique('ppp_secrets_tenant_username_unique'));
        }
        if (Schema::hasTable('hotspot_vouchers') && $this->hasIndex('hotspot_vouchers', 'vouchers_tenant_code_unique')) {
            Schema::table('hotspot_vouchers', fn (Blueprint $table) => $table->dropUnique('vouchers_tenant_code_unique'));
        }
    }
};
