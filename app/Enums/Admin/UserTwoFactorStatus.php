<?php

namespace App\Enums\Admin;

enum UserTwoFactorStatus: string
{
    case All = 'all';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
