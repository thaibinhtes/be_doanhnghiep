<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyTaxManagement extends Model
{
    protected $table = 'company_tax_managements';

    protected $fillable = [
        'doanh_nghiep_id',
        'tax_code',
        'tax_unit_id',
        'tax_paid_at',
        'imported_by_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tax_paid_at' => 'date',
            'is_active' => 'boolean',
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
