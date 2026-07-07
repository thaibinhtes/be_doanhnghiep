<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxUnit extends Model
{
    protected $table = 'tax_units';

    protected $fillable = [
        'unit_code',
        'unit_name',
    ];

    public function companyTaxManagements(): HasMany
    {
        return $this->hasMany(CompanyTaxManagement::class, 'tax_unit_id');
    }

    public function cooperativeTaxManagements(): HasMany
    {
        return $this->hasMany(CooperativeTaxManagement::class, 'tax_unit_id');
    }
}
