<?php
require 'db.php';

// Handle FormData input (which populates $_POST and $_FILES) instead of JSON
$id = $_POST['id'] ?? null;
$name = $_POST['name'] ?? null;
$expiry = $_POST['expiry'] ?? null;
$parentName = $_POST['parentName'] ?? null;
$parentPhone = $_POST['parentPhone'] ?? null;

if (!$id || !$name) {
    jsonResponse(['success' => false, 'error' => 'Missing ID or Name']);
}

// 1. Handle Photo Upload if provided
$photoSql = "";
$types = "ssi"; // name, expiry, id
$params = [$name, date('Y-m-d H:i:s', strtotime($expiry)), $id];

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/';
    if (!file_exists($uploadDir))
        mkdir($uploadDir, 0777, true);

    $tmpName = $_FILES['photo']['tmp_name'];
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $fileName = 'kid-' . uniqid() . '.' . $ext;

    if (move_uploaded_file($tmpName, $uploadDir . $fileName)) {
        $photoPath = 'uploads/' . $fileName;
        // Update photo_path too
        $photoSql = ", photo_path = ?";
        // Insert photo param before ID (the last param)
        array_splice($params, 2, 0, $photoPath);
        $types = "sssi"; // name, expiry, photo, id
    }
}

// 2. Update Kid
$stmt = $conn->prepare("UPDATE kids SET name = ?, membership_expiry = ? $photoSql WHERE id = ?");
$stmt->bind_param($types, ...$params);
$stmt->execute();

// 3. Update Parent
if ($parentName && $parentPhone) {
    $stmt = $conn->prepare("UPDATE parents p JOIN kids k ON p.id = k.parent_id SET p.name = ?, p.phone = ? WHERE k.id = ?");
    $stmt->bind_param("ssi", $parentName, $parentPhone, $id);
    $stmt->execute();
}

jsonResponse(['success' => true]);
?>