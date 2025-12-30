<?php
require_once 'auth.php';
require_once 'db.php';

$query = $_GET['q'] ?? '';

if (strlen($query) < 3) {
    echo json_encode([]);
    exit;
}

$search = "%$query%";
$sql = "SELECT customer_name, customer_type, gst_no, address_line1, address_line2, landmark, city, state, pincode, email, mobile1, mobile2 
        FROM sales_orders 
        WHERE customer_name LIKE ? OR mobile1 LIKE ? 
        ORDER BY id DESC LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

echo json_encode($customers);
$stmt->close();
$conn->close();
?>
