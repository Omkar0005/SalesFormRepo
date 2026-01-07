<?php
require_once 'auth.php';
// export.php
require_once 'db.php';

// Logic to fetch orders similar to View Orders
$sort_order = isset($_GET['sort']) && $_GET['sort'] == 'ASC' ? 'ASC' : 'DESC';
$sql = "SELECT * FROM sales_orders WHERE 1=1";

$types = "";
$params = [];

if (!empty($_GET['filter_from_date'])) {
    $sql .= " AND DATE(created_at) >= ?";
    $types .= "s";
    $params[] = $_GET['filter_from_date'];
}
if (!empty($_GET['filter_to_date'])) {
    $sql .= " AND DATE(created_at) <= ?";
    $types .= "s";
    $params[] = $_GET['filter_to_date'];
}
// Add Lead Source filter here as well since we are adding it to the main view
if (!empty($_GET['lead_source'])) {
    $sql .= " AND lead_source LIKE ?";
    $types .= "s";
    $params[] = "%" . $_GET['lead_source'] . "%";
}

// New Filters: Search (Name, City, Mobile)
if (!empty($_GET['search_query'])) {
    $search = "%" . $_GET['search_query'] . "%";
    $sql .= " AND (customer_name LIKE ? OR city LIKE ? OR mobile1 LIKE ?)";
    $types .= "sss";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// New Filters: Status (Cancellation)
if (!empty($_GET['status_filter'])) {
    $sql .= " AND status = ?";
    $types .= "s";
    $params[] = $_GET['status_filter'];
}

$sql .= " ORDER BY id $sort_order";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Set headers to download file
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_orders_export.csv"');

$output = fopen('php://output', 'w');

// Header Row
fputcsv($output, ['ID', 'Creation Date', 'Order Date', 'Customer Name', 'Mobile', 'Product', 'Delivery Type', 'Lead Source', 'Gloves Size', 'Color', 'Qty', 'Rate', 'GST %', 'MRP', 'Discount', 'Total Amount', 'Paid Amount', 'Pending Amount', 'Payment Term', 'Reminder Date', 'Delivery Status', 'Payment Status', 'Address', 'Area', 'City']);

// Data Rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'], 
        date('d-M-Y h:i A', strtotime($row['created_at'])), 
        !empty($row['order_date']) ? date('d-M-Y', strtotime($row['order_date'])) : '',
        $row['customer_name'], 
        $row['mobile1'], 
        $row['product_name'],
        $row['delivery_type'],
        $row['lead_source'],
        $row['gloves_size'],
        $row['product_color'],
        $row['quantity'],
        $row['rate'],
        $row['gst_percent'],
        $row['mrp'],
        $row['discount'],
        $row['total_amount'],
        $row['amount_paid'],
        $row['total_amount'] - $row['amount_paid'], // Calculate pending export
        $row['payment_term'],
        !empty($row['payment_reminder_date']) ? date('d-M-Y', strtotime($row['payment_reminder_date'])) : '',
        $row['status'],
        $row['payment_status'],
        $row['address_line1'] . " " . $row['address_line2'],
        $row['area'],
        $row['city']
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
?>
