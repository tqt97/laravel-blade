<?php

namespace App\Enums\Movie\Concessions;

enum InventoryMovementType: string
{
    case Initial = 'initial';
    case Adjustment = 'adjustment';
    case SaleReserve = 'sale_reserve';
    case Release = 'release';
    case Refund = 'refund';
}
