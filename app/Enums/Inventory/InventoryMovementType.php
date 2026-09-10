<?php

namespace App\Enums\Inventory;

enum InventoryMovementType: string
{
    case Initial = 'initial';
    case Adjustment = 'adjustment';
    case SaleReserve = 'sale_reserve';
    case Release = 'release';
    case Refund = 'refund';
}
