<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyTaxPaymentHistory extends Model
{
    protected $fillable = [
        'doanh_nghiep_id',
        'tax_unit_id',
        'tax_code',
        'tax_paid_at',
        'imported_by_user_id',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'tax_paid_at' => 'date',
        ];
    }

    public function doanhNghiep(): BelongsTo
    {
        return $this->belongsTo(DoanhNghiep::class, 'doanh_nghiep_id');
    }

    public function taxUnit(): BelongsTo
    {
        return $this->belongsTo(TaxUnit::class, 'tax_unit_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }
}
