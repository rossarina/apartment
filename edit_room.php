<?php
include 'config.php';
include 'header.php'; // ใช้ $conn จาก header.php

$message = "";
$room_data = null;
$room_id = 0;

// ----------------------------------------------------
// A. การดึงข้อมูลห้องพักปัจจุบัน
// ----------------------------------------------------
if (isset($_GET['id'])) {
    $room_id = $conn->real_escape_string($_GET['id']);
    
    $sql_fetch = "SELECT * FROM rooms WHERE room_id = '$room_id'";
    $result_fetch = $conn->query($sql_fetch);

    if ($result_fetch && $result_fetch->num_rows > 0) {
        $room_data = $result_fetch->fetch_assoc();
    } else {
        $message = "❌ Error: ไม่พบข้อมูลห้องพักที่ต้องการแก้ไข";
    }
} else {
    $message = "❌ Error: ไม่ได้ระบุ Room ID";
}

// ----------------------------------------------------
// B. การจัดการฟอร์ม POST (อัปเดตข้อมูล)
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['room_id_update'])) {
    
    $room_id_update = $conn->real_escape_string($_POST['room_id_update']);
    $room_number = $conn->real_escape_string($_POST['room_number']);
    $monthly_rent = (float)$conn->real_escape_string($_POST['monthly_rent']);
    $current_status = $conn->real_escape_string($_POST['current_status']);
    
    // ตรวจสอบว่าห้องนั้นไม่ถูกเช่าอยู่ แต่มีการตั้งค่าค่าเช่า (อาจเกิดความสับสน)
    if ($current_status == 'Vacant' && $monthly_rent > 0) {
         // อาจจะมีค่าเช่าเริ่มต้นสำหรับห้องว่าง
    }

    // UPDATE ข้อมูลห้อง
    $sql_update = "UPDATE rooms SET 
                   room_number = '$room_number', 
                   monthly_rent = ". ($current_status == 'Occupied' ? "'$monthly_rent'" : "NULL") .",
                   current_status = '$current_status'
                   WHERE room_id = '$room_id_update'";
                   
    if ($conn->query($sql_update)) {
        $message = "✅ อัปเดตข้อมูลห้อง {$room_number} เรียบร้อยแล้ว";
        // Redirect กลับไปหน้าหลักหลังอัปเดต
        header("Location: room_management.php?message=" . urlencode($message));
        exit();
    } else {
        $message = "❌ Error ในการอัปเดตข้อมูล: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลห้องพัก</title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 600px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="text"], .form-container input[type="number"], .form-container select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #607d8b; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2>✏️ แก้ไขข้อมูลห้องพัก</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>
        
        <?php if ($room_data): ?>
        <form action="edit_room.php" method="POST">
            <input type="hidden" name="room_id_update" value="<?php echo $room_data['room_id']; ?>">
            
            <label for="room_number">หมายเลขห้อง:</label>
            <input type="text" id="room_number" name="room_number" required value="<?php echo htmlspecialchars($room_data['room_number']); ?>">

            <label for="monthly_rent">ค่าเช่าต่อเดือน (฿):</label>
            <input type="number" id="monthly_rent" name="monthly_rent" step="0.01" min="0" value="<?php echo htmlspecialchars($room_data['monthly_rent']); ?>">

            <label for="current_status">สถานะห้องพัก:</label>
            <select id="current_status" name="current_status" required>
                <option value="Vacant" <?php echo ($room_data['current_status'] == 'Vacant' ? 'selected' : ''); ?>>Vacant (ว่าง)</option>
                <option value="Occupied" <?php echo ($room_data['current_status'] == 'Occupied' ? 'selected' : ''); ?>>Occupied (มีผู้เช่า)</option>
                </select>

            <input type="submit" value="อัปเดตข้อมูลห้องพัก">
        </form>
        <?php endif; ?>
    </div>

</body>
</html>