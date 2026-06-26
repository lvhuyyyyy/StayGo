SET NAMES utf8mb4;
USE tour_khach_san;

INSERT INTO blog_posts (title, category, summary, content, thumb, img, author, tags, read_time, is_active, created_at) VALUES

-- Bài 1: Đà Nẵng
(
  'Đà Nẵng – Thành Phố Đáng Sống Nhất Việt Nam Có Gì Hấp Dẫn?',
  'Khám phá',
  'Đà Nẵng không chỉ là thành phố biển đẹp mà còn là điểm giao thoa hoàn hảo giữa thiên nhiên, văn hóa và ẩm thực. Cùng khám phá những điểm đến không thể bỏ lỡ khi đến với thành phố năng động này.',
  '<h2>Đà Nẵng – Nơi Biển Xanh Gặp Núi Non Hùng Vĩ</h2>
<p>Nằm ở miền Trung Việt Nam, Đà Nẵng được mệnh danh là "thành phố đáng sống nhất" với cơ sở hạ tầng hiện đại, bãi biển trải dài và hệ thống cầu đường ấn tượng. Chỉ cách Hà Nội 1 giờ bay và TP.HCM 1 giờ 15 phút, đây là lựa chọn lý tưởng cho mọi hành trình du lịch.</p>

<h2>Những Điểm Đến Không Thể Bỏ Qua</h2>
<h3>1. Bãi Biển Mỹ Khê</h3>
<p>Được tạp chí Forbes bình chọn là một trong những bãi biển quyến rũ nhất hành tinh, Mỹ Khê trải dài hơn 30km với bãi cát trắng mịn, nước biển trong xanh và sóng vừa phải – lý tưởng cho cả tắm biển lẫn lướt sóng.</p>

<h3>2. Cầu Rồng – Biểu Tượng Của Thành Phố</h3>
<p>Cây cầu hình rồng uốn lượn qua sông Hàn là công trình kiến trúc độc đáo nhất Đà Nẵng. Vào tối thứ Bảy và Chủ nhật, cầu Rồng phun lửa và nước – màn trình diễn hoàn toàn miễn phí thu hút hàng nghìn du khách mỗi tuần.</p>

<h3>3. Bà Nà Hills – "Châu Âu Thu Nhỏ" Trên Đỉnh Núi</h3>
<p>Cáp treo Bà Nà nắm giữ nhiều kỷ lục thế giới đưa bạn lên độ cao 1.487m, nơi có làng Pháp cổ kính, công viên Fantasy Park và Cầu Vàng nổi tiếng được đỡ bởi hai bàn tay khổng lồ.</p>

<h3>4. Ngũ Hành Sơn</h3>
<p>Quần thể 5 ngọn núi đá cẩm thạch với hang động, chùa chiền hàng nghìn năm tuổi mang đậm màu sắc tâm linh và lịch sử. Từ đỉnh núi, bạn có thể nhìn bao quát toàn cảnh thành phố và biển Đông.</p>

<h2>Ẩm Thực Đà Nẵng Không Thể Bỏ Qua</h2>
<p>Mì Quảng, Bánh mì Đà Nẵng, Bún chả cá, Bánh tráng cuốn thịt heo, Hải sản tươi sống tại chợ Hàn – đây là những món ăn đặc trưng mà bất kỳ du khách nào cũng phải thử khi ghé Đà Nẵng.</p>

<h2>Kinh Nghiệm Di Chuyển & Lưu Trú</h2>
<p>Thời điểm lý tưởng nhất để du lịch Đà Nẵng là từ tháng 5 đến tháng 8 – mùa hè ít mưa, trời nắng đẹp. Với hệ thống khách sạn từ bình dân đến 5 sao dọc bãi biển Mỹ Khê và trung tâm thành phố, du khách có nhiều lựa chọn phù hợp với ngân sách.</p>',
  'danang.jpg',
  'danang.jpg',
  'StayGo Team',
  'Đà Nẵng, du lịch biển, Mỹ Khê, cầu Rồng, Bà Nà Hills',
  '6 phút đọc',
  1,
  '2026-06-20 08:00:00'
),

-- Bài 2: Vũng Tàu
(
  'Vũng Tàu Cuối Tuần – Trốn Phố Tìm Biển Chỉ 2 Giờ Từ Sài Gòn',
  'Trải nghiệm',
  'Vũng Tàu là điểm nghỉ dưỡng cuối tuần quen thuộc của người Sài Gòn – nơi sóng biển rì rào, hải sản tươi rói và không khí trong lành xua tan mọi mệt mỏi sau một tuần làm việc bận rộn.',
  '<h2>Vũng Tàu – Thiên Đường Cuối Tuần Gần Kề</h2>
<p>Chỉ cách TP.HCM khoảng 120km, Vũng Tàu là cái tên đầu tiên xuất hiện trong đầu mỗi khi người Sài Gòn muốn "chạy trốn" khỏi phố thị. Bán đảo nhỏ nhắn này sở hữu những bãi biển đẹp, ẩm thực phong phú và bầu không khí thư thái khó tìm thấy ở nơi nào khác.</p>

<h2>Các Bãi Biển Nổi Bật</h2>
<h3>Bãi Trước – Lý Tưởng Cho Buổi Chiều Tà</h3>
<p>Nằm ngay trung tâm thành phố, Bãi Trước là nơi lý tưởng để dạo bộ, ngắm hoàng hôn và thưởng thức đặc sản ven biển. Đây cũng là địa điểm check-in yêu thích với tượng Chúa Kitô Vua sừng sững trên đỉnh núi Nhỏ.</p>

<h3>Bãi Sau – Thiên Đường Bơi Lội</h3>
<p>Dài hơn 8km với sóng nhẹ, nước trong xanh, Bãi Sau (hay Thùy Vân) là nơi lý tưởng cho các hoạt động thể thao biển như lướt sóng, kayak và bơi lội. Dọc bãi có nhiều resort, nhà hàng và quán cà phê phục vụ cả ngày.</p>

<h2>Điểm Tham Quan Không Thể Bỏ Lỡ</h2>
<h3>Tượng Chúa Kitô Vua</h3>
<p>Tọa lạc trên đỉnh núi Nhỏ ở độ cao 170m, tượng Chúa Kitô Vua cao 32m (cộng thêm 10m bệ đỡ) là công trình tôn giáo biểu tượng của Vũng Tàu. Leo lên 133 bậc thang bên trong tượng để ngắm toàn cảnh bán đảo từ trên cao.</p>

<h3>Bạch Dinh – Cung Điện Mùa Hè</h3>
<p>Được xây dựng từ năm 1898 dưới thời Pháp thuộc, Bạch Dinh là tòa biệt thự cổ điển kiểu châu Âu nằm giữa những tán cây xanh mát. Bên trong lưu giữ nhiều hiện vật lịch sử quý giá từ thời thuộc địa.</p>

<h2>Ẩm Thực Vũng Tàu – Hải Sản Tươi Sống Và Bánh Khọt</h2>
<p>Bánh khọt giòn rụm chấm mắm tôm, bánh mì chảo, ghẹ hấp bia, ốc các loại tại chợ đêm Vũng Tàu – đây là những trải nghiệm ẩm thực khó quên. Khu vực đường Trần Phú và chợ Vũng Tàu là thiên đường hải sản tươi với giá cả hợp lý.</p>

<h2>Mẹo Di Chuyển</h2>
<p>Ngoài xe khách và ô tô cá nhân, bạn có thể di chuyển từ TP.HCM đến Vũng Tàu bằng tàu cánh ngầm chỉ 75 phút – vừa nhanh vừa là trải nghiệm thú vị. Thời điểm lý tưởng là từ tháng 11 đến tháng 5 năm sau khi biển êm sóng.</p>',
  'vungtau.webp',
  'vungtau.webp',
  'StayGo Team',
  'Vũng Tàu, biển, cuối tuần, Sài Gòn, hải sản, tượng Chúa',
  '5 phút đọc',
  1,
  '2026-06-22 09:00:00'
),

-- Bài 3: Phan Thiết
(
  'Phan Thiết – Mũi Né: Thiên Đường Đồi Cát Và Resort Bên Biển',
  'Nghỉ dưỡng',
  'Phan Thiết – Mũi Né hội tụ đủ mọi yếu tố của một kỳ nghỉ hoàn hảo: đồi cát đỏ kỳ vĩ, resort sang trọng sát biển, làng chài yên bình và những hoạt động thể thao mạo hiểm đầy thú vị.',
  '<h2>Phan Thiết – Mũi Né: Điểm Đến Của Sự Tự Do</h2>
<p>Cách TP.HCM khoảng 200km, Phan Thiết và Mũi Né từ lâu đã trở thành điểm đến ưa thích của du khách trong và ngoài nước. Nơi đây nổi tiếng với đồi cát đỏ và trắng độc đáo, hải sản tươi ngon và các khu resort nằm sát biển tuyệt đẹp.</p>

<h2>Những Điểm Nhất Định Phải Ghé</h2>
<h3>1. Đồi Cát Bay Mũi Né</h3>
<p>Đây là điểm đến đặc trưng nhất của Phan Thiết – những đụn cát đỏ và trắng trải dài như sa mạc thu nhỏ ngay giữa Việt Nam. Du khách có thể trượt cát bằng ván, cưỡi xe địa hình ATV hoặc đơn giản là ngắm bình minh từ đỉnh đồi – một trải nghiệm cực kỳ ấn tượng.</p>

<h3>2. Suối Tiên – Hẻm Núi Đầy Màu Sắc</h3>
<p>Con suối chảy qua hẻm núi với những tầng đất sét đỏ, vàng, trắng tạo nên bức tranh thiên nhiên kỳ diệu. Đi bộ dọc suối, ngâm chân trong nước mát và chiêm ngưỡng những hình thù kỳ lạ do gió và nước tạo ra.</p>

<h3>3. Làng Chài Mũi Né</h3>
<p>Ghé thăm làng chài lúc sáng sớm để chứng kiến cảnh ngư dân ra khơi và trở về với những mẻ cá tươi rói. Đây cũng là nơi bạn có thể mua hải sản khô đặc sản như mực một nắng, cá hấp và nước mắm Phan Thiết nổi tiếng.</p>

<h3>4. Tháp Chăm Poshanư</h3>
<p>Quần thể tháp Chăm hơn 1.000 năm tuổi nằm trên đồi cao với tầm nhìn bao quát biển cả. Đây là di tích lịch sử quan trọng mang đậm văn hóa của dân tộc Chăm Pa còn được bảo tồn tốt tại Việt Nam.</p>

<h2>Thiên Đường Resort Ven Biển</h2>
<p>Dọc theo bờ biển Mũi Né là hàng trăm resort từ bình dân đến 5 sao với hồ bơi vô cực nhìn ra biển, spa cao cấp và nhà hàng hải sản tươi sống. Mùa cao điểm từ tháng 11 đến tháng 4 là thời điểm lý tưởng nhất khi biển êm, trời nắng và gió phù hợp cho kitesurfing.</p>

<h2>Đặc Sản Không Thể Bỏ Qua</h2>
<p>Bánh căn Phan Thiết, lẩu cá đuối, gỏi cá mai, mực một nắng nướng than hoa – những món ăn đặc trưng này chỉ thực sự ngon khi thưởng thức ngay tại Phan Thiết. Đừng quên mang về vài chai nước mắm Phan Thiết chính hiệu – loại nước mắm được coi là "đệ nhất" Việt Nam.</p>',
  'phanthiet.webp',
  'phanthiet.webp',
  'StayGo Team',
  'Phan Thiết, Mũi Né, đồi cát, resort, kitesurfing, hải sản',
  '7 phút đọc',
  1,
  '2026-06-24 10:00:00'
);

SELECT id, title, category, created_at FROM blog_posts;
