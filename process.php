<?php
require_once 'auth.php';
require_once 'db.php';

// 3. Process Form Data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CSRF Check
    $form_csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($form_csrf)) {
        die("CSRF token validation failed.");
    }

    // Input Validation & Sanitization
    $cust_type = $_POST['cust_type'] ?? 'Unregistered';
    $gst_no = ($cust_type == 'Registered') ? trim($_POST['gst_no'] ?? '') : NULL;
    
    $name = trim($_POST['name'] ?? '');
    $addr1 = trim($_POST['addr1'] ?? '');
    $addr2 = trim($_POST['addr2'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $area = trim($_POST['area'] ?? '');
    
    $email_input = trim($_POST['email'] ?? '');
    $email = !empty($email_input) ? filter_var($email_input, FILTER_VALIDATE_EMAIL) : NULL;
    if (!empty($email_input) && !$email) die("Invalid email format");

    $mob1 = preg_replace('/[^0-9]/', '', $_POST['mob1'] ?? '');
    if (strlen($mob1) !== 10) die("Invalid primary mobile number (10 digits required)");

    $mob2 = preg_replace('/[^0-9]/', '', $_POST['mob2'] ?? '');
    
    $product = trim($_POST['product'] ?? '');
    $delivery_type = trim($_POST['delivery_type'] ?? '');
    $lead_source = trim($_POST['lead_source'] ?? '');
    $color = trim($_POST['color'] ?? '');
    
    // Capture Gloves Size Quantities
    $xs_qty = intval($_POST['size_extra_small_qty'] ?? 0);
    $s_qty = intval($_POST['size_small_qty'] ?? 0);
    $m_qty = intval($_POST['size_medium_qty'] ?? 0);
    $l_qty = intval($_POST['size_large_qty'] ?? 0);

    // Create summary string for gloves_size column (backward compatibility)
    $sizes = [];
    if($xs_qty > 0) $sizes[] = "XS:$xs_qty";
    if($s_qty > 0) $sizes[] = "S:$s_qty";
    if($m_qty > 0) $sizes[] = "M:$m_qty";
    if($l_qty > 0) $sizes[] = "L:$l_qty";
    $gloves_size = implode(', ', $sizes);
    
    $qty = intval($_POST['qty'] ?? 0);
    $rate = floatval($_POST['rate'] ?? 0);
    $gst_percent = floatval($_POST['gst_percent'] ?? 0);
    $mrp = floatval($_POST['mrp'] ?? 0); 
    $discount = floatval($_POST['discount'] ?? 0);
    $total = floatval($_POST['total_amount'] ?? 0);

    if ($qty <= 0) die("Quantity must be greater than 0");
    if ($rate < 0) die("Rate cannot be negative");

    // NEW FIELDS
    $payment_term = trim($_POST['payment_term'] ?? '');
    $payment_reminder = $_POST['payment_reminder'] ?? NULL;
    if ($payment_reminder === "") $payment_reminder = NULL;
    
    $order_date = $_POST['order_date'] ?? date('Y-m-d');
    if ($order_date === "") $order_date = date('Y-m-d');

    $status = "New"; // Default status

    // 4. Insert Data
    $sql = "INSERT INTO sales_orders 
            (customer_type, gst_no, customer_name, address_line1, address_line2, landmark, city, state, pincode, area,
            email, mobile1, mobile2, product_name, delivery_type, lead_source, product_color, gloves_size, 
            size_extra_small_qty, size_small_qty, size_medium_qty, size_large_qty,
            quantity, rate, gst_percent, mrp, discount, total_amount, 
            payment_term, payment_reminder_date, order_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; 

    $stmt = $conn->prepare($sql);
    
    if($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }
    
    // Bind Params: 32 items
    // Types: sssss sssss sssss sss iiiii dddd ssss
    $stmt->bind_param("ssssssssssssssssssiiiiiddddsssss", 
        $cust_type, $gst_no, $name, $addr1, $addr2, $landmark, $city, $state, $pincode, $area,
        $email, $mob1, $mob2, $product, $delivery_type, $lead_source, $color, $gloves_size,
        $xs_qty, $s_qty, $m_qty, $l_qty,
        $qty, $rate, $gst_percent, $mrp, $discount, $total,
        $payment_term, $payment_reminder, $order_date, $status
    );

    if ($stmt->execute()) {
        // Redirect to View Orders tab or back to form with success
        header("Location: index.php?tab=view_orders&status=success");
        exit();
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>