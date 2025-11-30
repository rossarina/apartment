<?php
include 'config.php'; 
include 'header.php'; 

$message = ""; 
$pending_invoices = [];

// --- A. ดึงข้อมูลใบแจ้งหนี้ที่ "ยังไม่จ่าย" (Pending) ---
$sql_invoices = "SELECT 
                i.invoice_id, i.issue_date, i.due_date, i.total_amount,
                r.room_number, t.first_name, t.last_name
               FROM invoices i
               JOIN leases l ON i.lease_id = l.lease_id
               JOIN rooms r ON l.room_id = r.room_id
               JOIN tenants t ON l.tenant_id = t.tenant_id
               WHERE i.status = 'Pending'
               ORDER BY i.due_date ASC";
$result_invoices = $conn->query($sql_invoices);
if ($result_invoices && $result_invoices->num_rows > 0) {
    while($row = $result_invoices->fetch_assoc()) { 
        $pending_invoices[] = $row; 
    }
}


// --- B. การจัดการฟอร์ม POST (บันทึกการชำระเงิน) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $invoice_id = $conn->real_escape_string($_POST['invoice_id']);
    $amount_paid = $conn->real_escape_string($_POST['amount_paid']);
    $payment_date = $conn->real_escape_string($_POST['payment_date']);

    // 1. INSERT การชำระเงินลงในตาราง payments
    $sql_insert_payment = "INSERT INTO payments (invoice_id, amount_paid, payment_date) 
                           VALUES ('$invoice_id', '$amount_paid', '$payment_date')";
    
    // 2. UPDATE สถานะใบแจ้งหนี้เป็น 'Paid'
    $sql_update_invoice = "UPDATE invoices SET status = 'Paid' WHERE invoice_id = '$invoice_id'";
    
    // ใช้ Transaction เพื่อให้แน่ใจว่าทำงานสำเร็จทั้งคู่
    $conn->begin_transaction();
    
    if ($conn->query($sql_insert_payment) && $conn->query($sql_update_invoice)) {
        $conn->commit();
        $message = "✅ บันทึกการชำระเงินเรียบร้อยแล้ว ใบแจ้งหนี้หมายเลข $invoice_id ถูกเปลี่ยนเป็น 'ชำระแล้ว'";
        // รีโหลดรายการใบแจ้งหนี้ที่ยังไม่จ่าย
        header("Location: update_payment.php?success=1");
        exit();
    } else {
        $conn->rollback();
        $message = "❌ Error ในการบันทึกการชำระเงิน: " . $conn->error;
    }
}

// ตรวจสอบ Success message หลัง redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
     $message = "✅ บันทึกการชำระเงินเรียบร้อยแล้ว ใบแจ้งหนี้ถูกเปลี่ยนสถานะเป็น 'ชำระแล้ว'";
}

$default_payment_date = date('Y-m-d');
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกการชำระเงิน</title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 500px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="date"], .form-container input[type="number"], .form-container select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .form-container input[type="submit"]:hover { background-color: #0056b3; }
        .pending-list { margin-top: 20px; }
        .pending-list h3 { border-bottom: 2px solid #ccc; padding-bottom: 5px; }
        .table-paid th { background-color: #f2f2f2; }
        .table-paid td, .table-paid th { border: 1px solid #ddd; padding: 8px; text-align: left; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2>💵 บันทึกการชำระเงินค่าเช่า</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>

        <form action="update_payment.php" method="POST">
            
            <label for="invoice_id">เลือกใบแจ้งหนี้ที่ชำระเงินแล้ว:</label>
            <select id="invoice_id" name="invoice_id" required>
                <option value="">-- เลือกใบแจ้งหนี้ (สถานะ Pending) --</option>
                <?php foreach ($pending_invoices as $invoice): ?>
                    <option value="<?php echo $invoice['invoice_id']; ?>">
                        #<?php echo $invoice['invoice_id']; ?>: ห้อง <?php echo $invoice['room_number']; ?> (<?php echo $invoice['first_name']; ?>) ยอด <?php echo number_format($invoice['total_amount'], 2); ?> ฿
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($pending_invoices)): ?>
                <p style="color: green; font-weight: bold;">*ไม่มีใบแจ้งหนี้ค้างชำระในระบบ</p>
            <?php endif; ?>

            <label for="amount_paid">จำนวนเงินที่ได้รับ (฿):</label>
            <input type="number" id="amount_paid" name="amount_paid" step="0.01" min="0" required>

            <label for="payment_date">วันที่ชำระเงิน:</label>
            <input type="date" id="payment_date" name="payment_date" required value="<?php echo $default_payment_date; ?>">

            <input type="submit" value="ยืนยันการชำระเงิน">
        </form>
    </div>

    <div class="container pending-list">
        <h3>รายการใบแจ้งหนี้ที่ยังค้างชำระ (Pending)</h3>
        <?php if (empty($pending_invoices)): ?>
            <p>ยอดเยี่ยม! ไม่มีรายการค้างชำระในขณะนี้</p>
        <?php else: ?>
            <table class="table-paid">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ห้องที่</th>
                        <th>ผู้เช่า</th>
                        <th>ยอดรวม</th>
                        <th>วันครบกำหนด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_invoices as $invoice): ?>
                        <tr>
                            <td>#<?php echo $invoice['invoice_id']; ?></td>
                            <td><?php echo $invoice['room_number']; ?></td>
                            <td><?php echo $invoice['first_name'] . ' ' . $invoice['last_name']; ?></td>
                            <td><?php echo number_format($invoice['total_amount'], 2); ?> ฿</td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>