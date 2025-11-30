<?php
include 'config.php';
include 'header.php';

$message = "";
$lease_id = 0;
$lease_data = null;
$result_invoices = null;

if (isset($_GET['id'])) {
    $lease_id = (int)$_GET['id'];
    
    // ดึงข้อมูลหลักของสัญญาเช่า, ห้องพัก, และผู้เช่า
    $sql_lease_details = "SELECT 
                            l.lease_id, l.room_id, l.tenant_id, l.start_date, l.end_date, l.monthly_rent,
                            r.room_number, r.floor, 
                            t.first_name, t.last_name, t.phone, t.email
                          FROM leases l
                          JOIN rooms r ON l.room_id = r.room_id
                          JOIN tenants t ON l.tenant_id = t.tenant_id
                          WHERE l.lease_id = '$lease_id'";
                          
    $result_details = $conn->query($sql_lease_details);

    if ($result_details && $result_details->num_rows == 1) {
        $lease_data = $result_details->fetch_assoc();
        
        // ดึงข้อมูลมิเตอร์ล่าสุด (ถ้ามี) - สมมติว่ามีคอลัมน์ water_unit, electric_unit ใน meter_readings
        $sql_meter = "SELECT water_unit AS water_reading, electric_unit AS electric_reading, reading_date 
                      FROM meter_readings 
                      WHERE room_id = '{$lease_data['room_id']}' 
                      ORDER BY reading_date DESC LIMIT 1";
        $result_meter = $conn->query($sql_meter);
        $meter_data = $result_meter && $result_meter->num_rows > 0 ? $result_meter->fetch_assoc() : null;
        
        // ดึงข้อมูลใบแจ้งหนี้ทั้งหมด (Invoice)
        $sql_invoices = "SELECT 
                           invoice_id, issue_date, due_date, total_amount, status 
                         FROM invoices 
                         WHERE lease_id = '$lease_id' 
                         ORDER BY issue_date DESC";
        $result_invoices = $conn->query($sql_invoices);
        
    } else {
        $message = "❌ ไม่พบข้อมูลสัญญาเช่าที่ระบุ";
        $lease_id = 0;
    }
} else {
    $message = "⚠️ กรุณาระบุรหัสสัญญาเช่า (Lease ID)";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดสัญญาเช่า #<?php echo $lease_id; ?></title>
    <?php echo $style_alerts; ?>
    <style>
        .detail-container { max-width: 1200px; margin: 30px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-top: 30px; }
        .grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .card { padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .card h3 { color: #007bff; margin-top: 0; }
        .info-pair { margin: 8px 0; }
        .info-pair strong { display: inline-block; width: 120px; }
        .action-buttons { margin-top: 20px; }
        .action-buttons a { padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px; }
        
        .invoice-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .invoice-table th, .invoice-table td { border: 1px solid #eee; padding: 10px; text-align: left; font-size: 14px; }
        .invoice-table th { background-color: #f8f9fa; }
        .paid { color: #28a745; font-weight: bold; }
        .unpaid { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="detail-container">
        <h1>รายละเอียดสัญญาเช่า #<?php echo $lease_id; ?></h1>
        
        <?php
        if (!empty($message)) {
            $style_class = (strpos($message, '❌') !== false) ? 'message-error' : 'message-warning';
            echo "<p class='$style_class'>$message</p>";
        }
        
        if ($lease_data): 
            $is_active = is_null($lease_data['end_date']);
        ?>
        
        <div class="action-buttons">
            <?php if ($is_active): ?>
                <span style="font-size: 1.2em; margin-right: 20px; padding: 5px 10px; border-radius: 4px; background-color: #e2f0d9; color: #28a745;">สถานะ: สัญญาใช้งานอยู่</span>
                
                <a href="end_lease.php?id=<?php echo $lease_data['lease_id']; ?>" class="action-button" style="background-color: #dc3545; color: white;">สิ้นสุดสัญญา</a>
                <a href="create_invoice.php?lease_id=<?php echo $lease_data['lease_id']; ?>" class="action-button" style="background-color: #007bff; color: white;">ออกบิลใหม่</a>
                <a href="add_meter_reading.php?room_id=<?php echo $lease_data['room_id']; ?>" class="action-button" style="background-color: #ffc107; color: #333;">จดมิเตอร์</a>
            <?php else: ?>
                 <span style="font-size: 1.2em; margin-right: 20px; padding: 5px 10px; border-radius: 4px; background-color: #f2f2f2; color: #6c757d;">สถานะ: สิ้นสุดสัญญาแล้ว (เมื่อ <?php echo date('d/m/Y', strtotime($lease_data['end_date'])); ?>)</span>
            <?php endif; ?>
        </div>
        
        <hr>
        
        <div class="grid-layout">
            <div class="card">
                <h3>🏠 ข้อมูลห้องพักและสัญญา</h3>
                <div class="info-pair"><strong>เลขห้อง:</strong> <?php echo htmlspecialchars($lease_data['room_number']); ?></div>
                <div class="info-pair"><strong>ชั้น:</strong> <?php echo htmlspecialchars($lease_data['floor']); ?></div>
                <div class="info-pair"><strong>ค่าเช่าต่อเดือน:</strong> <?php echo number_format($lease_data['monthly_rent'], 2); ?> บาท</div>
                <div class="info-pair"><strong>เริ่มต้นสัญญา:</strong> <?php echo date('d/m/Y', strtotime($lease_data['start_date'])); ?></div>
                <?php if (!$is_active): ?>
                    <div class="info-pair"><strong>สิ้นสุดสัญญา:</strong> <?php echo date('d/m/Y', strtotime($lease_data['end_date'])); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>👤 ข้อมูลผู้เช่า</h3>
                <div class="info-pair"><strong>ชื่อ-นามสกุล:</strong> <?php echo htmlspecialchars($lease_data['first_name'] . ' ' . $lease_data['last_name']); ?></div>
                <div class="info-pair"><strong>เบอร์โทร:</strong> <?php echo htmlspecialchars($lease_data['phone']); ?></div>
                <div class="info-pair"><strong>อีเมล:</strong> <?php echo htmlspecialchars($lease_data['email']); ?></div>
                <div class="info-pair"><a href="add_tenant.php?id=<?php echo $lease_data['tenant_id']; ?>">แก้ไขข้อมูลผู้เช่า</a></div>
                
                <?php if ($meter_data): ?>
                    <hr>
                    <h3 style="color: #333;">💡 มิเตอร์ล่าสุด</h3>
                    <div class="info-pair"><strong>น้ำ:</strong> <?php echo number_format($meter_data['water_reading']); ?> หน่วย</div>
                    <div class="info-pair"><strong>ไฟ:</strong> <?php echo number_format($meter_data['electric_reading']); ?> หน่วย</div>
                    <div class="info-pair"><strong>วันที่จด:</strong> <?php echo date('d/m/Y', strtotime($meter_data['reading_date'])); ?></div>
                <?php else: ?>
                    <p style="color: gray;">ยังไม่มีการจดมิเตอร์สำหรับห้องนี้</p>
                <?php endif; ?>
            </div>
        </div>
        
        <hr>

        <h2>🧾 ประวัติใบแจ้งหนี้และการชำระเงิน</h2>
        
        <?php if ($result_invoices && $result_invoices->num_rows > 0): ?>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>เลขที่บิล</th>
                        <th>วันที่ออกบิล</th>
                        <th>กำหนดชำระ</th>
                        <th>ยอดรวม</th>
                        <th>สถานะ</th>
                        <th>การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($invoice = $result_invoices->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $invoice['invoice_id']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                            <td><?php echo number_format($invoice['total_amount'], 2); ?></td>
                            <td>
                                <span class="<?php echo ($invoice['status'] == 'Paid') ? 'paid' : 'unpaid'; ?>">
                                    <?php echo ($invoice['status'] == 'Paid') ? 'ชำระแล้ว' : 'ค้างชำระ'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="action-button" style="background-color: #17a2b8; color: white;">ดูบิล</a>
                                <?php if ($invoice['status'] != 'Paid'): ?>
                                    <a href="update_payment.php?invoice_id=<?php echo $invoice['invoice_id']; ?>" class="action-button" style="background-color: #28a745; color: white;">บันทึกชำระ</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: gray;">ยังไม่มีใบแจ้งหนี้สำหรับสัญญาเช่านี้</p>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>