<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Hostname;

class StoreHostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hostname' => [
                'required',
                'string',
                'max:253',
                // RFC 1123: буквы, цифры, дефисы, точки
                new Hostname(),
                'unique:hosts,hostname',
            ],
            'ip' => [
                'required',
                'ip', // IPv4 и IPv6
            ],
            'tags' => [
                'sometimes',
                'array',
            ],
            'tags.*' => [
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'hostname.regex' => 'Hostname must be a valid RFC 1123 hostname',
            'hostname.unique' => 'This hostname is already registered',
            'ip.ip' => 'Invalid IP address format',
        ];
    }
}
