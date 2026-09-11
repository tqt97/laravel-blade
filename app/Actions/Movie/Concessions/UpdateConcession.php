<?php

namespace App\Actions\Movie\Concessions;

use App\Enums\Inventory\InventoryMovementType;
use App\Enums\Inventory\InventoryStockMode;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\StockAdjustmentAudit;
use App\Models\Movie\Concession;
use App\Models\User;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Support\Facades\DB;

final class UpdateConcession
{
    /** @param array<string, mixed> $attributes */
    public function execute(Concession $concession, array $attributes, User $actor): Concession
    {
        return DB::transaction(function () use ($concession, $attributes, $actor): Concession {
            $locked = Concession::query()->whereKey($concession->getKey())->lockForUpdate()->firstOrFail();

            $stockBefore = $locked->stock;
            $newStock = array_key_exists('stock', $attributes)
                ? ($attributes['stock'] === null ? null : (int) $attributes['stock'])
                : $stockBefore;
            $stockDelta = ($newStock ?? 0) - ($stockBefore ?? 0);

            if ($stockDelta !== 0 && blank($attributes['stock_reason'] ?? null)) {
                throw new BookingOperationFailed(__('cinema.admin.concession_stock_reason_required'));
            }

            $locked->update($this->catalogAttributes($attributes));
            if ($stockDelta !== 0) {
                StockAdjustmentAudit::query()->create([
                    'concession_id' => $locked->getKey(),
                    'actor_id' => $actor->getKey(),
                    'quantity_delta' => $stockDelta,
                    'stock_before' => $stockBefore,
                    'stock_after' => $newStock,
                    'reason' => $attributes['stock_reason'],
                ]);

                InventoryMovement::query()->create([
                    'concession_id' => $locked->getKey(),
                    'actor_id' => $actor->getKey(),
                    'type' => InventoryMovementType::Adjustment,
                    'stock_mode' => $newStock === null ? InventoryStockMode::Unlimited : InventoryStockMode::Finite,
                    'quantity_delta' => $stockDelta,
                    'stock_before' => $stockBefore,
                    'stock_after' => $newStock,
                    'reference' => 'admin-adjustment-'.$locked->getKey(),
                    'metadata' => ['reason' => $attributes['stock_reason']],
                ]);
            }

            return $locked->refresh();
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
