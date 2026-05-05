# Tài liệu Cơ sở dữ liệu — StayGo

> Database: `tour_khach_san` · Engine: InnoDB · Charset: utf8mb4  
> Cập nhật lần cuối: 15/04/2026

---

## Mục lục

1. [Sơ đồ quan hệ (ERD)](#sơ-đồ-quan-hệ)
2. [Danh sách bảng](#danh-sách-bảng)
3. [Chi tiết từng bảng](#chi-tiết-từng-bảng)
   - [users](#1-users)
   - [locations](#2-locations)
   - [hotels](#3-hotels)
   - [hotel_images](#4-hotel_images)
   - [rooms](#5-rooms)
   - [bookings](#6-bookings)
   - [payments](#7-payments)
   - [reviews](#8-reviews)
   - [favorites](#9-favorites)
   - [support_requests](#10-support_requests)
   - [blog_posts](#11-blog_posts)
   - [places](#12-places)
   - [vouchers](#13-vouchers)
   - [activity_log](#14-activity_log)
   - [site_settings](#15-site_settings)
4. [Quan hệ giữa các bảng](#quan-hệ-giữa-các-bảng)

---

## Sơ đồ quan hệ

```
┌─────────────┐        ┌──────────────┐        ┌───────────────┐
│   locations │ 1────n │    hotels    │ 1────n │  hotel_images │
└─────────────┘        └──────┬───────┘        └───────────────┘
                              │ 1                        
                         ┌────┴────┐                     
                         │        │                      
                        n│        │n                     
                ┌────────┘        └────────┐             
                │                          │             
         ┌──────▼──────┐          ┌────────▼──────┐     
         │    rooms    │          │   favorites   │     
         └──────┬──────┘          └───────┬───────┘     
                │ 1                       │             
                │                    ┌───┘             
                │ n                  │ n               
         ┌──────▼──────┐     ┌───────▼───────┐         
         │   bookings  │     │     users     │         
         └──┬──────┬───┘     └───────┬───────┘         
            │      │                 │                  
           1│     1│                n│                  
            │      │         ┌───────┘                  
           n│      └────────►│  support_requests        
     ┌──────▼──────┐         └──────────────────┘       
     │   payments  │                                    
     └─────────────┘                                    
            │                                           
           1│ bookings ◄──── reviews ────► hotels       
            │                │                          
            └────────────────┘ (booking_id, user_id, hotel_id)

┌─────────────┐   ┌──────────────┐   ┌──────────────┐
│  blog_posts │   │   vouchers   │   │ activity_log │
└─────────────┘   └──────────────┘   └──────────────┘
                  (độc lập)          (log admin)

┌───────────────┐   ┌───────────────┐
│    places     │   │ site_settings │
└───────┬───────┘   └───────────────┘
        │ n
   ┌────┘
   │ 1
┌──▼──────────┐
│  locations  │
└─────────────┘
```

---

## Danh sách bảng

| # | Tên bảng | Mô tả | Số cột |
|---|---|---|---|
| 1 | `users` | Tài khoản người dùng & admin | 16 |
| 2 | `locations` | Địa điểm / khu vực du lịch | 5 |
| 3 | `hotels` | Thông tin khách sạn | 17 |
| 4 | `hotel_images` | Ảnh gallery của khách sạn | 6 |
| 5 | `rooms` | Loại phòng trong khách sạn | 11 |
| 6 | `bookings` | Đặt phòng của khách hàng | 17 |
| 7 | `payments` | Giao dịch thanh toán | 13 |
| 8 | `reviews` | Đánh giá & nhận xét | 9 |
| 9 | `favorites` | Khách sạn yêu thích của user | 4 |
| 10 | `support_requests` | Yêu cầu hỗ trợ từ khách hàng | 11 |
| 11 | `blog_posts` | Bài viết blog du lịch | 13 |
| 12 | `places` | Địa điểm tham quan | 7 |
| 13 | `vouchers` | Mã giảm giá | 11 |
| 14 | `activity_log` | Nhật ký hoạt động admin | 9 |
| 15 | `site_settings` | Cài đặt hệ thống | 6 |

---

## Chi tiết từng bảng

---

### 1. `users`

> Lưu tài khoản của tất cả người dùng và admin.
>
> **Mô tả chức năng:**
>
> Bảng `users` là trung tâm của hệ thống xác thực và phân quyền, hỗ trợ đầy đủ các luồng sau:
>
> - **Đăng ký thông thường** — Người dùng nhập họ tên, email, số điện thoại (tùy chọn) và mật khẩu tối thiểu 6 ký tự; mật khẩu được hash bằng `bcrypt` trước khi lưu.
> - **Đăng nhập có bảo vệ brute-force** — Sau 5 lần nhập sai liên tiếp, tài khoản bị khóa đăng nhập 15 phút (theo IP, lưu trên session); mỗi lần đăng nhập thành công sẽ gọi `session_regenerate_id()` để chống session fixation. Tích hợp **Google reCAPTCHA v2** để ngăn bot.
> - **Đăng nhập mạng xã hội (OAuth2)** — Hỗ trợ đăng nhập qua **Google** và **Facebook**; nếu email chưa tồn tại sẽ tự động tạo tài khoản mới với mật khẩu ngẫu nhiên.
> - **Quên mật khẩu qua OTP** — Người dùng nhập email → hệ thống sinh mã OTP 4 chữ số lưu vào `otp_code` / `otp_expire` (hết hạn sau 15 phút) → xác minh OTP → đặt lại mật khẩu; sau khi đổi thành công, `otp_code` và `otp_expire` bị xóa về NULL.
> - **Đổi mật khẩu khi đã đăng nhập** — Yêu cầu xác nhận mật khẩu cũ, mật khẩu mới không được trùng mật khẩu cũ.
> - **Chỉnh sửa thông tin cá nhân** — User cập nhật họ tên, email, số điện thoại; hệ thống kiểm tra email trùng trước khi lưu, `updated_at` được tự động ghi lại.
> - **Phân quyền hai cấp** — `role = 'admin'` truy cập khu vực quản trị (`/admin_lvhuy_kontum/`); `role = 'user'` truy cập giao diện khách hàng.
> - **Quản lý trạng thái bởi Admin** — Admin có thể chuyển đổi `status` (active ↔ inactive) hoặc bật/tắt `is_banned`; tài khoản admin không thể bị khóa hoặc xóa từ giao diện quản trị.
> - **Ghi nhận lần đăng nhập cuối** — `last_login_at` cập nhật mỗi khi đăng nhập thành công, phục vụ thống kê và kiểm tra hoạt động tài khoản.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `full_name` | varchar(100) | YES | NULL | Họ và tên |
| `email` | varchar(100) | NO | — | Email đăng nhập _(UNIQUE)_ |
| `phone` | varchar(20) | YES | NULL | Số điện thoại |
| `password` | varchar(255) | NO | — | Mật khẩu đã hash (bcrypt) |
| `role` | enum('admin','user') | YES | 'user' | Vai trò |
| `status` | enum('active','inactive') | NO | 'active' | Trạng thái tài khoản |
| `is_banned` | tinyint(1) | NO | 0 | Bị cấm: 1=có, 0=không |
| `avatar` | varchar(255) | YES | NULL | Đường dẫn ảnh đại diện |
| `last_login_at` | datetime | YES | NULL | Lần đăng nhập cuối |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Ngày tạo tài khoản |
| `updated_at` | timestamp | YES | NULL | Lần cập nhật cuối |
| `reset_code` | varchar(10) | YES | NULL | Mã đặt lại mật khẩu |
| `reset_expire` | datetime | YES | NULL | Hết hạn reset |
| `otp_code` | varchar(10) | YES | NULL | Mã OTP |
| `otp_expire` | datetime | YES | NULL | Hết hạn OTP |

**Quan hệ:** `users.id` ← `bookings.user_id`, `favorites.user_id`, `reviews.user_id`, `support_requests.user_id`

---

### 2. `locations`

> Khu vực / địa danh du lịch (Kon Tum, Mang Đen, v.v.)

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `name` | varchar(100) | NO | — | Tên địa điểm |
| `description` | text | YES | NULL | Mô tả |
| `image` | varchar(255) | YES | NULL | Ảnh đại diện |
| `is_active` | tinyint(1) | NO | 1 | Đang hiển thị: 1=có |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Ngày tạo |

**Quan hệ:** `locations.id` ← `hotels.location_id`, `places.location_id`

---

### 3. `hotels`

> Thông tin chi tiết từng khách sạn.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `name` | varchar(150) | YES | NULL | Tên khách sạn |
| `address` | varchar(255) | YES | NULL | Địa chỉ |
| `description` | text | YES | NULL | Mô tả chi tiết |
| `image` | varchar(255) | YES | NULL | Ảnh bìa |
| `location_id` | int(11) | YES | NULL | FK → locations.id |
| `rating` | decimal(2,1) | YES | 0.0 | Điểm đánh giá (0–10) |
| `review_text` | varchar(50) | YES | NULL | Nhãn đánh giá ("Tuyệt vời") |
| `review_count` | int(11) | YES | 0 | Số lượng đánh giá |
| `price` | int(11) | YES | NULL | Giá phòng thấp nhất |
| `old_price` | int(11) | YES | NULL | Giá gốc (để hiện giảm giá) |
| `checkin_time` | varchar(10) | YES | '14:00' | Giờ nhận phòng |
| `checkout_time` | varchar(10) | YES | '12:00' | Giờ trả phòng |
| `is_active` | tinyint(1) | YES | 0 | Đang hoạt động |
| `is_weekend_deal` | tinyint(1) | NO | 0 | Deal cuối tuần |
| `star_category` | tinyint(1) | NO | 3 | Hạng sao khách sạn (1–5) |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Ngày tạo |
| `updated_at` | timestamp | YES | NULL | Lần cập nhật cuối |

**Quan hệ:** `hotels.id` ← `rooms.hotel_id`, `hotel_images.hotel_id`, `favorites.hotel_id`, `reviews.hotel_id`, `payments.hotel_id`

---

### 4. `hotel_images`

> Ảnh gallery của khách sạn (nhiều ảnh / khách sạn).

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `hotel_id` | int(11) | NO | — | FK → hotels.id |
| `image` | varchar(255) | NO | — | Đường dẫn ảnh |
| `caption` | varchar(100) | YES | NULL | Chú thích ảnh |
| `sort_order` | int(11) | YES | 0 | Thứ tự hiển thị |
| `is_primary` | tinyint(1) | NO | 0 | Ảnh đại diện chính: 1=có |

---

### 5. `rooms`

> Các loại phòng thuộc khách sạn.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `hotel_id` | int(11) | YES | NULL | FK → hotels.id |
| `room_name` | varchar(100) | YES | NULL | Tên loại phòng |
| `bed_type` | varchar(100) | YES | NULL | Loại giường |
| `price` | decimal(10,2) | YES | NULL | Giá / đêm |
| `quantity` | int(11) | YES | NULL | Số lượng phòng |
| `is_active` | tinyint(1) | NO | 1 | Đang cho đặt |
| `description` | text | YES | NULL | Mô tả phòng |
| `max_guests` | int(11) | NO | 2 | Số khách tối đa |
| `image` | varchar(255) | YES | NULL | Ảnh phòng |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Ngày tạo |
| `updated_at` | timestamp | YES | NULL | Lần cập nhật cuối |

**Quan hệ:** `rooms.id` ← `bookings.room_id`

---

### 6. `bookings`

> Đơn đặt phòng của khách hàng.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `order_code` | varchar(30) | NO | — | Mã đơn duy nhất _(UNIQUE)_ |
| `user_id` | int(11) | YES | NULL | FK → users.id (NULL nếu đặt không đăng nhập) |
| `room_id` | int(11) | YES | NULL | FK → rooms.id |
| `full_name` | varchar(100) | YES | NULL | Tên khách (nhập khi đặt) |
| `email` | varchar(100) | YES | NULL | Email liên hệ |
| `phone` | varchar(20) | YES | NULL | SĐT liên hệ |
| `check_in` | date | YES | NULL | Ngày nhận phòng |
| `check_out` | date | YES | NULL | Ngày trả phòng |
| `total_price` | decimal(10,2) | YES | NULL | Tổng tiền sau giảm giá |
| `payment_method` | enum('bank','momo','vnpay','hotel','card') | NO | — | Phương thức thanh toán |
| `note` | text | YES | NULL | Ghi chú của khách |
| `voucher_code` | varchar(30) | YES | NULL | Mã voucher đã dùng |
| `discount_amount` | decimal(10,2) | NO | 0.00 | Số tiền giảm |
| `status` | varchar(20) | NO | 'pending' | Trạng thái: pending/confirmed/completed/cancelled |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Thời gian đặt |
| `updated_at` | timestamp | YES | NULL | Lần cập nhật cuối |
| `refund_requested` | tinyint(1) | YES | 0 | 0=không, 1=chờ, 2=duyệt, 3=từ chối |
| `refund_requested_at` | datetime | YES | NULL | Thời gian yêu cầu hoàn tiền |
| `refund_amount` | decimal(15,2) | YES | 0.00 | Số tiền hoàn lại |

**Quan hệ:** `bookings.id` ← `payments.booking_id`, `reviews.booking_id`

---

### 7. `payments`

> Lịch sử giao dịch thanh toán cho từng booking.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `booking_id` | int(11) | YES | NULL | FK → bookings.id |
| `hotel_id` | int(11) | YES | NULL | FK → hotels.id (denormalized) |
| `hotel_name` | varchar(255) | YES | NULL | Tên KS (snapshot lúc thanh toán) |
| `room_name` | varchar(100) | YES | NULL | Tên phòng (snapshot) |
| `payment_method` | varchar(100) | YES | NULL | Phương thức thanh toán |
| `full_name` | varchar(100) | YES | NULL | Tên người thanh toán |
| `email` | varchar(150) | YES | NULL | Email |
| `phone` | varchar(20) | YES | NULL | SĐT |
| `amount` | decimal(10,2) | YES | NULL | Số tiền giao dịch |
| `payment_status` | varchar(20) | NO | 'pending' | pending / paid / failed / refunded |
| `qr_scanned` | tinyint(1) | YES | 0 | Đã quét QR: 1=có |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Thời gian giao dịch |
| `updated_at` | timestamp | YES | NULL | Lần cập nhật cuối |

---

### 8. `reviews`

> Đánh giá & nhận xét của khách sau khi hoàn thành booking.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `hotel_id` | int(11) | NO | — | FK → hotels.id |
| `user_id` | int(11) | NO | — | FK → users.id |
| `booking_id` | int(11) | NO | — | FK → bookings.id |
| `rating` | tinyint(1) | NO | — | Số sao (1–5) |
| `comment` | text | NO | — | Nội dung nhận xét |
| `is_active` | tinyint(1) | NO | 1 | Đang hiển thị |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | Ngày đánh giá |
| `updated_at` | datetime | YES | NULL | Lần cập nhật cuối |

> **Ràng buộc:** Mỗi `booking_id` chỉ được đánh giá 1 lần.

---

### 9. `favorites`

> Danh sách khách sạn yêu thích của từng user.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `user_id` | int(11) | NO | — | FK → users.id |
| `hotel_id` | int(11) | NO | — | FK → hotels.id |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Ngày thêm vào yêu thích |

> **Ràng buộc:** `(user_id, hotel_id)` là cặp duy nhất.

---

### 10. `support_requests`

> Yêu cầu hỗ trợ / liên hệ từ khách hàng.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `user_id` | int(11) | YES | NULL | FK → users.id (NULL nếu chưa đăng nhập) |
| `full_name` | varchar(100) | NO | — | Tên người gửi |
| `phone` | varchar(20) | NO | — | SĐT |
| `email` | varchar(100) | YES | NULL | Email |
| `subject` | varchar(255) | YES | NULL | Tiêu đề yêu cầu |
| `note` | text | YES | NULL | Nội dung chi tiết |
| `admin_note` | text | YES | NULL | Ghi chú phản hồi của admin |
| `status` | enum('pending','processing','resolved') | NO | 'pending' | Trạng thái xử lý |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | Ngày gửi |
| `updated_at` | datetime | YES | NULL | Lần cập nhật cuối |

---

### 11. `blog_posts`

> Bài viết blog du lịch.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `title` | varchar(500) | NO | — | Tiêu đề bài viết |
| `category` | varchar(100) | NO | — | Danh mục (Kon Tum, Mang Đen…) |
| `summary` | text | NO | — | Tóm tắt ngắn |
| `content` | longtext | NO | — | Nội dung HTML đầy đủ |
| `thumb` | varchar(500) | NO | — | Ảnh thumbnail |
| `img` | varchar(500) | NO | — | Ảnh bìa lớn |
| `author` | varchar(200) | NO | 'Admin' | Tác giả |
| `tags` | varchar(500) | YES | '' | Tags phân cách bằng dấu phẩy |
| `read_time` | varchar(50) | YES | '5 phút đọc' | Thời gian đọc ước tính |
| `is_active` | tinyint(1) | NO | 1 | Đang hiển thị |
| `view_count` | int(11) | NO | 0 | Lượt xem |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | Ngày đăng |
| `updated_at` | datetime | NO | CURRENT_TIMESTAMP | Lần cập nhật cuối |

---

### 12. `places`

> Địa điểm tham quan thuộc một khu vực.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `name` | varchar(150) | YES | NULL | Tên địa điểm |
| `description` | text | YES | NULL | Mô tả |
| `image` | varchar(255) | YES | NULL | Ảnh |
| `location_id` | int(11) | YES | NULL | FK → locations.id |
| `is_active` | tinyint(1) | NO | 1 | Đang hiển thị |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Ngày tạo |

---

### 13. `vouchers`

> Mã giảm giá cho đặt phòng.

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `code` | varchar(30) | NO | — | Mã voucher _(UNIQUE, in hoa)_ |
| `type` | enum('percent','fixed') | NO | 'percent' | Loại: % hoặc số tiền cố định |
| `value` | decimal(10,2) | NO | 0.00 | Giá trị giảm |
| `min_order` | decimal(10,2) | NO | 0.00 | Giá trị đơn tối thiểu để áp dụng |
| `max_uses` | int(11) | NO | 1 | Số lần dùng tối đa |
| `used_count` | int(11) | NO | 0 | Số lần đã dùng |
| `expires_at` | date | YES | NULL | Ngày hết hạn (NULL = không hết hạn) |
| `is_active` | tinyint(1) | NO | 1 | Đang kích hoạt |
| `description` | varchar(255) | YES | NULL | Mô tả voucher |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Ngày tạo |

> **Logic:** Voucher hết hiệu lực khi `used_count >= max_uses` hoặc `expires_at < TODAY` hoặc `is_active = 0`.

---

### 14. `activity_log`

> Nhật ký hành động của admin (audit trail).

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `admin_id` | int(11) | YES | NULL | ID admin thực hiện |
| `admin_name` | varchar(100) | NO | '' | Tên admin (snapshot) |
| `action` | varchar(100) | NO | — | Loại hành động (vd: confirm_booking) |
| `target` | varchar(100) | NO | '' | Đối tượng tác động (vd: booking) |
| `target_id` | int(11) | YES | NULL | ID đối tượng |
| `detail` | text | YES | NULL | Mô tả chi tiết |
| `ip` | varchar(45) | YES | NULL | IP của admin |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Thời gian thực hiện |

> **Các giá trị `action` hiện tại:** `confirm_booking`, `cancel_booking`, `complete_booking`, `edit_booking`, `approve_refund`, `reject_refund`, `delete_user`, `add_hotel`, `update_hotel`, `delete_hotel`, `add_voucher`, `delete_voucher`, `resolve_support`, `delete_review`, `login`, `update_settings`

---

### 15. `site_settings`

> Cài đặt cấu hình chung của hệ thống (key-value store).

| Cột | Kiểu | Null | Mặc định | Mô tả |
|---|---|---|---|---|
| `id` | int(11) | NO | AUTO_INCREMENT | Khoá chính |
| `key` | varchar(80) | NO | — | Tên cài đặt _(UNIQUE)_ |
| `value` | text | YES | NULL | Giá trị |
| `label` | varchar(120) | NO | '' | Nhãn hiển thị trong admin |
| `group_name` | varchar(60) | NO | 'general' | Nhóm: general/booking/social/system |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | Lần cập nhật cuối |

**Các key mặc định:**

| Key | Nhóm | Mô tả |
|---|---|---|
| `site_name` | general | Tên website |
| `site_email` | general | Email liên hệ |
| `site_phone` | general | Số điện thoại |
| `site_address` | general | Địa chỉ |
| `facebook_url` | social | Link Facebook |
| `zalo_url` | social | Link Zalo OA |
| `booking_fee_pct` | booking | Phí huỷ đặt phòng (%) |
| `max_advance_days` | booking | Đặt trước tối đa (ngày) |
| `maintenance_mode` | system | Chế độ bảo trì (0/1) |

---

## Quan hệ giữa các bảng

| Bảng con | Cột FK | Bảng cha | Cột PK | Kiểu quan hệ |
|---|---|---|---|---|
| `hotels` | `location_id` | `locations` | `id` | N–1 (nhiều KS thuộc 1 khu vực) |
| `hotel_images` | `hotel_id` | `hotels` | `id` | N–1 (nhiều ảnh / KS) |
| `rooms` | `hotel_id` | `hotels` | `id` | N–1 (nhiều phòng / KS) |
| `bookings` | `user_id` | `users` | `id` | N–1 (nhiều đơn / user) |
| `bookings` | `room_id` | `rooms` | `id` | N–1 (nhiều đơn / phòng) |
| `payments` | `booking_id` | `bookings` | `id` | N–1 (nhiều GD / booking) |
| `payments` | `hotel_id` | `hotels` | `id` | N–1 |
| `reviews` | `hotel_id` | `hotels` | `id` | N–1 |
| `reviews` | `user_id` | `users` | `id` | N–1 |
| `reviews` | `booking_id` | `bookings` | `id` | 1–1 (1 booking = 1 review) |
| `favorites` | `user_id` | `users` | `id` | N–M (user ↔ hotel) |
| `favorites` | `hotel_id` | `hotels` | `id` | N–M |
| `support_requests` | `user_id` | `users` | `id` | N–1 |
| `places` | `location_id` | `locations` | `id` | N–1 |
| `activity_log` | `admin_id` | `users` | `id` | N–1 (soft ref) |

---

## Ghi chú thiết kế

| Vấn đề | Cách xử lý |
|---|---|
| `payments` có `hotel_name`, `room_name` dạng text | **Snapshot pattern** — lưu tên tại thời điểm thanh toán để tránh bị ảnh hưởng khi đổi tên KS/phòng sau này |
| `bookings.user_id` cho phép NULL | Hỗ trợ đặt phòng không cần tài khoản (guest checkout) |
| `bookings.refund_requested` dùng tinyint 0/1/2/3 | 0=chưa yêu cầu, 1=chờ duyệt, 2=đã duyệt, 3=từ chối |
| `vouchers.used_count` | Cần tăng khi áp dụng voucher trong flow thanh toán |
| Không có Foreign Key cho `activity_log.admin_id` | Để log không bị mất khi xóa admin |
