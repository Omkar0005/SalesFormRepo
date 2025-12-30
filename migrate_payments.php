<?php
require_once 'db.php';

// Find orders with amount_paid > 0 but no history
$sql = "SELECT id, amount_paid, created_at FROM sales_orders WHERE amount_paid > 0 AND id NOT IN (SELECT DISTINCT order_id FROM order_payments)";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Found " . $result->num_rows . " orders to migrate.\n";
    $stmt = $conn->prepare("INSERT INTO order_payments (order_id, amount, payment_date) VALUES (?, ?, ?)");
    
    while ($row = $result->fetch_assoc()) {
        $stmt->bind_param("ids", $row['id'], $row['amount_paid'], $row['created_at']);
        $stmt->execute();
        echo "Migrated Order ID: " . $row['id'] . "\n";
    }
    $stmt->close();
} else {
    echo "No existing payments need migration.\n";
}

$conn->close();
?>
