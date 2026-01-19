<?php
require 'db.php';

if (isset($_GET['id'])) {
    // GET KID details
    $id = $_GET['id'];
    $stmt = $conn->prepare("
        SELECT k.*, p.name as parent_name, p.phone as parent_phone 
        FROM kids k 
        JOIN parents p ON k.parent_id = p.id 
        WHERE k.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $kid = $stmt->get_result()->fetch_assoc();

    if ($kid) {
        $kid['qrDataUrl'] = $kid['qr_code_data']; // Just pass data, frontend generates img
    }

    jsonResponse(['kid' => $kid]);

} else if (isset($_GET['stats'])) {
    // GET STATS
    $totalKids = $conn->query("SELECT COUNT(*) as c FROM kids")->fetch_assoc()['c'];
    $today = date('Y-m-d 00:00:00');
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM visits WHERE timestamp >= ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $todayVisits = $stmt->get_result()->fetch_assoc()['c'];

    jsonResponse(['totalKids' => $totalKids, 'todayVisits' => $todayVisits]);
}
?>