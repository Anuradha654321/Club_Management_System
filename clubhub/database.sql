-- Create event_reports table
CREATE TABLE IF NOT EXISTS event_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    upload_date DATETIME NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 