-- Add club_admin_id column to clubs table
ALTER TABLE clubs ADD COLUMN club_admin_id INT;
ALTER TABLE clubs ADD FOREIGN KEY (club_admin_id) REFERENCES users(id);

-- Update user_type enum to include club_admin
ALTER TABLE users MODIFY COLUMN user_type ENUM('student', 'club_leader', 'admin', 'club_admin'); 