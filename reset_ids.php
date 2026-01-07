<?php
require_once 'db.php';

echo "<h2>Resetting Order IDs</h2>";

// 1. Get all orders
$orders = [];
$res = $conn->query("SELECT id FROM sales_orders ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    $orders[] = $row['id'];
}

if (empty($orders)) {
    // If table is empty, just reset counter
    $conn->query("ALTER TABLE sales_orders AUTO_INCREMENT = 1");
    echo "Table empty. Auto-increment reset to 1.";
    exit;
}

// 2. Perform re-indexing
$new_id = 1;
foreach ($orders as $old_id) {
    if ($old_id != $new_id) {
        // Disable foreign key checks if any
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Update Order Payments first (if table exists)
        $conn->query("UPDATE order_payments SET order_id = $new_id WHERE order_id = $old_id");
        
        // Update the order itself
        $conn->query("UPDATE sales_orders SET id = $new_id WHERE id = $old_id");
        
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "Renumbered ID $old_id to $new_id<br>";
    }
    $new_id++;
}

// 3. Reset the increment counter
$conn->query("ALTER TABLE sales_orders AUTO_INCREMENT = $new_id");
echo "<strong>Success!</strong> All orders re-indexed. Next Order ID will be: $new_id";

$conn->close();
?>
