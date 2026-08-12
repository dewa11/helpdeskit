CREATE DATABASE IF NOT EXISTS helpdeskit;
USE helpdeskit;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('head_it', 'it_staff') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(50) NOT NULL,
    nama VARCHAR(255) NOT NULL,
    no_wa VARCHAR(20) NOT NULL,
    unit_dept VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    sub_category VARCHAR(100) NULL,
    description TEXT NOT NULL,
    attachment_path VARCHAR(500) NULL,
    status ENUM('submitted', 'waiting', 'assigned', 'in_progress', 'finished', 'waiting_confirmation', 'closed') DEFAULT 'submitted',
    assigned_to INT NULL,
    closure_reason VARCHAR(255) NULL,
    ticket_code VARCHAR(10) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);


CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    type ENUM('image', 'video') NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);


-- Head IT account: nip=user id, email populated with nip for legacy schema
INSERT INTO users (name, email, password, role) VALUES ('Ismul Aswan', '201908117', '$2y$10$fUyb5S4G419a4gkpz2qeBOZvhZ0medGCAfptnNecly0pej/zjer2e', 'head_it');