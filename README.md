# GSHS-Mentoring-Register
경기과학고 멘토 멘티 신청 시스템
---
2025년 1학기에 활용된 과학영재학교 경기과학고등학교 멘토 멘티 신청 시스템입니다.
HTML/CSS/JS , PHP , MARIADB 기반으로 구현되었습니다.

DB 생성 코드는 아래 코드를 활용하십시오.

```SQL
CREATE DATABASE IF NOT EXISTS `gshs_mentoring`;
USE `gshs_mentoring`;
```

```SQL
CREATE TABLE IF NOT EXISTS `menti_apply` (
  `apply_id` int NOT NULL AUTO_INCREMENT,
  `mento_no` int NOT NULL,
  `student_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `student_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `student_phone` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `apply_time` datetime NOT NULL,
  `apply_ip` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`apply_id`),
  UNIQUE KEY `unique_apply` (`mento_no`,`student_id`),
  CONSTRAINT `fk_mento_no` FOREIGN KEY (`mento_no`) REFERENCES `mento_info` (`mento_no`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=223 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

```SQL
CREATE TABLE IF NOT EXISTS `mento_info` (
  `mento_no` int NOT NULL AUTO_INCREMENT,
  `mento_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `mento_cv` text COLLATE utf8mb4_general_ci NOT NULL,
  `menti_limit` int NOT NULL,
  PRIMARY KEY (`mento_no`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

```SQL
CREATE TABLE IF NOT EXISTS `system_config` (
  `config_key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `config_value` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```
