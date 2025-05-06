CREATE DATABASE IF NOT EXISTS maktabsms CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;

USE maktabsms;

-- Table for users (managers, deputies, admins)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    mobile VARCHAR(11) NOT NULL UNIQUE,
    national_id VARCHAR(10) NOT NULL UNIQUE,
    birth_date DATE NOT NULL,
    role ENUM('admin', 'manager', 'deputy', 'user') NOT NULL DEFAULT 'user',
    school_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for schools
CREATE TABLE schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_type ENUM('دبستان', 'مدرسه', 'پیش‌دبستانی', 'متوسطه اول', 'متوسطه دوم') NOT NULL,
    gender_type ENUM('دخترانه', 'پسرانه', 'مختلط') NOT NULL,
    school_name VARCHAR(255) NOT NULL,
    national_id VARCHAR(20) NOT NULL UNIQUE,
    province VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    district VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    postal_code VARCHAR(10) NOT NULL,
    request_letter_path VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for phonebook groups
CREATE TABLE phonebook_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    group_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id)
);

-- Table for phonebook contacts
CREATE TABLE phonebook_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    name VARCHAR(255) NULL,
    mobile VARCHAR(11) NOT NULL,
    birth_date DATE NULL,
    field1 VARCHAR(255) NULL,
    field2 VARCHAR(255) NULL,
    field3 VARCHAR(255) NULL,
    field4 VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES phonebook_groups(id)
);

-- Table for SMS drafts
CREATE TABLE sms_drafts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    group_name VARCHAR(255) NULL,
    content TEXT NOT NULL,
    type ENUM('simple', 'smart') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id)
);

-- Table for sent SMS
CREATE TABLE sent_sms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    type ENUM('single', 'group', 'smart') NOT NULL,
    recipient_mobile VARCHAR(11) NULL,
    group_id INT NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    cost INT NOT NULL,
    send_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id),
    FOREIGN KEY (group_id) REFERENCES phonebook_groups(id)
);

-- Table for received SMS
CREATE TABLE received_sms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    reference_id INT NOT NULL,
    sender_mobile VARCHAR(11) NOT NULL,
    content TEXT NOT NULL,
    received_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id)
);

-- Table for transactions (SMS credits)
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    amount INT NOT NULL,
    status ENUM('pending', 'successful', 'failed') DEFAULT 'pending',
    payment_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id)
);

-- Table for settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL UNIQUE,
    key_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (key_name, key_value) VALUES
('sms_cost_per_part', '100'),
('sms_max_chars_part1', '70'),
('sms_max_chars_other', '67'),
('sms_footer_text', 'لغو11');