<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhongBan extends Model
{
    protected $table = 'phong_bans';

    protected $fillable = [
        'ma',
        'ten',
        'thu_tu',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'thu_tu' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'phong_ban_id');
    }
}
