<?php

namespace App\Models\Cinema;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['concession_id', 'actor_id', 'quantity_delta', 'stock_before', 'stock_after', 'reason'])]
class ConcessionStockAdjustmentAudit extends Model
{
    public function concession(): BelongsTo
    {
        return $this->belongsTo(Concession::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['quantity_delta' => 'integer', 'stock_before' => 'integer', 'stock_after' => 'integer'];
    }
}
