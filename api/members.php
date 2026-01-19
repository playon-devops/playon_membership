<?php
require 'db.php';

$q = $_GET['q'] ?? '';

if (!$q) {
    jsonResponse(['parents' => [], 'kids' => []]);
}

$likeQ = "%$q%";

// Search Parents
$stmt = $conn->prepare("SELECT * FROM parents WHERE name LIKE ? OR phone LIKE ?");
$stmt->bind_param("ss", $likeQ, $likeQ);
$stmt->execute();
$parents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Search Kids
$stmt = $conn->prepare("
    SELECT k.*, p.name as parent_name, p.phone as parent_phone 
    FROM kids k 
    JOIN parents p ON k.parent_id = p.id 
    WHERE k.name LIKE ? OR k.qr_code_data = ?
");
$stmt->bind_param("ss", $likeQ, $q);
$stmt->execute();
$kids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

jsonResponse(['parents' => $parents, 'kids' => $kids]);
?>