<?php
require_once 'auth.php';
// update_payment.php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($form_csrf)) {
        header('HTTP/1.1 403 Forbidden');
        exit("CSRF token validation failed.");
    }

    $id = intval($_POST['id']);
    $amount_added = floatval($_POST['amount_added'] ?? 0);
    $new_total_paid = floatval($_POST['amount_paid'] ?? 0); // Still accept for redundancy/verification if needed

    // Start Transaction
    $conn->begin_transaction();

    try {
        // Fetch current and total amount to validate
        $check_sql = "SELECT total_amount, amount_paid FROM sales_orders WHERE id = ? FOR UPDATE";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            $total_amount = floatval($row['total_amount']);
            $current_paid = floatval($row['amount_paid']);
            $calculated_total = $current_paid + $amount_added;

            // Check if amount paid exceeds total amount
            if ($calculated_total > $total_amount + 0.01) {
                 throw new Exception("Error: Paid amount cannot exceed Total Amount (" . $total_amount . ")");
            } else {
                 // 1. Update sales_orders
                 $update_sql = "UPDATE sales_orders SET amount_paid = ? WHERE id = ?";
                 $stmt_update = $conn->prepare($update_sql);
                 $stmt_update->bind_param("di", $calculated_total, $id);
                 $stmt_update->execute();
                 $stmt_update->close();

                 // 2. Insert into order_payments
                 $history_sql = "INSERT INTO order_payments (order_id, amount) VALUES (?, ?)";
                 $stmt_hist = $conn->prepare($history_sql);
                 $stmt_hist->bind_param("id", $id, $amount_added);
                 $stmt_hist->execute();
                 $stmt_hist->close();

                 $conn->commit();
                 echo "success";
            }
        } else {
            throw new Exception("Order not found");
        }
        $stmt_check->close();
    } catch (Exception $e) {
        $conn->rollback();
        echo $e->getMessage();
    }
}
$conn->close();
?>
