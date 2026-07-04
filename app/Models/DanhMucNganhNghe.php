<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DanhMucNganhNghe extends Model
{
    protected $table = 'danh_muc_nganh_nghes';

    protected $fillable = [
        'parent_id',
        'cap',
        'ma',
        'ten',
        'thu_tu',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cap' => 'integer',
            'thu_tu' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('thu_tu')->orderBy('ma');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }
}
