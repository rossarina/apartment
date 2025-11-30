<?php
include 'config.php';
include 'header.php';

$message = "";
$arrears_data = [];
$total_arrears_amount = 0;
$today = date('Y-m-d');

// ----------------------------------------------------
// 1. ดึงข้อมูลใบแจ้งหนี้ที่ค้างชำระ (Pending และ Due Date น้อยกว่าวันนี้)
// ----------------------------------------------------

$sql_arrears = "
    SELECT 
        i.invoice_id, i.issue_date, i.due_date, i.total_amount,
        r.room_number,
        t.first_name, t.last_name, t.phone
    FROM invoices i
    JOIN leases l ON i.lease_id = l.lease_id
    JOIN rooms r ON l.room_id = r.room_id
    JOIN tenants t ON l.tenant_id = t.tenant_id
    WHERE i.status = 'Pending' 
      AND i.due_date < '$today'
    ORDER BY i.due_date ASC";

$result_arrears = $conn->query($sql_arrears);

if ($result_arrears && $result_arrears->num_rows > 0) {
    while ($row = $result_arrears->fetch_assoc()) {
        
        // คำนวณจำนวนวันค้างชำระ
        $due_date_ts = strtotime($row['due_date']);
        $today_ts = strtotime($today);
        $diff_seconds = $today_ts - $due_date_ts;
        $days_overdue = floor($diff_seconds / (60 * 60 * 24));
        
        $row['days_overdue'] = $days_overdue;
        $arrears_data[] = $row;
        $total_arrears_amount += $row['total_amount'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสถานะค้างชำระ</title>
    <?php echo $style_alerts; ?>
    <style>
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .summary-box { background-color: #ffe6e6; border: 2px solid #f44336; padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .summary-box h3 { color: #cc0000; margin-top: 0; }
        .summary-box p { font-size: 1.2em; font-weight: bold; }
        .overdue-cell { color: red; font-weight: bold; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="container">
        <h2>⚠️ รายงานสถานะค้างชำระ (Arrears Report)</h2>
        <p>รายงานนี้แสดงเฉพาะใบแจ้งหนี้ที่มีสถานะ **รอชำระ (Pending)** และเลยวันครบกำหนดชำระแล้ว (<?php echo date('d/m/Y'); ?>)</p>

        <div class="summary-box">
            <h3>สรุปยอดค้างชำระ ณ วันนี้</h3>
            <p>ยอดค้างชำระรวมทั้งหมด: <?php echo number_format($total_arrears_amount, 2); ?> บาท</p>
            <p>จำนวนรายการค้างชำระ: <?php echo count($arrears_data); ?> รายการ</p>
        </div>

        <h3>รายละเอียดรายการค้างชำระ</h3>
        <table>
            <thead>
                <tr>
                    <th>ใบแจ้งหนี้ #</th>
                    <th>ห้องที่</th>
                    <th>ผู้เช่า</th>
                    <th>ยอดค้างชำระ (฿)</th>
                    <th>วันครบกำหนด</th>
                    <th>จำนวนวันค้าง</th>
                    <th>เบอร์โทร</th>
                    <th>การดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($arrears_data)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: green; font-weight: bold;">ยอดเยี่ยม! ไม่มีรายการค้างชำระในขณะนี้</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($arrears_data as $data): ?>
                        <tr>
                            <td>#<?php echo $data['invoice_id']; ?></td>
                            <td><?php echo $data['room_number']; ?></td>
                            <td><?php echo $data['first_name'] . ' ' . $data['last_name']; ?></td>
                            <td class="overdue-cell"><?php echo number_format($data['total_amount'], 2); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($data['due_date'])); ?></td>
                            <td class="overdue-cell"><?php echo $data['days_overdue']; ?> วัน</td>
                            <td><?php echo $data['phone']; ?></td>
                            <td>
                                <a href="update_payment.php?invoice_id=<?php echo $data['invoice_id']; ?>" style="color: blue; text-decoration: none;">บันทึกชำระ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right" style="font-weight: bold;">ยอดรวมทั้งหมด:</td>
                    <td style="font-weight: bold; color: red;"><?php echo number_format($total_arrears_amount, 2); ?></td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>