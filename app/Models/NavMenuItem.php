<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavMenuItem extends Model
{
    protected $fillable = [
        'item_key',
        'parent_id',
        'label',
        'path',
        'icon',
        'permission_key',
        'permission_keys',
        'sort_order',
        'is_dashboard',
        'is_root_only',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'permission_keys' => 'array',
            'is_dashboard' => 'boolean',
            'is_root_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
