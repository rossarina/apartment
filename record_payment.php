<?php
include 'config.php'; 
include 'header.php'; // **เชื่อมเมนู**

$message = ""; 
$pending_invoices = []; 

// ดึงรายการใบแจ้งหนี้ที่สถานะเป็น 'Pending' พร้อมดึงเลขห้องและยอดเงิน
$sql_invoices = "SELECT i.invoice_id, i.total_amount, r.room_number 
                 FROM invoices i JOIN leases l ON i.lease_id = l.lease_id
                 JOIN rooms r ON l.room_id = r.room_id
                 WHERE i.status = 'Pending' ORDER BY i.issue_date ASC";
$result_invoices = $conn->query($sql_invoices);

if ($result_invoices->num_rows > 0) {
    while($row = $result_invoices->fetch_assoc()) {
        $pending_invoices[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $invoice_id = $conn->real_escape_string($_POST['invoice_id']);
    $amount_paid = $conn->real_escape_string($_POST['amount_paid']);
    $payment_date = $conn->real_escape_string($_POST['payment_date']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    
    // **ใช้ Transaction**
    $conn->begin_transaction();
    $success = true;

    // A. บันทึกข้อมูลการชำระเงินลงในตาราง payments
    $sql_insert_payment = "INSERT INTO payments (invoice_id, payment_date, amount_paid, payment_method) 
                           VALUES ('$invoice_id', '$payment_date', '$amount_paid', '$payment_method')";
                           
    if (!$conn->query($sql_insert_payment)) {
        $success = false;
        $message = "❌ เกิดข้อผิดพลาดในการบันทึก Payments: " . $conn->error;
    }

    // B. อัปเดตสถานะใบแจ้งหนี้ในตาราง invoices ให้เป็น 'Paid'
    if ($success) {
        $sql_update_invoice = "UPDATE invoices SET status = 'Paid' WHERE invoice_id = '$invoice_id'";
        if (!$conn->query($sql_update_invoice)) {
            $success = false;
            $message = "❌ เกิดข้อผิดพลาดในการอัปเดต Invoices: " . $conn->error;
        }
    }

    // 3. ยืนยันหรือยกเลิก Transaction
    if ($success) {
        $conn->commit();
        // ใช้ header เพื่อล้างค่า POST และป้องกันการส่งซ้ำ
        header("Location: record_payment.php?status=success"); 
        exit();
    } else {
        $conn->rollback();
    }
}

if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $message = "✅ บันทึกการชำระเงินสำเร็จ! สถานะใบแจ้งหนี้อัปเดตแล้ว";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกการชำระเงิน</title>
    <?php echo $style_alerts; ?>
</head>
<body>
    
    <?php echo $nav_menu; // **แสดงเมนูนำทาง** ?>

    <h2>💵 บันทึกการชำระเงินค่าเช่า</h2>
    
    <?php
    if (!empty($message)) {
        $style_class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
        echo "<p class='$style_class'>$message</p>";
    }
    ?>

    <?php if (empty($pending_invoices)): ?>
        <p>🎉 ไม่มีใบแจ้งหนี้ที่ค้างชำระในขณะนี้!</p>
    <?php else: ?>
        <form action="record_payment.php" method="POST">
            
            <label for="invoice_id">เลือกใบแจ้งหนี้ที่ชำระแล้ว:</label><br>
            <select id="invoice_id" name="invoice_id" required>
                <option value="">-- เลือกใบแจ้งหนี้ --</option>
                <?php foreach ($pending_invoices as $invoice): ?>
                    <option value="<?php echo $invoice['invoice_id']; ?>">
                        ห้อง <?php echo $invoice['room_number']; ?> | ยอด: <?php echo number_format($invoice['total_amount'], 2); ?> ฿
                    </option>
                <?php endforeach; ?>
            </select><br><br>

            <label for="amount_paid">จำนวนเงินที่ชำระ:</label><br>
            <input type="number" id="amount_paid" name="amount_paid" step="0.01" min="0.01" required><br><br>

            <label for="payment_date">วันที่ชำระ:</label><br>
            <input type="datetime-local" id="payment_date" name="payment_date" required value="<?php echo date('Y-m-d\TH:i'); ?>"><br><br>

            <label for="payment_method">ช่องทางการชำระเงิน:</label><br>
            <select id="payment_method" name="payment_method" required>
                <option value="">-- เลือกช่องทาง --</option>
                <option value="เงินสด">เงินสด</option>
                <option value="โอนเงิน">โอนเงิน/QR Code</option>
                <option value="บัตรเครดิต">บัตรเครดิต</option>
            </select><br><br>

            <input type="submit" value="บันทึกการชำระเงิน">
        </form>
    <?php endif; ?>
</body>
</html>