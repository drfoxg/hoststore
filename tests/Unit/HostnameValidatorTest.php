<?php

namespace Tests\Unit;

use App\Http\Requests\StoreHostRequest;
use App\Http\Requests\RenameHostRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HostnameValidatorTest extends TestCase
{
    #[DataProvider('validHostnames')]
    public function test_store_request_accepts_valid_hostnames(string $hostname): void
    {
        $request = new StoreHostRequest();
        $rules = $request->rules();

        $validator = Validator::make(
            ['hostname' => $hostname, 'ip' => '10.0.0.1'],
            $rules
        );

        $this->assertFalse(
            $validator->errors()->has('hostname'),
            "Hostname '{$hostname}' should be valid"
        );
    }

    #[DataProvider('invalidHostnames')]
    public function test_store_request_rejects_invalid_hostnames(string $hostname): void
    {
        $request = new StoreHostRequest();
        $rules = $request->rules();

        $validator = Validator::make(
            ['hostname' => $hostname, 'ip' => '10.0.0.1'],
            $rules
        );

        $this->assertTrue(
            $validator->errors()->has('hostname'),
            "Hostname '{$hostname}' should be invalid"
        );
    }

    #[DataProvider('validHostnames')]
    public function test_rename_request_accepts_valid_hostnames(string $hostname): void
    {
        $request = new RenameHostRequest();
        $rules = $request->rules();

        $validator = Validator::make(
            ['new_hostname' => $hostname],
            $rules
        );

        $this->assertFalse(
            $validator->errors()->has('new_hostname'),
            "Hostname '{$hostname}' should be valid for rename"
        );
    }

    #[DataProvider('invalidHostnames')]
    public function test_rename_request_rejects_invalid_hostnames(string $hostname): void
    {
        $request = new RenameHostRequest();
        $rules = $request->rules();

        $validator = Validator::make(
            ['new_hostname' => $hostname],
            $rules
        );

        $this->assertTrue(
            $validator->errors()->has('new_hostname'),
            "Hostname '{$hostname}' should be invalid for rename"
        );
    }

    public static function validHostnames(): array
    {
        return [
            'simple' => ['web01'],
            'with hyphen' => ['web-01'],
            'with numbers' => ['server123'],
            'single char' => ['a'],
            'two chars' => ['ab'],
            'fqdn' => ['web-01.example.com'],
            'subdomain' => ['app.staging.example.com'],
            'uppercase' => ['WEB-01'],
            'mixed case' => ['Web-Server-01'],
            'max label length 63' => [str_repeat('a', 63)],
            'numeric start' => ['1server'],
            'all numeric' => ['123'],
            'hyphen in middle' => ['my-cool-server'],
            'multiple subdomains' => ['a.b.c.d.e.example.com'],
        ];
    }

    public static function invalidHostnames(): array
    {
        return [
            'starts with hyphen' => ['-web01'],
            'ends with hyphen' => ['web01-'],
            'starts and ends with hyphen' => ['-web01-'],
            'only hyphen' => ['-'],
            'starts with dot' => ['.example.com'],
            'ends with dot' => ['example.'],
            'double dots' => ['web..example.com'],
            'contains space' => ['web 01'],
            'contains underscore' => ['web_01'],
            'contains special chars' => ['web@01'],
            'contains asterisk' => ['*.example.com'],
            'label too long 64' => [str_repeat('a', 64)],
            'hyphen at label start' => ['web.-invalid.com'],
            'hyphen at label end' => ['web.invalid-.com'],
        ];
    }
}
