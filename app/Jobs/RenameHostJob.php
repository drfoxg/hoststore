<?php

namespace App\Jobs;

use App\Enums\OperationStatus;
use App\Jobs\Concerns\HasCorrelationId;
use App\Models\Host;
use App\Models\Operation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RenameHostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use HasCorrelationId;

    public int $tries = 3;
    public array $backoff = [5, 30, 60];

    public function __construct(
        public Operation $operation
    ) {
    }

    public function handle(): void
    {
        $this->setupCorrelationId();

        Log::info('RenameHostJob started', [
            'operation_id' => $this->operation->id,
            'host_id' => $this->operation->host_id,
        ]);

        // Идемпотентность: если уже done — ничего не делаем
        if ($this->operation->status === OperationStatus::Done) {
            Log::info('RenameHostJob skipped: already done', [
                'operation_id' => $this->operation->id,
            ]);
            return;
        }

        if ($this->operation->status === OperationStatus::Failed) {
            Log::info('RenameHostJob skipped: already failed', [
                'operation_id' => $this->operation->id,
            ]);
            return;
        }

        $this->operation->markAsProcessing();

        $host = $this->operation->host;
        $newHostname = $this->operation->payload['new_hostname'];

        Log::info('RenameHostJob processing', [
            'operation_id' => $this->operation->id,
            'old_hostname' => $host->hostname,
            'new_hostname' => $newHostname,
        ]);

        // Проверяем уникальность hostname
        $exists = Host::where('hostname', $newHostname)
            ->where('id', '!=', $host->id)
            ->exists();

        if ($exists) {
            $error = "Hostname '{$newHostname}' is already taken";
            $this->operation->markAsFailed($error);

            Log::warning('RenameHostJob failed: hostname taken', [
                'operation_id' => $this->operation->id,
                'new_hostname' => $newHostname,
            ]);
            return;
        }

        DB::transaction(function () use ($host, $newHostname) {
            $host->update(['hostname' => $newHostname]);
            $this->operation->markAsDone();
        });

        Log::info('RenameHostJob completed', [
            'operation_id' => $this->operation->id,
            'new_hostname' => $newHostname,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->setupCorrelationId();

        $this->operation->markAsFailed($e->getMessage());

        Log::error('RenameHostJob exception', [
            'operation_id' => $this->operation->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
