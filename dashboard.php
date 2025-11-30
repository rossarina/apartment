<?php
include 'config.php'; 
include 'header.php'; // **เชื่อมเมนู**

// 1. กำหนดช่วงเวลาที่ต้องการสรุป (เดือนปัจจุบัน)
$start_of_month = date('Y-m-01');
$end_of_month = date('Y-m-t'); 

// 2. กำหนดตัวแปรสำหรับเก็บผลลัพธ์
$summary = [
    'total_income' => 0,
    'total_expense' => 0,
    'net_profit' => 0,
    'pending_invoices_count' => 0,
    'pending_invoices_amount' => 0
];

// --- A. สรุปรายรับ (Income) ---
$sql_income = "SELECT SUM(amount_paid) AS total_income FROM payments 
               WHERE payment_date BETWEEN '$start_of_month 00:00:00' AND '$end_of_month 23:59:59'";
$result_income = $conn->query($sql_income);
if ($result_income && $row = $result_income->fetch_assoc()) {
    $summary['total_income'] = $row['total_income'] ?: 0;
}

// --- B. สรุปรายจ่าย (Expense) ---
$sql_expense = "SELECT SUM(amount) AS total_expense FROM expenses 
                WHERE expense_date BETWEEN '$start_of_month' AND '$end_of_month'";
$result_expense = $conn->query($sql_expense);
if ($result_expense && $row = $result_expense->fetch_assoc()) {
    $summary['total_expense'] = $row['total_expense'] ?: 0;
}

// คำนวณกำไรสุทธิ
$summary['net_profit'] = $summary['total_income'] - $summary['total_expense'];

// --- C. สรุปใบแจ้งหนี้ค้างชำระ (Pending) ---
$sql_pending = "SELECT COUNT(invoice_id) AS count, SUM(total_amount) AS amount 
                FROM invoices 
                WHERE status = 'Pending'";
$result_pending = $conn->query($sql_pending);
if ($result_pending && $row = $result_pending->fetch_assoc()) {
    $summary['pending_invoices_count'] = $row['count'] ?: 0;
    $summary['pending_invoices_amount'] = $row['amount'] ?: 0;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Dashboard ภาพรวมบัญชี</title>
    <?php echo $style_alerts; // เพิ่ม CSS Alerts ?>
    <style>
        /* CSS สำหรับ Dashboard Card */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; box-shadow: 2px 2px 5px rgba(0,0,0,0.1); }
        .card h3 { margin-top: 0; font-size: 1.1em; color: #555; }
        .card .value { font-size: 2em; font-weight: bold; }
        .profit { color: #4CAF50; } /* เขียว */
        .loss { color: #f44336; } /* แดง */
        .pending { color: #ff9800; } /* ส้ม */
    </style>
</head>
<body>
    
    <?php echo $nav_menu; // **แสดงเมนูนำทาง** ?>

    <h2>🏠 แดชบอร์ดสรุปภาพรวมบัญชี (เดือน <?php echo date('m/Y'); ?>)</h2>
    
    <div class="summary-grid">
        
        <div class="card">
            <h3>รายรับรวม (เดือนนี้)</h3>
            <div class="value"><?php echo number_format($summary['total_income'], 2); ?> ฿</div>
        </div>

        <div class="card">
            <h3>รายจ่ายรวม (เดือนนี้)</h3>
            <div class="value loss"><?php echo number_format($summary['total_expense'], 2); ?> ฿</div>
        </div>

        <div class="card">
            <h3>กำไร/ขาดทุนสุทธิ (เดือนนี้)</h3>
            <?php $profit_class = ($summary['net_profit'] >= 0) ? 'profit' : 'loss'; ?>
            <div class="value <?php echo $profit_class; ?>">
                <?php echo number_format($summary['net_profit'], 2); ?> ฿
            </div>
        </div>
        
        <div class="card">
            <h3>ยอดหนี้ค้างชำระ (รวม)</h3>
            <div class="value pending">
                <?php echo number_format($summary['pending_invoices_amount'], 2); ?> ฿
            </div>
            <p style="font-size: 0.8em; margin: 5px 0 0;">(จำนวน <?php echo $summary['pending_invoices_count']; ?> ใบ)</p>
        </div>

    </div>

</body>
</html>