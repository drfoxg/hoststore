<?php

namespace App\Jobs;

use App\Enums\OperationStatus;
use App\Models\Host;
use App\Models\Operation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class RenameHostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 30, 60];

    public function __construct(
        public Operation $operation
    ) {
    }

    public function handle(): void
    {
        // Идемпотентность: если уже done — ничего не делаем
        if ($this->operation->status === OperationStatus::Done) {
            return;
        }

        // Если failed — тоже не повторяем
        if ($this->operation->status === OperationStatus::Failed) {
            return;
        }

        $this->operation->markAsProcessing();

        $host = $this->operation->host;
        $newHostname = $this->operation->payload['new_hostname'];

        // Проверяем уникальность hostname
        $exists = Host::where('hostname', $newHostname)
            ->where('id', '!=', $host->id)
            ->exists();

        if ($exists) {
            $this->operation->markAsFailed("Hostname '{$newHostname}' is already taken");
            return;
        }

        DB::transaction(function () use ($host, $newHostname) {
            $host->update(['hostname' => $newHostname]);
            $this->operation->markAsDone();
        });
    }

    public function failed(Throwable $e): void
    {
        $this->operation->markAsFailed($e->getMessage());
    }
}
