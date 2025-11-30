<?php
include 'config.php';
include 'header.php';

$message = "";

// --- 1. การจัดการสิ้นสุดสัญญา (Action: end_lease) ---
if (isset($_GET['action']) && $_GET['action'] == 'end_lease' && isset($_GET['lease_id'])) {
    $lease_id = $conn->real_escape_string($_GET['lease_id']);

    // 1. ดึงข้อมูล room_id ที่เกี่ยวข้องกับสัญญาเช่านี้
    $sql_get_room = "SELECT room_id FROM leases WHERE lease_id = '$lease_id'";
    $result_get_room = $conn->query($sql_get_room);
    
    if ($result_get_room && $result_get_room->num_rows > 0) {
        $room_data = $result_get_room->fetch_assoc();
        $room_id = $room_data['room_id'];

        // 2. อัปเดตสถานะสัญญาเช่าเป็น Expired และกำหนด end_date เป็นวันนี้
        $sql_end_lease = "UPDATE leases SET status = 'Expired', end_date = CURDATE() WHERE lease_id = '$lease_id'";
        
        // 3. อัปเดตสถานะห้องพัก: ตั้งเป็น 'Vacant' และล้างข้อมูลผู้เช่าปัจจุบัน
        $sql_update_room = "UPDATE rooms SET current_status = 'Vacant', current_tenant_id = NULL, monthly_rent = NULL WHERE room_id = '$room_id'";

        if ($conn->query($sql_end_lease) && $conn->query($sql_update_room)) {
            $message = "✅ สิ้นสุดสัญญาเช่าเรียบร้อยแล้ว สถานะห้องพักถูกเปลี่ยนเป็น 'ว่าง' และล้างข้อมูลผู้เช่าแล้ว";
        } else {
            $message = "❌ Error ในการสิ้นสุดสัญญาหรืออัปเดตห้องพัก: " . $conn->error;
        }
    } else {
        $message = "❌ ไม่พบข้อมูลสัญญาเช่าที่ต้องการสิ้นสุด";
    }
}


// --- 2. ดึงข้อมูลสถานะห้องพักทั้งหมด (รวมชื่อผู้เช่าปัจจุบัน) ---
$sql_rooms = "SELECT 
                r.room_id, r.room_number, r.floor, r.current_status, r.monthly_rent,
                t.first_name, t.last_name,
                l.lease_id, l.status AS lease_status
              FROM rooms r
              LEFT JOIN tenants t ON r.current_tenant_id = t.tenant_id
              LEFT JOIN leases l ON r.room_id = l.room_id AND l.status = 'Active'
              ORDER BY r.floor ASC, r.room_number ASC";

$result_rooms = $conn->query($sql_rooms);
$rooms = [];
if ($result_rooms && $result_rooms->num_rows > 0) {
    while($row = $result_rooms->fetch_assoc()) {
        $rooms[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการห้องพัก</title>
    <?php echo $style_alerts; ?>
    <style>
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .action-btn { padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; color: white; display: inline-block; margin: 2px 0; }
        .edit-btn { background-color: #007bff; }
        .end-lease-btn { background-color: #dc3545; }
        .start-lease-btn { background-color: #28a745; }
        .invoice-btn { background-color: #ffc107; color: #333; }
        .meter-btn { background-color: #17a2b8; }
        .vacant-status { color: green; font-weight: bold; }
        .occupied-status { color: red; font-weight: bold; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="container">
        <h2>🧺 ภาพรวมสถานะห้องพัก</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>

        <table>
            <thead>
                <tr>
                    <th>ห้องที่</th>
                    <th>ชั้นที่</th>
                    <th>สถานะ</th>
                    <th>ผู้เช่าปัจจุบัน</th>
                    <th>ค่าเช่า/เดือน</th>
                    <th>การดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rooms)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">ยังไม่มีข้อมูลห้องพักในระบบ กรุณาเพิ่มห้องใหม่</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td><a href="edit_room.php?id=<?php echo $room['room_id']; ?>" style="text-decoration: none; font-weight: bold;"><?php echo $room['room_number']; ?></a></td>
                            <td><?php echo $room['floor']; ?></td>
                            <td class="<?php echo ($room['current_status'] == 'Vacant' ? 'vacant-status' : 'occupied-status'); ?>">
                                <?php 
                                    if ($room['current_status'] == 'Vacant') echo 'ว่าง';
                                    elseif ($room['current_status'] == 'Occupied') echo 'ไม่ว่าง';
                                    else echo 'ซ่อมบำรุง';
                                ?>
                            </td>
                            <td><?php echo $room['first_name'] ? $room['first_name'] . ' ' . $room['last_name'] : '-'; ?></td>
                            <td><?php echo $room['monthly_rent'] ? number_format($room['monthly_rent'], 2) . ' ฿' : '-'; ?></td>
                            <td>
                                <a href="edit_room.php?id=<?php echo $room['room_id']; ?>" class="action-btn edit-btn">แก้ไข</a>
                                
                                <?php if ($room['current_status'] == 'Occupied' && $room['lease_id']): ?>
                                    <a href="room_management.php?action=end_lease&lease_id=<?php echo $room['lease_id']; ?>" 
                                       onclick="return confirm('ยืนยันที่จะสิ้นสุดสัญญานี้และเปลี่ยนสถานะห้องเป็นว่างหรือไม่?')"
                                       class="action-btn end-lease-btn">สิ้นสุดสัญญา</a>
                                    <a href="create_invoice.php?lease_id=<?php echo $room['lease_id']; ?>" class="action-btn invoice-btn">ออกบิล</a>
                                    <a href="add_meter_reading.php?room_id=<?php echo $room['room_id']; ?>" class="action-btn meter-btn">จดมิเตอร์</a>
                                <?php else: ?>
                                    <a href="create_lease.php?room_id=<?php echo $room['room_id']; ?>" class="action-btn start-lease-btn">+ เริ่มสัญญา</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>