<?php
include 'config.php';
include 'header.php';

$alerts = [];
$today = date('Y-m-d');
$soon_due_date = date('Y-m-d', strtotime('+7 days')); // ภายใน 7 วันข้างหน้า
$soon_expiry_date = date('Y-m-d', strtotime('+30 days')); // ภายใน 30 วันข้างหน้า

// ----------------------------------------------------
// A. การแจ้งเตือนสัญญาเช่าใกล้หมดอายุ
// ----------------------------------------------------
$sql_expiry = "
    SELECT 
        l.lease_id, l.end_date, r.room_number, t.first_name, t.last_name, t.phone
    FROM leases l
    JOIN rooms r ON l.room_id = r.room_id
    JOIN tenants t ON l.tenant_id = t.tenant_id
    WHERE l.status = 'Active' 
      AND l.end_date IS NOT NULL
      AND l.end_date BETWEEN '$today' AND '$soon_expiry_date'
    ORDER BY l.end_date ASC";

$result_expiry = $conn->query($sql_expiry);

if ($result_expiry && $result_expiry->num_rows > 0) {
    while ($row = $result_expiry->fetch_assoc()) {
        $expiry_date_th = date('d/m/Y', strtotime($row['end_date']));
        $alerts['expiry'][] = [
            'type' => 'สัญญาใกล้หมดอายุ',
            'room' => $row['room_number'],
            'tenant' => $row['first_name'] . ' ' . $row['last_name'],
            'detail' => "จะหมดอายุในวันที่ **$expiry_date_th** (ภายใน 30 วัน) เบอร์: {$row['phone']}",
            'action_link' => "tenant_management.php?lease_id={$row['lease_id']}",
            'action_text' => 'จัดการสัญญา',
            'status_class' => 'alert-warning'
        ];
    }
}

// ----------------------------------------------------
// B. การแจ้งเตือนใบแจ้งหนี้ใกล้ถึงกำหนดชำระ
// ----------------------------------------------------
$sql_due = "
    SELECT 
        i.invoice_id, i.due_date, i.total_amount, r.room_number, t.first_name, t.last_name
    FROM invoices i
    JOIN leases l ON i.lease_id = l.lease_id
    JOIN rooms r ON l.room_id = r.room_id
    JOIN tenants t ON l.tenant_id = t.tenant_id
    WHERE i.status = 'Pending' 
      AND i.due_date BETWEEN '$today' AND '$soon_due_date'
    ORDER BY i.due_date ASC";

$result_due = $conn->query($sql_due);

if ($result_due && $result_due->num_rows > 0) {
    while ($row = $result_due->fetch_assoc()) {
        $due_date_th = date('d/m/Y', strtotime($row['due_date']));
        $alerts['due'][] = [
            'type' => 'ใบแจ้งหนี้ใกล้ถึงกำหนด',
            'room' => $row['room_number'],
            'tenant' => $row['first_name'] . ' ' . $row['last_name'],
            'detail' => "ยอด {$row['total_amount']} บาท ครบกำหนดวันที่ **$due_date_th** (ภายใน 7 วัน)",
            'action_link' => "update_payment.php?invoice_id={$row['invoice_id']}",
            'action_text' => 'บันทึกชำระเงิน',
            'status_class' => 'alert-info'
        ];
    }
}

$conn->close();

$total_alerts = count($alerts['expiry'] ?? []) + count($alerts['due'] ?? []);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ศูนย์แจ้งเตือนและข้อเตือนใจ</title>
    <?php echo $style_alerts; ?>
    <style>
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .alert-item { padding: 15px; border-radius: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .alert-item strong { font-size: 1.1em; }
        
        /* สไตล์ตามประเภทการแจ้งเตือน */
        .alert-warning { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; } /* สัญญาหมดอายุ */
        .alert-info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; } /* ใบแจ้งหนี้ใกล้ครบกำหนด */
        
        .alert-action a { padding: 5px 10px; border-radius: 4px; text-decoration: none; font-weight: bold; }
        .alert-warning .alert-action a { background-color: #ffc107; color: #333; }
        .alert-info .alert-action a { background-color: #17a2b8; color: white; }
        
        .no-alerts { text-align: center; padding: 30px; background-color: #e9f7ef; border: 1px solid #d4edda; color: #155724; border-radius: 6px; font-weight: bold; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="container">
        <h2>🔔 ศูนย์แจ้งเตือนและข้อเตือนใจ (Alerts Dashboard)</h2>
        <p>คุณมีการแจ้งเตือนที่ต้องดำเนินการทั้งหมด **<?php echo $total_alerts; ?>** รายการ</p>
        <hr>

        <?php if ($total_alerts == 0): ?>
            <div class="no-alerts">
                <p>🟢 ยอดเยี่ยม! ไม่มีรายการแจ้งเตือนที่เร่งด่วนในขณะนี้</p>
            </div>
        <?php else: ?>

            <?php if (!empty($alerts['expiry'])): ?>
                <h3>⚠️ สัญญาเช่าใกล้หมดอายุ (<?php echo count($alerts['expiry']); ?> รายการ)</h3>
                <?php foreach ($alerts['expiry'] as $alert): ?>
                    <div class="alert-item <?php echo $alert['status_class']; ?>">
                        <div>
                            <strong>ห้อง <?php echo $alert['room']; ?></strong> &mdash; 
                            <?php echo $alert['tenant']; ?>: 
                            <?php echo $alert['detail']; ?>
                        </div>
                        <div class="alert-action">
                            <a href="<?php echo $alert['action_link']; ?>"><?php echo $alert['action_text']; ?></a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <hr>
            <?php endif; ?>

            <?php if (!empty($alerts['due'])): ?>
                <h3>🕒 ใบแจ้งหนี้ใกล้ถึงกำหนดชำระ (<?php echo count($alerts['due']); ?> รายการ)</h3>
                <?php foreach ($alerts['due'] as $alert): ?>
                    <div class="alert-item <?php echo $alert['status_class']; ?>">
                        <div>
                            <strong>ใบแจ้งหนี้ ห้อง <?php echo $alert['room']; ?></strong> &mdash; 
                            <?php echo $alert['tenant']; ?>: 
                            <?php echo $alert['detail']; ?>
                        </div>
                        <div class="alert-action">
                            <a href="<?php echo $alert['action_link']; ?>"><?php echo $alert['action_text']; ?></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>