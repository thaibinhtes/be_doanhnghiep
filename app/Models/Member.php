<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'cccd',
        'full_name',
        'birthday',
        'gender',
        'date_join',
        'status',
        'position',
        'investment_amount',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'investment_amount' => 'decimal:2',
        ];
    }

    /**
     * Member belongs to many DoanhNghieps.
     */
    public function doanhNghieps(): BelongsToMany
    {
        return $this->belongsToMany(DoanhNghiep::class, 'member_companies');
    }
}
