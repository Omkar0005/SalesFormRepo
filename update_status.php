<?php
require_once 'auth.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($form_csrf)) {
        header('HTTP/1.1 403 Forbidden');
        exit("CSRF token validation failed.");
    }

    $id = $_POST['id'];
    $value = $_POST['value'];
    $column = $_POST['column']; // 'status' or 'payment_status'

    // Whitelist columns for security
    $allowed_columns = ['status', 'payment_status'];
    if (!in_array($column, $allowed_columns)) {
        die("Invalid column");
    }

    $stmt = $conn->prepare("UPDATE sales_orders SET $column = ? WHERE id = ?");
    $stmt->bind_param("si", $value, $id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: " . $conn->error;
    }

    $stmt->close();
}
$conn->close();
?>
