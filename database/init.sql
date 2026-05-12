SET NAMES 'utf8mb4';

CREATE DATABASE IF NOT EXISTS `csieDBTeam14`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `csieDBTeam14`;

CREATE TABLE IF NOT EXISTS `Y114_student` (
  `student_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_no` VARCHAR(20) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `department` VARCHAR(80) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `uq_Y114_student_student_no` (`student_no`),
  UNIQUE KEY `uq_Y114_student_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Y114_user` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NULL,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'reader') NOT NULL DEFAULT 'reader',
  `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_Y114_user_username` (`username`),
  KEY `idx_Y114_user_student_id` (`student_id`),
  CONSTRAINT `fk_Y114_user_student`
    FOREIGN KEY (`student_id`) REFERENCES `Y114_student` (`student_id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Y114_category` (
  `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_Y114_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Y114_book` (
  `book_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `isbn` VARCHAR(30) NULL,
  `title` VARCHAR(180) NOT NULL,
  `author` VARCHAR(120) NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `publication_year` SMALLINT UNSIGNED NULL,
  `status` ENUM('available', 'borrowed', 'removed') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`book_id`),
  UNIQUE KEY `uq_Y114_book_isbn` (`isbn`),
  KEY `idx_Y114_book_title` (`title`),
  KEY `idx_Y114_book_author` (`author`),
  KEY `idx_Y114_book_category_id` (`category_id`),
  CONSTRAINT `fk_Y114_book_category`
    FOREIGN KEY (`category_id`) REFERENCES `Y114_category` (`category_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Y114_borrow_record` (
  `record_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `borrow_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `return_date` DATE NULL,
  `status` ENUM('borrowed', 'returned') NOT NULL DEFAULT 'borrowed',
  `fine_amount` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `active_book_id` INT UNSIGNED NULL,
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `uq_Y114_one_active_loan_per_book` (`active_book_id`),
  KEY `idx_Y114_borrow_record_user_id` (`user_id`),
  KEY `idx_Y114_borrow_record_book_id` (`book_id`),
  KEY `idx_Y114_borrow_record_due_date` (`due_date`),
  CONSTRAINT `fk_Y114_borrow_record_book`
    FOREIGN KEY (`book_id`) REFERENCES `Y114_book` (`book_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_Y114_borrow_record_user`
    FOREIGN KEY (`user_id`) REFERENCES `Y114_user` (`user_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `Y114_category` (`name`) VALUES
  ('小說'),
  ('科技'),
  ('歷史'),
  ('資料庫'),
  ('程式設計');

INSERT INTO `Y114_book` (`isbn`, `title`, `author`, `category_id`, `publication_year`, `status`) VALUES
  ('9789865025674', '資料庫系統概論', 'Abraham Silberschatz', 4, 2020, 'available'),
  ('9789864761528', 'PHP 與 MySQL Web 開發', 'Luke Welling', 5, 2017, 'available'),
  ('9789863126991', '深入淺出 SQL', 'Lynn Beighley', 4, 2018, 'borrowed'),
  ('9786264014380', '系統分析與設計：使用UML(第二版)', '余顯強, 傅詠絮', 4, 2025, 'available'),
  ('9789862358263', 'Clean Code', 'Robert C. Martin', 5, 2008, 'available');

INSERT INTO `Y114_student` (`student_no`, `name`, `email`, `phone`, `department`) VALUES
  ('41243201', '王小明', '41243201@nfu.edu.tw', '0912-345-678', '資訊工程學系'),
  ('41243202', '林雅婷', '41243202@nfu.edu.tw', '0922-456-789', '資訊工程學系');

INSERT INTO `Y114_user` (`student_id`, `username`, `password_hash`, `role`, `status`) VALUES
  (NULL, 'admin', '$2y$10$aimdYQMKVoZr4blNfWNj7uttx8fY4QIXGswjXbI33l0xdca2YxW2W', 'admin', 'active'),
  (1, 'reader', '$2y$10$1q6/b5nnC6X7V0.9R0Qmu.YmJtUhrJSqxbOp4sPYyIURuKYPgcmvW', 'reader', 'active'),
  (2, 'reader2', '$2y$10$1q6/b5nnC6X7V0.9R0Qmu.YmJtUhrJSqxbOp4sPYyIURuKYPgcmvW', 'reader', 'active');

INSERT INTO `Y114_borrow_record` (`book_id`, `user_id`, `borrow_date`, `due_date`, `return_date`, `status`, `fine_amount`, `active_book_id`) VALUES
  (3, 2, CURRENT_DATE - INTERVAL 5 DAY, CURRENT_DATE + INTERVAL 9 DAY, NULL, 'borrowed', 0.00, 3);
