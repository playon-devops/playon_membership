<?php
require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$qrCode = $input['qrCode'] ?? '';

if (!$qrCode) {
    jsonResponse(['success' => false, 'message' => 'No QR Code provided']);
}

// 1. Get Kid
$stmt = $conn->prepare("SELECT * FROM kids WHERE qr_code_data = ?");
$stmt->bind_param("s", $qrCode);
$stmt->execute();
$kid = $stmt->get_result()->fetch_assoc();

if (!$kid) {
    jsonResponse(['success' => false, 'message' => 'Invalid QR Code']);
}

// 2. Check Expiry
if (strtotime($kid['membership_expiry']) < time()) {
    jsonResponse(['success' => false, 'message' => 'Membership Expired']);
}

// 3. Determine Session
function getCurrentSession()
{
    $day = date('w'); // 0=Sun, 6=Sat
    $hour = date('G');
    $minute = date('i');
    $timeVal = $hour * 60 + $minute;

    // Tue(2)-Fri(5)
    if ($day >= 2 && $day <= 5) {
        if ($timeVal >= 14 * 60 && $timeVal <= 16 * 60 + 30)
            return "Tue-Fri Session 1 (2:00pm - 4:30pm)";
        if ($timeVal >= 17 * 60 && $timeVal <= 20 * 60)
            return "Tue-Fri Session 2 (5:00pm - 8:00pm)";
    }
    // Sat(6), Sun(0)
    else if ($day == 0 || $day == 6) {
        if ($timeVal >= 11 * 60 && $timeVal <= 13 * 60)
            return "Weekend Session 1 (11:00am - 1:00pm)";
        if ($timeVal >= 14 * 60 && $timeVal <= 16 * 60)
            return "Weekend Session 2 (2:00pm - 4:00pm)";
        if ($timeVal >= 16 * 60 + 30 && $timeVal <= 18 * 60 + 30)
            return "Weekend Session 3 (4:30pm - 6:30pm)";
        if ($timeVal >= 19 * 60 && $timeVal <= 21 * 60)
            return "Weekend Session 4 (7:00pm - 9:00pm)";
    }
    return null;
}

$session = getCurrentSession();
if (!$session) {
    jsonResponse(['success' => false, 'message' => 'No active session currently.']);
}

// 4. Check Duplicate Check-in
$today = date('Y-m-d 00:00:00');
$stmt = $conn->prepare("SELECT * FROM visits WHERE kid_id = ? AND timestamp >= ?");
$stmt->bind_param("is", $kid['id'], $today);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    jsonResponse([
        'success' => false,
        'message' => "Already checked in today for " . $existing['session_name'] . " at " . date('H:i', strtotime($existing['timestamp']))
    ]);
}

// 5. Record Visit
$stmt = $conn->prepare("INSERT INTO visits (kid_id, session_name) VALUES (?, ?)");
$stmt->bind_param("is", $kid['id'], $session);
$stmt->execute();

// Get Visit Count
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM visits WHERE kid_id = ?");
$stmt->bind_param("i", $kid['id']);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['c'];

jsonResponse(['success' => true, 'message' => "Checked in for $session", 'visitCount' => $count]);
?>