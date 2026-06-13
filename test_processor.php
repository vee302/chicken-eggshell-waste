<?php
/**
 * test_processor.php
 * 
 * Demonstration script showing how to call process_image.py using PHP
 * and decode the resulting JSON evaluation data.
 */

// 1. Specify the path to the fingerprint image you want to process
$image_path = "images/glass-surface.png"; // Example image from your project folder

// 2. Prepare the shell command (make sure Python is in your system's PATH)
// Escape the argument to prevent command injection vulnerability
$escaped_image_path = escapeshellarg($image_path);
$command = "python python/process_image.py " . $escaped_image_path;

echo "<h2>Green Forensics Image Processing Demo</h2>";
echo "<p>Running command: <code>$command</code></p>";

// 3. Execute the command and capture the output
$output = shell_exec($command);

// 4. Validate and decode the JSON response
if ($output === null) {
    echo "<p style='color: red;'><strong>Error:</strong> Failed to execute the Python script. Check if Python is installed and added to the System Environment Variables (PATH).</p>";
} else {
    $result = json_decode($output, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p style='color: red;'><strong>Error parsing JSON output from Python script:</strong> " . json_last_error_msg() . "</p>";
        echo "<pre>Raw Output: " . htmlspecialchars($output) . "</pre>";
    } else {
        // 5. Display the parsed evaluation results
        echo "<h3>Evaluation Results:</h3>";
        if ($result['success']) {
            echo "<ul>";
            echo "<li><strong>Status:</strong> Success</li>";
            echo "<li><strong>Clarity Score:</strong> " . htmlspecialchars($result['clarity_score']) . " / 100</li>";
            echo "<li><strong>Quality Rating:</strong> " . htmlspecialchars($result['quality_result']) . "</li>";
            echo "<li><strong>Message:</strong> " . htmlspecialchars($result['message']) . "</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'><strong>Processing Failed:</strong> " . htmlspecialchars($result['message']) . "</p>";
            echo "<ul>";
            echo "<li><strong>Clarity Score:</strong> " . htmlspecialchars($result['clarity_score']) . "</li>";
            echo "<li><strong>Quality Rating:</strong> " . htmlspecialchars($result['quality_result']) . "</li>";
            echo "</ul>";
            
            if (strpos($result['message'], 'not installed') !== false) {
                echo "<p><strong>Tip:</strong> Run the following command in your terminal to install the dependencies:<br>";
                echo "<code>pip install opencv-python numpy</code></p>";
            }
        }
    }
}
?>
