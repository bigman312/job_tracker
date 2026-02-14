CREATE DATABASE IF NOT EXISTS job_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;

-- Use the newly created database
USE job_tracker;
-- Table: applications
-- ----------------------------
CREATE TABLE applications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    position_title VARCHAR(255) NOT NULL,
    current_status VARCHAR(50) NOT NULL DEFAULT 'applied',
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ----------------------------
-- Table: emails
-- ----------------------------
CREATE TABLE emails (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email_uid VARCHAR(255) NOT NULL UNIQUE,
    subject TEXT NOT NULL,
    sender VARCHAR(255),
    detected_status VARCHAR(50),
    application_id BIGINT NOT NULL,
    received_at DATETIME NOT NULL,
    processed BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);
-- Insert products into the table

