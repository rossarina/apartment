<?php
include 'config.php'; 
include 'header.php'; 

$message = ""; 
$room_id = 0;
$room_number = '';
$floor = '';
$current_status = 'Vacant'; 
$form_title = 'เพิ่มห้องพักใหม่';

// --- A. การจัดการฟอร์ม POST (บันทึก/อัปเดตข้อมูล) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $room_id_post = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $room_number_post = $conn->real_escape_string($_POST['room_number']);
    $floor_post = $conn->real_escape_string($_POST['floor']);
    $status_post = $conn->real_escape_string($_POST['current_status']);

    // 1. ตรวจสอบ Room Number ซ้ำซ้อน
    $sql_check = "SELECT room_id FROM rooms WHERE room_number = '$room_number_post' AND room_id != $room_id_post";
    $result_check = $conn->query($sql_check);
    
    if ($result_check && $result_check->num_rows > 0) {
        $message = "❌ Error: หมายเลขห้อง $room_number_post มีอยู่แล้วในระบบ กรุณาใช้หมายเลขอื่น";
    } else {
        if ($room_id_post > 0) {
            // 2. อัปเดตข้อมูลห้องเดิม (UPDATE)
            $sql = "UPDATE rooms SET 
                    room_number = '$room_number_post', 
                    floor = '$floor_post', 
                    current_status = '$status_post' 
                    WHERE room_id = $room_id_post";
            $success_msg = "✅ แก้ไขข้อมูลห้องพักหมายเลข $room_number_post สำเร็จแล้ว";
        } else {
            // 2. เพิ่มห้องใหม่ (INSERT)
            $sql = "INSERT INTO rooms (room_number, floor, current_status) 
                    VALUES ('$room_number_post', '$floor_post', '$status_post')";
            $success_msg = "✅ เพิ่มห้องพักหมายเลข $room_number_post เข้าสู่ระบบสำเร็จ";
        }

        if (isset($sql) && $conn->query($sql) === TRUE) {
            $message = $success_msg;
            if ($room_id_post == 0) { 
                $room_number = $floor = '';
                $current_status = 'Vacant';
            }
        } else {
            $message = "❌ Error ในการบันทึกข้อมูล: " . $conn->error;
        }
    }
}


// --- B. การจัดการฟอร์ม GET (ดึงข้อมูลห้องเพื่อแก้ไข) ---
if (isset($_GET['id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $room_id = (int)$_GET['id'];
    $sql_fetch = "SELECT room_number, floor, current_status FROM rooms WHERE room_id = $room_id";
    $result_fetch = $conn->query($sql_fetch);

    if ($result_fetch && $result_fetch->num_rows == 1) {
        $room_data = $result_fetch->fetch_assoc();
        $room_number = $room_data['room_number'];
        $floor = $room_data['floor'];
        $current_status = $room_data['current_status'];
        $form_title = 'แก้ไขข้อมูลห้องพัก #' . $room_number;
    } else {
        $message = "⚠️ ไม่พบข้อมูลห้องพักที่ต้องการแก้ไข";
        $room_id = 0; 
        $form_title = 'เพิ่มห้องพักใหม่';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?php echo $form_title; ?></title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 400px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="text"], .form-container select, .form-container input[type="number"] { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .form-container input[type="submit"]:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2><?php echo $form_title; ?></h2>
        
        <?php
        if (!empty($message)) {
            if (strpos($message, '✅') !== false) {
                $style_class = 'message-success';
            } elseif (strpos($message, '❌') !== false) {
                $style_class = 'message-error';
            } else {
                 $style_class = 'message-warning';
            }
            echo "<p class='$style_class'>$message</p>";
        }
        ?>

        <form action="add_room.php" method="POST">
            <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
            
            <label for="room_number">หมายเลขห้อง (เช่น A101):</label>
            <input type="text" id="room_number" name="room_number" value="<?php echo htmlspecialchars($room_number); ?>" required>

            <label for="floor">ชั้นที่:</label>
            <input type="number" id="floor" name="floor" value="<?php echo htmlspecialchars($floor); ?>" min="1" required>

            <label for="current_status">สถานะห้อง:</label>
            <select id="current_status" name="current_status" required>
                <option value="Vacant" <?php if ($current_status == 'Vacant') echo 'selected'; ?>>ว่าง (Vacant)</option>
                <option value="Occupied" <?php if ($current_status == 'Occupied') echo 'selected'; ?> disabled>มีผู้เช่า (Occupied) - *ไม่ควรเปลี่ยนที่นี่</option>
                <option value="Maintenance" <?php if ($current_status == 'Maintenance') echo 'selected'; ?>>อยู่ในระหว่างซ่อมบำรุง (Maintenance)</option>
            </select>
            <small style="color: gray;">*สถานะ 'มีผู้เช่า' ควรถูกอัปเดตโดยอัตโนมัติเมื่อสร้างสัญญาเช่า</small>

            <input type="submit" value="<?php echo ($room_id > 0) ? 'บันทึกการแก้ไข' : 'เพิ่มห้องใหม่'; ?>">
            
            <a href="room_management.php" style="display: block; text-align: center; margin-top: 15px;">ยกเลิก/กลับไปหน้าจัดการห้องพัก</a>
        </form>
    </div>
</body>
</html>