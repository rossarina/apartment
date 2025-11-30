<?php
include 'config.php';
include 'header.php';

$message = "";
$rooms = [];
$default_reading_date = date('Y-m-d');
$selected_room_id = '';

// --- A. ดึงข้อมูลห้องพักทั้งหมด ---
$sql_rooms = "SELECT room_id, room_number FROM rooms ORDER BY room_number ASC";
$result_rooms = $conn->query($sql_rooms);
if ($result_rooms && $result_rooms->num_rows > 0) {
    while($row = $result_rooms->fetch_assoc()) {
        $rooms[] = $row;
    }
}

if (isset($_GET['room_id'])) { $selected_room_id = (int)$_GET['room_id']; }

// --- B. การจัดการฟอร์ม POST (บันทึกมิเตอร์) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $room_id = $conn->real_escape_string($_POST['room_id']);
    $reading_date = $conn->real_escape_string($_POST['reading_date']);
    // ตัวแปรเหล่านี้จะเก็บค่าเลขมิเตอร์ล่าสุด
    $electric_unit = $conn->real_escape_string($_POST['electric_unit']); 
    $water_unit = $conn->real_escape_string($_POST['water_unit']);

    // ตรวจสอบว่ามีการบันทึกมิเตอร์สำหรับห้องนี้ในวันที่นี้ไปแล้วหรือไม่
    $sql_check = "SELECT reading_id FROM meter_readings WHERE room_id = '$room_id' AND reading_date = '$reading_date'";
    $result_check = $conn->query($sql_check);
    if ($result_check && $result_check->num_rows > 0) {
        $message = "❌ Error: มีการบันทึกมิเตอร์สำหรับห้องนี้ในวันที่นี้ไปแล้ว กรุณาเลือกวันที่อื่นหรือแก้ไขรายการเดิม";
    } else {
        // SQL INSERT: บันทึกเลขมิเตอร์ล่าสุดลงในตาราง
        $sql_insert = "INSERT INTO meter_readings (room_id, reading_date, electric_unit, water_unit) 
                       VALUES ('$room_id', '$reading_date', '$electric_unit', '$water_unit')";
        
        if ($conn->query($sql_insert)) {
            $message = "✅ บันทึกเลขมิเตอร์เรียบร้อยแล้ว";
        } else {
            $message = "❌ Error ในการบันทึกข้อมูล: " . $conn->error;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกมิเตอร์น้ำ-ไฟ</title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 500px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="date"], .form-container input[type="number"], .form-container select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .form-container input[type="submit"]:hover { background-color: #1e7e34; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2>💡 บันทึกเลขมิเตอร์น้ำ-ไฟ</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>
        
        <form action="add_meter_reading.php" method="POST">
            
            <label for="room_id">เลือกห้องพัก:</label>
            <select id="room_id" name="room_id" required>
                <option value="">-- เลือกห้อง --</option>
                <?php foreach ($rooms as $room): ?>
                    <option value="<?php echo $room['room_id']; ?>" <?php if ($room['room_id'] == $selected_room_id) echo 'selected'; ?>>ห้อง <?php echo $room['room_number']; ?></option>
                <?php endforeach; ?>
            </select>

            <label for="reading_date">วันที่จดมิเตอร์:</label>
            <input type="date" id="reading_date" name="reading_date" required value="<?php echo $default_reading_date; ?>">

            <label for="electric_unit">เลขมิเตอร์ไฟฟ้า (หน่วยล่าสุด):</label>
            <input type="number" id="electric_unit" name="electric_unit" step="0.01" min="0" required>

            <label for="water_unit">เลขมิเตอร์น้ำ (หน่วยล่าสุด):</label>
            <input type="number" id="water_unit" name="water_unit" step="0.01" min="0" required>

            <input type="submit" value="บันทึกมิเตอร์">
        </form>
    </div>
</body>
</html>