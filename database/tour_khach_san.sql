-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2026 at 06:17 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tour_khach_san`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_name` varchar(100) NOT NULL DEFAULT '',
  `action` varchar(100) NOT NULL,
  `target` varchar(100) NOT NULL DEFAULT '',
  `target_id` int(11) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `admin_id`, `admin_name`, `action`, `target`, `target_id`, `detail`, `ip`, `created_at`) VALUES
(1, 5, 'Administrator', 'confirm_booking', 'booking', 24, 'Đơn #24 → confirmed', '::1', '2026-04-16 06:52:52'),
(2, 5, 'Administrator', 'confirm_booking', 'booking', 10, 'Đơn #10 → confirmed', '::1', '2026-04-16 06:52:54'),
(3, 5, 'Administrator', 'complete_booking', 'booking', 18, 'Đơn #18 → completed', '::1', '2026-04-16 06:52:56'),
(4, 5, 'Administrator', 'cancel_booking', 'booking', 41, 'Đơn #41 → cancelled', '::1', '2026-04-16 06:52:57'),
(5, 5, 'Administrator', 'cancel_booking', 'booking', 40, 'Đơn #40 → cancelled', '::1', '2026-04-16 06:52:59'),
(6, 5, 'Administrator', 'edit_hotel', 'hotel', 51, 'Sửa khách sạn: Khách sạn Xà Nu Kon Tum', '::1', '2026-04-16 07:02:10'),
(7, 5, 'Administrator', 'delete_hotel', 'hotel', 51, 'Xóa khách sạn #51', '::1', '2026-04-16 07:02:31'),
(8, 5, 'Administrator', 'add_room', 'room', 47, 'Thêm phòng: Phòng suite', '::1', '2026-04-16 07:03:27'),
(9, 5, 'Administrator', 'delete_room', 'room', 47, 'Xóa phòng: Phòng suite', '::1', '2026-04-16 07:04:00');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(500) NOT NULL,
  `category` varchar(100) NOT NULL,
  `summary` text NOT NULL,
  `content` longtext NOT NULL,
  `thumb` varchar(500) NOT NULL,
  `img` varchar(500) NOT NULL,
  `author` varchar(200) NOT NULL DEFAULT 'Admin',
  `tags` varchar(500) DEFAULT '',
  `read_time` varchar(50) DEFAULT '5 phút đọc',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `category`, `summary`, `content`, `thumb`, `img`, `author`, `tags`, `read_time`, `is_active`, `view_count`, `created_at`, `updated_at`) VALUES
(1, 'KON TUM – THÀNH PHỐ CỦA NHỮNG NHÀ THỜ GỖ ĐỘC ĐÁO', 'Kon Tum', 'Khám phá vẻ đẹp trầm mặc của Kon Tum qua những nhà thờ gỗ cổ kính, chiếc cầu treo huyền thoại bắc qua sông Đăk Bla và hương vị ẩm thực Tây Nguyên nguyên bản khó quên.', '<p>Kon Tum &mdash; một trong những tỉnh miền n&uacute;i y&ecirc;n tĩnh nhất T&acirc;y Nguy&ecirc;n &mdash; đang dần trở th&agrave;nh điểm đến được giới trẻ y&ecirc;u du lịch kh&aacute;m ph&aacute; săn đ&oacute;n. Kh&ocirc;ng ồn &agrave;o như Đ&agrave; Lạt, kh&ocirc;ng nhộn nhịp như Hội An, Kon Tum mang một vẻ đẹp trầm lắng, ch&acirc;n thực đến mức bạn sẽ muốn ở lại l&acirc;u hơn dự định.</p>\r\n<h3>⛪️ Nh&agrave; Thờ Gỗ Kon Tum &mdash; Kiệt T&aacute;c Kiến Tr&uacute;c Giữa Đại Ng&agrave;n</h3>\r\n<p>C&ocirc;ng tr&igrave;nh biểu tượng nhất của th&agrave;nh phố ch&iacute;nh l&agrave; <strong>Nh&agrave; thờ Gỗ Kon Tum</strong>, được x&acirc;y dựng v&agrave;o năm 1913 bởi c&aacute;c linh mục người Ph&aacute;p. Điều đặc biệt l&agrave; to&agrave;n bộ c&ocirc;ng tr&igrave;nh được l&agrave;m từ gỗ c&agrave; ch&iacute;t &mdash; một loại gỗ qu&yacute; của T&acirc;y Nguy&ecirc;n &mdash; với kỹ thuật chạm khắc thủ c&ocirc;ng tinh xảo theo phong c&aacute;ch Romanesque kết hợp kiến tr&uacute;c nh&agrave; r&ocirc;ng của người Ba Na. Đ&acirc;y l&agrave; một trong những nh&agrave; thờ gỗ hiếm hoi c&ograve;n tồn tại nguy&ecirc;n vẹn ở Việt Nam.</p>\r\n<p>Buổi s&aacute;ng sớm, khi &aacute;nh nắng v&agrave;ng rọi qua những &ocirc; cửa sổ hoa văn, kh&ocirc;ng gian b&ecirc;n trong nh&agrave; thờ trở n&ecirc;n lung linh v&agrave; huyền ảo đến lạ thường. Đ&acirc;y cũng l&agrave; thời điểm l&yacute; tưởng để chụp ảnh m&agrave; kh&ocirc;ng bị đ&ocirc;ng người.</p>\r\n<h3>🌉 Cầu Treo Kon Klor &mdash; Nơi S&ocirc;ng Đăk Bla Uốn Lượn</h3>\r\n<p>Chỉ c&aacute;ch trung t&acirc;m th&agrave;nh phố khoảng 5km, <strong>Cầu treo Kon Klor</strong> bắc qua s&ocirc;ng Đăk Bla l&agrave; điểm check-in kh&ocirc;ng thể bỏ qua. Từ tr&ecirc;n cầu nh&igrave;n xuống, d&ograve;ng s&ocirc;ng xanh m&aacute;t uốn lượn giữa những ngọn đồi xanh r&igrave; tạo n&ecirc;n khung cảnh đẹp như tranh vẽ. B&ecirc;n kia cầu l&agrave; l&agrave;ng Kon Klor &mdash; nơi người Ba Na vẫn đang sinh sống v&agrave; g&igrave;n giữ những n&eacute;t văn h&oacute;a truyền thống ng&agrave;n đời.</p>\r\n<h3>🍜 Ẩm Thực Kon Tum &mdash; Đậm Đ&agrave; Hương Vị T&acirc;y Nguy&ecirc;n</h3>\r\n<p>Đến Kon Tum m&agrave; chưa thử <strong>phở kh&ocirc;</strong> th&igrave; coi như chưa đến. Phở kh&ocirc; Kon Tum kh&aacute;c ho&agrave;n to&agrave;n với phở nước: sợi phở được trụng ch&iacute;n, trộn với thịt băm x&agrave;o, h&agrave;nh phi, v&agrave; ăn k&egrave;m với một b&aacute;t nước d&ugrave;ng đậm đ&agrave; ri&ecirc;ng. M&oacute;n ăn đặc biệt n&agrave;y c&oacute; mặt ở khắp c&aacute;c qu&aacute;n ăn trong th&agrave;nh phố với gi&aacute; chỉ từ 30.000đ/t&ocirc;.</p>\r\n<p>Ngo&agrave;i ra, đừng qu&ecirc;n thử <strong>cơm lam g&agrave; nướng</strong> &mdash; m&oacute;n ăn truyền thống của đồng b&agrave;o Ba Na, Jrai với hương thơm của ống lam v&agrave; g&agrave; thả vườn nướng than hoa, chấm với muối l&aacute; &eacute; đặc trưng v&ugrave;ng T&acirc;y Nguy&ecirc;n.</p>\r\n<h3>⚠️ Lưu &Yacute; Khi Du Lịch Kon Tum</h3>\r\n<p>Thời điểm đẹp nhất để thăm Kon Tum l&agrave; từ <strong>th&aacute;ng 11 đến th&aacute;ng 4</strong> &mdash; m&ugrave;a kh&ocirc;, trời trong xanh, &iacute;t mưa. Từ th&aacute;ng 5 đến th&aacute;ng 10 l&agrave; m&ugrave;a mưa, đường v&agrave;o c&aacute;c l&agrave;ng d&acirc;n tộc c&oacute; thể kh&oacute; đi. Từ TP.HCM, bạn c&oacute; thể bay thẳng đến Pleiku (Gia Lai) rồi đi xe khoảng 50km l&agrave; đến Kon Tum.</p>', '/tour_khach_san_project/assets/images/blog/blog_1774108299_6903.jpg', '/tour_khach_san_project/assets/images/blog/blog_1774108304_1994.jpg', 'NGUYỄN THỊ MAI', 'Kon Tum,Nhà thờ gỗ,Tây Nguyên,Du lịch văn hóa', '5 phút đọc', 1, 0, '2026-01-05 08:00:00', '2026-04-08 17:09:27'),
(2, 'MĂNG ĐEN – ĐÀ LẠT THỨ HAI CỦA TÂY NGUYÊN', 'Măng Đen', 'Nằm ở độ cao 1.200m, Măng Đen sở hữu khí hậu mát mẻ quanh năm, những hồ nước trong vắt, rừng thông bạt ngàn và không khí trong lành tuyệt đối — thiên đường ẩn mình giữa đại ngàn.', '<p>Nếu Đ&agrave; Lạt l&agrave; \"th&agrave;nh phố ng&agrave;n hoa\" th&igrave; Măng Đen ch&iacute;nh l&agrave; \"thi&ecirc;n đường xanh\" c&ograve;n nguy&ecirc;n vẹn của T&acirc;y Nguy&ecirc;n. Nằm ở độ cao trung b&igrave;nh 1.200m so với mực nước biển, thuộc huyện Kon Pl&ocirc;ng, tỉnh Kon Tum, Măng Đen đang nhanh ch&oacute;ng trở th&agrave;nh điểm đến mới nổi được giới phượt thủ v&agrave; những người y&ecirc;u thi&ecirc;n nhi&ecirc;n cực kỳ y&ecirc;u th&iacute;ch.</p>\r\n<h3>🌲 Rừng Th&ocirc;ng Măng Đen &mdash; L&aacute; Phổi Xanh Của T&acirc;y Nguy&ecirc;n</h3>\r\n<p>Điều đầu ti&ecirc;n đập v&agrave;o mắt khi đặt ch&acirc;n đến Măng Đen l&agrave; m&agrave;u xanh bạt ng&agrave;n của rừng th&ocirc;ng trải d&agrave;i tới tận ch&acirc;n trời. Kh&ocirc;ng kh&iacute; ở đ&acirc;y trong l&agrave;nh đến mức bạn sẽ cảm nhận được sự kh&aacute;c biệt ngay từ hơi thở đầu ti&ecirc;n. Nhiệt độ trung b&igrave;nh chỉ 18-22&deg;C, thậm ch&iacute; ban đ&ecirc;m c&oacute; thể xuống dưới 15&deg;C &mdash; m&aacute;t mẻ như Đ&agrave; Lạt nhưng hoang sơ v&agrave; y&ecirc;n tĩnh hơn rất nhiều.</p>\r\n<h3>🏞️ Hệ Thống Hồ &mdash; Vẻ Đẹp Kh&ocirc;ng Thể Cưỡng Lại</h3>\r\n<p>Măng Đen sở hữu một chuỗi hồ nước tự nhi&ecirc;n v&agrave; nh&acirc;n tạo tuyệt đẹp: <strong>Hồ Toong Đam</strong>, <strong>Hồ Toong Zơ Ri</strong>, <strong>Hồ Toong P&ocirc;</strong>... Mỗi hồ mang một vẻ đẹp ri&ecirc;ng, nhưng điểm chung l&agrave; l&agrave;n nước xanh biếc trong vắt phản chiếu bầu trời v&agrave; những t&aacute;n rừng xanh xung quanh. Buổi s&aacute;ng sớm, mặt hồ thường bị bao phủ bởi l&agrave;n sương mỏng, tạo n&ecirc;n khung cảnh huyền ảo như trong phim.</p>\r\n<h3>💧 Th&aacute;c Nước &amp; Vườn Rau Sạch</h3>\r\n<p><strong>Th&aacute;c Pa Sỹ</strong> với d&ograve;ng nước đổ từ độ cao hơn 30m xuống b&atilde;i đ&aacute; granite trắng l&agrave; một trong những th&aacute;c đẹp nhất v&ugrave;ng T&acirc;y Nguy&ecirc;n. Ngo&agrave;i ra, Măng Đen c&ograve;n nổi tiếng với c&aacute;c vườn rau sạch, vườn d&acirc;u t&acirc;y, v&agrave; đặc biệt l&agrave; <strong>hoa d&atilde; quỳ</strong> nở rộ v&agrave;o th&aacute;ng 11-12 tạo n&ecirc;n những thảm hoa v&agrave;ng rực rỡ dọc hai b&ecirc;n đường.</p>\r\n<h3>🏨 Ở Đ&acirc;u &amp; Ăn G&igrave; Tại Măng Đen?</h3>\r\n<p>Hệ thống lưu tr&uacute; tại Măng Đen đang ph&aacute;t triển nhanh với nhiều homestay, villa trong rừng th&ocirc;ng gi&aacute; từ 300.000đ - 2.000.000đ/đ&ecirc;m. Đặc sản n&ecirc;n thử: <strong>c&aacute; tầm nướng than hoa</strong>, <strong>g&agrave; đồi nướng muối ớt</strong>, <strong>rau rừng x&agrave;o tỏi</strong>, v&agrave; đặc biệt l&agrave; <strong>rượu cần</strong> uống c&ugrave;ng d&acirc;n l&agrave;ng b&ecirc;n bếp lửa hồng.</p>', '/tour_khach_san_project/assets/images/blog/blog_1774108966_8518.jpg', '/tour_khach_san_project/assets/images/blog/blog_1774108970_3500.jpg', 'TRẦN VĂN HÙNG', 'Măng Đen,Kon Plông,Rừng thông,Hồ nước,Du lịch sinh thái', '5 phút đọc', 1, 0, '2026-02-18 08:00:00', '2026-04-08 16:11:15'),
(3, 'BIỂN MỸ KHÊ QUẢNG NGÃI – VIÊN NGỌC XANH MIỀN TRUNG', 'Quảng Ngãi', 'Bãi biển Mỹ Khê với bờ cát trắng mịn, sóng êm và hải sản tươi ngon là điểm đến lý tưởng cho những ai yêu biển nhưng muốn thoát khỏi sự đông đúc ồn ào của các khu du lịch lớn.', '<p>Kh&ocirc;ng phải Đ&agrave; Nẵng, kh&ocirc;ng phải Nha Trang &mdash; <strong>Mỹ Kh&ecirc; Quảng Ng&atilde;i</strong> ch&iacute;nh l&agrave; b&atilde;i biển m&agrave; những người biết du lịch thật sự đang t&igrave;m đến. Trải d&agrave;i hơn 10km với b&atilde;i c&aacute;t trắng mịn phẳng lặng, nước biển trong xanh nhiều tầng m&agrave;u, Mỹ Kh&ecirc; Quảng Ng&atilde;i l&agrave; một vi&ecirc;n ngọc th&ocirc; chưa bị khai th&aacute;c qu&aacute; mức.</p>\r\n<h3>🏖️ Vẻ Đẹp Của Mỹ Kh&ecirc; Quảng Ng&atilde;i</h3>\r\n<p>B&atilde;i biển Mỹ Kh&ecirc; thuộc x&atilde; Tịnh Kh&ecirc;, huyện Sơn Tịnh, c&aacute;ch trung t&acirc;m TP. Quảng Ng&atilde;i khoảng 17km. Bờ biển thoai thoải, s&oacute;ng vừa phải &mdash; l&yacute; tưởng để tắm biển, chơi c&aacute;c m&ocirc;n thể thao nước. Đặc biệt, mỗi buổi s&aacute;ng sớm, khi ngư d&acirc;n k&eacute;o thuyền v&agrave;o bờ, cả b&atilde;i biển rực l&ecirc;n m&agrave;u cam của những chiếc th&uacute;ng chai v&agrave; tiếng n&oacute;i cười tấp nập của phi&ecirc;n chợ c&aacute; tươi ngay tr&ecirc;n b&atilde;i c&aacute;t.</p>\r\n<h3>🦞 Thi&ecirc;n Đường Hải Sản</h3>\r\n<p>Hải sản ở Mỹ Kh&ecirc; Quảng Ng&atilde;i tươi ngon v&agrave; rẻ đến mức kh&ocirc;ng tưởng. Dọc theo b&atilde;i biển l&agrave; h&agrave;ng chục qu&aacute;n nhậu hải sản với gi&aacute; cực b&igrave;nh d&acirc;n: <strong>t&ocirc;m h&ugrave;m nướng muối ớt</strong>, <strong>cua huỳnh đế hấp bia</strong>, <strong>ghẹ rang me</strong>, <strong>mực một nắng nướng than</strong>... Bữa ăn hải sản tươi sống cho 4 người chỉ từ 400.000-600.000đ, rẻ hơn nhiều so với c&aacute;c b&atilde;i biển nổi tiếng kh&aacute;c.</p>\r\n<h3>🏄 Hoạt Động Vui Chơi</h3>\r\n<p>Ngo&agrave;i tắm biển, du kh&aacute;ch c&oacute; thể tham gia: <strong>lướt s&oacute;ng</strong> (c&oacute; lớp học cho người mới), <strong>ch&egrave;o kayak</strong>, <strong>đi thuyền th&uacute;ng</strong> với ngư d&acirc;n địa phương, hoặc đơn giản l&agrave; thu&ecirc; xe đạp đạo dọc bờ biển ngắm ho&agrave;ng h&ocirc;n. Đặc biệt kh&ocirc;ng n&ecirc;n bỏ lỡ chuyến thăm <strong>L&agrave;ng ch&agrave;i Tịnh Kh&ecirc;</strong> &mdash; nơi thời gian như đứng y&ecirc;n với những chiếc thuyền gỗ cũ v&agrave; những người d&acirc;n hiếu kh&aacute;ch.</p>\r\n<h3>🗺️ Th&ocirc;ng Tin Di Chuyển</h3>\r\n<p>Từ TP. Quảng Ng&atilde;i đi xe m&aacute;y hoặc taxi khoảng 20 ph&uacute;t. C&oacute; thể đi t&agrave;u hỏa hoặc xe kh&aacute;ch từ H&agrave; Nội/TP.HCM đến Quảng Ng&atilde;i, sau đ&oacute; di chuyển ra biển. Lưu &yacute; tr&aacute;nh m&ugrave;a mưa b&atilde;o (th&aacute;ng 10-11), thời điểm đẹp nhất l&agrave; <strong>th&aacute;ng 3 đến th&aacute;ng 8</strong>.</p>', '/tour_khach_san_project/assets/images/blog/blog_1774108596_4368.jpg', '/tour_khach_san_project/assets/images/blog/blog_1774108590_1379.jpg', 'LÊ THỊ HƯƠNG', 'Quảng Ngãi,Biển Mỹ Khê,Hải sản,Bãi biển', '4 phút đọc', 1, 0, '2026-03-02 08:00:00', '2026-04-08 16:11:15'),
(4, 'VƯỜN QUỐC GIA KON KA KINH – RỪNG NGUYÊN SINH HÙNG VĨ', 'Kon Tum', 'Vườn quốc gia Kon Ka Kinh là nơi sinh sống của hàng trăm loài động thực vật quý hiếm. Hành trình trekking xuyên rừng nguyên sinh sẽ là trải nghiệm không thể nào quên trong cuộc đời bạn.', '<p><strong>Vườn quốc gia Kon Ka Kinh</strong> nằm tr&ecirc;n địa b&agrave;n huyện Mang Yang v&agrave; KBang (Gia Lai), gi&aacute;p ranh với Kon Tum, được UNESCO c&ocirc;ng nhận l&agrave; Khu dự trữ sinh quyển thế giới năm 2003. Đ&acirc;y l&agrave; một trong những khu rừng nguy&ecirc;n sinh &iacute;t bị t&aacute;c động nhất của Việt Nam, nơi thi&ecirc;n nhi&ecirc;n vẫn đang vận h&agrave;nh theo đ&uacute;ng quy luật nguy&ecirc;n thủy của n&oacute;.</p>\r\n<h3>🌿 Hệ Sinh Th&aacute;i Độc Đ&aacute;o</h3>\r\n<p>Vườn c&oacute; diện t&iacute;ch hơn 41.000 ha với độ cao từ 700m đến 1.748m (đỉnh Kon Ka Kinh). Nơi đ&acirc;y l&agrave; \"ng&ocirc;i nh&agrave;\" của hơn <strong>1.400 lo&agrave;i thực vật</strong>, trong đ&oacute; c&oacute; nhiều lo&agrave;i đặc hữu của T&acirc;y Nguy&ecirc;n. Về động vật, đ&acirc;y l&agrave; nơi sinh sống của c&aacute;c lo&agrave;i cực kỳ qu&yacute; hiếm như <strong>voọc ch&agrave; v&aacute; ch&acirc;n x&aacute;m</strong> (biểu tượng của vườn), <strong>gấu ngựa</strong>, <strong>hổ Đ&ocirc;ng Dương</strong>, v&agrave; hơn 350 lo&agrave;i chim.</p>\r\n<h3>🥾 Trekking &mdash; Trải Nghiệm Kh&ocirc;ng D&agrave;nh Cho Người Nh&uacute;t Nh&aacute;t</h3>\r\n<p>Vườn quốc gia Kon Ka Kinh cung cấp nhiều tuyến trekking từ dễ đến kh&oacute;. Tuyến phổ biến nhất l&agrave; <strong>h&agrave;nh tr&igrave;nh 2 ng&agrave;y 1 đ&ecirc;m</strong> l&ecirc;n đỉnh Kon Ka Kinh (1.748m) &mdash; đi qua rừng gi&agrave; nguy&ecirc;n sinh, lội suối, v&agrave; cắm trại dưới t&aacute;n rừng nguy&ecirc;n sinh. Đ&acirc;y l&agrave; một trong những chuyến trekking đẹp nhất miền Trung T&acirc;y Nguy&ecirc;n, nhưng đ&ograve;i hỏi thể lực tốt v&agrave; phải đi c&ugrave;ng hướng dẫn vi&ecirc;n địa phương.</p>\r\n<h3>🦅 Xem Chim &mdash; Thi&ecirc;n Đường Của Bird Watchers</h3>\r\n<p>Kon Ka Kinh l&agrave; một trong 63 v&ugrave;ng chim quan trọng của Việt Nam. Nếu bạn l&agrave; người y&ecirc;u th&iacute;ch quan s&aacute;t chim, h&atilde;y đến v&agrave;o s&aacute;ng sớm (5h-8h) &mdash; đ&acirc;y l&agrave; thời điểm hoạt động nhộn nhịp nhất của c&aacute;c lo&agrave;i chim rừng. C&oacute; thể bắt gặp <strong>chim trĩ sao</strong>, <strong>chim h&uacute;t mật</strong>, v&agrave; nhiều lo&agrave;i chim đặc hữu T&acirc;y Nguy&ecirc;n.</p>\r\n<h3>⚠️ Lưu &Yacute; Quan Trọng</h3>\r\n<p>Phải đăng k&yacute; tham quan tại Ban quản l&yacute; vườn. Kh&ocirc;ng được tự &yacute; v&agrave;o rừng m&agrave; kh&ocirc;ng c&oacute; hướng dẫn vi&ecirc;n. Mang theo đủ nước uống, thuốc chống muỗi, v&agrave; trang phục bảo hộ. Tuyệt đối kh&ocirc;ng săn bắt hay l&agrave;m ảnh hưởng đến động thực vật trong vườn.</p>', '/tour_khach_san_project/assets/images/blog/blog_1774104840_5615.jpg', '/tour_khach_san_project/assets/images/blog/blog_1774104847_2975.jpg', 'PHẠM MINH TUẤN', 'Kon Ka Kinh,Trekking,Rừng nguyên sinh,UNESCO,Sinh thái', '6 phút đọc', 1, 0, '2026-01-10 08:00:00', '2026-04-08 16:11:15'),
(5, 'LÝ SƠN – ĐẢO TỎI HUYỀN THOẠI GIỮA BIỂN ĐÔNG', 'Quảng Ngãi', 'Hòn đảo nhỏ thuộc Quảng Ngãi với những cánh đồng tỏi xanh mướt, miệng núi lửa hàng triệu năm tuổi, làng chài cổ kính và làn nước biển xanh biếc trong vắt đến tận đáy.', '<p>C&aacute;ch bờ biển Quảng Ng&atilde;i khoảng 15 hải l&yacute;, <strong>đảo L&yacute; Sơn</strong> l&agrave; h&ograve;n đảo n&uacute;i lửa duy nhất ở miền Trung Việt Nam c&ograve;n lưu giữ gần như nguy&ecirc;n vẹn cấu tr&uacute;c địa chất độc đ&aacute;o h&agrave;ng triệu năm tuổi. Với diện t&iacute;ch chỉ 10km&sup2;, L&yacute; Sơn g&oacute;i gọn trong m&igrave;nh v&ocirc; số điều kỳ diệu &mdash; từ những miệng n&uacute;i lửa cổ đại, c&aacute;nh đồng tỏi xanh rờn, đến l&agrave;n nước biển trong vắt đến tận đ&aacute;y.</p>\r\n<h3>🧄 Vương Quốc Tỏi &mdash; Độc Nhất V&ocirc; Nhị</h3>\r\n<p>L&yacute; Sơn được mệnh danh l&agrave; \"<strong>Vương quốc tỏi</strong>\" của Việt Nam. Tỏi L&yacute; Sơn được trồng tr&ecirc;n nền đất n&uacute;i lửa gi&agrave;u kho&aacute;ng chất, được tưới bằng nước biển pha lo&atilde;ng, tạo n&ecirc;n hương vị đặc biệt kh&ocirc;ng nơi n&agrave;o c&oacute; được &mdash; cay nồng, thơm lừng, củ nhỏ nhưng tinh dầu cực cao. V&agrave;o m&ugrave;a thu hoạch (th&aacute;ng 1-3), những c&aacute;nh đồng tỏi xanh mướt trải d&agrave;i dưới ch&acirc;n miệng n&uacute;i lửa l&agrave; một trong những cảnh đẹp đặc trưng nhất của đảo.</p>\r\n<h3>🌋 Ch&ugrave;a Hang &amp; Miệng N&uacute;i Lửa Thới Lới</h3>\r\n<p><strong>Ch&ugrave;a Hang</strong> (t&ecirc;n ch&iacute;nh x&aacute;c l&agrave; ch&ugrave;a Đục) được tạc v&agrave;o v&aacute;ch đ&aacute; n&uacute;i lửa basalt h&agrave;ng trăm năm trước &mdash; một c&ocirc;ng tr&igrave;nh t&acirc;m linh độc đ&aacute;o chỉ c&oacute; ở L&yacute; Sơn. Từ đ&acirc;y leo l&ecirc;n <strong>miệng n&uacute;i lửa Thới Lới</strong> (cao 169m) để ngắm to&agrave;n cảnh h&ograve;n đảo nhỏ nhắn nằm giữa biển khơi m&ecirc;nh m&ocirc;ng &mdash; một trong những điểm ngắm cảnh đẹp nhất miền Trung.</p>\r\n<h3>🤿 Lặn Biển &mdash; Thế Giới San H&ocirc; Rực Rỡ</h3>\r\n<p>V&ugrave;ng biển quanh L&yacute; Sơn c&oacute; độ trong suốt đặc biệt, cho ph&eacute;p nh&igrave;n thấy đ&aacute;y ở độ s&acirc;u 5-7m bằng mắt thường. Dưới đ&aacute;y biển l&agrave; những rạn san h&ocirc; đa dạng với h&agrave;ng trăm lo&agrave;i c&aacute; nhiệt đới đủ m&agrave;u sắc. Hiện c&oacute; nhiều dịch vụ lặn biển c&oacute; hướng dẫn với gi&aacute; từ 300.000-500.000đ/người.</p>\r\n<h3>⛴️ C&aacute;ch Đi &amp; Lưu Tr&uacute;</h3>\r\n<p>T&agrave;u cao tốc từ cảng Sa Kỳ (Quảng Ng&atilde;i) ra đảo mất khoảng 30 ph&uacute;t, gi&aacute; v&eacute; 150.000-200.000đ/chiều. Tr&ecirc;n đảo c&oacute; nhiều nh&agrave; nghỉ, homestay gi&aacute; b&igrave;nh d&acirc;n từ 200.000đ/đ&ecirc;m. Thời điểm đẹp nhất: <strong>th&aacute;ng 1 đến th&aacute;ng 8</strong>, tr&aacute;nh m&ugrave;a b&atilde;o từ th&aacute;ng 9-11.</p>', '/tour_khach_san_project/assets/images/blog/blog_1774108823_6978.jpg', '/tour_khach_san_project/assets/images/blog/blog_1774108828_2147.jpg', 'VÕ THỊ LAN', 'Lý Sơn,Quảng Ngãi,Đảo núi lửa,Tỏi Lý Sơn,Lặn biển', '5 phút đọc', 1, 0, '2026-02-20 08:00:00', '2026-04-08 16:11:15'),
(6, 'HỒ ĐĂK KE MĂNG ĐEN – GƯƠNG NƯỚC PHẢN CHIẾU MÂY TRỜI', 'Măng Đen', 'Hồ Đăk Ke ẩn mình trong cánh rừng thông Măng Đen như một tấm gương khổng lồ phản chiếu mây trắng và trời xanh. Buổi sáng sớm, mặt hồ phủ sương mờ ảo như chốn bồng lai tiên cảnh.', '<p>Trong số những hồ nước đẹp của Măng Đen, <strong>Hồ Đăk Ke</strong> được xem là \"viên ngọc\" đẹp nhất — một mặt gương trong vắt phản chiếu những tán thông xanh mướt và bầu trời trong xanh của cao nguyên Kon Plông.</p>\r\n<h3>🌅 Buổi Sáng Sớm — Khoảnh Khắc Vàng</h3>\r\n<p>Nếu chỉ có thể chọn một thời điểm đến Hồ Đăk Ke, hãy chọn <strong>6h-7h30 sáng</strong>. Đây là lúc sương mù từ rừng thông tràn xuống mặt hồ, tạo nên lớp màn trắng mỏng huyền ảo phủ kín mặt nước. Khi ánh nắng đầu ngày xuyên qua những kẽ lá, những tia sáng vàng rọi xuống mặt hồ như những sợi chỉ vàng — một khung cảnh chỉ có thể thấy ở đây.</p>\r\n<h3>🚣 Chèo Thuyền Kayak Trên Hồ</h3>\r\n<p>Du khách có thể thuê thuyền kayak hoặc thuyền tôn để bơi ra giữa hồ với giá từ 50.000-100.000đ/giờ. Đứng trên thuyền giữa mặt hồ phẳng lặng, xung quanh là núi rừng bao phủ — cảm giác yên bình đến mức bạn sẽ quên mất mình đang ở thế kỷ 21. Đây cũng là góc máy tuyệt vời để chụp những bức ảnh phản chiếu hoàn hảo.</p>\r\n<h3>🏕️ Cắm Trại Bên Hồ</h3>\r\n<p>Nhiều nhóm bạn trẻ chọn <strong>cắm trại qua đêm</strong> bên hồ Đăk Ke để được trải nghiệm đêm Măng Đen dưới bầu trời đầy sao và sáng hôm sau đón bình minh ngay bên mặt hồ. Một số đơn vị du lịch địa phương cung cấp dịch vụ cắm trại trọn gói gồm lều, bếp, và thực phẩm với giá từ 250.000đ/người.</p>\r\n<h3>🗺️ Hồ Đăk Ke Nằm Ở Đâu?</h3>\r\n<p>Hồ cách trung tâm thị trấn Măng Đen khoảng 3km, đường đi khá dễ. Từ thị trấn, đi theo đường Hùng Vương về phía Tây, qua cầu rồi rẽ trái là đến hồ. Xung quanh hồ có đường mòn nhỏ cho phép đi bộ vòng quanh hồ (khoảng 2-3km, mất 45-60 phút) — một trải nghiệm tuyệt vời để cảm nhận vẻ đẹp toàn diện của hồ từ nhiều góc độ khác nhau.</p>', 'https://zoomtravel.vn/upload/images/ho-dak-ke-4.jpeg', 'https://zoomtravel.vn/upload/images/ho-dak-ke-4.jpeg', 'ĐINH VĂN THẮNG', 'Măng Đen,Hồ Đăk Ke,Kon Plông,Cắm trại,Kayak', '4 phút đọc', 1, 0, '2026-03-08 08:00:00', '2026-04-08 17:07:56');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `order_code` varchar(30) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `check_in` date DEFAULT NULL,
  `check_out` date DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('bank','momo','vnpay','hotel','card') NOT NULL,
  `note` text DEFAULT NULL,
  `voucher_code` varchar(30) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `refund_requested` tinyint(1) DEFAULT 0,
  `refund_requested_at` datetime DEFAULT NULL,
  `refund_amount` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `order_code`, `user_id`, `room_id`, `full_name`, `email`, `phone`, `check_in`, `check_out`, `total_price`, `payment_method`, `note`, `voucher_code`, `discount_amount`, `status`, `created_at`, `updated_at`, `refund_requested`, `refund_requested_at`, `refund_amount`) VALUES
(1, 'ORD1772629724', 9, NULL, 'bacxiu', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', '2026-02-03', '2031-06-03', 1500000.00, 'momo', '', NULL, 0.00, 'cancelled', '2026-03-04 13:09:53', NULL, 0, NULL, 0.00),
(3, 'ORD1772894265', 9, 23, 'bacxiu', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', '2026-03-14', '2026-03-16', 2000000.00, '', '', NULL, 0.00, 'completed', '2026-03-07 14:38:09', NULL, 0, NULL, 0.00),
(10, 'ORD1773328334', 9, 25, 'Văn Huy', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', '2026-03-15', '2026-03-20', 3250000.00, '', 'háafsa', NULL, 0.00, 'confirmed', '2026-03-12 15:12:40', '2026-04-16 06:52:54', 0, NULL, 0.00),
(23, 'ORD177367644948731', NULL, 37, 'DIGI Thế Giới', 'thegioimarketingdigi@gmail.com', '0934077360', '2026-03-02', '2026-03-01', 750000.00, 'bank', '', NULL, 0.00, 'completed', '2026-03-16 15:55:35', NULL, 0, NULL, 0.00),
(24, 'ORD17736765570.214', NULL, 22, 'DIGI Thế Giới', 'thegioimarketingdigi@gmail.com', '0934077360', '2026-03-02', '2026-03-01', 700000.00, '', '', NULL, 0.00, 'confirmed', '2026-03-16 15:56:36', '2026-04-16 06:52:52', 0, NULL, 0.00),
(26, 'ORD17736784593.280', NULL, 19, 'DIGI Thế Giới', 'thegioimarketingdigi@gmail.com', '0934077360', '2026-03-01', '2025-01-12', 1200000.00, '', '', NULL, 0.00, 'completed', '2026-03-16 16:29:11', '2026-04-16 06:37:46', 0, NULL, 0.00),
(28, 'ORD17745380479.255', 9, 12, 'Lê Văn Huy', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', '2026-03-28', '2026-03-31', 2800000.00, '', '', NULL, 0.00, 'confirmed', '2026-03-26 15:14:23', NULL, 0, NULL, 0.00),
(30, 'ORD17747922928.416', 9, 37, 'Lê Văn Huy', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', '2026-03-30', '2026-04-02', 1800000.00, 'bank', '', NULL, 0.00, 'completed', '2026-03-29 13:53:09', NULL, 0, NULL, 0.00),
(34, 'ORD17747946155071', 9, 37, 'Lê Văn Huy', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', '2026-03-30', '2026-04-02', 1800000.00, 'bank', '', NULL, 0.00, 'completed', '2026-03-29 14:30:15', NULL, 0, NULL, 0.00),
(36, 'ORD17747961157900', 9, 37, 'Lê Văn Huy', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', '2026-03-30', '2026-04-02', 1800000.00, 'bank', '', NULL, 0.00, 'cancelled', '2026-03-29 14:55:15', NULL, 0, NULL, 0.00),
(38, 'ORD17747963241730', 9, 37, 'Lê Văn Huy', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', '2026-03-30', '2026-04-02', 1800000.00, 'bank', '', NULL, 0.00, 'completed', '2026-03-29 14:58:44', NULL, 0, NULL, 0.00),
(40, 'ORD17749397803.589', NULL, 17, 'huy test 6', 'hzbluesestart@gmail.com', '0373848395', '2026-04-02', '2026-04-05', 2040000.00, '', '', NULL, 0.00, 'cancelled', '2026-03-31 06:50:13', '2026-04-16 06:52:59', 0, NULL, 0.00),
(42, 'ORD17756332528.149', NULL, 20, 'Lê Văn Huy', 'lvhuy.kontum@gmail.com', '0373848395', '2026-04-08', '2026-04-11', 2550000.00, 'momo', 'checkin sớm', NULL, 0.00, 'completed', '2026-04-08 07:30:13', NULL, 0, NULL, 0.00),
(43, 'ORD17756424427.325', 9, 30, 'Lê Văn Huy', 'lvhuy.kontum@gmail.com', '0373848395', '2026-04-17', '2026-04-20', 2100000.00, '', '', NULL, 0.00, 'cancelled', '2026-04-08 10:01:12', NULL, 2, '2026-04-08 17:01:53', 1680000.00),
(44, 'ORD17756425767.830', 9, 13, 'Lê Văn Huy', 'lvhuy.kontum@gmail.com', '0373848395', '2026-04-23', '2026-04-27', 1600000.00, 'momo', '', NULL, 0.00, 'completed', '2026-04-08 10:03:14', '2026-04-16 06:37:48', 3, '2026-04-08 17:03:33', 1280000.00),
(45, 'ORD17757462939.740', NULL, 45, 'Lê Văn Huy', 'lvhuy.kontum@gmail.com', '0373848395', '2026-04-23', '2026-04-27', 1200000.00, '', 'test 7', NULL, 0.00, 'cancelled', '2026-04-09 14:52:21', '2026-04-16 06:37:51', 0, NULL, 0.00),
(46, 'BK2026001', 16, 31, 'Nguy?n Th? Mai', 'mai.nguyen@gmail.com', '0901234561', '2026-01-20', '2026-01-22', 1300000.00, 'bank', NULL, NULL, 0.00, 'completed', '2026-01-18 01:00:00', NULL, 0, NULL, 0.00),
(47, 'BK2026002', 17, 34, 'Tr?n Van Nam', 'nam.tran@gmail.com', '0901234562', '2026-02-10', '2026-02-13', 4500000.00, 'momo', NULL, NULL, 0.00, 'completed', '2026-02-08 02:00:00', NULL, 0, NULL, 0.00),
(48, 'BK2026003', 18, 37, 'Ph?m Th? Lan', 'lan.pham@yahoo.com', '0901234563', '2026-02-20', '2026-02-22', 1200000.00, 'bank', NULL, NULL, 0.00, 'completed', '2026-02-18 03:00:00', NULL, 0, NULL, 0.00),
(49, 'BK2026004', 19, 1, 'LÛ Van ð?c', 'duc.le@gmail.com', '0901234564', '2026-03-05', '2026-03-07', 1200000.00, 'hotel', NULL, NULL, 0.00, 'completed', '2026-03-03 04:00:00', NULL, 0, NULL, 0.00),
(50, 'BK2026005', 20, 4, 'HoÓng Th? Huong', 'huong.hoang@gmail.com', '0901234565', '2026-03-15', '2026-03-17', 3200000.00, 'bank', NULL, NULL, 0.00, 'completed', '2026-03-13 05:00:00', NULL, 0, NULL, 0.00),
(51, 'BK2026006', 16, 7, 'Nguy?n Th? Mai', 'mai.nguyen@gmail.com', '0901234561', '2026-03-25', '2026-03-27', 1200000.00, 'momo', NULL, NULL, 0.00, 'completed', '2026-03-23 01:00:00', NULL, 0, NULL, 0.00),
(52, 'BK2026007', 17, 19, 'Tr?n Van Nam', 'nam.tran@gmail.com', '0901234562', '2026-04-05', '2026-04-07', 1700000.00, 'bank', NULL, NULL, 0.00, 'completed', '2026-04-03 02:00:00', NULL, 0, NULL, 0.00),
(53, 'BK2026008', 18, 35, 'Ph?m Th? Lan', 'lan.pham@yahoo.com', '0901234563', '2026-04-12', '2026-04-14', 4400000.00, 'card', NULL, NULL, 0.00, 'completed', '2026-04-10 03:00:00', NULL, 0, NULL, 0.00),
(54, 'BK2026009', 19, 32, 'LÛ Van ð?c', 'duc.le@gmail.com', '0901234564', '2026-04-18', '2026-04-20', 1600000.00, 'bank', NULL, NULL, 0.00, 'completed', '2026-04-16 04:00:00', NULL, 0, NULL, 0.00),
(55, 'BK2026010', 20, 38, 'HoÓng Th? Huong', 'huong.hoang@gmail.com', '0901234565', '2026-04-22', '2026-04-24', 1700000.00, 'momo', NULL, NULL, 0.00, 'completed', '2026-04-20 05:00:00', NULL, 0, NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `hotel_id`, `created_at`) VALUES
(1, 5, 19, '2026-03-07 16:41:36'),
(9, 9, 32, '2026-04-14 06:31:16'),
(10, 9, 27, '2026-04-14 06:35:50');

-- --------------------------------------------------------

--
-- Table structure for table `hotels`
--

CREATE TABLE `hotels` (
  `id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `location_id` int(11) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 0.0,
  `review_text` varchar(50) DEFAULT NULL,
  `review_count` int(11) DEFAULT 0,
  `price` int(11) DEFAULT NULL,
  `old_price` int(11) DEFAULT NULL,
  `checkin_time` varchar(10) DEFAULT '14:00',
  `checkout_time` varchar(10) DEFAULT '12:00',
  `is_active` tinyint(1) DEFAULT 0,
  `is_weekend_deal` tinyint(1) NOT NULL DEFAULT 0,
  `star_category` tinyint(1) NOT NULL DEFAULT 3 COMMENT '1-5 sao (hang khach san)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotels`
--

INSERT INTO `hotels` (`id`, `name`, `address`, `description`, `image`, `created_at`, `updated_at`, `location_id`, `rating`, `review_text`, `review_count`, `price`, `old_price`, `checkin_time`, `checkout_time`, `is_active`, `is_weekend_deal`, `star_category`) VALUES
(17, 'Khách sạn Indochine Kon Tum', 'Kon Tum', 'Gần sông Đăk Bla, view đẹp', 'hotel2.jpg', '2026-02-04 14:03:10', NULL, 2, 7.8, 'Rất tốt', 247, 500000, 625000, '14:00', '12:00', 1, 1, 3),
(18, 'Khách sạn CocoLand Thu Xá Quảng Ngãi', 'Quảng Ngãi', 'Gần biển Mỹ Khê', 'hotel3.jpg', '2026-02-04 14:03:10', NULL, 3, 8.5, 'Tuyệt vời', 389, 1500000, 1875000, '14:00', '12:00', 1, 1, 3),
(19, 'Khách sạn Casa Vanilla Măng đen', 'Măng Đen', 'Khách sạn nghỉ dưỡng giữa rừng thông', 'hotel1.jpg', '2026-02-04 13:53:32', NULL, 1, 9.1, 'Trên cả tuyệt vời', 62, 600000, 750000, '14:00', '12:00', 1, 1, 3),
(25, 'Măng Đen Green Forest Hotel', 'Trung tâm Măng Đen, Kon Tum', 'Khách sạn giữa rừng thông, không khí mát mẻ quanh năm', 'manden_green.jpg', '2026-03-04 03:35:04', NULL, 1, 8.9, 'Tuyệt vời', 154, 450000, 563000, '14:00', '12:00', 1, 0, 3),
(26, 'Golden Boutique Hotel Măng Đen', '38 Nguyễn Du, Thị trấn Măng Đen, Kon Plông, Kon Tum.', 'View săn mây và hoàng hôn cực đẹp', 'Golden_Boutique.jpg', '2026-03-04 03:35:04', NULL, 1, 9.2, 'Trên cả tuyệt vời', 203, 1400000, 1750000, '14:00', '12:00', 1, 0, 3),
(27, 'Măng Đen Mountain Lodge', 'Hồ Xuân Hương, khu du lịch Măng Đen, Kon Plông, Kon Tum', 'Phong cách sinh thái, bungalow gỗ riêng tư', 'manden_eco.jpg', '2026-03-04 03:35:04', NULL, 1, 8.7, 'Rất tốt', 98, 600000, 750000, '14:00', '12:00', 1, 0, 3),
(28, 'Resort Đăk Ke Măng Đen', 'Hồ Đăk Ke, thị trấn Măng Đen', 'Thiên đường bên hồ thơ mộng', 'Resort_Đăk_Ke.jpg', '2026-03-04 03:35:04', NULL, 1, 9.0, 'Tuyệt vời', 176, 650000, 813000, '14:00', '12:00', 1, 0, 3),
(29, 'Mỹ Khê Beach Hotel', 'Bãi biển Mỹ Khê, Quảng Ngãi', 'Khách sạn sát biển, phòng hướng biển', 'mykhe_beach.jpg', '2026-03-04 03:36:27', NULL, 3, 8.6, 'Rất tốt', 210, 400000, 500000, '14:00', '12:00', 1, 0, 3),
(30, 'Lý Sơn Pearl Island Hotel & Resort', 'An Vĩnh, Lý Sơn, Quảng Ngãi', 'View biển rộng, phòng sang trọng', 'Lý_Sơn_ocean.jpg', '2026-03-04 03:36:27', NULL, 3, 8.8, 'Tuyệt vời', 134, 600000, 750000, '14:00', '12:00', 1, 0, 3),
(31, 'Sa Huỳnh Resort & Spa', 'Sa Huỳnh, Quảng Ngãi', 'Resort nghỉ dưỡng cao cấp ven biển', 'sahuynh_resort.jpg', '2026-03-04 03:36:27', NULL, 3, 9.1, 'Trên cả tuyệt vời', 287, 750000, 938000, '14:00', '12:00', 1, 0, 3),
(32, 'Friendly Homestay Kon Tum', '32 Ngô Tất Tố, Phường Quyết Thắng, Kon Tum', 'Khách sạn view sông lãng mạn', 'Friendly_Homestay_kontum.jpg', '2026-03-04 03:37:19', NULL, 2, 5.0, 'Trên cả tuyệt vời', 1, 300000, 375000, '14:00', '12:00', 1, 0, 3),
(33, 'Window 1 Hotel Kon Tum', '189 (số cũ 06) Đoàn Thị Điểm, Phường Quyết Thắng, Kon Tum', 'Vị trí trung tâm, thuận tiện di chuyển', 'window1_kontum.jpg', '2026-03-04 03:37:19', NULL, 2, 8.3, 'Tốt', 116, 300000, 375000, '14:00', '12:00', 1, 0, 3),
(34, 'Bông Boutique Hotel', 'Nguyễn Huệ, Kon Tum', 'Khách sạn phong cách hiện đại', 'bong_boutique.jpg', '2026-03-04 03:37:19', NULL, 2, 5.0, 'Trên cả tuyệt vời', 1, 350000, 438000, '14:00', '12:00', 1, 0, 3);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_images`
--

CREATE TABLE `hotel_images` (
  `id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `caption` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotel_images`
--

INSERT INTO `hotel_images` (`id`, `hotel_id`, `image`, `caption`, `sort_order`, `is_primary`) VALUES
(1, 17, 'indochine_hotel/indochine_hotel1.jpg', NULL, 1, 0),
(2, 17, 'indochine_hotel/indochine_hotel2.jpg', NULL, 2, 0),
(3, 17, 'indochine_hotel/indochine_hotel3.jpg', NULL, 3, 0),
(4, 17, 'indochine_hotel/indochine_hotel4.jpg', NULL, 4, 0),
(5, 32, 'Friendly_Homestay_KonTum/Friendly_Homestay_KonTum1.jpg', '', 1, 0),
(6, 32, 'Friendly_Homestay_KonTum/Friendly_Homestay_KonTum2.jpg', '', 2, 0),
(7, 32, 'Friendly_Homestay_KonTum/Friendly_Homestay_KonTum3.jpg', '', 3, 0),
(8, 32, 'Friendly_Homestay_KonTum/Friendly_Homestay_KonTum4.jpg', '', 4, 0),
(9, 34, 'Bông_Boutique_Hotel/Bông_Boutique_Hotel1.jpg', '', 1, 0),
(10, 34, 'Bông_Boutique_Hotel/Bông_Boutique_Hotel2.jpg', '', 2, 0),
(11, 34, 'Bông_Boutique_Hotel/Bông_Boutique_Hotel3.jpg', '', 3, 0),
(12, 34, 'Bông_Boutique_Hotel/Bông_Boutique_Hotel4.jpg', '', 4, 0),
(17, 33, 'Window1_Hotel_KonTum/Window1_Hotel_KonTum1.jpg', '', 1, 0),
(18, 33, 'Window1_Hotel_KonTum/Window1_Hotel_KonTum2.jpg', '', 2, 0),
(19, 33, 'Window1_Hotel_KonTum/Window1_Hotel_KonTum3.jpg', '', 3, 0),
(20, 33, 'Window1_Hotel_KonTum/Window1_Hotel_KonTum4.jpg', '', 4, 0),
(21, 29, 'My_Khe_Beach_Hotel/My_Khe_Beach_Hotel1.jpg', '', 1, 0),
(22, 29, 'My_Khe_Beach_Hotel/My_Khe_Beach_Hotel2.jpg', '', 2, 0),
(23, 29, 'My_Khe_Beach_Hotel/My_Khe_Beach_Hotel3.jpg', '', 3, 0),
(24, 29, 'My_Khe_Beach_Hotel/My_Khe_Beach_Hotel4.jpg', '', 4, 0),
(45, 30, 'Ly_Son_Pearl_Island_HotelResort/Ly_Son_Pearl_Island_HotelResort1.jpg', '', 1, 0),
(46, 30, 'Ly_Son_Pearl_Island_HotelResort/Ly_Son_Pearl_Island_HotelResort2.jpg', '', 2, 0),
(47, 30, 'Ly_Son_Pearl_Island_HotelResort/Ly_Son_Pearl_Island_HotelResort3.jpg', '', 3, 0),
(48, 30, 'Ly_Son_Pearl_Island_HotelResort/Ly_Son_Pearl_Island_HotelResort4.jpg', '', 4, 0),
(49, 31, 'Sa_Huynh_ResortSpa/Sa_Huynh_ResortSpa1.jpg', '', 1, 0),
(50, 31, 'Sa_Huynh_ResortSpa/Sa_Huynh_ResortSpa2.jpg', '', 2, 0),
(51, 31, 'Sa_Huynh_ResortSpa/Sa_Huynh_ResortSpa3.jpg', '', 3, 0),
(52, 31, 'Sa_Huynh_ResortSpa/Sa_Huynh_ResortSpa4.jpg', '', 4, 0),
(57, 25, 'Mang_ĐenGreen_ForestHotel/Mang_ĐenGreen_ForestHotel1.jpg', '', 1, 0),
(58, 25, 'Mang_ĐenGreen_ForestHotel/Mang_ĐenGreen_ForestHotel2.jpg', '', 2, 0),
(59, 25, 'Mang_ĐenGreen_ForestHotel/Mang_ĐenGreen_ForestHotel3.jpg', '', 3, 0),
(60, 25, 'Mang_ĐenGreen_ForestHotel/Mang_ĐenGreen_ForestHotel4.jpg', '', 4, 0),
(61, 26, 'Golden_Boutique_HotelMangĐen/Golden_Boutique_HotelMangĐen1.jpg', '', 1, 0),
(62, 26, 'Golden_Boutique_HotelMangĐen/Golden_Boutique_HotelMangĐen2.jpg', '', 2, 0),
(63, 26, 'Golden_Boutique_HotelMangĐen/Golden_Boutique_HotelMangĐen3.jpg', '', 3, 0),
(64, 26, 'Golden_Boutique_HotelMangĐen/Golden_Boutique_HotelMangĐen4.jpg', '', 4, 0),
(65, 27, 'MangDen_MountainLodge/MangDen_MountainLodge1.jpg', '', 1, 0),
(66, 27, 'MangDen_MountainLodge/MangDen_MountainLodge2.jpg', '', 2, 0),
(67, 27, 'MangDen_MountainLodge/MangDen_MountainLodge3.jpg', '', 3, 0),
(68, 27, 'MangDen_MountainLodge/MangDen_MountainLodge4.jpg', '', 4, 0),
(69, 28, 'Resort_ĐakKeMangDen/Resort_ĐakKeMangDen1.jpg', '', 1, 0),
(70, 28, 'Resort_ĐakKeMangDen/Resort_ĐakKeMangDen2.jpg', '', 2, 0),
(71, 28, 'Resort_ĐakKeMangDen/Resort_ĐakKeMangDen3.jpg', '', 3, 0),
(72, 28, 'Resort_ĐakKeMangDen/Resort_ĐakKeMangDen4.jpg', '', 4, 0),
(77, 18, 'KhachSanCocoLand_ThuXaQuangNgai/KhachSanCocoLand_ThuXaQuangNgai1.jpg', '', 1, 0),
(78, 18, 'KhachSanCocoLand_ThuXaQuangNgai/KhachSanCocoLand_ThuXaQuangNgai2.jpg', '', 2, 0),
(79, 18, 'KhachSanCocoLand_ThuXaQuangNgai/KhachSanCocoLand_ThuXaQuangNgai3.jpg', '', 3, 0),
(80, 18, 'KhachSanCocoLand_ThuXaQuangNgai/KhachSanCocoLand_ThuXaQuangNgai4.jpg', '', 4, 0),
(81, 19, 'KhachSan_CasaVanillaMangDen/KhachSan_CasaVanillaMangDen1.jpg', '', 1, 0),
(82, 19, 'KhachSan_CasaVanillaMangDen/KhachSan_CasaVanillaMangDen2.jpg', '', 2, 0),
(83, 19, 'KhachSan_CasaVanillaMangDen/KhachSan_CasaVanillaMangDen3.jpg', '', 3, 0),
(84, 19, 'KhachSan_CasaVanillaMangDen/KhachSan_CasaVanillaMangDen4.jpg', '', 4, 0);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `description`, `is_active`, `created_at`, `image`) VALUES
(1, 'Măng Đen', 'Thị trấn du lịch sinh thái, khí hậu mát mẻ quanh năm', 1, '2026-04-15 06:33:10', 'mangden.jpg'),
(2, 'Kon Tum', 'Vùng đất Tây Nguyên với nhà rông và văn hóa bản địa', 1, '2026-04-15 06:33:10', 'kontum.jpg'),
(3, 'Quảng Ngãi', 'Nổi tiếng với biển đảo và danh thắng thiên nhiên', 1, '2026-04-15 06:33:10', 'quangngai.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `hotel_id` int(11) DEFAULT NULL,
  `hotel_name` varchar(255) DEFAULT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `qr_scanned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `hotel_id`, `hotel_name`, `room_name`, `payment_method`, `full_name`, `email`, `phone`, `amount`, `payment_status`, `qr_scanned`, `created_at`, `updated_at`) VALUES
(29, 38, 19, 'Khách sạn Casa Vanilla Măng đen', 'Phòng Tiêu Chuẩn', 'Chuyển khoản ngân hàng', 'Lê Văn Huy', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', 1800000.00, 'paid', 0, '2026-03-29 14:58:44', NULL),
(31, 40, 30, 'Lý Sơn Pearl Island Hotel & Resort', 'Phòng Cao Cấp', 'Thanh toán tại khách sạn', 'huy test 6', 'hzbluesestart@gmail.com', '0373848395', 2040000.00, 'paid', 0, '2026-03-31 06:50:13', NULL),
(33, 42, 31, 'Sa Huỳnh Resort & Spa', 'Phòng Cao Cấp', 'Ví MoMo', 'Lê Văn Huy', 'lvhuy.kontum@gmail.com', '0373848395', 2550000.00, 'paid', 0, '2026-04-08 07:30:13', NULL),
(34, 43, 34, 'Bông Boutique Hotel', 'Phòng Hạng Sang', 'Thanh toán tại khách sạn', 'Lê Văn Huy', 'lvhuy.kontum@gmail.com', '0373848395', 2100000.00, 'refunded', 0, '2026-04-08 10:01:12', NULL),
(35, 44, 29, 'Mỹ Khê Beach Hotel', 'Phòng Tiêu Chuẩn', 'Ví MoMo', 'Lê Văn Huy', 'lvhuy.kontum@gmail.com', '0373848395', 1600000.00, 'paid', 0, '2026-04-08 10:03:14', NULL),
(36, 45, 32, 'Friendly Homestay Kon Tum', 'Phòng Tiết Kiệm', 'Thanh toán tại khách sạn', 'Lê Văn Huy', 'lvhuy.kontum@gmail.com', '0373848395', 1200000.00, 'paid', 0, '2026-04-09 14:52:21', '2026-04-16 06:53:07');

-- --------------------------------------------------------

--
-- Table structure for table `places`
--

CREATE TABLE `places` (
  `id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `places`
--

INSERT INTO `places` (`id`, `name`, `description`, `image`, `location_id`, `is_active`, `created_at`) VALUES
(1, 'Hồ Đăk Ke', 'Hồ nước trong xanh giữa rừng thông, buổi sáng sương mù bao phủ tạo cảnh đẹp huyền ảo. Có thể thuê kayak, chèo thuyền ngắm cảnh.', 'places/manden_ho_dakke.jpg', 1, 1, '2026-04-15 06:33:10'),
(2, 'Thác Pa Sỹ', 'Thác nước đổ từ độ cao hơn 30m xuống bãi đá granite trắng, một trong những thác đẹp nhất Tây Nguyên.', 'places/manden_thac_pasy.jpg', 1, 1, '2026-04-15 06:33:10'),
(3, 'Rừng thông Măng Đen', 'Cánh rừng thông bạt ngàn, khí hậu mát mẻ 18-22°C quanh năm. Lý tưởng để đi bộ, cắm trại và hít thở không khí trong lành.', 'places/rung thong.jpg', 1, 1, '2026-04-15 06:33:10'),
(4, 'Hồ Toong Zơ Ri', 'Hồ nước tự nhiên đẹp với làn nước xanh biếc, xung quanh là rừng nguyên sinh. Điểm check-in lý tưởng cho giới trẻ.', 'places/manden_ho_toong_zori.jpg', 1, 1, '2026-04-15 06:33:10'),
(5, 'Vườn dâu tây Măng Đen', 'Các vườn dâu tây sạch nằm giữa khí hậu mát mẻ, du khách có thể tự tay hái dâu tươi và thưởng thức tại chỗ.', 'places/manden_vuon_dau_tay.jpg', 1, 1, '2026-04-15 06:33:10'),
(7, 'Làng dân tộc Kon Bring', 'Làng đồng bào Mơ Nâm, Xơ Đăng còn lưu giữ nhiều nét văn hóa truyền thống: nhà sàn, lễ hội cồng chiêng, dệt thổ cẩm.', 'places/manden_lang_kon_bring.jpg', 1, 1, '2026-04-15 06:33:10'),
(8, 'Nhà thờ Gỗ Kon Tum', 'Công trình kiến trúc độc đáo xây năm 1913, làm hoàn toàn bằng gỗ cà chít theo phong cách Romanesque kết hợp nhà rông Ba Na. Biểu tượng của thành phố Kon Tum.', 'places/kontum_nha_tho_go.jpg', 2, 1, '2026-04-15 06:33:10'),
(9, 'Cầu treo Kon Klor', 'Cầu treo bắc qua sông Đăk Bla, từ trên cầu nhìn xuống dòng sông xanh mát uốn lượn giữa núi đồi. Bên kia cầu là làng Ba Na truyền thống.', 'places/kontum_cau_treo_konklor.jpg', 2, 1, '2026-04-15 06:33:10'),
(10, 'Sông Đăk Bla', 'Con sông chảy ngược độc đáo của Tây Nguyên. Buổi chiều hoàng hôn trên sông Đăk Bla là một trong những cảnh đẹp nhất Kon Tum.', 'places/kontum_song_dakbla.jpg', 2, 1, '2026-04-15 06:33:10'),
(11, 'Làng văn hóa Ba Na - Kon Klor', 'Làng đồng bào Ba Na ngay sát thành phố, còn lưu giữ nhà rông truyền thống, tượng nhà mồ và các lễ hội cồng chiêng đặc sắc.', 'places/kontum_lang_bana.jpg', 2, 1, '2026-04-15 06:33:10'),
(12, 'Nhà Rông Kon Tum', 'Nhà rông truyền thống của người Ba Na - công trình kiến trúc độc đáo được làm hoàn toàn bằng tre nứa, là trung tâm sinh hoạt văn hóa cộng đồng.', 'places/kontum_nha_rong.jpg', 2, 1, '2026-04-15 06:33:10'),
(13, 'Bảo tàng tỉnh Kon Tum', 'Lưu giữ hơn 3.000 hiện vật về lịch sử, văn hóa các dân tộc Tây Nguyên. Nơi tìm hiểu sâu về văn hóa bản địa Kon Tum.', 'places/kontum_bao_tang.jpg', 2, 1, '2026-04-15 06:33:10'),
(14, 'Chùa Bác Ái', 'Ngôi chùa Phật giáo nổi tiếng tại Kon Tum với kiến trúc độc đáo và không gian yên tĩnh, thanh bình giữa lòng thành phố.', 'places/kontum_chua_bac_ai.jpg', 2, 1, '2026-04-15 06:33:10'),
(15, 'Biển Mỹ Khê Quảng Ngãi', 'Bãi biển dài 10km với cát trắng mịn, nước trong xanh. Buổi sáng có chợ cá tươi ngay trên bãi biển, hải sản tươi ngon giá rẻ.', 'places/quangngai_bien_my_khe.jpg', 3, 1, '2026-04-15 06:33:10'),
(16, 'Đảo Lý Sơn', 'Hòn đảo núi lửa độc nhất miền Trung với cánh đồng tỏi xanh mướt, miệng núi lửa Thới Lới, làn nước biển trong vắt và san hô đa dạng. Đi tàu cao tốc 30 phút từ cảng Sa Kỳ.', 'places/quangngai_dao_ly_son.jpg', 3, 1, '2026-04-15 06:33:10'),
(17, 'Miệng núi lửa Thới Lới - Lý Sơn', 'Leo lên đỉnh miệng núi lửa Thới Lới (169m) ngắm toàn cảnh đảo Lý Sơn giữa biển khơi — một trong những điểm ngắm cảnh đẹp nhất miền Trung.', 'places/quangngai_nui_lua_thoi_loi.jpg', 3, 1, '2026-04-15 06:33:10'),
(18, 'Chùa Hang - Lý Sơn', 'Ngôi chùa cổ được tạc vào vách đá núi lửa basalt hàng trăm năm trước, công trình tâm linh độc đáo chỉ có ở Lý Sơn.', 'places/quangngai_chua_hang.jpg', 3, 1, '2026-04-15 06:33:10'),
(19, 'Di tích Sơn Mỹ', 'Khu di tích lịch sử ghi dấu sự kiện lịch sử năm 1968, hiện là bảo tàng và công viên tưởng niệm trang nghiêm.', 'places/quangngai_son_my.jpg', 3, 1, '2026-04-15 06:33:10'),
(20, 'Thác Trắng Ba Tơ', 'Thác nước hùng vĩ nằm trong rừng nguyên sinh huyện Ba Tơ, cảnh quan hoang sơ và làn nước mát lạnh trong vắt.', 'places/quangngai_thac_trang.jpg', 3, 1, '2026-04-15 06:33:10'),
(21, 'Biển Sa Huỳnh', 'Bãi biển yên tĩnh và đẹp, nổi tiếng với muối Sa Huỳnh và văn hóa Sa Huỳnh cổ đại. Hoàng hôn trên biển Sa Huỳnh rất nổi tiếng.', 'places/quangngai_bien_sa_huynh.jpg', 3, 1, '2026-04-15 06:33:10'),
(22, 'Cổng Tò Vò - Lý Sơn', 'Vòm đá tự nhiên hình thành từ dung nham núi lửa, một trong những địa danh check-in nổi tiếng nhất đảo Lý Sơn.', 'places/quangngai_cong_to_vo.jpg', 3, 1, '2026-04-15 06:33:10');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL COMMENT '1-5 sao',
  `comment` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `hotel_id`, `user_id`, `booking_id`, `rating`, `comment`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 32, 9, 3, 5, 'trải nghiệm tốt, nhân viên phục vụ nhiệt tình', 1, '2026-04-07 22:53:53', NULL),
(27, 17, 16, 46, 8, 'Khßch s?n s?ch s?, v? trÝ trung tÔm Kon Tum, thu?n ti?n di l?i. NhÔn viÛn thÔn thi?n vÓ nhi?t týnh.', 1, '2026-01-23 10:00:00', NULL),
(28, 18, 17, 47, 9, 'Ph‗ng r?ng rÒi, view d?p nhýn ra bi?n. B?a sßng buffet da d?ng vÓ ngon. R?t hÓi l‗ng, s? quay l?i!', 1, '2026-02-14 09:30:00', NULL),
(29, 19, 18, 48, 10, 'Tuy?t v?i! Kh¶ng khÝ trong lÓnh gi?a r?ng Mang ðen, d?ch v? chuyÛn nghi?p. X?ng dßng lÓ di?m d?n hÓng d?u.', 1, '2026-02-23 11:00:00', NULL),
(30, 25, 19, 49, 9, 'Resort xanh mßt gi?a r?ng th¶ng, yÛn tinh vÓ thu giÒn. NhÔn viÛn nhi?t týnh, h? tr? chu dßo.', 1, '2026-03-08 14:00:00', NULL),
(31, 26, 20, 50, 10, 'Khßch s?n boutique sang tr?ng, thi?t k? tinh t?. Giß cao nhung hoÓn toÓn x?ng dßng v?i ch?t lu?ng d?ch v?.', 1, '2026-03-18 16:00:00', NULL),
(32, 27, 16, 51, 8, 'View n·i tuy?t d?p bu?i sßng suong m¨. Ph‗ng ?m c·ng, giu?ng ng? tho?i mßi. An sßng ngon.', 1, '2026-03-28 09:00:00', NULL),
(33, 31, 17, 52, 9, 'Spa tuy?t v?i, h? boi nu?c m?n r?t s?ch vÓ d?p. G?n bi?n Sa Hu?nh. K? ngh? th?c s? thu giÒn!', 1, '2026-04-08 10:00:00', NULL),
(34, 18, 18, 53, 8, 'L?n th? 2 ? dÔy, v?n r?t hÓi l‗ng. Ph‗ng tiÛu chu?n nhung s?ch vÓ d?y d? ti?n nghi. V? trÝ g?n bi?n.', 1, '2026-04-15 15:00:00', NULL),
(35, 17, 19, 54, 7, 'Ph‗ng s?ch, di?u h‗a ho?t d?ng t?t. NhÓ hÓng trong khßch s?n c¾ nhi?u m¾n an d?c s?n TÔy NguyÛn.', 1, '2026-04-21 08:30:00', NULL),
(36, 19, 20, 55, 9, 'Casa Vanilla c?c k? d?p vÓ lÒng m?n. Thi?t k? d?c dßo, h‗a quy?n v?i thiÛn nhiÛn Mang ðen. Xu?t s?c!', 1, '2026-04-25 17:00:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `hotel_id` int(11) DEFAULT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `bed_type` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `max_guests` int(11) NOT NULL DEFAULT 2,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `hotel_id`, `room_name`, `bed_type`, `price`, `quantity`, `is_active`, `description`, `max_guests`, `created_at`, `updated_at`, `image`) VALUES
(1, 25, 'Phòng Tiêu Chuẩn', '1 giường đơn lớn', 600000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(2, 25, 'Phòng Cao Cấp', '2 giường đơn', 850000.00, 3, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(3, 25, 'Phòng Hạng Sang', '1 giường lớn', 1200000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(4, 26, 'Phòng Tiêu Chuẩn', '2 giường đơn', 1600000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(5, 26, 'Phòng Cao Cấp', '1 giường đơn lớn', 1400000.00, 3, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(6, 26, 'Phòng Hạng Sang', '1 giường King lớn', 2500000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(7, 27, 'Phòng Tiêu Chuẩn', '1 giường đơn lớn', 600000.00, 6, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(8, 27, 'Phòng Cao Cấp', '1 giường đôi lớn', 700000.00, 4, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(9, 27, 'Phòng Hạng Sang', '2 giường đôi lớn', 1200000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(10, 28, 'Phòng Tiêu Chuẩn', '1 giường đơn lớn', 650000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(11, 28, 'Phòng Cao Cấp', '1 giường đôi lớn', 800000.00, 3, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(12, 28, 'Phòng Hạng Sang', '2 giường đôi lớn', 1400000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(13, 29, 'Phòng Tiêu Chuẩn', '1 giường đơn lớn', 400000.00, 6, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(14, 29, 'Phòng Cao Cấp', '1 giường đôi lớn', 500000.00, 4, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(15, 29, 'Phòng Hạng Sang', '1 giường lớn', 950000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(16, 30, 'Phòng Tiêu Chuẩn', '1 giường đơn lớn', 600000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(17, 30, 'Phòng Cao Cấp', '1 giường đôi lớn', 680000.00, 3, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(18, 30, 'Phòng Hạng Sang', '1 giường lớn', 1500000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(19, 31, 'Phòng Tiêu Chuẩn', '1 giường đơn lớn', 750000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(20, 31, 'Phòng Cao Cấp', '1 giường đôi lớn', 850000.00, 3, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(21, 31, 'Phòng Hạng Sang', '1 giường lớn', 1800000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(22, 32, 'Phòng Tiêu Chuẩn', '1 giường đôi lớn', 400000.00, 6, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(23, 32, 'Phòng Cao Cấp', '1 giường đôi lớn (view)', 500000.00, 4, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(25, 33, 'Phòng Tiêu Chuẩn', '1 giường đơn lớn', 350000.00, 6, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(26, 33, 'Phòng Cao Cấp', '1 giường đôi lớn', 390000.00, 4, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(27, 33, 'Phòng Hạng Sang', '2 giường đôi lớn', 550000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(28, 34, 'Phòng Tiêu Chuẩn', '1 giường đơn lớn', 350000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(29, 34, 'Phòng Cao Cấp', '1 giường đôi lớn', 410000.00, 3, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(30, 34, 'Phòng Hạng Sang', '1 giường lớn', 700000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(31, 17, 'Phòng Tiêu Chuẩn', '1 giường đôi lớn', 650000.00, 6, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(32, 17, 'Phòng Cao Cấp', '2 giường đơn', 800000.00, 4, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(33, 17, 'Phòng Hạng Sang', '1 giường lớn', 1100000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(34, 18, 'Phòng Tiêu Chuẩn', '1 giường đôi lớn', 1500000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(35, 18, 'Phòng Cao Cấp', '2 giường đơn', 2200000.00, 3, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(36, 18, 'Phòng Hạng Sang', '2 giường đôi lớn (4 người)', 4500000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(37, 19, 'Phòng Tiêu Chuẩn', '1 giường đôi lớn', 600000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(38, 19, 'Phòng Cao Cấp', '2 giường đơn', 850000.00, 3, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(39, 19, 'Phòng Hạng Sang', '1 giường lớn', 1100000.00, 2, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(43, 17, 'Phòng Tiết Kiệm', '1 giường đơn', 500000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(44, 25, 'Phòng Tiết Kiệm', '1 giường đơn', 450000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(45, 32, 'Phòng Tiết Kiệm', '1 giường đơn', 300000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL),
(46, 33, 'Phòng Tiết Kiệm', '1 giường đơn', 300000.00, 5, 1, NULL, 2, '2026-04-15 06:32:34', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `key` varchar(80) NOT NULL,
  `value` text DEFAULT NULL,
  `label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `group_name` varchar(60) NOT NULL DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `key`, `value`, `label`, `group_name`, `updated_at`) VALUES
(1, 'site_name', 'StayGo', 'Tên website', 'general', '2026-04-15 13:49:28'),
(2, 'site_email', 'hello.staygovn@gmail.com', 'Email liên hệ', 'general', '2026-04-15 13:49:28'),
(3, 'site_phone', '0373848395', 'Số điện thoại', 'general', '2026-04-15 13:49:28'),
(4, 'site_address', '146 Nguyễn Văn Cừ, Kon Tum', 'Địa chỉ', 'general', '2026-04-15 13:49:28'),
(5, 'facebook_url', '', 'Link Facebook', 'social', '2026-04-15 06:24:20'),
(6, 'zalo_url', '', 'Link Zalo OA', 'social', '2026-04-15 06:24:20'),
(7, 'booking_fee_pct', '20', 'Phí hủy đặt phòng (%)', 'booking', '2026-04-15 13:49:28'),
(8, 'max_advance_days', '30', 'Đặt trước tối đa (ngày)', 'booking', '2026-04-15 13:49:28'),
(9, 'maintenance_mode', '0', 'Chế độ bảo trì', 'system', '2026-04-15 13:49:28');

-- --------------------------------------------------------

--
-- Table structure for table `support_requests`
--

CREATE TABLE `support_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `status` enum('pending','processing','resolved') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_requests`
--

INSERT INTO `support_requests` (`id`, `user_id`, `full_name`, `phone`, `email`, `subject`, `note`, `admin_note`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 'huy', '0373848395', 'lvhuy.k22tt@kontum.udn.vn', 'lỗi thanh toán', 'hỗ trợ thanh toán', '', 'resolved', '2026-04-01 22:32:32', '2026-04-01 22:32:47'),
(2, NULL, 'Lê Văn Huy', '0373848395', '', '', 'tư vấn đặt phòng', '', 'resolved', '2026-04-01 22:35:39', '2026-04-08 17:10:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `avatar` varchar(255) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `reset_code` varchar(10) DEFAULT NULL,
  `reset_expire` datetime DEFAULT NULL,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expire` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `role`, `status`, `is_banned`, `avatar`, `last_login_at`, `created_at`, `updated_at`, `reset_code`, `reset_expire`, `otp_code`, `otp_expire`) VALUES
(5, 'Administrator', 'admin@gmail.com', NULL, '$2y$10$reN4JHI7g.Ci1TkWefBjm.NirppL/lvfVROcyu6uynp5Uob68FkFW', 'admin', 'active', 0, NULL, NULL, '2026-03-06 13:46:55', NULL, NULL, NULL, NULL, NULL),
(9, 'Lê Văn Huy', 'lvhuy.k22tt@kontum.udn.vn', '0373848395', '$2y$10$fzeO0O/GjMH25oB5ubGGkeb384yO8myvzUktwa2yKTE.I5/f5/fXy', 'user', 'active', 0, NULL, NULL, '2026-03-15 14:20:14', '2026-04-08 09:22:20', NULL, NULL, NULL, NULL),
(15, 'Admin 2', 'hq294677@gmail.com', NULL, '$2y$10$sB5ZYKu7yWZSj54S3Yz/nOAApajtPxd4JgLf55rDvFoL9q.3Og5x6', 'admin', 'active', 0, NULL, NULL, '2026-04-08 09:57:21', NULL, NULL, NULL, NULL, NULL),
(16, 'Nguy?n Th? Mai', 'mai.nguyen@gmail.com', NULL, '$2y$10$abcdefghijklmnopqrstuuVGZzYi8HXvB8YlsIU7rEKpAFLCqWm6', 'user', 'active', 0, NULL, NULL, '2026-01-15 01:00:00', NULL, NULL, NULL, NULL, NULL),
(17, 'Tr?n Van Nam', 'nam.tran@gmail.com', NULL, '$2y$10$abcdefghijklmnopqrstuuVGZzYi8HXvB8YlsIU7rEKpAFLCqWm6', 'user', 'active', 0, NULL, NULL, '2026-02-10 02:30:00', NULL, NULL, NULL, NULL, NULL),
(18, 'Ph?m Th? Lan', 'lan.pham@yahoo.com', NULL, '$2y$10$abcdefghijklmnopqrstuuVGZzYi8HXvB8YlsIU7rEKpAFLCqWm6', 'user', 'active', 0, NULL, NULL, '2026-02-20 07:00:00', NULL, NULL, NULL, NULL, NULL),
(19, 'LÛ Van ð?c', 'duc.le@gmail.com', NULL, '$2y$10$abcdefghijklmnopqrstuuVGZzYi8HXvB8YlsIU7rEKpAFLCqWm6', 'user', 'active', 0, NULL, NULL, '2026-03-05 03:00:00', NULL, NULL, NULL, NULL, NULL),
(20, 'HoÓng Th? Huong', 'huong.hoang@gmail.com', NULL, '$2y$10$abcdefghijklmnopqrstuuVGZzYi8HXvB8YlsIU7rEKpAFLCqWm6', 'user', 'active', 0, NULL, NULL, '2026-03-15 04:00:00', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_uses` int(11) NOT NULL DEFAULT 1,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `type`, `value`, `min_order`, `max_uses`, `used_count`, `expires_at`, `is_active`, `description`, `created_at`) VALUES
(1, 'SUMMER2026', 'percent', 10.00, 0.00, 100, 0, '2026-12-31', 1, 'Gi?m 10% cho m?i d?t ph‗ng', '2026-04-15 07:03:19'),
(2, 'WELCOME20', 'percent', 20.00, 500000.00, 50, 0, '2026-12-31', 1, 'Gi?m 20% cho khßch hÓng m?i', '2026-04-30 17:00:00'),
(3, 'STAYGOFIXED', 'fixed', 200000.00, 1000000.00, 30, 0, '2026-09-30', 1, 'Gi?m 200.000d cho don t? 1 tri?u', '2026-04-30 17:00:00'),
(4, 'MANGDEN15', 'percent', 15.00, 600000.00, 40, 0, '2026-08-31', 1, 'Uu dÒi 15% khßch s?n Mang ðen', '2026-04-30 17:00:00'),
(5, 'HOLIDAY30', 'percent', 30.00, 2000000.00, 20, 0, '2026-06-30', 1, 'Sale hÞ - Gi?m 30% don t? 2 tri?u', '2026-04-30 17:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_admin` (`admin_id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_hotel` (`user_id`,`hotel_id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `hotels`
--
ALTER TABLE `hotels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hotels_location_id` (`location_id`),
  ADD KEY `idx_hotels_is_active` (`is_active`),
  ADD KEY `idx_hotels_rating` (`rating`);

--
-- Indexes for table `hotel_images`
--
ALTER TABLE `hotel_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `fk_payments_hotel` (`hotel_id`);

--
-- Indexes for table `places`
--
ALTER TABLE `places`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_booking_review` (`booking_id`),
  ADD KEY `idx_hotel` (`hotel_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `support_requests`
--
ALTER TABLE `support_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `hotels`
--
ALTER TABLE `hotels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `hotel_images`
--
ALTER TABLE `hotel_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `places`
--
ALTER TABLE `places`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `support_requests`
--
ALTER TABLE `support_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hotel_images`
--
ALTER TABLE `hotel_images`
  ADD CONSTRAINT `hotel_images_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);

--
-- Constraints for table `places`
--
ALTER TABLE `places`
  ADD CONSTRAINT `places_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
