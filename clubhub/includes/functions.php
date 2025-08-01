<?php
// Sanitize input
function sanitize($input) {
    global $conn;
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)));
}

// Get user details
function getUserDetails($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get club details
function getClubDetails($club_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
    $stmt->bind_param("i", $club_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Format date
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Upload file
function uploadFile($file, $destination_path, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']) {
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check if file type is allowed
    if (!in_array($file_extension, $allowed_types)) {
        return [
            'success' => false,
            'message' => 'File type not allowed'
        ];
    }
    
    // Generate unique filename
    $new_filename = generateRandomString() . '_' . time() . '.' . $file_extension;
    $target_file = $destination_path . $new_filename;
    
    // Create directory if it doesn't exist
    if (!file_exists($destination_path)) {
        mkdir($destination_path, 0777, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'success' => true,
            'filename' => $new_filename,
            'path' => $target_file
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to upload file'
    ];
}

// Delete file
function deleteFile($file_path) {
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}

// Get user role in club
function getUserClubRole($user_id, $club_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT cr.role_name, cr.role_type
        FROM club_members cm
        JOIN club_roles cr ON cm.role_id = cr.id
        WHERE cm.user_id = ? AND cm.club_id = ? AND cm.active_status = 1
    ");
    $stmt->bind_param("ii", $user_id, $club_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Check if user can manage club
function canManageClub($user_id, $club_id) {
    $role = getUserClubRole($user_id, $club_id);
    return $role && $role['role_type'] === 'executive_body';
}

// Get academic year
function getAcademicYear($date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $year = date('Y', strtotime($date));
    $month = date('n', strtotime($date));
    
    // Academic year starts in June
    if ($month < 6) {
        return [
            'start' => ($year - 1) . '-06-01',
            'end' => $year . '-05-31'
        ];
    }
    
    return [
        'start' => $year . '-06-01',
        'end' => ($year + 1) . '-05-31'
    ];
}

// Validate file upload
function validateFileUpload($file, $allowed_types = ['pdf', 'doc', 'docx'], $max_size = 5242880) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = array(
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        );
        return [
            'success' => false,
            'message' => isset($upload_errors[$file['error']]) ? $upload_errors[$file['error']] : 'Unknown upload error'
        ];
    }

    // Check filesize
    if ($file['size'] > $max_size) {
        return [
            'success' => false,
            'message' => 'File size too large. Maximum size allowed is ' . ($max_size / 1024 / 1024) . 'MB'
        ];
    }

    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return [
            'success' => false,
            'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types)
        ];
    }

    // All validations passed
    return [
        'success' => true,
        'message' => 'File is valid'
    ];
}
?> 