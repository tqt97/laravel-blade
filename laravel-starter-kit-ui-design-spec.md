# Laravel Starter Kit UI — Design Specification

Version: 1.0  
Language: Vietnamese  
Target: Laravel + Inertia/React + Tailwind/shadcn UI; có thể chuyển sang Blade

## 1. Mục tiêu thiết kế

Xây dựng giao diện có cảm giác giống Laravel Starter Kit hiện đại:

- Tối giản, nhiều khoảng thở, ưu tiên nội dung và khả năng đọc.
- Surface trung tính trắng/xám, viền mảnh, bo góc vừa phải.
- Laravel Red chỉ là điểm nhấn thương hiệu, không phủ toàn bộ giao diện.
- Hỗ trợ light/dark mode bằng design token, không hard-code màu trong component.
- Component có trạng thái đầy đủ: default, hover, focus, disabled, loading, error.

## 2. Nghiên cứu và quyết định visual

Laravel React Starter Kit chính thức hiện sử dụng React 19, TypeScript, Tailwind,
shadcn/ui và Radix UI. Hệ theme của starter kit tổ chức màu bằng CSS variables,
light/dark theme và OKLCH; các token chính gồm background, foreground, card,
popover, primary, secondary, muted, accent, destructive, border, input và ring.

Laravel brand red thường được nhận diện là `#FF2D20`, nhưng nên dùng như brand
accent. Màu hành động chính trong ứng dụng nghiệp vụ nên là đỏ đậm vừa phải để
đạt tương phản tốt hơn.

Nguồn tham khảo:

- [Laravel React Starter Kit](https://github.com/laravel/react-starter-kit)
- [Laravel Starter Kits documentation](https://laravel.com/docs/master/starter-kits)
- [Starter Kit theming source](https://github.com/laravel/react-starter-kit/blob/main/resources/css/app.css)
- [Laravel brand color reference](https://brandfetch.com/laravel.com)

## 3. Design tokens

### 3.1 Màu thương hiệu

| Token | Light | Dark | Cách dùng |
|---|---|---|---|
| `--brand` | `#FF2D20` | `#FF5A50` | Logo, accent, link nổi bật |
| `--brand-strong` | `#E3261C` | `#FF7068` | Primary button, active state |
| `--brand-soft` | `#FFF1EF` | `#3A1715` | Badge, selected background |
| `--brand-foreground` | `#FFFFFF` | `#210806` | Chữ trên brand button |

### 3.2 Semantic theme

```css
:root {
  --background: oklch(0.985 0.002 286);
  --foreground: oklch(0.205 0.015 285);
  --card: oklch(1 0 0);
  --card-foreground: oklch(0.205 0.015 285);
  --popover: oklch(1 0 0);
  --popover-foreground: oklch(0.205 0.015 285);
  --primary: oklch(0.56 0.22 28);
  --primary-foreground: oklch(0.99 0 0);
  --secondary: oklch(0.95 0.006 286);
  --secondary-foreground: oklch(0.29 0.015 285);
  --muted: oklch(0.95 0.006 286);
  --muted-foreground: oklch(0.50 0.018 285);
  --accent: oklch(0.96 0.025 28);
  --accent-foreground: oklch(0.32 0.12 28);
  --destructive: oklch(0.58 0.22 28);
  --border: oklch(0.90 0.008 286);
  --input: oklch(0.90 0.008 286);
  --ring: oklch(0.56 0.22 28);
  --radius: 0.625rem;
}

.dark {
  --background: oklch(0.145 0.008 286);
  --foreground: oklch(0.96 0.004 286);
  --card: oklch(0.18 0.009 286);
  --card-foreground: oklch(0.96 0.004 286);
  --popover: oklch(0.18 0.009 286);
  --popover-foreground: oklch(0.96 0.004 286);
  --primary: oklch(0.67 0.20 28);
  --primary-foreground: oklch(0.16 0.04 28);
  --secondary: oklch(0.25 0.01 286);
  --secondary-foreground: oklch(0.94 0.004 286);
  --muted: oklch(0.25 0.01 286);
  --muted-foreground: oklch(0.68 0.012 286);
  --accent: oklch(0.26 0.06 28);
  --accent-foreground: oklch(0.90 0.08 28);
  --destructive: oklch(0.65 0.19 25);
  --border: oklch(0.30 0.012 286);
  --input: oklch(0.30 0.012 286);
  --ring: oklch(0.67 0.20 28);
}
```

### 3.3 Spacing, typography, elevation

| Nhóm | Quy ước |
|---|---|
| Font | `Inter`, system-ui, -apple-system, Segoe UI, sans-serif |
| Body | 14–16px, line-height 1.5–1.6 |
| Heading | 600–700, tracking nhẹ (-0.015em) |
| Spacing | Bội số 4px; section desktop 32px, mobile 24px |
| Radius | Control 8px; card 12px; modal 16px; pill 999px |
| Shadow | Mặc định không shadow; dùng shadow nhẹ cho dropdown/modal |
| Border | 1px solid `var(--border)`; tránh border quá tương phản |
| Focus | 2px ring `var(--ring)` + offset 2px |

## 4. Layout đề xuất

### App shell

- Desktop: sidebar 248px, header 64px, content max-width 1280px.
- Tablet: sidebar thu gọn còn 72px hoặc chuyển thành drawer.
- Mobile: header 56px, sidebar thành sheet/drawer; content padding 16px.
- Background app dùng `--background`; card dùng `--card`, không dùng trắng cố định.
- Header sticky chỉ khi thực sự cần; luôn có border-bottom mảnh.

### Dashboard/content page

1. Breadcrumb hoặc page title.
2. Tiêu đề + mô tả ngắn + action chính bên phải.
3. Filter/search row.
4. Nội dung chính: card, table hoặc form.
5. Pagination/action footer.

## 5. Component rules

### Button

- Primary: nền `--primary`, chữ `--primary-foreground`; chỉ dùng cho action quan trọng nhất.
- Secondary: nền `--secondary`, không dùng đỏ.
- Outline: nền trong suốt, border `--border`.
- Ghost: không nền; hover dùng `--accent`.
- Destructive: dùng cho xóa/hủy, luôn có confirm nếu mất dữ liệu.
- Chiều cao: 36px compact, 40px default, 44px mobile/touch.
- Có icon cách chữ 8px; icon không được thay thế label trong action quan trọng.

### Form

- Label luôn hiển thị, không dùng placeholder thay cho label.
- Input cao 40px, padding ngang 12px, radius 8px.
- Focus không đổi layout; chỉ thêm ring.
- Error đặt ngay dưới field, màu destructive, kèm thông báo có hướng xử lý.
- Disabled giảm opacity nhưng vẫn giữ contrast đủ đọc.

### Card

- Padding 20–24px desktop, 16px mobile.
- Header card gồm title, description và action tùy chọn.
- Không lạm dụng card lồng card; phân nhóm bằng spacing trước khi thêm border.

### Table

- Header nền muted rất nhẹ, chữ nhỏ hơn body một bậc.
- Row height tối thiểu 52px; hover dùng accent rất nhẹ.
- Action column căn phải; mobile chuyển thành card/list hoặc horizontal scroll.
- Empty state phải có mô tả và action tiếp theo.

### Toast/dialog

- Toast cho kết quả ngắn, không thay thế validation trong form.
- Dialog destructive phải nêu rõ đối tượng, hậu quả và action xác nhận.
- Không tự đóng dialog khi request thất bại.

## 6. Trạng thái và accessibility

- Contrast mục tiêu: WCAG AA cho text thường và control chính.
- Mọi control keyboard-accessible; focus-visible phải nhìn thấy rõ.
- Không truyền tải trạng thái chỉ bằng màu; thêm icon/text.
- Loading dùng skeleton cho vùng nội dung; button dùng spinner và khóa submit chống double-click.
- Success: xanh lá tiết chế; warning: amber; error: đỏ; info: blue. Các màu này là semantic, không cạnh tranh với brand.
- Có `prefers-reduced-motion`; transition mặc định 150–200ms.

## 7. Tailwind mapping gợi ý

```txt
bg-background text-foreground
bg-card text-card-foreground border-border
bg-primary text-primary-foreground
bg-secondary text-secondary-foreground
bg-muted text-muted-foreground
bg-accent text-accent-foreground
text-destructive ring-ring
rounded-lg shadow-sm
focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2
```

## 8. Checklist triển khai

- [ ] Đưa toàn bộ màu vào CSS variables hoặc Tailwind theme.
- [ ] Không dùng trực tiếp `#FF2D20` cho mọi button/card.
- [ ] Có light/dark theme và tránh flash khi khởi tạo theme.
- [ ] Chuẩn hóa Button, Input, Select, Modal, Table, Toast trước khi làm page.
- [ ] Kiểm tra responsive ở 375px, 768px, 1024px và 1440px.
- [ ] Kiểm tra keyboard navigation, focus, contrast và screen reader label.
- [ ] Kiểm tra loading/error/empty/permission-denied cho từng màn hình.
- [ ] Dùng screenshot review để so sánh spacing, alignment và hierarchy.

## 9. Nguyên tắc chốt

“Laravel-like” nên được hiểu là sự kết hợp giữa nền tảng trung tính, typography
sạch, component có hệ thống, dark mode tốt và điểm nhấn đỏ cam có chủ đích; không
phải biến toàn bộ UI thành màu đỏ Laravel.
