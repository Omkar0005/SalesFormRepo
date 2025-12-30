<?php
require_once 'db.php';

// Add columns if missing
$alter_sql = [];

// Check for status column
$result = $conn->query("SHOW COLUMNS FROM sales_orders LIKE 'status'");
if ($result->num_rows == 0) {
    $alter_sql[] = "ADD COLUMN status VARCHAR(50) DEFAULT 'Pending' AFTER total_amount";
}

// Check for payment_term column
$result = $conn->query("SHOW COLUMNS FROM sales_orders LIKE 'payment_term'");
if ($result->num_rows == 0) {
    $alter_sql[] = "ADD COLUMN payment_term VARCHAR(100) AFTER status";
}

// Check for payment_reminder_date column
$result = $conn->query("SHOW COLUMNS FROM sales_orders LIKE 'payment_reminder_date'");
if ($result->num_rows == 0) {
    $alter_sql[] = "ADD COLUMN payment_reminder_date DATE AFTER payment_term";
}

// Check for amount_paid column
$result = $conn->query("SHOW COLUMNS FROM sales_orders LIKE 'amount_paid'");
if ($result->num_rows == 0) {
    $alter_sql[] = "ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount";
}

// Check for payment_status column
$result = $conn->query("SHOW COLUMNS FROM sales_orders LIKE 'payment_status'");
if ($result->num_rows == 0) {
    $alter_sql[] = "ADD COLUMN payment_status VARCHAR(50) DEFAULT 'Pending' AFTER status";
    // Migration Logic will be run via separate queries after structure update
}

// Check for gloves_size column
$result = $conn->query("SHOW COLUMNS FROM sales_orders LIKE 'gloves_size'");
if ($result->num_rows == 0) {
    $alter_sql[] = "ADD COLUMN gloves_size VARCHAR(255) AFTER product_color";
}

// Check for size-specific quantity columns
$result = $conn->query("SHOW COLUMNS FROM sales_orders LIKE 'size_small_qty'");
if ($result->num_rows == 0) {
    $alter_sql[] = "ADD COLUMN size_small_qty INT DEFAULT 0 AFTER gloves_size";
}
$result = $conn->query("SHOW COLUMNS FROM sales_orders LIKE 'size_medium_qty'");
if ($result->num_rows == 0) {
    $alter_sql[] = "ADD COLUMN size_medium_qty INT DEFAULT 0 AFTER size_small_qty";
}
$result = $conn->query("SHOW COLUMNS FROM sales_orders LIKE 'size_large_qty'");
if ($result->num_rows == 0) {
    $alter_sql[] = "ADD COLUMN size_large_qty INT DEFAULT 0 AFTER size_medium_qty";
}

if (!empty($alter_sql)) {
    foreach ($alter_sql as $sql) {
        if ($conn->query("ALTER TABLE sales_orders " . $sql) === TRUE) {
            echo "Column added successfully: " . $sql . "\n";
        } else {
            echo "Error adding column: " . $conn->error . "\n";
        }
    }
} else {
    echo "Required columns already exist.\n";
}

// Data Migration (Run always or check? Safe to run always if idempotent-ish, or just run once manually. 
// I'll make it conditional on the column adding, or just run it. 
// Actually, let's run it.
echo "Migrating data...\n";

// 1. Map 'Successful' to 'Delivered'
$conn->query("UPDATE sales_orders SET status = 'Delivered' WHERE status = 'Successful'");

// 2. Map 'Payment Received' to payment_status='Received' and status='New' (assuming not delivered yet)
// Note: We need to do this carefully. If we just set status='New', we lose the fact it was 'Payment Received'.
// We should update payment_status FIRST based on old status.
$conn->query("UPDATE sales_orders SET payment_status = 'Received' WHERE status = 'Payment Received'");
$conn->query("UPDATE sales_orders SET status = 'New' WHERE status = 'Payment Received'");

// 3. Map 'Pending' to 'New'
$conn->query("UPDATE sales_orders SET status = 'New' WHERE status = 'Pending'");

// 4. Ensure payment_status is 'Pending' where it's not set (handled by Default, but good to be sure for existing rows if default didn't apply retrospectively to all which it usually does)
// Actually ADD COLUMN ... DEFAULT 'Pending' fills existing rows. So we are good.

echo "Migration complete.\n";

$conn->close();
?>
