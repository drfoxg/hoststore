<?php

namespace Tests\Feature;

use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём тестовые хосты
        Host::create(['hostname' => 'web-01.prod.example.com', 'ip' => '10.0.1.1', 'tags' => []]);
        Host::create(['hostname' => 'web-02.prod.example.com', 'ip' => '10.0.1.2', 'tags' => []]);
        Host::create(['hostname' => 'db-01.prod.example.com', 'ip' => '10.0.2.1', 'tags' => []]);
        Host::create(['hostname' => 'cache-01.staging.example.com', 'ip' => '10.0.3.1', 'tags' => []]);
    }

    public function test_search_finds_hosts_by_partial_hostname(): void
    {
        $response = $this->getJson('/api/hosts?q=web');

        $response
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.hostname', fn ($h) => str_contains($h, 'web'))
            ->assertJsonPath('data.1.hostname', fn ($h) => str_contains($h, 'web'));
    }

    public function test_search_finds_by_middle_of_hostname(): void
    {
        $response = $this->getJson('/api/hosts?q=prod');

        $response
            ->assertStatus(200)
            ->assertJsonCount(3, 'data'); // web-01.prod, web-02.prod, db-01.prod
    }

    public function test_search_finds_by_exact_ip(): void
    {
        $response = $this->getJson('/api/hosts?q=10.0.2.1');

        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.hostname', 'db-01.prod.example.com');
    }
}
