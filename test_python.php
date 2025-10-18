<?php
echo "<h2>Python Environment Test</h2>";

$command = 'python --version 2>&1';
$output = shell_exec($command);

echo "<p>Python Version: " . $output . "</p>";

$test_command = 'python data_with_input.py predict "2025-09-16 23:56:00" "2025-09-17 02:59:00" "E100011" 2>&1';
$test_output = shell_exec($test_command);

echo "<h3>Script Test Results:</h3>";
echo "<pre>" . htmlspecialchars($test_output) . "</pre>";

$model_files = [
    'trained_location_model.joblib',
    'model_feature_columns.json',
    'data_with_input.py'
];

echo "<h3>File Status:</h3>";
foreach ($model_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file</p>";
    } else {
        echo "<p style='color: red;'>✗ $file</p>";
    }
}
?>