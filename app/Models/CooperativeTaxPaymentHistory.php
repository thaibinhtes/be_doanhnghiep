<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CooperativeTaxPaymentHistory extends Model
{
    protected $table = 'cooperative_tax_payment_histories';

    protected $fillable = [
        'hop_tac_xa_id',
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

    public function hopTacXa(): BelongsTo
    {
        return $this->belongsTo(HopTacXa::class, 'hop_tac_xa_id');
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
