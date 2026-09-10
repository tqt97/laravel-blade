<?php

namespace App\Models\Inventory;

use App\Models\Movie\Concession;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['concession_id', 'actor_id', 'quantity_delta', 'stock_before', 'stock_after', 'reason'])]
class StockAdjustmentAudit extends Model
{
    protected $table = 'concession_stock_adjustment_audits';

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
        return [
            'quantity_delta' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
        ];
    }
}
