CREATE DATABASE IF NOT EXISTS lectura CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lectura;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS announcements, calendar_weeks, calendar_events, slot_requests, booking_sections,
  bookings, series_sections, booking_series, rooms, course_allocations, courses, users, section_terms, sections, semesters;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE semesters (
  semester_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_name VARCHAR(50) NOT NULL UNIQUE,
  start_date DATE NOT NULL, teaching_end_date DATE NOT NULL, end_date DATE NOT NULL,
  CHECK (start_date <= teaching_end_date AND teaching_end_date <= end_date)
) ENGINE=InnoDB;

CREATE TABLE sections (
  section_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  program VARCHAR(30) NOT NULL, batch VARCHAR(20) NOT NULL, section_name VARCHAR(20) NOT NULL,
  study_track ENUM('regular','self_support') NOT NULL,
  UNIQUE (program, batch, section_name, study_track)
) ENGINE=InnoDB;

CREATE TABLE section_terms (
  section_term_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id INT UNSIGNED NOT NULL, semester_id INT UNSIGNED NOT NULL,
  semester_number TINYINT UNSIGNED NOT NULL,
  student_count SMALLINT UNSIGNED NOT NULL,
  max_minutes_per_day SMALLINT UNSIGNED NULL,
  UNIQUE (section_id, semester_id),
  CHECK (student_count > 0), CHECK (max_minutes_per_day IS NULL OR max_minutes_per_day > 0),
  FOREIGN KEY (section_id) REFERENCES sections(section_id),
  FOREIGN KEY (semester_id) REFERENCES semesters(semester_id)
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','teacher') NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE courses (
  course_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_code VARCHAR(20) NOT NULL UNIQUE, course_title VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE course_allocations (
  allocation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL, teacher_id INT UNSIGNED NOT NULL, section_term_id INT UNSIGNED NOT NULL,
  session_type ENUM('theory','lab') NOT NULL,
  sessions_per_week TINYINT UNSIGNED NOT NULL, duration_minutes SMALLINT UNSIGNED NOT NULL,
  UNIQUE (course_id, section_term_id, session_type),
  CHECK (sessions_per_week > 0 AND duration_minutes > 0),
  FOREIGN KEY (course_id) REFERENCES courses(course_id),
  FOREIGN KEY (teacher_id) REFERENCES users(user_id),
  FOREIGN KEY (section_term_id) REFERENCES section_terms(section_term_id)
) ENGINE=InnoDB;

CREATE TABLE rooms (
  room_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_name VARCHAR(50) NOT NULL UNIQUE,
  room_type ENUM('classroom','lab','hall') NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  allow_classes BOOLEAN NOT NULL DEFAULT TRUE,        -- FALSE = events only (Senate Hall)
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  CHECK (capacity > 0)
) ENGINE=InnoDB;

CREATE TABLE booking_series (                         -- the REGULAR timetable template
  series_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL, course_id INT UNSIGNED NOT NULL, teacher_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NULL,
  session_type ENUM('theory','lab') NOT NULL,
  delivery_mode ENUM('physical','online') NOT NULL,
  repeat_day TINYINT UNSIGNED NOT NULL,               -- 1=Mon .. 7=Sun
  start_time TIME NOT NULL, end_time TIME NOT NULL,
  start_date DATE NOT NULL, end_date DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CHECK (repeat_day BETWEEN 1 AND 7), CHECK (start_date <= end_date), CHECK (start_time < end_time),
  FOREIGN KEY (semester_id) REFERENCES semesters(semester_id),
  FOREIGN KEY (course_id) REFERENCES courses(course_id),
  FOREIGN KEY (teacher_id) REFERENCES users(user_id),
  FOREIGN KEY (room_id) REFERENCES rooms(room_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE series_sections (
  series_id INT UNSIGNED NOT NULL, section_term_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (series_id, section_term_id),
  FOREIGN KEY (series_id) REFERENCES booking_series(series_id),
  FOREIGN KEY (section_term_id) REFERENCES section_terms(section_term_id)
) ENGINE=InnoDB;

CREATE TABLE bookings (                               -- every actual dated meeting
  booking_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL, series_id INT UNSIGNED NULL,
  course_id INT UNSIGNED NOT NULL, teacher_id INT UNSIGNED NOT NULL, room_id INT UNSIGNED NULL,
  booking_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL,
  session_type ENUM('theory','lab') NOT NULL,
  delivery_mode ENUM('physical','online') NOT NULL,
  booking_type ENUM('regular','extra','merged') NOT NULL,
  status ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
  is_modified BOOLEAN NOT NULL DEFAULT FALSE,
  change_note VARCHAR(255) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (start_time < end_time),
  CHECK ((delivery_mode='physical' AND room_id IS NOT NULL) OR (delivery_mode='online' AND room_id IS NULL)),
  INDEX idx_room (room_id, booking_date, start_time, end_time),
  INDEX idx_teacher (teacher_id, booking_date, start_time, end_time),
  INDEX idx_date (booking_date, status),
  FOREIGN KEY (semester_id) REFERENCES semesters(semester_id),
  FOREIGN KEY (series_id) REFERENCES booking_series(series_id),
  FOREIGN KEY (course_id) REFERENCES courses(course_id),
  FOREIGN KEY (teacher_id) REFERENCES users(user_id),
  FOREIGN KEY (room_id) REFERENCES rooms(room_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE booking_sections (
  booking_id INT UNSIGNED NOT NULL, section_term_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (booking_id, section_term_id),
  INDEX idx_st (section_term_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id),
  FOREIGN KEY (section_term_id) REFERENCES section_terms(section_term_id)
) ENGINE=InnoDB;

CREATE TABLE slot_requests (
  request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL, requester_id INT UNSIGNED NOT NULL, owner_id INT UNSIGNED NOT NULL,
  allocation_id INT UNSIGNED NOT NULL,
  message VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','declined','withdrawn') NOT NULL DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP, responded_at DATETIME NULL,
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id),
  FOREIGN KEY (requester_id) REFERENCES users(user_id),
  FOREIGN KEY (owner_id) REFERENCES users(user_id),
  FOREIGN KEY (allocation_id) REFERENCES course_allocations(allocation_id)
) ENGINE=InnoDB;

CREATE TABLE calendar_events (
  event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL, description TEXT NULL,
  event_type ENUM('classes','exam','holiday','deadline','result','other') NOT NULL,
  start_date DATE NOT NULL, end_date DATE NOT NULL,
  is_tentative BOOLEAN NOT NULL DEFAULT FALSE,
  suspends_regular_classes BOOLEAN NOT NULL DEFAULT FALSE,
  created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (start_date <= end_date),
  FOREIGN KEY (semester_id) REFERENCES semesters(semester_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id),
  FOREIGN KEY (updated_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE calendar_weeks (
  calendar_week_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL,
  phase ENUM('pre_mid','post_mid') NOT NULL, week_number TINYINT UNSIGNED NOT NULL,
  start_date DATE NOT NULL, end_date DATE NOT NULL,
  planned_lectures TINYINT UNSIGNED NULL, notes VARCHAR(255) NULL,
  UNIQUE (semester_id, phase, week_number),
  CHECK (week_number > 0), CHECK (start_date <= end_date),
  FOREIGN KEY (semester_id) REFERENCES semesters(semester_id)
) ENGINE=InnoDB;

CREATE TABLE announcements (                          -- quizzes / general info on the Overview tab
  ann_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL, section_term_id INT UNSIGNED NULL,   -- NULL = everyone
  kind ENUM('quiz','info','notice') NOT NULL,
  title VARCHAR(150) NOT NULL, body TEXT NULL, event_date DATE NULL,
  created_by INT UNSIGNED NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (semester_id) REFERENCES semesters(semester_id),
  FOREIGN KEY (section_term_id) REFERENCES section_terms(section_term_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;