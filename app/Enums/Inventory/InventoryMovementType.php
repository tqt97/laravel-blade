<?php

namespace App\Enums\Inventory;

enum InventoryMovementType: string
{
    case Initial = 'initial';
    case Adjustment = 'adjustment';
    case Reserve = 'reserve';
    case Release = 'release';
    case Refund = 'refund';
}
