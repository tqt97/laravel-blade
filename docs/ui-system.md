# UI system và frontend runtime

## 1. Blade push và bundle contract

Các layout Blade hiện push cùng một Vite entrypoint:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

Entry point này được dùng ở:

- `components/layouts/auth.blade.php` cho admin.
- `components/layouts/guest.blade.php` cho login/register/2FA.
- `welcome.blade.php` cho public landing.

`app.js` chỉ composition module. Mỗi module phải tự kiểm tra hook và return sớm nếu page không dùng tính năng đó. Vì vậy shared bundle không tạo thêm listener cho page không có password/modal/toast.

## 2. Module map

| Module | Blade hook | Chức năng |
|---|---|---|
| `common.js` | `data-theme-toggle`, `data-password-*`, `data-modal` | theme, password, modal |
| `admin-shell.js` | `data-admin-shell`, `data-sidebar-*` | sidebar desktop/mobile |
| `language-menus.js` | `data-language-menu` | đổi locale |
| `user-selection.js` | `data-user-selection` | select all/bulk actions |

Không import module vào Blade riêng lẻ vì dễ tạo duplicate runtime và khó kiểm soát thứ tự khởi tạo.

## 3. Event listener và tối ưu

Các nguyên tắc đang áp dụng:

- `initPasswordControls`, `initModals`, `initToasts` return sớm nếu không có selector.
- Language menu gom outside-click handler thay vì gắn một `document` listener cho từng menu.
- Admin shell chỉ bind khi tồn tại `data-admin-shell`.
- Modal form chỉ tạo khi user confirm, không tạo hidden form cho từng table row.
- Body scroll chỉ unlock khi cả modal và mobile sidebar đều đã đóng.

Đoạn tạo hidden input dùng DOM API:

```js
const appendHiddenInput = (name, value) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.append(input);
};
```

Không đưa CSRF token, method hoặc ID vào `innerHTML`. Inline SVG password icon là template cố định, không nhận dữ liệu user.

## 4. User table partials

`admin/users/index.blade.php` chỉ orchestration. Markup được tách thành:

```text
admin/users/_filters.blade.php
admin/users/_row.blade.php
admin/users/_actions.blade.php
admin/users/_status-badge.blade.php
```

Business authorization không nằm trong partial. Partial chỉ render control; server-side middleware, Form Request, Policy và Action vẫn là security boundary.

## 5. Accessibility contract

Filter phải giữ cặp `label for` và `input/select id`:

```blade
<label for="users-role">{{ __('ui.users.role') }}</label>
<select id="users-role" name="role">...</select>
```

Các rule bắt buộc:

- icon-only button có `title`/`aria-label`;
- row checkbox mô tả user cụ thể;
- modal có `role=dialog`, `aria-modal`, heading và description;
- focus-visible không bị tắt;
- status luôn có text, không chỉ dùng màu;
- UI phải đọc được ở dark mode, zoom 200% và mobile width.

## 6. Semantic color tokens

Màu được khai báo trong `resources/css/app.css`, view không tự định nghĩa màu brand:

```text
background / foreground
card / card-foreground
muted / muted-foreground
primary / primary-soft / primary-strong
success / success-soft / success-foreground
warning / warning-soft / warning-foreground
destructive / destructive-foreground
border / input / ring
```

Primary action là xanh dương; destructive action là đỏ; brand đỏ chỉ dành cho logo/brand mark.

## 7. Landing page

`welcome.blade.php` đã được thay bằng landing page semantic dùng chung Vite asset. Không còn CSS inline mặc định Laravel và không có style bundle riêng. Khi landing page phát triển thêm nội dung động, nên tách các card thành Blade component và cân nhắc entrypoint riêng để không tải admin interaction trên public page.

## 8. Performance checklist

- Bảng dùng pagination, không render unbounded collection.
- User row chỉ select cột cần thiết.
- Search MySQL dùng FULLTEXT; SQLite fallback phục vụ test/local.
- Không có async request theo từng keypress.
- Không load UI framework hoặc thư viện icon ngoài.
- Vite build hiện tạo JS khoảng 9 KB và CSS khoảng 76 KB trước gzip; cần đo lại khi thêm dependency.
- `fontaine` là optional warning của Vite, không phải build failure.

## 9. Verification

### User-management safety UX

The user table mirrors, but never replaces, the server-side invariants:

- the signed-in administrator's active row hides delete and disables bulk selection;
- editing the signed-in administrator keeps the administrator checkbox checked and disabled, with a localized warning;
- restore remains available for a trashed row, while force delete is hidden for the signed-in user;
- modal destructive actions do not force focus to the close or confirm button on open, trap focus when keyboard navigation enters the modal, close on Escape, and return focus to the original trigger.

These controls reduce accidental actions and explain why an action is unavailable. Every mutation is still authorized and validated by the backend because HTML/JavaScript can be bypassed.

```bash
npm run lint
npm run build
php artisan view:cache
php artisan test --compact
git diff --check
```

Browser E2E cho keyboard/focus/responsive chưa được cài trong project. Khi bổ sung browser test, cần kiểm tra modal, sidebar, filter label, long translation, dark mode và JavaScript-disabled fallback.
