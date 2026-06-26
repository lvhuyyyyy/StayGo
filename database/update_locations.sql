SET NAMES utf8mb4;
USE tour_khach_san;

UPDATE locations SET
  name        = 'Đà Nẵng',
  description = 'Thành phố biển năng động, nổi tiếng với bãi biển Mỹ Khê và cầu Rồng',
  image       = 'danang.jpg'
WHERE id = 1;

UPDATE locations SET
  name        = 'Vũng Tàu',
  description = 'Thành phố biển gần TP.HCM, điểm đến nghỉ dưỡng cuối tuần lý tưởng',
  image       = 'vungtau.webp'
WHERE id = 2;

UPDATE locations SET
  name        = 'Phan Thiết',
  description = 'Thiên đường biển với đồi cát, resort sang trọng và hải sản tươi ngon',
  image       = 'phanthiet.webp'
WHERE id = 3;

SELECT id, name, image FROM locations;
