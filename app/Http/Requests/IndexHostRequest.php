<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexHostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'page.size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page.after' => ['nullable', 'uuid'],
            'page.before' => ['nullable', 'uuid'],
        ];
    }

    public function searchQuery(): ?string
    {
        return $this->input('q');
    }

    public function pageSize(): int
    {
        return (int) $this->input('page.size', 20);
    }

    public function cursorAfter(): ?string
    {
        return $this->input('page.after');
    }

    public function cursorBefore(): ?string
    {
        return $this->input('page.before');
    }

    public function useKeyset(): bool
    {
        return $this->has('page.after') || $this->has('page.before');
    }
}
