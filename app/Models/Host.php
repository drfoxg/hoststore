<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $hostname
 * @property string $ip
 * @property array $tags
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<Operation> $operations
 */
class Host extends Model
{
    use HasUuids;

    protected $fillable = [
        'hostname',
        'ip',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    /**
     * Поиск по hostname с использованием pg_trgm индекса
     */
    public function scopeSearchHostname($query, string $search)
    {
        return $query->whereRaw('hostname ILIKE ?', ['%' . $search . '%']);
    }

    /**
     * Фильтр по тегу (jsonb containment)
     */
    public function scopeWithTag($query, string $tag)
    {
        return $query->whereRaw('tags @> ?', [json_encode([$tag])]);
    }

    /**
     * Фильтр по ключ-значение в тегах
     */
    public function scopeWithTagValue($query, string $key, string $value)
    {
        return $query->whereRaw('tags @> ?', [json_encode([$key => $value])]);
    }
}
