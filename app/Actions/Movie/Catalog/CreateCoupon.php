<?php

namespace App\Actions\Movie\Catalog;

use App\Models\Movie\Coupon;

final class CreateCoupon
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): Coupon
    {
        $attributes['code'] = strtoupper((string) $attributes['code']);
        $attributes['currency'] = filled($attributes['currency'] ?? null)
            ? strtoupper((string) $attributes['currency'])
            : null;
        $attributes['is_active'] = (bool) ($attributes['is_active'] ?? false);

        return Coupon::query()->create($attributes);
    }
}
