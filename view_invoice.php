<?php
include 'config.php';
include 'header.php';

$message = "";
$invoice_data = null;
$room_number = '';
$tenant_name = '';

if (isset($_GET['id'])) {
    $invoice_id = (int)$_GET['id'];

    // ดึงข้อมูลใบแจ้งหนี้ พร้อมข้อมูลห้องพักและผู้เช่า
    $sql = "SELECT 
                i.invoice_id, i.issue_date, i.due_date, i.total_amount, i.status,
                l.monthly_rent,
                r.room_number,
                t.first_name, t.last_name, t.phone, t.email
            FROM invoices i
            JOIN leases l ON i.lease_id = l.lease_id
            JOIN rooms r ON l.room_id = r.room_id
            JOIN tenants t ON l.tenant_id = t.tenant_id
            WHERE i.invoice_id = '$invoice_id'";

    $result = $conn->query($sql);

    if ($result && $result->num_rows == 1) {
        $invoice_data = $result->fetch_assoc();
        $room_number = $invoice_data['room_number'];
        $tenant_name = $invoice_data['first_name'] . ' ' . $invoice_data['last_name'];
    } else {
        $message = "❌ ไม่พบใบแจ้งหนี้ที่ระบุ";
    }
} else {
    $message = "⚠️ กรุณาระบุรหัสใบแจ้งหนี้";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบแจ้งหนี้ #<?php echo isset($invoice_data['invoice_id']) ? $invoice_data['invoice_id'] : ''; ?></title>
    <?php echo $style_alerts; ?>
    <style>
        .invoice-box { max-width: 800px; margin: 30px auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); font-size: 16px; line-height: 24px; font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; color: #555; background-color: white; }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; }
        .invoice-box table td { padding: 5px; vertical-align: top; }
        .invoice-box table tr td:nth-child(2) { text-align: right; }
        .invoice-box table tr.top table td { padding-bottom: 20px; }
        .invoice-box table tr.top table td.title { font-size: 45px; line-height: 45px; color: #333; }
        .invoice-box table tr.information table td { padding-bottom: 40px; }
        .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
        .invoice-box table tr.details td { padding-bottom: 20px; }
        .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
        .invoice-box table tr.item.last td { border-bottom: none; }
        .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
        .status-paid { color: #28a745; font-weight: bold; }
        .status-pending { color: #ff9800; font-weight: bold; }
    </style>
</head>
<body>
    <?php echo $nav_menu; ?>
    <div class="invoice-box">
        <?php if (!empty($message)): ?>
            <p class="message-error"><?php echo $message; ?></p>
        <?php elseif ($invoice_data): ?>
            <table cellpadding="0" cellspacing="0">
                <tr class="top">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td class="title">
                                    ใบแจ้งหนี้
                                </td>
                                <td>
                                    เลขที่บิล: **<?php echo $invoice_data['invoice_id']; ?>**<br>
                                    วันที่ออก: <?php echo date('d/m/Y', strtotime($invoice_data['issue_date'])); ?><br>
                                    กำหนดชำระ: <?php echo date('d/m/Y', strtotime($invoice_data['due_date'])); ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <tr class="information">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td>
                                    **ผู้เช่า:** <?php echo htmlspecialchars($tenant_name); ?><br>
                                    โทรศัพท์: <?php echo htmlspecialchars($invoice_data['phone']); ?><br>
                                    อีเมล: <?php echo htmlspecialchars($invoice_data['email']); ?>
                                </td>
                                
                                <td>
                                    **สำหรับห้อง:** <?php echo htmlspecialchars($room_number); ?><br>
                                    สถานะ: 
                                    <span class="status-<?php echo strtolower($invoice_data['status']); ?>">
                                        <?php echo ($invoice_data['status'] == 'Paid') ? 'ชำระแล้ว' : 'ค้างชำระ'; ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <tr class="heading">
                    <td>รายการ</td>
                    <td>ราคา</td>
                </tr>
                
                <tr class="item">
                    <td>ค่าเช่ารายเดือน</td>
                    <td><?php echo number_format($invoice_data['monthly_rent'], 2); ?> ฿</td>
                </tr>
                
                <tr class="total">
                    <td></td>
                    <td>
                       รวมทั้งสิ้น: **<?php echo number_format($invoice_data['total_amount'], 2); ?> ฿**
                    </td>
                </tr>
            </table>
            
            <p style="text-align: center; margin-top: 40px;">
                <?php if ($invoice_data['status'] != 'Paid'): ?>
                    <a href="update_payment.php?invoice_id=<?php echo $invoice_data['invoice_id']; ?>" class="action-button" style="background-color: #28a745; color: white;">บันทึกการชำระเงิน</a>
                <?php endif; ?>
            </p>

        <?php endif; ?>
    </div>
</body>
</html>