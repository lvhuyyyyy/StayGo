# Tài liệu Yêu cầu Chức năng — StayGo
> Hệ thống đặt phòng khách sạn tỉnh Quảng Ngãi  
> Phiên bản: 1.0 | Ngày cập nhật: 15/04/2026

---

## Mục lục

1. [Tổng quan hệ thống](#1-tổng-quan-hệ-thống)
2. [Vai trò người dùng](#2-vai-trò-người-dùng)
3. [Module Xác thực](#3-module-xác-thực)
4. [Module Trang khách (Frontend)](#4-module-trang-khách-frontend)
5. [Module Quản trị (Admin)](#5-module-quản-trị-admin)
6. [Quy trình nghiệp vụ](#6-quy-trình-nghiệp-vụ)
7. [Yêu cầu phi chức năng](#7-yêu-cầu-phi-chức-năng)
8. [Phụ lục: Sơ đồ luồng](#8-phụ-lục-sơ-đồ-luồng)

---

## 1. Tổng quan hệ thống

**StayGo** là ứng dụng web đặt phòng khách sạn trực tuyến dành cho khu vực tỉnh Quảng Ngãi. Hệ thống cho phép khách hàng tìm kiếm, đặt phòng và thanh toán online; đồng thời cung cấp bảng quản trị đầy đủ cho Admin vận hành hệ thống.

| Thông tin | Chi tiết |
|-----------|----------|
| Nền tảng  | Web (PHP/MySQL, XAMPP) |
| Đường dẫn | `/tour_khach_san_project/` |
| Admin     | `/tour_khach_san_project/admin_lvhuy_kontum/` |
| Ngôn ngữ  | Tiếng Việt |

---

## 2. Vai trò người dùng

| Vai trò | Mô tả | Quyền truy cập |
|---------|-------|----------------|
| **Guest** | Chưa đăng nhập | Xem khách sạn, phòng, blog; không đặt phòng |
| **User** | Đã đăng ký & đăng nhập | Đặt phòng, thanh toán, đánh giá, quản lý booking |
| **Admin** | Quản trị viên | Toàn quyền hệ thống qua bảng admin |

---

## 3. Module Xác thực

### 3.1 Đăng ký tài khoản
- **File:** `auth/register.php`
- Nhập: họ tên, email, mật khẩu, xác nhận mật khẩu
- Validation: email hợp lệ, mật khẩu ≥ 8 ký tự, email chưa tồn tại
- Sau đăng ký: chuyển về trang đăng nhập

### 3.2 Đăng nhập
- **File:** `auth/login.php`
- Đăng nhập bằng email + mật khẩu
- **Brute force protection:** khoá tài khoản 15 phút sau 5 lần thất bại
- Nhớ phiên qua session PHP
- Hỗ trợ CSRF token

### 3.3 Đăng nhập mạng xã hội
- **Google OAuth:** `auth/google_login.php`, `auth/google_callback.php`
- **Facebook OAuth:** `auth/facebook_login.php`, `auth/facebook_callback.php`
- Tự động tạo tài khoản nếu email chưa tồn tại

### 3.4 Quên mật khẩu / OTP
- **File:** `auth/forgot_password.php`, `auth/verify_otp.php`, `auth/reset_password.php`
- Gửi mã OTP về email
- OTP có thời hạn, dùng một lần
- Reset mật khẩu sau xác minh OTP thành công

### 3.5 Đăng xuất
- **File:** `auth/logout.php` (user), `auth/logout_admin.php` (admin)
- Huỷ session, redirect về trang chủ / trang đăng nhập admin

### 3.6 Đổi mật khẩu
- **File:** `pages/change_password.php` (user), `admin_lvhuy_kontum/change_password.php` (admin)
- Xác minh mật khẩu cũ trước khi đổi

---

## 4. Module Trang khách (Frontend)

### 4.1 Trang chủ
- **File:** `index.php` + `pages/home.php`
- Hiển thị: banner ưu đãi, địa điểm thịnh hành, khách sạn nổi bật
- Section địa điểm: lấy từ bảng `locations`, sắp xếp theo số khách sạn
- Section khách sạn nổi bật: `hotels` lọc `is_active=1`
- Chatbot hỗ trợ: nổi góc phải dưới

### 4.2 Tìm kiếm & Danh sách khách sạn
- **File:** `pages/hotels.php`
- Tìm theo: tên, địa điểm, giá min/max, số sao
- Hiển thị: ảnh, tên, địa chỉ, rating trung bình, giá từ
- Phân trang
- Nút "Yêu thích" (toggle, yêu cầu đăng nhập)

### 4.3 Chi tiết khách sạn
- **File:** `pages/hotel_detail.php`
- Hiển thị: gallery ảnh, thông tin, mô tả, rating tổng hợp
- Danh sách phòng còn trống theo ngày check-in/check-out
- Section đánh giá (review) của khách trước
- Nút đặt phòng → form booking

### 4.4 Đặt phòng
- **File:** xử lý trong `pages/hotel_detail.php` → `pages/payment.php`
- Chọn phòng, ngày check-in / check-out
- Tính tổng tiền = số đêm × giá phòng
- Nhập mã voucher (giảm giá nếu hợp lệ)
- Xác nhận đặt → tạo booking với `status = pending`
- Yêu cầu đăng nhập trước khi đặt

### 4.5 Thanh toán
- **File:** `pages/process_payment.php`, `pages/check_payment.php`
- Phương thức: chuyển khoản ngân hàng, tiền mặt tại quầy
- Sau thanh toán: booking chuyển sang `status = confirmed`
- Ghi nhận vào bảng `payments`

### 4.6 Quản lý đặt phòng của tôi
- **File:** `pages/my_bookings.php`
- Hiển thị danh sách booking của user đang đăng nhập
- Lọc theo trạng thái: tất cả / đang chờ / đã xác nhận / đã hoàn thành / đã huỷ
- Huỷ booking (nếu còn trong thời hạn)
- Yêu cầu hoàn tiền: `pages/request_refund.php`

### 4.7 Đánh giá
- **File:** `pages/reviews_handler.php`, `includes/review_section.php`
- User viết đánh giá sau khi hoàn thành stay
- Đánh giá: số sao (1–5) + nội dung text
- Một user chỉ đánh giá một lần mỗi booking
- Hiển thị trên trang chi tiết khách sạn

### 4.8 Yêu thích
- **File:** `pages/my_favorites.php`, `pages/toggle_favorite.php`
- Lưu danh sách khách sạn yêu thích vào bảng `favorites`
- Toggle (thêm/xoá) qua AJAX
- Hiển thị trang "Yêu thích của tôi"

### 4.9 Blog / Tin tức
- **File:** `pages/blog-list.php`, `pages/blog-detail.php`
- Danh sách bài viết: lọc theo danh mục, tìm kiếm
- Chi tiết bài: ảnh thumbnail, nội dung, tác giả, ngày đăng
- `view_count` tăng mỗi lần xem

### 4.10 Hồ sơ cá nhân
- **File:** `pages/profile.php`, `pages/edit_profile.php`
- Xem & chỉnh sửa: họ tên, email, số điện thoại, ảnh đại diện
- Upload avatar

### 4.11 Ưu đãi
- **File:** `pages/deals.php`
- Hiển thị các chương trình ưu đãi, khuyến mãi đang chạy

### 4.12 Liên hệ / Hỗ trợ
- **File:** `api/contact_support.php`
- Gửi yêu cầu hỗ trợ → lưu vào `support_requests`
- Widget nổi góc màn hình: `includes/contact_floating.php`

### 4.13 Chatbot
- **File:** `chatbot_api.php`, `includes/chatbot.php`
- Chatbot AI hỗ trợ khách hàng tìm khách sạn, trả lời câu hỏi

---

## 5. Module Quản trị (Admin)

> Đường dẫn: `/admin_lvhuy_kontum/` | Yêu cầu đăng nhập admin

### 5.1 Dashboard
- **File:** `dashboard.php`
- Thống kê tổng quan: tổng user, khách sạn, phòng, booking, doanh thu
- Số booking đang chờ xử lý, yêu cầu hoàn tiền, hỗ trợ chưa giải quyết
- Biểu đồ doanh thu 6 tháng (Chart.js)
- Top 5 khách sạn được đặt nhiều nhất
- Booking mới nhất

### 5.2 Quản lý Khách sạn
- **File:** `hotels.php`, `add_hotel.php`, `manage_hotel.php`
- Danh sách khách sạn: phân trang (15/trang), tìm kiếm
- Thêm/sửa: tên, địa chỉ, địa điểm, số sao, mô tả, trạng thái active
- Bật/tắt hiển thị khách sạn
- Nút "📊 Xem thống kê" → chuyển sang hotel_stats.php

### 5.3 Quản lý Phòng
- **File:** `rooms.php`, `edit_room.php`, `delete_room.php`
- Danh sách phòng: phân trang (15/trang), tìm theo tên phòng hoặc tên khách sạn
- Thêm/sửa: tên phòng, khách sạn, giá, sức chứa, mô tả, trạng thái
- Xoá phòng (kiểm tra ràng buộc booking)

### 5.4 Quản lý Ảnh khách sạn
- **File:** `hotel_images.php`, `upload_image.php`
- Upload nhiều ảnh cho từng khách sạn
- Đánh dấu ảnh chính (`is_primary`)
- Xoá ảnh

### 5.5 Quản lý Đặt phòng
- **File:** `bookings.php`, `booking_detail.php`, `booking_edit.php`, `update_booking.php`
- Danh sách booking: tìm kiếm, lọc theo trạng thái (pending/confirmed/completed/cancelled)
- Chi tiết booking: thông tin khách, phòng, ngày, thanh toán, lịch sử giao dịch
- Sửa booking: check-in/check-out, phòng, ghi chú; tính lại giá tự động
- Thay đổi trạng thái: xác nhận / hoàn thành / huỷ

### 5.6 Quản lý Thanh toán
- **File:** `payments.php`, `update_payment.php`
- Danh sách thanh toán: tìm kiếm, lọc theo phương thức (`payment_method`), trạng thái, ngày
- Chi tiết: khách sạn, phòng, khách hàng, số tiền, phương thức, trạng thái
- Cập nhật trạng thái thanh toán

### 5.7 Quản lý User
- **File:** `users.php`, `edit_user.php`, `delete_user.php`
- Danh sách user: phân trang (15/trang), tìm theo tên/email/điện thoại
- Lọc theo Role (Admin/User) và Trạng thái (Hoạt động/Tạm dừng/Bị cấm)
- Cột "Trạng thái hoạt động": badge trực quan + nút toggle inline
  - **Hoạt động** → nút `⏸ Tạm dừng` + `🚫 Cấm`
  - **Tạm dừng** → nút `▶ Kích hoạt` + `🚫 Cấm`
  - **Bị cấm** → nút `✅ Bỏ cấm`
- Không thể toggle/xoá tài khoản Admin
- Thêm/sửa/xoá user; modal xác nhận xoá

### 5.8 Quản lý Blog
- **File:** `blog_list.php`, `blog_form.php`
- Danh sách bài viết: tìm theo tiêu đề/tác giả, lọc danh mục, phân trang (12/trang)
- Thêm/sửa bài: tiêu đề, nội dung, thumbnail, danh mục, trạng thái xuất bản
- Xoá bài viết

### 5.9 Quản lý Đánh giá
- **File:** `reviews.php`
- Xem toàn bộ đánh giá của khách
- Duyệt / ẩn đánh giá không phù hợp

### 5.10 Yêu cầu Hoàn tiền
- **File:** `refund_requests.php`
- Danh sách booking có `refund_requested = 1`
- Xử lý: chấp thuận / từ chối hoàn tiền

### 5.11 Yêu cầu Hỗ trợ
- **File:** `support_requests.php`
- Xem yêu cầu từ khách hàng
- Cập nhật trạng thái xử lý (pending / resolved)

### 5.12 Thống kê Khách sạn
- **File:** `hotel_stats.php`
- Chọn khách sạn từ dropdown
- Hiển thị: tổng booking, doanh thu, rating trung bình, tỉ lệ lấp đầy
- Biểu đồ đường: doanh thu theo 6 tháng
- Biểu đồ cột: doanh thu từng loại phòng
- Bảng 10 booking gần nhất + 5 đánh giá gần nhất

### 5.13 Voucher
- **File:** `vouchers.php`
- Tạo mã giảm giá: code, loại (% hoặc số tiền cố định), giá trị, đơn tối thiểu, số lần dùng, ngày hết hạn
- Bật/tắt voucher (`is_active`)
- Danh sách với phân trang, sửa/xoá inline

### 5.14 Nhật ký Hoạt động
- **File:** `activity_log.php`
- Ghi lại mọi thao tác quan trọng của admin
- Lọc theo: từ khoá, loại hành động, khoảng thời gian
- Phân trang; màu sắc phân biệt loại action

### 5.15 Cài đặt Hệ thống
- **File:** `site_settings.php`
- Quản lý cấu hình theo nhóm: Thông tin chung, Mạng xã hội, Đặt phòng, Hệ thống
- Các key: tên web, email, điện thoại, địa chỉ, link Facebook/Zalo, phí huỷ (%), đặt trước tối đa (ngày), chế độ bảo trì
- Lưu ngay khi submit form

### 5.16 Tải báo cáo
- **File:** `download_report.php`
- Xuất báo cáo doanh thu / booking ra file (Excel/PDF)

---

## 6. Quy trình nghiệp vụ

### 6.1 Luồng Đặt phòng

```
Khách xem danh sách khách sạn
    → Chọn khách sạn → Xem chi tiết & phòng
    → Chọn phòng + ngày check-in/out
    → [Chưa đăng nhập] → Chuyển trang đăng nhập → Quay lại
    → Nhập voucher (tuỳ chọn)
    → Xác nhận đặt → Tạo booking (status=pending)
    → Chọn phương thức thanh toán
    → Thanh toán → booking chuyển (status=confirmed)
    → Admin xác nhận → (status=confirmed/completed)
```

### 6.2 Luồng Huỷ & Hoàn tiền

```
User huỷ booking từ "Đặt phòng của tôi"
    → booking chuyển (status=cancelled)
    → [Đã thanh toán] → User gửi yêu cầu hoàn tiền
    → Admin xem refund_requests.php
    → Admin duyệt → Xử lý hoàn tiền ngoài hệ thống
    → Cập nhật trạng thái
```

### 6.3 Luồng Quản lý User

```
Admin vào users.php
    → Xem danh sách / tìm kiếm / lọc role & trạng thái
    → Tạm dừng tài khoản (status=inactive): user không đăng nhập được
    → Cấm tài khoản (is_banned=1): khoá hoàn toàn
    → Bỏ cấm → Kích hoạt lại bình thường
```

### 6.4 Trạng thái Booking

```
pending → confirmed → completed
pending → cancelled
confirmed → cancelled (với điều kiện)
```

| Trạng thái | Ý nghĩa |
|------------|---------|
| `pending`   | Chờ xác nhận / chờ thanh toán |
| `confirmed` | Đã xác nhận, đã thanh toán |
| `completed` | Đã check-out, hoàn thành |
| `cancelled` | Đã huỷ |

### 6.5 Trạng thái Thanh toán

| Trạng thái | Ý nghĩa |
|------------|---------|
| `pending`   | Chờ thanh toán |
| `paid`      | Đã thanh toán |
| `refunded`  | Đã hoàn tiền |
| `failed`    | Thanh toán thất bại |

---

## 7. Yêu cầu phi chức năng

### 7.1 Bảo mật
- CSRF token trên tất cả form POST
- Brute force protection: khoá IP sau 5 lần đăng nhập sai (15 phút)
- Escape toàn bộ đầu vào với `real_escape_string()` / `htmlspecialchars()`
- Session kiểm tra vai trò trước khi truy cập admin
- Không thể xoá / toggle tài khoản Admin
- File `includes/security.php` tập trung xử lý bảo mật

### 7.2 Giao diện
- Responsive (mobile-friendly)
- Ngôn ngữ: Tiếng Việt toàn bộ
- Encoding: UTF-8
- Admin panel: dark sidebar + card layout
- Thông báo thành công/lỗi hiển thị inline, tự ẩn sau 3 giây

### 7.3 Hiệu năng
- Phân trang tất cả danh sách lớn (15 hoặc 12 bản ghi/trang)
- Query tối ưu: dùng COUNT riêng thay vì fetch all
- Ảnh upload lưu trực tiếp vào thư mục `assets/`

### 7.4 Nhật ký
- Mọi thao tác quan trọng của admin được ghi vào bảng `activity_log`
- Helper: `includes/activity_helper.php` → `log_activity($conn, $action, $target, $id, $detail)`

---

## 8. Phụ lục: Sơ đồ luồng

### Sơ đồ trang chính

```
index.php (trang chủ)
├── pages/hotels.php         — Danh sách khách sạn
│   └── pages/hotel_detail.php — Chi tiết + đặt phòng
│       └── pages/payment.php  — Thanh toán
├── pages/blog-list.php      — Danh sách blog
│   └── pages/blog-detail.php
├── pages/deals.php          — Ưu đãi
├── pages/my_bookings.php    — Đặt phòng của tôi (login)
├── pages/my_favorites.php   — Yêu thích (login)
├── pages/profile.php        — Hồ sơ (login)
└── auth/
    ├── login.php / register.php
    ├── forgot_password.php → verify_otp.php → reset_password.php
    ├── google_login.php / facebook_login.php
    └── logout.php
```

### Sơ đồ Admin panel

```
admin_lvhuy_kontum/
├── dashboard.php            — Tổng quan
├── hotels.php               — Khách sạn
│   ├── add_hotel.php / manage_hotel.php
│   ├── hotel_images.php
│   └── hotel_stats.php
├── rooms.php                — Phòng
│   └── edit_room.php
├── bookings.php             — Đặt phòng
│   ├── booking_detail.php
│   ├── booking_edit.php
│   └── update_booking.php
├── payments.php             — Thanh toán
├── users.php                — User
│   └── edit_user.php
├── blog_list.php            — Blog
│   └── blog_form.php
├── reviews.php              — Đánh giá
├── refund_requests.php      — Hoàn tiền
├── support_requests.php     — Hỗ trợ
├── vouchers.php             — Voucher
├── activity_log.php         — Nhật ký
└── site_settings.php        — Cài đặt
```

---

*Tài liệu này mô tả các chức năng đã được triển khai trong hệ thống StayGo tính đến ngày 15/04/2026.*
