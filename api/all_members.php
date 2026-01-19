<?php
require 'db.php';

// Fetch all kids with parent details
$sql = "
    SELECT k.*, p.name as parent_name, p.phone as parent_phone 
    FROM kids k 
    JOIN parents p ON k.parent_id = p.id 
    ORDER BY k.id DESC
";

$result = $conn->query($sql);
$members = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Calculate Status
        $expiry = strtotime($row['membership_expiry']);
        $now = time();
        $isActive = $expiry > $now;

        $row['status'] = $isActive ? 'Active' : 'Expired';
        $menuStatusClass = $isActive ? 'status-active' : 'status-expired';

        // Add calculated formatted expiry for easy display
        $row['expiry_formatted'] = date('d M Y', $expiry);

        $members[] = $row;
    }
}

jsonResponse(['members' => $members]);
?>