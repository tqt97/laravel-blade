<?php

namespace App\Models\Cinema;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['screening_id', 'seat_type', 'price_minor_units', 'currency'])]
class ScreeningPrice extends Model
{
    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    protected function casts(): array
    {
        return ['price_minor_units' => 'integer'];
    }
}
