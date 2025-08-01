<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$test_file = 'uploads/reports/test.txt';
$report_dir = 'uploads/reports/';

echo "<h2>File Access Test</h2>";

// Check if directory exists
echo "Directory exists: " . (is_dir($report_dir) ? 'Yes' : 'No') . "<br>";
echo "Directory readable: " . (is_readable($report_dir) ? 'Yes' : 'No') . "<br>";

// List files in directory
echo "<h3>Files in directory:</h3>";
$files = scandir($report_dir);
echo "<pre>";
print_r($files);
echo "</pre>";

// Check test file
echo "<h3>Test file check:</h3>";
echo "File exists: " . (file_exists($test_file) ? 'Yes' : 'No') . "<br>";
echo "File readable: " . (is_readable($test_file) ? 'Yes' : 'No') . "<br>";

// Try to read test file
echo "<h3>File contents:</h3>";
if(file_exists($test_file) && is_readable($test_file)) {
    echo file_get_contents($test_file);
} else {
    echo "Cannot read file";
}

// Check Apache user and permissions
echo "<h3>Server Info:</h3>";
echo "PHP running as user: " . get_current_user() . "<br>";
echo "Script owner: " . fileowner(__FILE__) . "<br>";
echo "Directory owner: " . fileowner($report_dir) . "<br>";
?> 