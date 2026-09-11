<?php

namespace App\Actions\Movie\Concessions;

use App\Enums\Inventory\InventoryMovementType;
use App\Models\Inventory\InventoryMovement;
use App\Models\Movie\Concession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateConcession
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes, User $actor): Concession
    {
        return DB::transaction(function () use ($attributes, $actor): Concession {
            $concession = Concession::query()->create($this->catalogAttributes($attributes));

            if ($concession->stock !== null) {
                InventoryMovement::query()->create([
                    'concession_id' => $concession->getKey(),
                    'actor_id' => $actor->getKey(),
                    'type' => InventoryMovementType::Initial,
                    'quantity_delta' => (int) $concession->stock,
                    'stock_before' => null,
                    'stock_after' => (int) $concession->stock,
                    'reference' => 'concession-'.$concession->getKey(),
                ]);
            }

            return $concession;
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    private function catalogAttributes(array $attributes): array
    {
        unset($attributes['stock_reason']);

        $attributes['currency'] = strtoupper((string) $attributes['currency']);
        $attributes['image_url'] = filled($attributes['image_url'] ?? null) ? $attributes['image_url'] : null;
        $attributes['is_active'] = (bool) ($attributes['is_active'] ?? false);

        return $attributes;
    }
}
