<?php
include 'config.php';
include 'header.php';

// **ข้อควรระวัง:** ไฟล์ config.php ต้องกำหนดค่าคงที่ DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME ไว้แล้ว

$message = "";

// ----------------------------------------------------
// 1. ดึงข้อมูลห้องพักและสถานะปัจจุบัน
// ----------------------------------------------------
$sql = "
    SELECT 
        r.room_id, r.room_number, r.current_status, r.monthly_rent,
        t.first_name, t.last_name,
        l.lease_id
    FROM rooms r
    LEFT JOIN tenants t ON r.current_tenant_id = t.tenant_id
    LEFT JOIN leases l ON r.room_id = l.room_id AND l.status = 'Active'
    ORDER BY r.room_number ASC";

$result = $conn->query($sql);

// ----------------------------------------------------
// 2. การจัดการฟอร์ม POST (สิ้นสุดสัญญา)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['end_lease'])) {
    $lease_id_to_end = $conn->real_escape_string($_POST['lease_id']);
    $room_id_to_vacant = $conn->real_escape_string($_POST['room_id']);

    // 1. อัปเดตสถานะสัญญาเป็น Expired และกำหนด end_date
    $sql_update_lease = "UPDATE leases SET status = 'Expired', end_date = CURDATE() WHERE lease_id = '$lease_id_to_end'";
    
    // 2. อัปเดตสถานะห้องเป็น Vacant และล้างข้อมูลผู้เช่าปัจจุบัน
    $sql_update_room = "UPDATE rooms SET current_status = 'Vacant', current_tenant_id = NULL, monthly_rent = NULL WHERE room_id = '$room_id_to_vacant'";

    if ($conn->query($sql_update_lease) && $conn->query($sql_update_room)) {
        $message = "✅ สิ้นสุดสัญญาเรียบร้อยแล้ว! ห้องว่างสำหรับผู้เช่ารายใหม่";
    } else {
        $message = "❌ Error ในการสิ้นสุดสัญญา: " . $conn->error;
    }
    // โหลดหน้าซ้ำเพื่อแสดงผลอัปเดต
    header("Location: room_management.php?message=" . urlencode($message));
    exit();
}

// ----------------------------------------------------
// 3. แสดงข้อความแจ้งเตือนที่มาจาก redirect
// ----------------------------------------------------
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}

// **ไม่ได้ปิดการเชื่อมต่อที่นี่**

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการห้องพัก</title>
    <?php echo $style_alerts; ?>
    <style>
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .table-rooms td, .table-rooms th { text-align: center; }
        .btn-edit, .btn-manage, .btn-start, .btn-invoice, .btn-meter, .btn-print { 
            padding: 5px 10px; margin: 2px; text-decoration: none; color: white; border-radius: 4px; display: inline-block; font-size: 0.9em; 
        }
        .btn-edit { background-color: #607d8b; } /* เทา */
        .btn-manage { background-color: #f44336; } /* แดง */
        .btn-start { background-color: #4CAF50; } /* เขียว */
        .btn-invoice { background-color: #00bcd4; } /* ฟ้า */
        .btn-meter { background-color: #ffc107; } /* เหลือง */
        .btn-print { background-color: #3f51b5; } /* น้ำเงิน */
        .status-vacant { color: #4CAF50; font-weight: bold; }
        .status-occupied { color: #f44336; font-weight: bold; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="container">
        <h2>🏨 ภาพรวมสถานะห้องพัก</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>

        <table class="table-rooms">
            <thead>
                <tr>
                    <th>ห้องที่</th>
                    <th>สถานะ</th>
                    <th>ผู้เช่าปัจจุบัน</th>
                    <th>ค่าเช่าต่อเดือน (฿)</th>
                    <th>การดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // ใช้ $conn ที่ถูก include มาจาก config.php โดยตรง
                // ไม่ต้องสร้างการเชื่อมต่อใหม่
                
                // $sql ถูกรันไปแล้วด้านบน และผลลัพธ์อยู่ใน $result
                if ($result->num_rows > 0): 
                    while ($row = $result->fetch_assoc()): 
                ?>
                    <tr>
                        <td><a href="edit_room.php?id=<?php echo $row['room_id']; ?>" style="color: blue; text-decoration: none;"><?php echo $row['room_number']; ?></a></td>
                        <td class="<?php echo ($row['current_status'] == 'Vacant' ? 'status-vacant' : 'status-occupied'); ?>">
                            <?php echo ($row['current_status'] == 'Vacant' ? 'ว่าง' : 'มีผู้เช่า'); ?>
                        </td>
                        <td><?php echo ($row['first_name'] ? $row['first_name'] . ' ' . $row['last_name'] : '-'); ?></td>
                        <td><?php echo ($row['monthly_rent'] ? number_format($row['monthly_rent'], 2) . ' ฿' : '-'); ?></td>
                        <td>
                            <a href='edit_room.php?id=<?php echo $row['room_id']; ?>' class='btn-edit'>แก้ไข</a> 

                            <?php if ($row['current_status'] == 'Occupied'): ?>
                                
                                <form method="POST" action="room_management.php" style="display: inline-block;">
                                    <input type="hidden" name="end_lease" value="1">
                                    <input type="hidden" name="lease_id" value="<?php echo $row['lease_id']; ?>">
                                    <input type="hidden" name="room_id" value="<?php echo $row['room_id']; ?>">
                                    <button type="submit" class='btn-manage' onclick="return confirm('คุณแน่ใจหรือไม่ที่จะสิ้นสุดสัญญานี้?');">สิ้นสุดสัญญา</button>
                                </form>
                                
                                <a href='create_invoice.php' class='btn-invoice'>ออกบิล</a> 
                                
                                <?php 
                                // **โค้ดสำหรับดึงใบแจ้งหนี้ล่าสุด (สำคัญสำหรับการพิมพ์บิล)**
                                $last_invoice_id = null;
                                // ต้องตรวจสอบว่ามี lease_id ก่อน
                                if ($row['lease_id']) {
                                    $sql_last_invoice = "SELECT invoice_id FROM invoices WHERE lease_id = '{$row['lease_id']}' ORDER BY issue_date DESC LIMIT 1";
                                    $result_last_invoice = $conn->query($sql_last_invoice);
                                    if ($result_last_invoice && $result_last_invoice->num_rows > 0) {
                                        $last_invoice_id = $result_last_invoice->fetch_assoc()['invoice_id'];
                                    }
                                }
                                
                                if ($last_invoice_id): 
                                ?>
                                    <a href='print_invoice.php?invoice_id=<?php echo $last_invoice_id; ?>' target='_blank' class='btn-print'>🖨️ พิมพ์บิล</a> 
                                <?php endif; ?>
                                
                                <a href='add_meter_reading.php' class='btn-meter'>จดมิเตอร์</a>

                            <?php else: // สถานะ Vacant ?>
                                <a href='create_lease.php?room_id=<?php echo $row['room_id']; ?>' class='btn-start'>+ เริ่มสัญญา</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php 
                    endwhile;
                else: 
                ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">ยังไม่มีห้องพักในระบบ กรุณาเพิ่มห้องใหม่</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php $conn->close(); ?> </div>
</body>
</html>