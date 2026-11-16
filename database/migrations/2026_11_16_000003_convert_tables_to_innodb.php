<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tables were created as MyISAM (the MySQL server's default), which has no
 * transactions and silently ignores foreign keys. Convert everything to InnoDB
 * and add the foreign keys the earlier migrations declared.
 */
return new class extends Migration
{
    /** [table, column, referenced table, on delete] */
    protected array $foreignKeys = [
        ['users', 'company_id', 'companies', 'set null'],
        ['branches', 'company_id', 'companies', 'cascade'],
        ['roles', 'company_id', 'companies', 'cascade'],
        ['model_has_permissions', 'permission_id', 'permissions', 'cascade'],
        ['model_has_roles', 'role_id', 'roles', 'cascade'],
        ['role_has_permissions', 'permission_id', 'permissions', 'cascade'],
        ['role_has_permissions', 'role_id', 'roles', 'cascade'],
        ['social_accounts', 'company_id', 'companies', 'cascade'],
        ['social_accounts', 'parent_id', 'social_accounts', 'cascade'],
        ['social_accounts', 'connected_by', 'users', 'set null'],
        ['social_posts', 'social_account_id', 'social_accounts', 'cascade'],
        ['social_comments', 'social_post_id', 'social_posts', 'cascade'],
        ['social_insights', 'social_account_id', 'social_accounts', 'cascade'],
        ['social_conversations', 'social_account_id', 'social_accounts', 'cascade'],
        ['social_messages', 'social_conversation_id', 'social_conversations', 'cascade'],
        ['social_ads', 'social_account_id', 'social_accounts', 'cascade'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tables = DB::select(
            "SELECT TABLE_NAME AS name FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'"
        );
        foreach ($tables as $table) {
            DB::statement("ALTER TABLE `{$table->name}` ENGINE = InnoDB");
        }

        foreach ($this->foreignKeys as [$table, $column, $references, $onDelete]) {
            if ($this->hasForeignKey($table, $column)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($column, $references, $onDelete) {
                $t->foreign($column)->references('id')->on($references)->onDelete($onDelete);
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Only the foreign keys are removed; the tables stay InnoDB.
        foreach (array_reverse($this->foreignKeys) as [$table, $column]) {
            if ($this->hasForeignKey($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
            }
        }
    }

    protected function hasForeignKey(string $table, string $column): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 AS x FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$table, $column]
        );
    }
};
