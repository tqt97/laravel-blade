<?php

namespace App\Enums\Inventory;

enum InventoryStockMode: string
{
    case Finite = 'finite';
    case Unlimited = 'unlimited';
}
