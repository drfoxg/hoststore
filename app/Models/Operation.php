<?php

namespace App\Models;

use App\Enums\OperationStatus;
use App\Enums\OperationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property OperationType $type
 * @property OperationStatus $status
 * @property string $host_id
 * @property array $payload
 * @property string|null $idempotency_key
 * @property string|null $error
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Host $host
 */
class Operation extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'status',
        'host_id',
        'payload',
        'idempotency_key',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'type' => OperationType::class,
            'status' => OperationStatus::class,
            'payload' => 'array',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    /**
     * Только незавершённые операции (для очереди)
     */
    public function scopePending($query)
    {
        return $query->where('status', OperationStatus::Pending);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', OperationStatus::Processing);
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [
            OperationStatus::Pending,
            OperationStatus::Processing,
        ]);
    }

    /**
     * Пометить как выполняющуюся
     */
    public function markAsProcessing(): bool
    {
        return $this->update(['status' => OperationStatus::Processing]);
    }

    /**
     * Пометить как завершённую
     */
    public function markAsDone(): bool
    {
        return $this->update([
            'status' => OperationStatus::Done,
            'error' => null,
        ]);
    }

    /**
     * Пометить как проваленную
     */
    public function markAsFailed(string $error): bool
    {
        return $this->update([
            'status' => OperationStatus::Failed,
            'error' => $error,
        ]);
    }
}
