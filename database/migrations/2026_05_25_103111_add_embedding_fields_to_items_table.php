<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const int EMBEDDING_DIMENSIONS = 1536;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $connection = DB::connection($this->getConnection());
        $isPostgreSql = $connection->getDriverName() === 'pgsql';

        if ($isPostgreSql) {
            $schema->ensureVectorExtensionExists();
        }

        $schema->table('items', function (Blueprint $table) use ($isPostgreSql) {
            if ($isPostgreSql) {
                $table->vector('embedding', self::EMBEDDING_DIMENSIONS)->nullable();
            } else {
                $table->json('embedding')->nullable();
            }

            $table->string('embedding_source_hash', 64)->nullable();
            $table->timestamp('embedding_generated_at')->nullable();
        });

        if ($isPostgreSql) {
            $connection->statement(
                'create index if not exists items_embedding_hnsw_cosine_index on items using hnsw (embedding vector_cosine_ops) where embedding is not null'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());
        $connection = DB::connection($this->getConnection());

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement('drop index if exists items_embedding_hnsw_cosine_index');
        }

        $schema->table('items', function (Blueprint $table) {
            $table->dropColumn([
                'embedding',
                'embedding_source_hash',
                'embedding_generated_at',
            ]);
        });
    }
};
