<?php
include 'config.php'; 
include 'header.php'; 

$message = ""; 
$lease_id = 0;
$room_info = null;

// --- A. ดึงข้อมูลสัญญาเช่าและห้องพักที่เกี่ยวข้อง ---
if (isset($_GET['id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $lease_id = (int)$_GET['id'];
    
    $sql_fetch = "SELECT 
                    l.lease_id, l.room_id, l.start_date, l.monthly_rent,
                    r.room_number, t.first_name, t.last_name
                  FROM leases l
                  JOIN rooms r ON l.room_id = r.room_id
                  JOIN tenants t ON l.tenant_id = t.tenant_id
                  WHERE l.lease_id = '$lease_id' AND l.end_date IS NULL";
                  
    $result_fetch = $conn->query($sql_fetch);

    if ($result_fetch && $result_fetch->num_rows == 1) {
        $room_info = $result_fetch->fetch_assoc();
    } else {
        $message = "❌ Error: ไม่พบสัญญาเช่าที่กำลังใช้งานอยู่ หรือสัญญานี้ถูกสิ้นสุดไปแล้ว";
        $lease_id = 0;
    }
}


// --- B. การจัดการฟอร์ม POST (บันทึกวันที่สิ้นสุดและอัปเดตสถานะห้อง) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $lease_id_post = $conn->real_escape_string($_POST['lease_id']);
    $room_id_post = $conn->real_escape_string($_POST['room_id']);
    $end_date_post = $conn->real_escape_string($_POST['end_date']);
    
    $conn->begin_transaction();
    $success = true;

    // 1. UPDATE วันที่สิ้นสุดสัญญาในตาราง leases
    $sql_update_lease = "UPDATE leases SET end_date = '$end_date_post' WHERE lease_id = '$lease_id_post'";
    if (!$conn->query($sql_update_lease)) {
        $success = false;
        $message = "❌ Error ในการบันทึกวันสิ้นสุดสัญญา: " . $conn->error;
    }
    
    // 2. UPDATE สถานะห้องในตาราง rooms ให้เป็น 'Vacant'
    if ($success) {
        $sql_update_room = "UPDATE rooms SET current_status = 'Vacant' WHERE room_id = '$room_id_post'";
        if (!$conn->query($sql_update_room)) {
            $success = false;
            $message = "❌ Error ในการอัปเดตสถานะห้อง: " . $conn->error;
        }
    }
    
    // 3. สรุปผล Transaction
    if ($success) {
        $conn->commit();
        header("Location: room_management.php?status=end_lease_success"); 
        exit();
    } else {
        $conn->rollback();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สิ้นสุดสัญญาเช่า</title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 500px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="date"] { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #dc3545; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .form-container input[type="submit"]:hover { background-color: #c82333; }
        .lease-info p { margin: 5px 0; padding: 8px; background-color: #f8f9fa; border-radius: 4px; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2>❌ สิ้นสุดสัญญาเช่า</h2>
        
        <?php
        if (!empty($message)) {
            $style_class = (strpos($message, '❌') !== false) ? 'message-error' : 'message-warning';
            echo "<p class='$style_class'>$message</p>";
        }
        ?>

        <?php if ($lease_id > 0 && $room_info): ?>
            
            <div class="lease-info">
                <p><strong>ห้องที่:</strong> <?php echo $room_info['room_number']; ?></p>
                <p><strong>ผู้เช่า:</strong> <?php echo $room_info['first_name'] . ' ' . $room_info['last_name']; ?></p>
                <p><strong>เริ่มต้นสัญญา:</strong> <?php echo date('d/m/Y', strtotime($room_info['start_date'])); ?></p>
                <p><strong>ค่าเช่า:</strong> <?php echo number_format($room_info['monthly_rent'], 2); ?> บาท</p>
            </div>
            <hr>

            <form action="end_lease.php" method="POST">
                
                <input type="hidden" name="lease_id" value="<?php echo $lease_id; ?>">
                <input type="hidden" name="room_id" value="<?php echo $room_info['room_id']; ?>">
                
                <label for="end_date">วันที่สิ้นสุดสัญญา (วันที่ผู้เช่าย้ายออก):</label><br>
                <input type="date" id="end_date" name="end_date" required value="<?php echo date('Y-m-d'); ?>"><br><br>

                <p style="color: red; font-weight: bold;">⚠️ การดำเนินการนี้จะเปลี่ยนสถานะห้องให้เป็น "ว่าง" ทันที</p>
                <input type="submit" value="ยืนยันการสิ้นสุดสัญญา">
            </form>
        <?php endif; ?>
    </div>
</body>
</html>