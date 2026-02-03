<?php

namespace Tests\Feature;

use App\Enums\OperationStatus;
use App\Jobs\RenameHostJob;
use App\Models\Host;
use App\Models\Operation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class HostRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_rename_full_cycle_to_done(): void
    {
        // Используем sync очередь — Job выполнится сразу
        config(['queue.default' => 'sync']);

        $host = Host::create([
            'hostname' => 'old-hostname',
            'ip' => '10.0.0.1',
            'tags' => [],
        ]);

        $response = $this->patchJson("/api/hosts/{$host->id}/rename", [
            'new_hostname' => 'new-hostname',
        ], [
            'Idempotency-Key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(202);

        $operationId = $response->json('operation_id');

        // Проверяем статус операции
        $statusResponse = $this->getJson("/api/operations/{$operationId}");

        $statusResponse
            ->assertStatus(200)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('error', null)
            ->assertJsonPath('host.hostname', 'new-hostname');

        // Проверяем что хост переименован в БД
        $this->assertDatabaseHas('hosts', [
            'id' => $host->id,
            'hostname' => 'new-hostname',
        ]);

        $this->assertDatabaseMissing('hosts', [
            'hostname' => 'old-hostname',
        ]);
    }

    public function test_rename_idempotency_returns_same_operation(): void
    {
        Queue::fake();

        $host = Host::create([
            'hostname' => 'test-host',
            'ip' => '10.0.0.1',
            'tags' => [],
        ]);

        $idempotencyKey = Str::uuid()->toString();

        // Первый запрос
        $response1 = $this->patchJson("/api/hosts/{$host->id}/rename", [
            'new_hostname' => 'renamed-host',
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response1->assertStatus(202);
        $operationId1 = $response1->json('operation_id');

        // Повторный запрос с тем же ключом
        $response2 = $this->patchJson("/api/hosts/{$host->id}/rename", [
            'new_hostname' => 'renamed-host',
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response2->assertStatus(202);
        $operationId2 = $response2->json('operation_id');

        // Должен вернуться тот же operation_id
        $this->assertEquals($operationId1, $operationId2);

        // В БД только одна операция
        $this->assertCount(1, Operation::all());
    }
}
