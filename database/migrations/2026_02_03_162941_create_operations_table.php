<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Удаляем enum типы если остались от предыдущих миграций
        DB::statement('DROP TYPE IF EXISTS operation_status');
        DB::statement('DROP TYPE IF EXISTS operation_type');

        DB::statement("CREATE TYPE operation_type AS ENUM ('rename')");
        DB::statement("CREATE TYPE operation_status AS ENUM ('pending', 'processing', 'done', 'failed')");

        Schema::create('operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('host_id');
            $table->jsonb('payload')->default('{}');
            $table->string('idempotency_key', 255)->unique()->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('host_id')
                ->references('id')
                ->on('hosts')
                ->onDelete('cascade');

            $table->index('host_id');
        });

        DB::statement("ALTER TABLE operations ADD COLUMN type operation_type NOT NULL");
        DB::statement("ALTER TABLE operations ADD COLUMN status operation_status NOT NULL DEFAULT 'pending'");

        DB::statement('CREATE INDEX operations_status_idx ON operations (status) WHERE status IN (\'pending\', \'processing\')');

        DB::statement('CREATE INDEX operations_host_status_idx ON operations (host_id, status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');

        DB::statement('DROP TYPE IF EXISTS operation_status');
        DB::statement('DROP TYPE IF EXISTS operation_type');
    }
};
