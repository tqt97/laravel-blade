<?php

namespace App\Enums\Admin;

enum UserStatus: string
{
    case Active = 'active';
    case All = 'all';
    case Deleted = 'deleted';
}
