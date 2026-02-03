<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Host;

class HostCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_host_with_valid_data(): void
    {
        $payload = [
            'hostname' => 'web-01.example.com',
            'ip' => '192.168.1.10',
            'tags' => ['web', 'production'],
        ];

        $response = $this->postJson('/api/hosts', $payload);

        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'hostname',
                    'ip',
                    'tags',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.hostname', 'web-01.example.com')
            ->assertJsonPath('data.ip', '192.168.1.10')
            ->assertJsonPath('data.tags', ['web', 'production']);

        $this->assertDatabaseHas('hosts', [
            'hostname' => 'web-01.example.com',
            'ip' => '192.168.1.10',
        ]);
    }

    public function test_cannot_create_host_with_invalid_ip(): void
    {
        $payload = [
            'hostname' => 'web-01',
            'ip' => 'not-an-ip',
        ];

        $response = $this->postJson('/api/hosts', $payload);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ip']);
    }

    public function test_cannot_create_host_with_duplicate_hostname(): void
    {
        Host::create([
            'hostname' => 'existing-host',
            'ip' => '10.0.0.1',
            'tags' => [],
        ]);

        $payload = [
            'hostname' => 'existing-host',
            'ip' => '10.0.0.2',
        ];

        $response = $this->postJson('/api/hosts', $payload);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hostname']);
    }
}
