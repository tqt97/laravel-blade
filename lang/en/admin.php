<?php

return [
    'users' => [
        'messages' => [
            'created' => 'User created successfully.',
            'updated' => 'User updated successfully.',
            'deleted' => 'User deleted successfully.',
            'bulk_deleted' => ':count users deleted successfully.',
            'restored' => 'User restored successfully.', 'bulk_restored' => ':count users restored successfully.',
            'force_deleted' => 'User permanently deleted.', 'bulk_force_deleted' => ':count users permanently deleted.',
        ],
        'errors' => [
            'invalid_bulk_state' => 'One or more selected users are no longer in the required state. Refresh the list and try again.',
            'cannot_delete_self' => 'You cannot delete your own account.',
            'last_admin' => 'At least one administrator must remain.',
            'cannot_demote_self' => 'You cannot remove administrator access from your own account.',
            'cannot_demote_last_admin' => 'The last administrator must keep administrator access.',
        ],
    ],
];
