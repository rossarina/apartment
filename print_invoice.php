<?php
include 'config.php';

// ไม่ต้อง include header.php เพราะหน้านี้ไม่มีเมนู
$message = "";

// ตรวจสอบว่ามี invoice_id ส่งมาหรือไม่
if (!isset($_GET['invoice_id']) || !is_numeric($_GET['invoice_id'])) {
    die("❌ Error: ไม่พบรหัสใบแจ้งหนี้ (Invoice ID) ที่ถูกต้อง");
}

$invoice_id = $conn->real_escape_string($_GET['invoice_id']);
$invoice_data = null;
$payment_data = null;
$details_data = []; 

// ----------------------------------------------------
// A. ดึงข้อมูลใบแจ้งหนี้, สัญญา, ห้อง และผู้เช่า
// ----------------------------------------------------
$sql_invoice = "
    SELECT 
        i.invoice_id, i.issue_date, i.due_date, i.total_amount, i.status,
        r.room_number,
        t.first_name, t.last_name, t.phone, t.email,
        l.start_date
    FROM invoices i
    JOIN leases l ON i.lease_id = l.lease_id
    JOIN rooms r ON l.room_id = r.room_id
    JOIN tenants t ON l.tenant_id = t.tenant_id
    WHERE i.invoice_id = '$invoice_id'";
    
$result_invoice = $conn->query($sql_invoice);

if ($result_invoice && $result_invoice->num_rows > 0) {
    $invoice_data = $result_invoice->fetch_assoc();
    
    // ----------------------------------------------------
    // B. ดึงข้อมูลการชำระเงินที่เกี่ยวข้อง
    // ----------------------------------------------------
    $sql_payment = "SELECT SUM(amount_paid) AS total_paid FROM payments WHERE invoice_id = '$invoice_id'";
    $result_payment = $conn->query($sql_payment);
    if ($result_payment && $result_payment->num_rows > 0) {
        $payment_data = $result_payment->fetch_assoc();
    }
    
    // ----------------------------------------------------
    // C. ดึงรายละเอียดรายการจากตาราง invoice_details
    // ----------------------------------------------------
    // item_type DESC เพื่อให้ Rent, Electric, Water ขึ้นก่อน Other, Fine
    $sql_details = "SELECT item_description, item_amount, item_type FROM invoice_details WHERE invoice_id = '$invoice_id' ORDER BY item_type DESC"; 
    $result_details = $conn->query($sql_details);
    if ($result_details && $result_details->num_rows > 0) {
        while ($row = $result_details->fetch_assoc()) {
            $details_data[] = $row;
        }
    }
    
} else {
    die("❌ Error: ไม่พบใบแจ้งหนี้ตามรหัสที่ระบุ");
}

$conn->close();

// คำนวณยอดคงเหลือ
$total_paid = $payment_data['total_paid'] ?? 0;
$balance = $invoice_data['total_amount'] - $total_paid;

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบแจ้งหนี้ #<?php echo $invoice_data['invoice_id']; ?></title>
    <style>
        body { font-family: 'TH Sarabun New', Arial, sans-serif; font-size: 16pt; margin: 0; padding: 0; background-color: #f4f4f4; }
        .invoice-box { width: 800px; margin: 50px auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); font-size: 10pt; line-height: 18pt; color: #555; background: white; }
        .header-title { font-size: 24pt; font-weight: bold; color: #333; margin-bottom: 5px; }
        .sub-header { font-size: 16pt; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details-table td { padding: 5px; vertical-align: top; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .item-table th, .item-table td { border: 1px solid #eee; padding: 8px; text-align: left; }
        .item-table th { background-color: #f2f2f2; }
        .total-row td { font-weight: bold; background-color: #f9f9f9; }
        .balance-row td { background-color: #f7e6e6; color: #f44336; }
        .paid-row td { background-color: #e6ffe6; color: #4CAF50; }
        .text-right { text-align: right; }
        .note { margin-top: 30px; font-size: 10pt; border-top: 1px dashed #ccc; padding-top: 10px; }

        @media print {
            body { background: white; }
            .invoice-box { border: none; box-shadow: none; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="invoice-box">
        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print()" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">🖨️ พิมพ์เอกสาร</button>
            <a href="dashboard.php" style="padding: 10px 20px; background-color: #6c757d; color: white; border-radius: 5px; text-decoration: none;">กลับหน้าหลัก</a>
        </div>

        <div class="header-title">ใบแจ้งหนี้ค่าเช่า (INVOICE)</div>
        <div class="sub-header">หอพัก/อพาร์ตเมนต์ (ชื่อสถานที่ของคุณ)</div>

        <table class="details-table">
            <tr>
                <td style="width: 50%;">
                    <strong>สำหรับ (ผู้เช่า):</strong><br>
                    ชื่อ: <?php echo $invoice_data['first_name'] . ' ' . $invoice_data['last_name']; ?><br>
                    ห้อง: <?php echo $invoice_data['room_number']; ?><br>
                    เบอร์โทร: <?php echo $invoice_data['phone']; ?>
                </td>
                <td style="width: 50%; text-align: right;">
                    <strong>เลขที่ใบแจ้งหนี้:</strong> #<?php echo $invoice_data['invoice_id']; ?><br>
                    วันที่ออก: <?php echo date('d/m/Y', strtotime($invoice_data['issue_date'])); ?><br>
                    วันครบกำหนด: <span style="font-weight: bold; color: #f44336;"><?php echo date('d/m/Y', strtotime($invoice_data['due_date'])); ?></span>
                </td>
            </tr>
        </table>

        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 70%;">รายละเอียด</th>
                    <th class="text-right">จำนวนเงิน (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 1;
                // วนลูปแสดงรายการจาก invoice_details
                foreach ($details_data as $item): 
                ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo $item['item_description']; ?></td>
                    <td class="text-right"><?php echo number_format($item['item_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <tr class="total-row">
                    <td colspan="2" class="text-right">ยอดรวมใบแจ้งหนี้ (Total Due):</td>
                    <td class="text-right"><?php echo number_format($invoice_data['total_amount'], 2); ?></td>
                </tr>

                <?php if ($total_paid > 0): ?>
                    <tr class="paid-row">
                        <td colspan="2" class="text-right">ชำระแล้ว (Paid Amount):</td>
                        <td class="text-right"><?php echo number_format($total_paid, 2); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($balance != 0): ?>
                    <tr class="balance-row">
                        <td colspan="2" class="text-right">ยอดคงเหลือ/ค้างชำระ (Balance Due):</td>
                        <td class="text-right"><?php echo number_format($balance, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="note">
            **หมายเหตุ:** โปรดชำระภายในวันครบกำหนดเพื่อหลีกเลี่ยงค่าปรับ<br>
            สถานะใบแจ้งหนี้: <span style="font-weight: bold; color: <?php echo ($invoice_data['status'] == 'Paid') ? '#4CAF50' : '#f44336'; ?>;"><?php echo $invoice_data['status']; ?></span>
        </div>
        
    </div>
</body>
</html>