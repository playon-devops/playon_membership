<?php
require_once 'db.php';

echo "<h1>Database Storage Test</h1>";

// 1. Test Connection
if ($conn->connect_error) {
    die("<h2 style='color:red'>Connection Failed: " . $conn->connect_error . "</h2>");
}
echo "<p style='color:green'>✔ Database Connected Successfully</p>";

// 2. Test Write (Insert)
$testName = "Test Parent " . rand(1000, 9999);
$testPhone = "0700" . rand(100000, 999999);

$sql = "INSERT INTO parents (name, phone, photo_path) VALUES ('$testName', '$testPhone', 'uploads/test.jpg')";

if ($conn->query($sql) === TRUE) {
    $last_id = $conn->insert_id;
    echo "<h2 style='color:green'>✔ STORAGE TEST PASSED!</h2>";
    echo "<p>Successfully inserted new parent with ID: <strong>$last_id</strong></p>";
    echo "<p>Name: $testName</p>";
    echo "<p>Phone: $testPhone</p>";

    // Clean up
    $conn->query("DELETE FROM parents WHERE id = $last_id");
    echo "<p><em>(Test record deleted automatically)</em></p>";

} else {
    echo "<h2 style='color:red'>❌ Storage Test Failed</h2>";
    echo "<p>Error: " . $sql . "<br>" . $conn->error . "</p>";
}

$conn->close();
?>