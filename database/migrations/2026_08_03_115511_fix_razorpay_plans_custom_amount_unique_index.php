<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('razorpay_plans')) {
            return;
        }

        // Drop every FK first so index/column changes cannot fail with MySQL 1215.
        $this->dropAllForeignKeys('razorpay_plans');
        $this->dropIndexIfExists('razorpay_plans', 'razorpay_plans_custom_amount_unique');

        if (! Schema::hasColumn('razorpay_plans', 'custom_plan_key')) {
            Schema::table('razorpay_plans', function (Blueprint $table) {
                $table->string('custom_plan_key', 80)->nullable()->after('amount');
            });
        }

        // Backfill only custom (no-package) plans. Package plans keep NULL (unique allows many NULLs).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("
                UPDATE razorpay_plans
                SET custom_plan_key = CONCAT(cause_id, '-', frequency, '-', CAST(amount AS CHAR))
                WHERE cause_package_id IS NULL
                  AND custom_plan_key IS NULL
                  AND cause_id IS NOT NULL
            ");
        } else {
            DB::table('razorpay_plans')
                ->whereNull('cause_package_id')
                ->whereNull('custom_plan_key')
                ->whereNotNull('cause_id')
                ->orderBy('id')
                ->get()
                ->each(function (object $plan): void {
                    DB::table('razorpay_plans')
                        ->where('id', $plan->id)
                        ->update([
                            'custom_plan_key' => $plan->cause_id.'-'.$plan->frequency.'-'.$plan->amount,
                        ]);
                });
        }

        $this->createUniqueIndexIfMissing(
            'razorpay_plans',
            'razorpay_plans_custom_amount_unique',
            ['custom_plan_key']
        );

        $this->ensureIndex('razorpay_plans', 'cause_id', 'razorpay_plans_cause_id_index');
        $this->ensureIndex('razorpay_plans', 'cause_package_id', 'razorpay_plans_cause_package_id_index');

        $this->ensureForeignKey(
            'razorpay_plans',
            'cause_id',
            'causes',
            'id',
            'cascade'
        );
        $this->ensureForeignKey(
            'razorpay_plans',
            'cause_package_id',
            'cause_packages',
            'id',
            'set null'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('razorpay_plans')) {
            return;
        }

        $this->dropAllForeignKeys('razorpay_plans');
        $this->dropIndexIfExists('razorpay_plans', 'razorpay_plans_custom_amount_unique');

        if (Schema::hasColumn('razorpay_plans', 'custom_plan_key')) {
            Schema::table('razorpay_plans', function (Blueprint $table) {
                $table->dropColumn('custom_plan_key');
            });
        }

        $this->createUniqueIndexIfMissing(
            'razorpay_plans',
            'razorpay_plans_custom_amount_unique',
            ['cause_id', 'frequency', 'amount']
        );

        $this->ensureForeignKey('razorpay_plans', 'cause_id', 'causes', 'id', 'cascade');
        $this->ensureForeignKey('razorpay_plans', 'cause_package_id', 'cause_packages', 'id', 'set null');
    }

    private function dropAllForeignKeys(string $table): void
    {
        foreach ($this->foreignKeyNames($table) as $constraintName) {
            Schema::table($table, function (Blueprint $blueprint) use ($constraintName) {
                $blueprint->dropForeign($constraintName);
            });
        }
    }

    /**
     * @return list<string>
     */
    private function foreignKeyNames(string $table): array
    {
        $names = collect(Schema::getForeignKeys($table))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        if ($names !== [] || Schema::getConnection()->getDriverName() !== 'mysql') {
            return array_values(array_unique($names));
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->pluck('CONSTRAINT_NAME')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);

        if (! $exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropUnique($indexName);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function createUniqueIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        $exists = collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);

        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName, $columns) {
            $blueprint->unique($columns, $indexName);
        });
    }

    private function ensureIndex(string $table, string $column, string $indexName): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $hasIndex = collect(Schema::getIndexes($table))
            ->contains(function (array $index) use ($column): bool {
                return ($index['columns'][0] ?? null) === $column;
            });

        if ($hasIndex) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName) {
            $blueprint->index($column, $indexName);
        });
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete
    ): void {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $exists = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'] ?? [], true));

        if ($exists) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $onDelete) {
                $foreign = $blueprint->foreign($column)->references($referencedColumn)->on($referencedTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'set null') {
                    $foreign->nullOnDelete();
                }
            });
        } catch (\Throwable) {
            // Do not block uniqueness fix if live FK recreation is incompatible.
        }
    }
};
