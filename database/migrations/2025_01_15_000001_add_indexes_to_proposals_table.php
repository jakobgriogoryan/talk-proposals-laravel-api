<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            // Add indexes for frequently queried columns
            if (! $this->indexExists('proposals', 'proposals_user_id_index')) {
                $table->index('user_id', 'proposals_user_id_index');
            }

            if (! $this->indexExists('proposals', 'proposals_status_index')) {
                $table->index('status', 'proposals_status_index');
            }

            if (! $this->indexExists('proposals', 'proposals_created_at_index')) {
                $table->index('created_at', 'proposals_created_at_index');
            }

            // Composite index for common query patterns (status + created_at)
            if (! $this->indexExists('proposals', 'proposals_status_created_at_index')) {
                $table->index(['status', 'created_at'], 'proposals_status_created_at_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropIndex('proposals_user_id_index');
            $table->dropIndex('proposals_status_index');
            $table->dropIndex('proposals_created_at_index');
            $table->dropIndex('proposals_status_created_at_index');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support information_schema, use PRAGMA instead
            try {
                $indexes = $connection->select("PRAGMA index_list({$table})");
                foreach ($indexes as $idx) {
                    if (isset($idx->name) && $idx->name === $index) {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                // If table doesn't exist yet or query fails, index doesn't exist
                return false;
            }

            return false;
        }

        // For MySQL/PostgreSQL, use information_schema
        try {
            $database = $connection->getDatabaseName();
            $tableName = $connection->getTablePrefix().$table;

            $result = $connection->select(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$database, $tableName, $index]
            );

            return isset($result[0]) && $result[0]->count > 0;
        } catch (\Exception $e) {
            // If information_schema query fails, assume index doesn't exist
            return false;
        }
    }
};

