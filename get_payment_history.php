<?php
require_once 'auth.php';
// get_payment_history.php
require_once 'db.php';

$order_id = intval($_GET['order_id'] ?? 0);

if ($order_id > 0) {
    $stmt = $conn->prepare("SELECT amount, payment_date FROM order_payments WHERE order_id = ? ORDER BY payment_date ASC");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $row['payment_date'] = date('d-M-Y h:i A', strtotime($row['payment_date']));
        $payments[] = $row;
    }
    
    echo json_encode($payments);
    $stmt->close();
} else {
    echo json_encode(["error" => "Invalid order ID"]);
}

$conn->close();
?>
