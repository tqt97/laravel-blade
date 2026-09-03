<?php

return [
    'users' => [
        'messages' => [
            'created' => 'Đã tạo người dùng thành công.',
            'updated' => 'Đã cập nhật người dùng thành công.',
            'deleted' => 'Đã xóa người dùng thành công.',
            'bulk_deleted' => 'Đã xóa thành công :count người dùng.',
            'restored' => 'Đã khôi phục người dùng thành công.', 'bulk_restored' => 'Đã khôi phục thành công :count người dùng.',
            'force_deleted' => 'Đã xóa vĩnh viễn người dùng.', 'bulk_force_deleted' => 'Đã xóa vĩnh viễn :count người dùng.',
        ],
        'errors' => [
            'invalid_bulk_state' => 'Một hoặc nhiều người dùng đã chọn không còn ở đúng trạng thái. Hãy tải lại danh sách và thử lại.',
            'cannot_delete_self' => 'Bạn không thể tự xóa tài khoản của mình.',
            'last_admin' => 'Phải giữ lại ít nhất một quản trị viên.',
            'cannot_demote_self' => 'Bạn không thể tự gỡ quyền quản trị của tài khoản mình.',
            'cannot_demote_last_admin' => 'Quản trị viên cuối cùng phải được giữ quyền quản trị.',
        ],
    ],
];
