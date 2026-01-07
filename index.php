<?php
require_once 'auth.php';
require_once 'db.php';

// Logic for View Leads Filter
$lead_counts = [];
$filter_lead_source = "";
$date_from = "";
$date_to = "";
$show_leads_result = false;

if (isset($_GET['filter_leads'])) {
    $filter_lead_source = $_GET['lead_source_filter'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    // Query to count orders by lead source within date range
    $lead_sql = "SELECT lead_source, COUNT(*) as count, SUM(total_amount) as total_revenue 
                 FROM sales_orders WHERE 1=1";
    
    // Params for binding
    $types = "";
    $params = [];

    if (!empty($filter_lead_source)) {
        $lead_sql .= " AND lead_source = ?";
        $types .= "s";
        $params[] = $filter_lead_source;
    }
    if (!empty($date_from)) {
        $lead_sql .= " AND created_at >= ?"; // Assuming created_at exists, if not use reg_date or modify DB
        $types .= "s";
        $params[] = $date_from . " 00:00:00";
    }
    if (!empty($date_to)) {
        $lead_sql .= " AND created_at <= ?";
        $types .= "s";
        $params[] = $date_to . " 23:59:59";
    }

    $lead_sql .= " GROUP BY lead_source";

    $stmt = $conn->prepare($lead_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lead_counts[] = $row;
    }
    $show_leads_result = true;
}

// Logic to fetch all orders for "View Orders" Tab
// Fetch limited or all? "View Orders - This should display order list"
$sort_order = isset($_GET['sort']) && $_GET['sort'] == 'ASC' ? 'ASC' : 'DESC';
$orders_sql = "SELECT * FROM sales_orders WHERE 1=1";

$types = "";
$params = [];

if (!empty($_GET['filter_from_date'])) {
    $orders_sql .= " AND DATE(created_at) >= ?";
    $types .= "s";
    $params[] = $_GET['filter_from_date'];
}
if (!empty($_GET['filter_to_date'])) {
    $orders_sql .= " AND DATE(created_at) <= ?";
    $types .= "s";
    $params[] = $_GET['filter_to_date'];
}
if (!empty($_GET['lead_source'])) {
    $orders_sql .= " AND lead_source LIKE ?";
    $types .= "s";
    $params[] = "%" . $_GET['lead_source'] . "%";
}

// New Filters: Search (Name, City, Mobile)
if (!empty($_GET['search_query'])) {
    $search = "%" . $_GET['search_query'] . "%";
    $orders_sql .= " AND (customer_name LIKE ? OR city LIKE ? OR mobile1 LIKE ?)";
    $types .= "sss";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// New Filters: Status (Cancellation)
if (!empty($_GET['status_filter'])) {
    $orders_sql .= " AND status = ?";
    $types .= "s";
    $params[] = $_GET['status_filter'];
}

$orders_sql .= " ORDER BY id $sort_order LIMIT 7";

$stmt = $conn->prepare($orders_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders_result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Entry Form</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-color: #f3f4f6;
            --card-bg: #ffffff;
            --text-color: #1f2937;
            --text-muted: #6b7280;
            --border-color: #d1d5db;
            --input-bg: #f9fafb;
            --radius: 0.5rem;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding: 1rem;
            line-height: 1.4;
        }

        .container {
            max-width: 1400px; /* Increased for table */
            margin: 0 auto;
            background: var(--card-bg);
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            min-height: 80vh;
        }

        h2 {
            text-align: center;
            color: #111827;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* --- TABS Styles --- */
        .tabs {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-muted);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            color: var(--primary-color);
            background-color: #f3f4f6;
            border-radius: var(--radius);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- FORM Styles --- */
        .form-layout {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
        }

        .left-panel { flex: 6; padding-right: 1rem; border-right: 1px solid #e5e7eb; }
        .right-panel { flex: 4; padding-left: 1rem; }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
        }

        .section-title::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 1.2em;
            background-color: var(--primary-color);
            margin-right: 0.75rem;
            border-radius: 2px;
        }

        .section-title.mt-2 { margin-top: 1.5rem; }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .col-12 { grid-column: span 12; }
        .col-6 { grid-column: span 6; }
        .col-4 { grid-column: span 4; }
        .col-3 { grid-column: span 3; }

        label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
            font-size: 0.8rem;
            color: #374151;
        }

        input[type="text"], input[type="email"], input[type="number"], input[type="date"], select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: var(--input-bg);
            font-size: 0.9rem;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background-color: #fff;
        }

        input[readonly] { background-color: #f3f4f6; color: #6b7280; cursor: not-allowed; }

        .radio-group { display: flex; align-items: center; gap: 1rem; height: 38px; }
        .radio-label { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.85rem; }
        input[type="radio"] { width: 1em; height: 1em; accent-color: var(--primary-color); }

        #gst_field { display: none; }

        .btn-submit, .btn-filter {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-filter { width: auto; padding: 0.5rem 1.5rem; margin-top: 1.3rem; } /* Align with inputs */

        .btn-submit:hover, .btn-filter:hover { background-color: var(--primary-hover); }

        /* --- TABLE Styles --- */
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            white-space: nowrap;
        }
        tr:hover { background-color: #f9fafb; }

        /* --- MODAL Styles --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: relative;
        }
        .modal-close {
            position: absolute;
            right: 1rem;
            top: 0.5rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        .modal-title {
            margin-bottom: 1rem;
            font-weight: 600;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }
        #bifurcation_list {
            list-style: none;
            max-height: 300px;
            overflow-y: auto;
        }
        #bifurcation_list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }
        #bifurcation_list li:last-child { border-bottom: none; }

        /* Responsive */
        @media (max-width: 1024px) {
            .form-layout { flex-direction: column; }
            .left-panel, .right-panel { flex: auto; width: 100%; padding: 0; border: none; }
            .right-panel { margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 2rem; }
        }

        /* --- Search Suggestions --- */
        .search-container { position: relative; width: 100%; margin-bottom: 1.5rem; }
        .search-results { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            right: 0; 
            background: white; 
            border: 1px solid var(--border-color); 
            border-top: none; 
            z-index: 1000; 
            max-height: 200px; 
            overflow-y: auto; 
            box-shadow: var(--shadow);
            display: none;
            border-radius: 0 0 var(--radius) var(--radius);
        }
        .search-item { 
            padding: 0.75rem; 
            cursor: pointer; 
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.9rem;
        }
        .search-item:hover { background-color: #f3f4f6; }
        .search-item .sub-info { font-size: 0.8rem; color: var(--text-muted); display: block; }

        /* --- Multi-select Dropdown --- */
        .multi-select {
            position: relative;
            width: 100%;
        }
        .select-box {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: var(--input-bg);
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .select-box:after {
            content: "▼";
            font-size: 0.6rem;
            color: var(--text-muted);
        }
        .options-container {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            z-index: 100;
            display: none;
            box-shadow: var(--shadow);
            border-radius: 0 0 var(--radius) var(--radius);
        }
        .option-item {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .option-item:hover { background-color: #f3f4f6; }
        .option-item input { margin-right: 0.5rem; }
    </style>
</head>

<body>

    <div class="container">
        <!-- Tabs Header -->
        <div class="tabs" style="justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 1rem;">
                <button class="tab-btn active" onclick="openTab('new_order')">New Order</button>
                <button class="tab-btn" onclick="openTab('view_orders')">View Orders</button>
                <button class="tab-btn" onclick="openTab('view_leads')">View Lead</button>
            </div>
            <div style="padding-bottom: 2px;">
                <span style="font-size: 0.9rem; color: var(--text-muted); margin-right: 1rem;">Hi, <?php echo $_SESSION['username'] ?? 'User'; ?></span>
                <a href="logout.php" style="color: #dc2626; text-decoration: none; font-size: 0.9rem; font-weight: 600; padding: 0.5rem 1rem; border: 1px solid #fecaca; border-radius: 4px; transition: all 0.2s;">Logout</a>
            </div>
        </div>

        <!-- 1. NEW ORDER TAB -->
        <div id="new_order" class="tab-content active">
            <form action="process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-layout">

                    <!-- Left Side: Customer Details -->
                    <div class="left-panel">
                        <div class="section-title">Customer Details</div>

                        <div class="search-container">
                            <label>Search Existing Customer (Name or Mobile)</label>
                            <input type="text" id="customer_search" placeholder="Type at least 3 characters..." autocomplete="off">
                            <div id="search_results" class="search-results"></div>
                        </div>

                        <div class="grid">
                            <div class="col-6">
                                <label>Status</label>
                                <div class="radio-group">
                                    <label class="radio-label"><input type="radio" name="cust_type" id="type_registered" value="Registered" onclick="toggleGST(true)"> Registered</label>
                                    <label class="radio-label"><input type="radio" name="cust_type" id="type_unregistered" value="Unregistered" checked onclick="toggleGST(false)"> Unregistered</label>
                                </div>
                            </div>
                        </div>

                        <div class="grid" id="gst_field">
                            <div class="col-12">
                                <label>GST No</label>
                                <input type="text" name="gst_no" placeholder="Enter GST Number">
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-12">
                                <label>Customer Name</label>
                                <input type="text" name="name" required placeholder="Enter customer name">
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-6">
                                <label>Address Line 1</label>
                                <input type="text" name="addr1" placeholder="Line 1" required>
                            </div>
                            <div class="col-6">
                                <label>Address Line 2</label>
                                <input type="text" name="addr2" placeholder="Line 2">
                            </div>
                            <div class="col-6">
                                <label>Landmark</label>
                                <input type="text" name="landmark" placeholder="Landmark">
                            </div>
                            <div class="col-6">
                                <label>City</label>
                                <input type="text" name="city" placeholder="City" required>
                            </div>
                            <div class="col-6">
                                <label>State</label>
                                <input type="text" name="state" placeholder="State">
                            </div>
                            <div class="col-6">
                                <label>Pincode</label>
                                <input type="text" name="pincode" placeholder="Pincode" required>
                            </div>
                        </div>

                        <div class="section-title mt-2">Contact Info</div>
                        <div class="grid">
                            <div class="col-4">
                                <label>Email ID</label>
                                <input type="email" name="email" placeholder="email@example.com" required> 
                                <!-- Added required -->
                            </div>
                            <div class="col-4">
                                <label>Mobile 1 *</label>
                                <input type="text" name="mob1" required pattern="\d{10}" maxlength="10" title="10 digit mobile number" placeholder="10 Digits Only" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                <!-- Enforced Numeric 10 digit -->
                            </div>
                            <div class="col-4">
                                <label>Mobile 2</label>
                                <input type="text" name="mob2" placeholder="Alternate">
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Order Details -->
                    <div class="right-panel">
                        <div class="section-title">Order Details</div>

                        <div class="grid">
                            <div class="col-12">
                                <label>Product</label>
                                <select name="product" required>
                                    <option value="">-- Select Product --</option>
                                    <option value="QuNile : Nitrile Examination Gloves">QuNile : Nitrile Examination Gloves</option>
                                    <option value="QuLex : Latex Examination Gloves">QuLex : Latex Examination Gloves</option>
                                    <option value="QuNDis : Nitrile Disposable Gloves">QuNDis : Nitrile Disposable Gloves</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label>Order Date</label>
                                <input type="date" name="order_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <!-- NEW FIELDS -->
                        <div class="grid">
                            <div class="col-6">
                                <label>Delivery Type</label>
                                <select name="delivery_type">
                                    <option value="">-- Select --</option>
                                    <option value="Gonny Bag">Gonny Bag</option>
                                    <option value="Box">Box</option>
                                    <option value="Pieces">Pieces</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label>Lead Source</label>
                                <select name="lead_source">
                                    <option value="">-- Select --</option>
                                    <option value="Website order">Website order</option>
                                    <option value="Database calling">Database calling</option>
                                    <option value="Existing customer">Existing customer</option>
                                    <option value="Corporate customer">Corporate customer</option>
                                    <option value="Google marketing">Google marketing</option>
                                    <option value="Amazon">Amazon</option>
                                    <option value="Flipkart">Flipkart</option>
                                    <option value="Just dial">Just dial</option>
                                    <option value="India Mart">India Mart</option>
                                    <option value="Bahadur">Bahadur</option>
                                    <option value="Harddik">Harddik</option>
                                    <option value="Shraddha">Shraddha</option>
                                    <option value="Sheetal">Sheetal</option>
                                    <option value="Sarika">Sarika</option>
                                    <option value="Akshata">Akshata</option>
                                    <option value="Lokesh">Lokesh</option>
                                    <option value="Satish">Satish</option>
                                    <option value="Agent RAH">Agent RAH</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-12">
                                <label>Color</label>
                                <select name="color">
                                    <option value="Cobalt Blue">Cobalt Blue</option>
                                    <option value="Violet Blue">Violet Blue</option>
                                    <option value="Sky Blue">Sky Blue</option>
                                    <option value="Dark Blue">Dark Blue</option>
                                    <option value="White">White</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-12">
                                <label>Gloves Size & Quantities</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <div style="flex: 1;">
                                        <label style="font-size: 0.7rem; color: var(--text-muted);">Small</label>
                                        <input type="number" name="size_small_qty" id="size_small_qty" value="0" min="0" oninput="calculateTotal()" placeholder="S Qty">
                                    </div>
                                    <div style="flex: 1;">
                                        <label style="font-size: 0.7rem; color: var(--text-muted);">Medium</label>
                                        <input type="number" name="size_medium_qty" id="size_medium_qty" value="0" min="0" oninput="calculateTotal()" placeholder="M Qty">
                                    </div>
                                    <div style="flex: 1;">
                                        <label style="font-size: 0.7rem; color: var(--text-muted);">Large</label>
                                        <input type="number" name="size_large_qty" id="size_large_qty" value="0" min="0" oninput="calculateTotal()" placeholder="L Qty">
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="grid">
                            <div class="col-6">
                                <label>Unit (Qty) [Sum of Sizes]</label>
                                <input type="number" id="qty" name="qty" value="0" step="any" readonly style="background-color: #f3f4f6; cursor: default;">
                            </div>
                            <div class="col-6">
                                <label>Rate</label>
                                <input type="number" id="rate" name="rate" value="0" step="0.01" oninput="calculateTotal()">
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-6">
                                <label>GST %</label>
                                <select id="gst_percent" name="gst_percent" onchange="calculateTotal()">
                                    <option value="5">5%</option>
                                    <option value="12">12%</option>
                                    <option value="18">18%</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label>MRP</label>
                                <input type="number" name="mrp" placeholder="MRP" step="0.01">
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-6">
                                <label>Discount</label>
                                <input type="number" id="discount" name="discount" value="0" step="any" oninput="calculateTotal()">
                            </div>
                            <div class="col-6">
                                <label>Total Amount</label>
                                <input type="text" id="total" name="total_amount" readonly style="font-weight: bold; color: var(--primary-color);">
                            </div>
                        </div>

                        <div class="grid">
                            <div class="col-6">
                                <label>Payment Term</label>
                                <input type="text" name="payment_term" placeholder="e.g. Net 30, COD, Advance">
                            </div>
                            <div class="col-6">
                                <label>Payment Reminder Date</label>
                                <input type="date" name="payment_reminder">
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">Save Order</button>
                    </div>

                </div>
            </form>
        </div>

        <!-- 2. VIEW ORDERS TAB -->
        <div id="view_orders" class="tab-content">
            <h3 style="margin-bottom:1rem;">Order List</h3>
            
            <!-- Filter & Sort Form -->
            <form method="GET" action="index.php" style="margin-bottom: 1rem; background: #fff; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                <input type="hidden" name="tab" value="view_orders">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div>
                    <label style="display:block; font-size: 0.8rem; margin-bottom: 0.3rem; color: #4b5563;">From Date</label>
                    <input type="date" name="filter_from_date" value="<?php echo htmlspecialchars($_GET['filter_from_date'] ?? ''); ?>" style="width: auto; padding: 0.4rem;">
                </div>
                
                <div>
                    <label style="display:block; font-size: 0.8rem; margin-bottom: 0.3rem; color: #4b5563;">To Date</label>
                    <input type="date" name="filter_to_date" value="<?php echo $_GET['filter_to_date'] ?? ''; ?>" style="width: auto; padding: 0.4rem;">
                </div>

                <div>
                    <label style="display:block; font-size: 0.8rem; margin-bottom: 0.3rem; color: #4b5563;">Sort By ID</label>
                    <select name="sort" style="width: auto; padding: 0.45rem;">
                        <option value="DESC" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'DESC') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="ASC" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'ASC') ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size: 0.8rem; margin-bottom: 0.3rem; color: #4b5563;">Lead Source</label>
                    <input type="text" name="lead_source" value="<?php echo $_GET['lead_source'] ?? ''; ?>" placeholder="Search lead..." style="width: auto; padding: 0.45rem;">
                </div>

                <div>
                    <label style="display:block; font-size: 0.8rem; margin-bottom: 0.3rem; color: #4b5563;">Search (Name/City/Mob)</label>
                    <input type="text" name="search_query" value="<?php echo $_GET['search_query'] ?? ''; ?>" placeholder="Search..." style="width: auto; padding: 0.45rem;">
                </div>

                <div>
                    <label style="display:block; font-size: 0.8rem; margin-bottom: 0.3rem; color: #4b5563;">Status</label>
                    <select name="status_filter" style="width: auto; padding: 0.45rem;">
                        <option value="">All Status</option>
                        <option value="Cancelled" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled Only</option>
                    </select>
                </div>

                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <button type="submit" class="btn-filter" style="margin-top:0; padding: 0.5rem 1.5rem;">Filter</button>
                    <button type="button" class="btn-filter" style="margin-top:0; padding: 0.5rem 1.5rem; background-color: #10b981;" onclick="exportData()">Export</button>
                    <a href="index.php?tab=view_orders" style="color: var(--text-muted); text-decoration: underline; font-size: 0.9rem;">Clear</a>
                </div>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                            <tr>
                                <th>ID</th>
                                <th>Creation Date</th>
                                <th>Order Date</th>
                                <th>Customer Name</th>
                                <th>Mobile Number</th>
                                <th>Product</th>
                                <th>City</th>
                                <th>Address</th>
                                <th>Lead Source</th>
                                <th>Size</th>
                                <th>Delivery Status</th>
                                <th>Payment Status</th>
                                <th>Total Amount</th>
                                <th>Payment Progress</th>
                                <th>Pending Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($orders_result->num_rows > 0): ?>
                                <?php while($row = $orders_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td><?php echo date('d-M-Y h:i A', strtotime($row['created_at'])); ?></td>
                                        <td><?php echo !empty($row['order_date']) ? date('d-M-Y', strtotime($row['order_date'])) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['mobile1']); ?></td>
                                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['city']); ?></td>
                                        <td><?php echo htmlspecialchars($row['address_line1']); ?></td>
                                        <td><?php echo htmlspecialchars($row['lead_source']); ?></td>
                                        <td>
                                            <?php 
                                            $sizes = [];
                                            if(($row['size_small_qty'] ?? 0) > 0) $sizes[] = "S:" . $row['size_small_qty'];
                                            if(($row['size_medium_qty'] ?? 0) > 0) $sizes[] = "M:" . $row['size_medium_qty'];
                                            if(($row['size_large_qty'] ?? 0) > 0) $sizes[] = "L:" . $row['size_large_qty'];
                                            echo !empty($sizes) ? implode(', ', $sizes) : htmlspecialchars($row['gloves_size'] ?? '-');
                                            ?>
                                        </td>
                                        <td>
                                            <select onchange="updateStatus(<?php echo $row['id']; ?>, this.value, 'status')" style="padding: 0.25rem; font-size: 0.85rem;">
                                                <option value="New" <?php echo ($row['status'] == 'New' || $row['status'] == 'Pending') ? 'selected' : ''; ?>>New</option>
                                                <option value="Delivered" <?php echo ($row['status'] == 'Delivered' || $row['status'] == 'Successful') ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="Cancelled" <?php echo ($row['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="Return" <?php echo ($row['status'] == 'Return') ? 'selected' : ''; ?>>Return</option>
                                            </select>
                                        </td>
                                        <td>
                                             <select onchange="updateStatus(<?php echo $row['id']; ?>, this.value, 'payment_status')" style="padding: 0.25rem; font-size: 0.85rem;">
                                                <option value="Pending" <?php echo ($row['payment_status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Received" <?php echo ($row['payment_status'] == 'Received') ? 'selected' : ''; ?>>Received</option>
                                            </select>
                                        </td>
                                        <td><strong><?php echo $row['total_amount']; ?></strong></td>
                                        <td>
                                            <div style="font-size:0.85rem; margin-bottom:4px;">
                                                Paid: <strong><span id="display_paid_<?php echo $row['id']; ?>" style="cursor:pointer; color: var(--primary-color); text-decoration: underline;" title="Click to see breakdown" onclick="showBifurcation(<?php echo $row['id']; ?>)"><?php echo number_format($row['amount_paid'] ?? 0, 2); ?></span></strong>
                                            </div>
                                            <input type="number" 
                                                   id="add_paid_<?php echo $row['id']; ?>" 
                                                   placeholder="+ Add" 
                                                   step="0.01" 
                                                   style="width: 80px; padding: 4px; border:1px solid #d1d5db; border-radius:4px;"
                                                   onchange="addPayment(<?php echo $row['id']; ?>, <?php echo $row['total_amount']; ?>)">
                                            
                                            <input type="hidden" id="current_paid_<?php echo $row['id']; ?>" value="<?php echo $row['amount_paid'] ?? 0; ?>">
                                        </td>
                                        <td>
                                            <span id="pending_<?php echo $row['id']; ?>" style="font-weight:bold; color:#dc2626;">
                                                <?php echo number_format($row['total_amount'] - ($row['amount_paid'] ?? 0), 2); ?>
                                            </span>
                                        </td>
                                    </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9" style="text-align:center;">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. VIEW LEADS TAB -->
        <div id="view_leads" class="tab-content">
            <h3 style="margin-bottom:1rem;">View Leads</h3>
            <form method="GET" action="index.php" style="margin-bottom:2rem; background:#f9f9f9; padding:1.5rem; border-radius:8px;">
                <input type="hidden" name="tab" value="view_leads"> <!-- Keep tab active -->
                <input type="hidden" name="filter_leads" value="1">
                <div class="grid">
                    <div class="col-3">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" required>
                    </div>
                    <div class="col-3">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" required>
                    </div>
                    <div class="col-4">
                        <label>Lead Source</label>
                        <select name="lead_source_filter">
                            <option value="">All Sources</option>
                            <option value="Website order" <?php echo ($filter_lead_source == 'Website order')?'selected':''; ?>>Website order</option>
                            <option value="Database calling" <?php echo ($filter_lead_source == 'Database calling')?'selected':''; ?>>Database calling</option>
                            <option value="Existing customer" <?php echo ($filter_lead_source == 'Existing customer')?'selected':''; ?>>Existing customer</option>
                            <option value="Corporate customer" <?php echo ($filter_lead_source == 'Corporate customer')?'selected':''; ?>>Corporate customer</option>
                            <option value="Google marketing" <?php echo ($filter_lead_source == 'Google marketing')?'selected':''; ?>>Google marketing</option>
                            <option value="Amazon" <?php echo ($filter_lead_source == 'Amazon')?'selected':''; ?>>Amazon</option>
                            <option value="Flipkart" <?php echo ($filter_lead_source == 'Flipkart')?'selected':''; ?>>Flipkart</option>
                            <option value="Just dial" <?php echo ($filter_lead_source == 'Just dial')?'selected':''; ?>>Just dial</option>
                            <option value="India Mart" <?php echo ($filter_lead_source == 'India Mart')?'selected':''; ?>>India Mart</option>
                            <option value="Bahadur" <?php echo ($filter_lead_source == 'Bahadur')?'selected':''; ?>>Bahadur</option>
                            <option value="Harddik" <?php echo ($filter_lead_source == 'Harddik')?'selected':''; ?>>Harddik</option>
                            <option value="Shraddha" <?php echo ($filter_lead_source == 'Shraddha')?'selected':''; ?>>Shraddha</option>
                            <option value="Sheetal" <?php echo ($filter_lead_source == 'Sheetal')?'selected':''; ?>>Sheetal</option>
                            <option value="Sarika" <?php echo ($filter_lead_source == 'Sarika')?'selected':''; ?>>Sarika</option>
                            <option value="Akshata" <?php echo ($filter_lead_source == 'Akshata')?'selected':''; ?>>Akshata</option>
                            <option value="Lokesh" <?php echo ($filter_lead_source == 'Lokesh')?'selected':''; ?>>Lokesh</option>
                            <option value="Satish" <?php echo ($filter_lead_source == 'Satish')?'selected':''; ?>>Satish</option>
                            <option value="Agent RAH" <?php echo ($filter_lead_source == 'Agent RAH')?'selected':''; ?>>Agent RAH</option>
                        </select>
                    </div>
                    <div class="col-2">
                        <button type="submit" class="btn-filter">Filter</button>
                    </div>
                </div>
            </form>

            <?php if ($show_leads_result): ?>
                <h4 style="margin-bottom:1rem;">Results</h4>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Lead Source</th>
                                <th>Order Count</th>
                                <th>Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lead_counts) > 0): ?>
                                <?php foreach ($lead_counts as $lead): ?>
                                    <tr>
                                        <td><?php echo $lead['lead_source'] ?: 'Unknown'; ?></td>
                                        <td><?php echo $lead['count']; ?></td>
                                        <td><?php echo number_format($lead['total_revenue'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No records found for this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        // Tab Switching Logic
        function openTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show clicked tab
            document.getElementById(tabId).classList.add('active');
            // Check button
            const buttons = document.getElementsByClassName('tab-btn');
            // This logic is simple, assume order: 0=New, 1=Orders, 2=Leads
            if(tabId == 'new_order') buttons[0].classList.add('active');
            if(tabId == 'view_orders') buttons[1].classList.add('active');
            if(tabId == 'view_leads') buttons[2].classList.add('active');
        }

        // Logic 1: Show/Hide GST Field
        function toggleGST(isRegistered) {
            const gstField = document.getElementById('gst_field');
            if (isRegistered) {
                gstField.style.display = 'block';
            } else {
                gstField.style.display = 'none';
                document.querySelector('input[name="gst_no"]').value = ''; // Clear value
            }
        }

        // Logic 2: Auto Calculate Total & Discount
        function calculateTotal() {
            let s_qty = parseFloat(document.getElementById('size_small_qty').value) || 0;
            let m_qty = parseFloat(document.getElementById('size_medium_qty').value) || 0;
            let l_qty = parseFloat(document.getElementById('size_large_qty').value) || 0;
            
            let total_qty = s_qty + m_qty + l_qty;
            document.getElementById('qty').value = total_qty;

            let qty = total_qty;
            let rate = parseFloat(document.getElementById('rate').value) || 0;
            let mrp = parseFloat(document.getElementsByName('mrp')[0].value) || 0; 
            let gstPer = parseFloat(document.getElementById('gst_percent').value) || 0;

            let baseAmount = qty * rate;
            let gstAmount = (baseAmount * gstPer) / 100;
            let finalTotal = baseAmount + gstAmount;

            if (mrp > 0 && rate > 0) {
                let discountPerItem = mrp - rate;
                document.getElementById('discount').value = discountPerItem.toFixed(2);
            }

            document.getElementById('total').value = finalTotal.toFixed(2);
        }

        document.getElementsByName('mrp')[0].addEventListener('input', calculateTotal);

        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if(tab) {
            openTab(tab);
        }

        // Logic 3: Update Status AJAX
        function updateStatus(orderId, newValue, columnName) {
            // Optional confirm
            // if(!confirm("Update " + columnName + " to " + newValue + "?")) return;

            const formData = new FormData();
            formData.append('id', orderId);
            formData.append('value', newValue);
            formData.append('column', columnName);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            fetch('update_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if(data.trim() == 'success') {
                    // Visual feedback?
                    // console.log("Status updated");
                } else {
                    alert('Failed to update status: ' + data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred.');
            });
        }

        // Logic 4: Export to Excel
        function exportData() {
            // Get current filter params
            const urlParams = new URLSearchParams(window.location.search);
            let queryString = urlParams.toString();
            window.location.href = 'export.php?' + queryString;
        }

        // Logic 5: Add Payment (Incremental)
        function addPayment(orderId, totalAmount) {
            const input = document.getElementById('add_paid_' + orderId);
            const displaySpan = document.getElementById('display_paid_' + orderId);
            const currentHidden = document.getElementById('current_paid_' + orderId);
            const pendingSpan = document.getElementById('pending_' + orderId);

            let amountToAdd = parseFloat(input.value);
            if (isNaN(amountToAdd) || amountToAdd === 0) return; // No change

            let currentPaid = parseFloat(currentHidden.value) || 0;
            let newTotalPaid = currentPaid + amountToAdd;

            // Validate negative total? 
            if (newTotalPaid < 0) {
                alert("Total paid cannot be negative.");
                input.value = '';
                return;
            }
            
            // Validate exceeding total?
            // Allow small float margin error
            if (newTotalPaid > totalAmount + 0.01) {
                alert("Total paid cannot exceed Order Value (" + totalAmount + ")");
                input.value = '';
                return;
            }

            // Send AJAX
            const formData = new FormData();
            formData.append('id', orderId);
            formData.append('amount_added', amountToAdd);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            // formData.append('amount_paid', newTotalPaid); // Optional legacy

            fetch('update_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if(data.trim() == 'success') {
                    // Optimistic UI Update AFTER success to be safe with history
                    displaySpan.textContent = newTotalPaid.toFixed(2);
                    pendingSpan.textContent = (totalAmount - newTotalPaid).toFixed(2);
                    currentHidden.value = newTotalPaid;
                    input.value = ''; // Clear input

                    displaySpan.style.color = 'green';
                    setTimeout(() => displaySpan.style.color = 'inherit', 1500);
                } else {
                    alert('Failed to update payment: ' + data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Connection error');
            });
        }

        // Logic 6: Show Bifurcation
        function showBifurcation(orderId) {
            const list = document.getElementById('bifurcation_list');
            list.innerHTML = '<li>Loading...</li>';
            document.getElementById('payment_modal').style.display = 'flex';

            fetch('get_payment_history.php?order_id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    list.innerHTML = '';
                    if (data.length === 0) {
                        list.innerHTML = '<li>No payments recorded.</li>';
                    } else {
                        data.forEach(payment => {
                            const li = document.createElement('li');
                            li.innerHTML = `<span>${payment.payment_date}</span> <strong>${parseFloat(payment.amount).toFixed(2)}</strong>`;
                            list.appendChild(li);
                        });
                    }
                })
                .catch(err => {
                    list.innerHTML = '<li>Error loading history.</li>';
                    console.error(err);
                });
        }

        function closeModal() {
            document.getElementById('payment_modal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('payment_modal');
            if (event.target == modal) {
                closeModal();
            }

            // Close size options if clicked outside
            const multiSelect = document.getElementById('gloves_size_multi');
            const options = document.getElementById('size_options');
            if (!multiSelect.contains(event.target)) {
                options.style.display = 'none';
            }
        }

        // Logic 7: Customer Search
        const searchInput = document.getElementById('customer_search');
        const resultsDiv = document.getElementById('search_results');

        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length < 3) {
                resultsDiv.style.display = 'none';
                return;
            }

            fetch('search_customer.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    resultsDiv.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(cust => {
                            const item = document.createElement('div');
                            item.className = 'search-item';
                            item.innerHTML = `<strong>${cust.customer_name}</strong> <span class="sub-info">${cust.mobile1} | ${cust.city}</span>`;
                            item.onclick = function() {
                                fillForm(cust);
                                resultsDiv.style.display = 'none';
                                searchInput.value = '';
                            };
                            resultsDiv.appendChild(item);
                        });
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.style.display = 'none';
                    }
                });
        });

        function fillForm(cust) {
            // Fill common fields
            document.querySelector('input[name="name"]').value = cust.customer_name;
            document.querySelector('input[name="addr1"]').value = cust.address_line1;
            document.querySelector('input[name="addr2"]').value = cust.address_line2;
            document.querySelector('input[name="landmark"]').value = cust.landmark;
            document.querySelector('input[name="city"]').value = cust.city;
            document.querySelector('input[name="state"]').value = cust.state;
            document.querySelector('input[name="pincode"]').value = cust.pincode;
            document.querySelector('input[name="email"]').value = cust.email;
            document.querySelector('input[name="mob1"]').value = cust.mobile1;
            document.querySelector('input[name="mob2"]').value = cust.mobile2;

            // Handle Customer Type and GST
            if (cust.customer_type === 'Registered') {
                document.getElementById('type_registered').checked = true;
                toggleGST(true);
                document.querySelector('input[name="gst_no"]').value = cust.gst_no;
            } else {
                document.getElementById('type_unregistered').checked = true;
                toggleGST(false);
            }
        }

        // Logic 8: Multi-select Dropdown
        function toggleOptions() {
            const options = document.getElementById('size_options');
            options.style.display = options.style.display === 'block' ? 'none' : 'block';
        }

        function updateSelectedSizes() {
            const checkboxes = document.querySelectorAll('input[name="gloves_size[]"]:checked');
            const selectedText = document.getElementById('selected_sizes_text');
            const values = Array.from(checkboxes).map(cb => cb.value);
            
            if (values.length > 0) {
                selectedText.textContent = values.join(', ');
            } else {
                selectedText.textContent = 'Select sizes...';
            }
        }
    </script>

    <div id="payment_modal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <div class="modal-title">Payment Breakdown</div>
            <ul id="bifurcation_list"></ul>
        </div>
    </div>

</body>
</html>