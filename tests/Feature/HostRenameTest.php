<?php

namespace Tests\Feature;

use App\Enums\OperationStatus;
use App\Jobs\RenameHostJob;
use App\Models\Host;
use App\Models\User;
use App\Models\Operation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class HostRenameTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->admin()->create();
    }

    public function test_rename_full_cycle_to_done(): void
    {
        config(['queue.default' => 'sync']);

        $host = Host::create([
            'hostname' => 'old-hostname',
            'ip' => '10.0.0.1',
            'tags' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/admin/hosts/{$host->id}/rename", [
                'new_hostname' => 'new-hostname',
            ], [
                'Idempotency-Key' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(202);

        $operationId = $response->json('operation_id');

        $statusResponse = $this->actingAs($this->user)
            ->getJson("/api/operations/{$operationId}");

        $statusResponse
            ->assertStatus(200)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('error', null)
            ->assertJsonPath('host.hostname', 'new-hostname');

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

        $response1 = $this->actingAs($this->user)
            ->patchJson("/api/hosts/{$host->id}/rename", [
                'new_hostname' => 'renamed-host',
            ], [
                'Idempotency-Key' => $idempotencyKey,
            ]);

        $response1->assertStatus(202);
        $operationId1 = $response1->json('operation_id');

        $response2 = $this->actingAs($this->user)
            ->patchJson("/api/hosts/{$host->id}/rename", [
                'new_hostname' => 'renamed-host',
            ], [
                'Idempotency-Key' => $idempotencyKey,
            ]);

        $response2->assertStatus(202);
        $operationId2 = $response2->json('operation_id');

        $this->assertEquals($operationId1, $operationId2);
        $this->assertCount(1, Operation::all());
    }
}
