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
| `seat-picker.js` | `data-seat-picker` | chọn ghế, availability polling, combo, tổng tiền và modal xác nhận |
| `booking.js` | `data-booking-checkout` | countdown và checkout review read-only |

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

### 5.1 Showtime booking UI contract

Seat map phải hoạt động đúng trước và sau khi JavaScript hydrate. Blade render trạng thái hold hiện tại vào `data-seat-selected`, `data-seat-own-hold`, số ghế ban đầu, tiền ghế, tiền combo và trạng thái nút submit. JavaScript chỉ enhance state đó, không được khởi tạo từ selection rỗng rồi ghi đè dữ liệu server.

```blade
<div
    data-seat-picker
    data-seat-initial-count="{{ $initialSeatCount }}"
    data-seat-initial-seat-total="{{ $initialSeatTotal }}"
    data-seat-initial-combo-total="{{ $initialComboTotal }}"
>
    <button
        data-seat-id="{{ $screeningSeat->id }}"
        data-seat-selected="true"
        data-seat-own-hold="true"
        aria-pressed="true"
    >A1</button>
</div>
```

`data-seat-own-hold="true"` là ghế thuộc booking `held` hiện tại và được phép edit. Ghế này phải có màu active, không bị disable và không bị availability polling loại khỏi selection. Đây là điều kiện để user bấm “Chỉnh sửa ghế & combo” rồi giữ nguyên ghế mà vẫn thấy nút tiếp tục active.

Giới hạn được chặn sớm ở frontend để giảm lỗi submit:

- tối đa `config('booking.limits.max_seats')` ghế trong một booking;
- tổng quantity của tất cả combo không vượt quá `số vé × config('booking.limits.max_combos_per_ticket')`;
- nút `+` và nhập số trực tiếp đều bị clamp theo tồn kho và số vé;
- khi user giảm số ghế, combo dư sẽ tự giảm về giới hạn mới và hiển thị cảnh báo `role="status"`.

```js
const maximumComboQuantity = Math.min(
    Number(input.max),
    selectedSeatCount() * combosPerSeat,
);

if (quantity > maximumComboQuantity) {
    input.value = String(maximumComboQuantity);
    showLimitMessage(comboLimitLabel);
}
```

Đây chỉ là guard UX. `HoldSeatsRequest` và `AddConcessions` cùng đọc `config/booking.php`; request thủ công hoặc JavaScript bị tắt không thể vượt nghiệp vụ.

Summary luôn có đúng ba dòng ổn định: tiền ghế, tiền combo và tổng tiền. Modal xác nhận đặt ghế phía trên combo, nhóm các ghế cùng giá thành một dòng, nhấn mạnh tổng tiền bằng font đậm/màu semantic. Header và footer compact, phần detail có thể scroll để modal không quá cao.

Các control trong modal dùng SVG cố định kèm label accessible. Modal có `role="dialog"`, `aria-modal="true"`, heading/description, đóng bằng Escape và trả focus về nút mở. Màu chỉ hỗ trợ việc phân biệt; text và currency vẫn luôn hiển thị.

### 5.2 Login/resume state

Guest submit phải lưu cả ghế và combo vào `cinema.pending_hold` trước khi redirect login. `LoginResponse` ưu tiên `user.cinema.hold.resume` khi session key tồn tại; dashboard redirect không được ghi đè. Resume tạo hoặc reuse hold, áp dụng quantities, rồi quay về `/movies/{slug}/showtimes/{id}`. Nếu resume lỗi, payload được put lại session trước khi trả về seat map.

Response đầu tiên sau login phải render được: ghế cũ active và `aria-pressed="true"`, combo quantity cũ, tiền ghế, tiền combo, tổng tiền và nút continue enabled. Không suy luận state từ disabled HTML hoặc chỉ từ localStorage; backend vẫn là authority cho availability, giá, ownership, expiry và stock.

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
