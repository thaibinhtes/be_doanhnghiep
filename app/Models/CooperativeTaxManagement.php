<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CooperativeTaxManagement extends Model
{
    protected $table = 'cooperative_tax_managements';

    protected $fillable = [
        'hop_tac_xa_id',
        'tax_code',
        'tax_unit_id',
    ];

    public function hopTacXa(): BelongsTo
    {
        return $this->belongsTo(HopTacXa::class, 'hop_tac_xa_id');
    }

    public function taxUnit(): BelongsTo
    {
        return $this->belongsTo(TaxUnit::class, 'tax_unit_id');
    }
}
