<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'target_user_id', 'action', 'target_snapshot', 'metadata'])]
class UserManagementAudit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }
}
