<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('hosts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('hostname', 253)->unique();
            $table->jsonb('tags')->default('{}');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE hosts ADD COLUMN ip inet NOT NULL');

        // CHECK constraint для валидации hostname (RFC 1123)
        // Разрешает: буквы, цифры, дефисы, точки
        // Запрещает: начало/конец с дефиса, двойные точки, длина метки > 63
        DB::statement("
            ALTER TABLE hosts 
            ADD CONSTRAINT hosts_hostname_format_check 
            CHECK (
                hostname ~ '^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$'
            )
        ");

        DB::statement('CREATE INDEX hosts_hostname_trgm_idx ON hosts USING GIN (hostname gin_trgm_ops)');

        DB::statement('CREATE INDEX hosts_tags_gin_idx ON hosts USING GIN (tags)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hosts');
    }
};
