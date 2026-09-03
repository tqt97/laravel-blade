<?php

namespace App\Enums\Admin;

enum UserRole: string
{
    case All = 'all';
    case Administrator = 'admin';
    case User = 'user';
}
