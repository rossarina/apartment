<?php
include 'config.php';
include 'header.php';

$message = "";
$invoices_pending = [];

// ----------------------------------------------------
// A. ดึงรายการใบแจ้งหนี้ที่ค้างชำระ (Pending)
// ----------------------------------------------------
$sql_invoices = "
    SELECT 
        i.invoice_id, i.total_amount, i.issue_date,
        r.room_number,
        t.first_name, t.last_name,
        
        -- คำนวณยอดที่ชำระแล้ว
        (SELECT IFNULL(SUM(amount_paid), 0) FROM payments WHERE invoice_id = i.invoice_id) AS total_paid
        
    FROM invoices i
    JOIN leases l ON i.lease_id = l.lease_id
    JOIN rooms r ON l.room_id = r.room_id
    JOIN tenants t ON l.tenant_id = t.tenant_id
    WHERE i.status != 'Paid' OR i.status = 'Partial'
    ORDER BY i.issue_date ASC";

$result_invoices = $conn->query($sql_invoices);

if ($result_invoices && $result_invoices->num_rows > 0) {
    while ($row = $result_invoices->fetch_assoc()) {
        $row['balance_due'] = $row['total_amount'] - $row['total_paid'];
        // แสดงเฉพาะใบแจ้งหนี้ที่ยังมีเงินค้างชำระ > 0
        if ($row['balance_due'] > 0.00) {
            $invoices_pending[] = $row;
        }
    }
}

// ----------------------------------------------------
// B. การจัดการฟอร์ม POST (บันทึกการชำระเงิน)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $invoice_id = $conn->real_escape_string($_POST['invoice_id']);
    $payment_date = $conn->real_escape_string($_POST['payment_date']);
    $amount_paid = (float)$conn->real_escape_string($_POST['amount_paid']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $note = $conn->real_escape_string($_POST['note']);
    $balance_due = (float)$conn->real_escape_string($_POST['balance_due']);

    // 1. ตรวจสอบความถูกต้องของยอดเงิน
    if ($amount_paid <= 0) {
        $message = "❌ Error: ยอดเงินที่ชำระต้องมากกว่า 0 บาท";
    } elseif ($amount_paid > $balance_due + 0.01) { // เผื่อค่า Decimal น้อยๆ
        $message = "❌ Error: ยอดเงินที่ชำระ ($amount_paid) มากกว่ายอดค้างชำระ ($balance_due)";
    } else {
        // 2. INSERT ข้อมูลการชำระเงิน
        $sql_insert = "INSERT INTO payments (invoice_id, payment_date, amount_paid, payment_method, note)
                       VALUES ('$invoice_id', '$payment_date', '$amount_paid', '$payment_method', '$note')";
        
        if ($conn->query($sql_insert)) {
            
            // 3. อัปเดตสถานะใบแจ้งหนี้
            $new_balance = $balance_due - $amount_paid;
            $new_status = 'Partial'; // สถานะเริ่มต้น

            if (abs($new_balance) < 0.01) { // ถ้าเหลือศูนย์ (ชำระเต็มจำนวน)
                $new_status = 'Paid';
                $message_suffix = " (ชำระครบแล้ว)";
            } else {
                $new_status = 'Partial';
                $message_suffix = " (ยังค้างชำระ " . number_format($new_balance, 2) . " บาท)";
            }
            
            $sql_update_invoice = "UPDATE invoices SET status = '$new_status' WHERE invoice_id = '$invoice_id'";
            $conn->query($sql_update_invoice);

            $message = "✅ บันทึกการชำระเงินจำนวน " . number_format($amount_paid, 2) . " บาท เรียบร้อยแล้ว" . $message_suffix;
            
            // Redirect เพื่อล้างค่า POST และแสดงผลลัพธ์
            header("Location: add_payment.php?message=" . urlencode($message));
            exit();

        } else {
            $message = "❌ Error ในการบันทึกการชำระเงิน: " . $conn->error;
        }
    }
}

// ----------------------------------------------------
// C. การแสดงข้อความแจ้งเตือนที่มาจาก redirect
// ----------------------------------------------------
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกการชำระเงิน</title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 600px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="date"], .form-container input[type="number"], .form-container select, .form-container textarea { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .form-container input[type="submit"]:hover { background-color: #45a049; }
        .detail-box { border: 1px solid #f44336; padding: 15px; margin-top: 15px; border-radius: 4px; background-color: #ffe0e0; }
        .detail-box p { margin: 5px 0; font-size: 1.1em; }
        .balance-amount { color: #f44336; font-weight: bold; font-size: 1.2em; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2>💰 บันทึกการชำระเงิน</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>
        
        <form action="add_payment.php" method="POST">
            
            <label for="invoice_select">เลือกใบแจ้งหนี้ที่ต้องการชำระ:</label>
            <select id="invoice_select" name="invoice_id" required onchange="updateInvoiceDetails(this.value)">
                <option value="">-- เลือกใบแจ้งหนี้ค้างชำระ --</option>
                <?php foreach ($invoices_pending as $inv): ?>
                    <option 
                        value="<?php echo $inv['invoice_id']; ?>" 
                        data-total-amount="<?php echo $inv['total_amount']; ?>"
                        data-paid-amount="<?php echo $inv['total_paid']; ?>"
                        data-balance-due="<?php echo $inv['balance_due']; ?>"
                        data-room="<?php echo $inv['room_number']; ?>"
                        data-tenant="<?php echo $inv['first_name'] . ' ' . $inv['last_name']; ?>"
                    >
                        #<?php echo $inv['invoice_id']; ?> | ห้อง <?php echo $inv['room_number']; ?> | ค้าง <?php echo number_format($inv['balance_due'], 2); ?> ฿
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="hidden" id="balance_due_input" name="balance_due" value="0.00">

            <div class="detail-box" id="invoice_details" style="display: none;">
                <p><strong>ผู้เช่า:</strong> <span id="tenant_name">-</span> (ห้อง <span id="room_number">-</span>)</p>
                <p><strong>ยอดรวมใบแจ้งหนี้:</strong> <span id="total_amount_span">-</span> บาท</p>
                <p><strong>ชำระแล้ว:</strong> <span id="total_paid_span">-</span> บาท</p>
                <p><strong>ยอดค้างชำระ:</strong> <span id="balance_due_span" class="balance-amount">-</span> บาท</p>
            </div>

            <div style="display: flex; gap: 20px;">
                <div style="flex: 1;">
                    <label for="payment_date">วันที่ชำระเงิน:</label>
                    <input type="date" id="payment_date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div style="flex: 1;">
                    <label for="amount_paid">จำนวนเงินที่ชำระ:</label>
                    <input type="number" id="amount_paid" name="amount_paid" step="0.01" min="0.01" required placeholder="กรอกยอดเงิน">
                </div>
            </div>

            <label for="payment_method">วิธีการชำระเงิน:</label>
            <select id="payment_method" name="payment_method" required>
                <option value="Cash">เงินสด</option>
                <option value="Transfer">โอนธนาคาร</option>
                <option value="Other">อื่น ๆ</option>
            </select>

            <label for="note">บันทึก/หมายเหตุ:</label>
            <textarea id="note" name="note" rows="3"></textarea>

            <input type="submit" value="บันทึกการชำระเงิน">
        </form>
    </div>

    <script>
        const invoiceSelect = document.getElementById('invoice_select');
        const detailsBox = document.getElementById('invoice_details');
        const balanceDueInput = document.getElementById('balance_due_input');
        
        const tenantNameSpan = document.getElementById('tenant_name');
        const roomNumberSpan = document.getElementById('room_number');
        const totalAmountSpan = document.getElementById('total_amount_span');
        const totalPaidSpan = document.getElementById('total_paid_span');
        const balanceDueSpan = document.getElementById('balance_due_span');
        const amountPaidInput = document.getElementById('amount_paid');

        function updateInvoiceDetails(invoiceId) {
            const selectedOption = invoiceSelect.options[invoiceSelect.selectedIndex];
            
            if (invoiceId) {
                const total = parseFloat(selectedOption.getAttribute('data-total-amount'));
                const paid = parseFloat(selectedOption.getAttribute('data-paid-amount'));
                const balance = parseFloat(selectedOption.getAttribute('data-balance-due'));
                const room = selectedOption.getAttribute('data-room');
                const tenant = selectedOption.getAttribute('data-tenant');

                tenantNameSpan.textContent = tenant;
                roomNumberSpan.textContent = room;
                totalAmountSpan.textContent = total.toFixed(2);
                totalPaidSpan.textContent = paid.toFixed(2);
                balanceDueSpan.textContent = balance.toFixed(2);
                
                balanceDueInput.value = balance.toFixed(2);
                amountPaidInput.value = balance.toFixed(2); // ตั้งค่าเริ่มต้นให้ชำระเต็มจำนวน
                
                detailsBox.style.display = 'block';
                
            } else {
                detailsBox.style.display = 'none';
                balanceDueInput.value = '0.00';
            }
        }
        
        // โหลดรายละเอียดใบแจ้งหนี้เมื่อหน้าโหลด หากมีการเลือกไว้แล้ว
        updateInvoiceDetails(invoiceSelect.value);

    </script>
</body>
</html>