<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCompany extends Model
{
    use HasFactory;

    protected $table = 'member_companies';

    protected $fillable = [
        'member_id',
        'doanh_nghiep_id',
        'date_join',
        'position',
        'investment_amount',
    ];

    protected function casts(): array
    {
        return [
            'investment_amount' => 'decimal:2',
        ];
    }

    /**
     * MemberCompany belongs to a Member.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * MemberCompany belongs to a DoanhNghiep.
     */
    public function doanhNghiep(): BelongsTo
    {
        return $this->belongsTo(DoanhNghiep::class);
    }
}
