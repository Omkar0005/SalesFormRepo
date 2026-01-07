<?php
require_once 'db.php';

// Check columns
$result = $conn->query("SHOW COLUMNS FROM sales_orders");
$columns = [];
while($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

echo "Columns: " . implode(", ", $columns) . "\n";

// Add columns if missing
$alter_sql = [];
if (!in_array('delivery_type', $columns)) {
    $alter_sql[] = "ADD COLUMN delivery_type VARCHAR(50) AFTER product_name";
}
if (!in_array('lead_source', $columns)) {
    $alter_sql[] = "ADD COLUMN lead_source VARCHAR(100) AFTER delivery_type";
}
if (!in_array('created_at', $columns)) { // Check for timestamp
    $alter_sql[] = "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
}
if (!in_array('gloves_size', $columns)) {
    $alter_sql[] = "ADD COLUMN gloves_size VARCHAR(255) AFTER product_color";
}
if (!in_array('order_date', $columns)) {
    $alter_sql[] = "ADD COLUMN order_date DATE AFTER created_at";
}

if (!empty($alter_sql)) {
    $sql = "ALTER TABLE sales_orders " . implode(", ", $alter_sql);
    if ($conn->query($sql) === TRUE) {
        echo "Table updated successfully\n";
    } else {
        echo "Error updating table: " . $conn->error . "\n";
    }
} else {
    echo "Table already has required columns\n";
}

$conn->close();
?>
