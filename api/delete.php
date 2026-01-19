<?php
require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed']);
}

$type = $input['type'] ?? ''; // 'kid' or 'parent' (parent delete not implemented in UI yet but good only if needed)
$id = $input['id'] ?? null;

if (!$id || $type !== 'kid') {
    jsonResponse(['success' => false, 'error' => 'Invalid request']);
}

// Delete Kid (Cascade deletes visits due to foreign key)
$stmt = $conn->prepare("DELETE FROM kids WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    jsonResponse(['success' => true]);
} else {
    jsonResponse(['success' => false, 'error' => $conn->error]);
}
?>
