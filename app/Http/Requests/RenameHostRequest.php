<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Hostname;

class RenameHostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_hostname' => [
                'required',
                'string',
                'max:253',
                new Hostname(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_hostname.regex' => 'Hostname must be a valid RFC 1123 hostname',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->header('Idempotency-Key'))) {
                $validator->errors()->add('Idempotency-Key', 'Idempotency-Key header is required');
            }
        });
    }

    public function idempotencyKey(): string
    {
        return $this->header('Idempotency-Key');
    }
}
