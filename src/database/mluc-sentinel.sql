-- -----------------------------------------------------
-- Database: mluc-sentinel
-- -----------------------------------------------------

DROP DATABASE IF EXISTS `mluc-sentinel`;
CREATE DATABASE `mluc-sentinel` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mluc-sentinel`;

-- -----------------------------------------------------
-- Table: users
-- -----------------------------------------------------
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_type ENUM('admin', 'personnel', 'student', 'student_official', 'parent') NOT NULL,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    avatar VARCHAR(255), -- profile/avatar
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_credentials_required
        CHECK (
            (user_type IN ('admin','personnel','student_official') 
                AND username IS NOT NULL 
                AND email IS NOT NULL 
                AND password IS NOT NULL)
            OR (user_type IN ('student','parent'))
        )
);

-- -----------------------------------------------------
-- Table: admins
-- -----------------------------------------------------
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Table: personnel
-- -----------------------------------------------------
CREATE TABLE personnel (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    personnel_id VARCHAR(20) UNIQUE NOT NULL,
    licensed_id VARCHAR(50),
    licensed_id_image VARCHAR(255),
    phone VARCHAR(15),
    expiration_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Table: students
-- -----------------------------------------------------
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    school_id VARCHAR(20) UNIQUE NOT NULL,
    licensed_id VARCHAR(50),
    licensed_id_image VARCHAR(255),
    college VARCHAR(100),
    phone VARCHAR(15),
    expiration_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Table: student_officials
-- -----------------------------------------------------
CREATE TABLE student_officials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    position VARCHAR(50) NOT NULL,
    school_id VARCHAR(20) UNIQUE NOT NULL,
    expiration_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Table: parents
-- -----------------------------------------------------
CREATE TABLE parents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    licensed_id VARCHAR(50),
    licensed_id_image VARCHAR(255),
    expiration_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Table: vehicles
-- -----------------------------------------------------
CREATE TABLE vehicles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    vehicle_type ENUM('motorcycle', 'car', 'electric_bike') NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    sticker_color VARCHAR(20) NOT NULL,
    sticker_number VARCHAR(10) NOT NULL,
    qr_code TEXT,
    sticker VARCHAR(255), -- location to store the sticker
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Table: colleges
-- -----------------------------------------------------
CREATE TABLE colleges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL
);

-- Pre-populate colleges
INSERT INTO colleges (name) VALUES
('College of Graduate Studies'),
('College of Law'),
('College of Engineering'),
('College of Information Technology'),
('College of Arts and Sciences'),
('College of Education'),
('College of Management'),
('Institute of Criminal Justice Education'),
('College of Technology');

-- -----------------------------------------------------
-- Table: reports
-- -----------------------------------------------------
CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reported_by INT NOT NULL,
    violator_sticker_number VARCHAR(10) NOT NULL,
    reason TEXT NOT NULL,
    evidence_image VARCHAR(255),
    location VARCHAR(100) NOT NULL,
    remarks TEXT,
    report_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
    FOREIGN KEY (reported_by) REFERENCES users(id)
);

-- -----------------------------------------------------
-- Table: sticker_counters
-- -----------------------------------------------------
CREATE TABLE sticker_counters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    color VARCHAR(20) UNIQUE NOT NULL,
    counter INT DEFAULT 0
);

-- Pre-populate sticker counters
INSERT INTO sticker_counters (color, counter) VALUES
('green', 0),
('yellow', 0),
('red', 0),
('orange', 0),
('pink', 0),
('blue', 0),
('maroon', 0),
('white', 0);




