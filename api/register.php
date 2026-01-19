<?php
require 'db.php';
// QR Code logic: We generate a unique string here and store it.
// The frontend generates the visual QR code using an external API or JS library.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed']);
}

$parentName = $_POST['parentName'] ?? '';
$parentPhone = $_POST['parentPhone'] ?? '';

if (!$parentName || !$parentPhone) {
    jsonResponse(['success' => false, 'error' => 'Missing parent details']);
}

$uploadDir = '../uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function handleUpload($fileKey, $prefix)
{
    global $uploadDir;
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES[$fileKey]['tmp_name'];
        $name = basename($_FILES[$fileKey]['name']);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (!$ext)
            $ext = 'jpg'; // Default to jpg if blob
        $fileName = $prefix . '-' . uniqid() . '.' . $ext;
        if (move_uploaded_file($tmpName, $uploadDir . $fileName)) {
            return 'uploads/' . $fileName;
        }
    }
    return null;
}

$conn->begin_transaction();

try {
    // 1. Check if parent exists
    $stmt = $conn->prepare("SELECT id FROM parents WHERE phone = ?");
    $stmt->bind_param("s", $parentPhone);
    $stmt->execute();
    $res = $stmt->get_result();

    $parentId = 0;
    if ($row = $res->fetch_assoc()) {
        $parentId = $row['id'];
        // Update photo if provided? Removed as per request.
        // $conn->query("UPDATE parents SET photo_path='$pPhoto' WHERE id=$parentId");
    } else {
        $stmt = $conn->prepare("INSERT INTO parents (name, phone) VALUES (?, ?)");
        $stmt->bind_param("ss", $parentName, $parentPhone);
        $stmt->execute();
        $parentId = $stmt->insert_id;
    }

    // 2. Process Kids
    $kidsParam = []; // Just for response
    $i = 0;
    while (isset($_POST["kidName_$i"])) {
        $kName = $_POST["kidName_$i"];
        // Age Logic
        $kAge = $_POST["kidAge_$i"] ?? 0;
        $kDob = date('Y-m-d', strtotime("-$kAge years")); // Approximate DOB

        $kPhoto = handleUpload("kidPhoto_$i", 'kid');

        $qrData = "PO-" . time() . "-$parentId-$i"; // Shorter QR data
        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $conn->prepare("INSERT INTO kids (parent_id, name, dob, photo_path, qr_code_data, membership_expiry) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $parentId, $kName, $kDob, $kPhoto, $qrData, $expiry);
        $stmt->execute();

        $kidsParam[] = [
            'id' => $stmt->insert_id,
            'name' => $kName,
            'qrData' => $qrData
        ];
        $i++;
    }

    $conn->commit();
    jsonResponse(['success' => true, 'parentId' => $parentId, 'kids' => $kidsParam]);

} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
}
?>