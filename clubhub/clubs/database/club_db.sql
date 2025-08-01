-- Create database if not exists
CREATE DATABASE IF NOT EXISTS club_db;
USE club_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('student', 'club_leader', 'admin') NOT NULL,
    student_id VARCHAR(50) NULL,
    profile_image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    club_id INT NULL
);

-- Clubs table
CREATE TABLE IF NOT EXISTS clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    logo VARCHAR(255) NULL,
    category ENUM('IEEE', 'CSI', 'IAPC', 'IESE', 'Department') NOT NULL,
    established_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add foreign key to users table
ALTER TABLE users
ADD CONSTRAINT fk_user_club
FOREIGN KEY (club_id) REFERENCES clubs(id)
ON DELETE SET NULL;

-- Club membership table
CREATE TABLE IF NOT EXISTS club_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    club_id INT NOT NULL,
    role ENUM('Member', 'President', 'Secretary', 'Treasurer', 'Vice President', 'Joint Secretary') NOT NULL,
    join_date DATE NOT NULL,
    active_status BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    UNIQUE (user_id, club_id)
);

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    event_date DATE NOT NULL,
    event_time VARCHAR(50) NOT NULL,
    location VARCHAR(255) NOT NULL,
    status ENUM('upcoming', 'ongoing', 'past') NOT NULL,
    enrollment_open BOOLEAN DEFAULT FALSE,
    max_participants INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Event media table
CREATE TABLE IF NOT EXISTS event_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    media_type ENUM('photo', 'report', 'other') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Event participation table
CREATE TABLE IF NOT EXISTS event_participation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    proof_file VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    feedback TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (event_id, user_id)
);

-- Club ratings table
CREATE TABLE IF NOT EXISTS club_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    feedback TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (club_id, user_id)
);

-- Club executive body table
CREATE TABLE IF NOT EXISTS club_executives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    user_id INT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Meeting minutes table
CREATE TABLE IF NOT EXISTS meeting_minutes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    meeting_date DATE NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample data for clubs
INSERT INTO clubs (name, description, category, established_date) VALUES
('IEEE Student Branch', 'The IEEE Student Branch is dedicated to advancing technology for humanity.', 'IEEE', '2010-01-15'),
('Computer Society of India', 'CSI is one of the largest IT professional societies in India.', 'CSI', '2012-03-22'),
('Indian Association for Physics', 'IAPC promotes physics education and research.', 'IAPC', '2015-07-10'),
('Indian Engineering Society', 'IESE focuses on engineering excellence and innovation.', 'IESE', '2013-09-05'),
('Computer Science Department Club', 'The CS Department Club focuses on computer science activities and events.', 'Department', '2011-05-18');

-- Insert admin user
INSERT INTO users (name, email, password, user_type) VALUES
('Admin', 'admin@college.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'); -- password is 'password'
