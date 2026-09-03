<?php

namespace App\Enums\Admin;

enum UserVerificationStatus: string
{
    case All = 'all';
    case Verified = 'verified';
    case Unverified = 'unverified';
}
