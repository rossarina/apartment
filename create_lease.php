<?php
include 'config.php';
include 'header.php';

$message = "";
$tenants = [];
$rooms = [];
$selected_room_id = '';

// --- A. ดึงข้อมูลผู้เช่าทั้งหมด ---
$sql_tenants = "SELECT tenant_id, first_name, last_name FROM tenants ORDER BY first_name ASC";
$result_tenants = $conn->query($sql_tenants);
if ($result_tenants && $result_tenants->num_rows > 0) {
    while($row = $result_tenants->fetch_assoc()) {
        $tenants[] = $row;
    }
}

// --- B. ดึงข้อมูลห้องว่างทั้งหมด (current_status = 'Vacant') ---
$sql_rooms = "SELECT room_id, room_number, floor FROM rooms WHERE current_status = 'Vacant' ORDER BY room_number ASC";
$result_rooms = $conn->query($sql_rooms);
if ($result_rooms && $result_rooms->num_rows > 0) {
    while($row = $result_rooms->fetch_assoc()) {
        $rooms[] = $row;
    }
}

// ตั้งค่าห้องที่ถูกเลือกไว้ล่วงหน้า (ถ้ามาจากปุ่มใน room_management.php)
if (isset($_GET['room_id'])) {
    $selected_room_id = (int)$_GET['room_id'];
}

// กำหนดวันที่เริ่มต้นเริ่มต้นสำหรับฟอร์ม
$default_start_date = date('Y-m-d');


// --- C. การจัดการฟอร์ม POST (สร้างสัญญาเช่า) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $tenant_id = $conn->real_escape_string($_POST['tenant_id']);
    $room_id = $conn->real_escape_string($_POST['room_id']);
    $start_date = $conn->real_escape_string($_POST['start_date']);
    $end_date = !empty($_POST['end_date']) ? $conn->real_escape_string($_POST['end_date']) : NULL;
    $monthly_rent = $conn->real_escape_string($_POST['monthly_rent']);

    // 1. ตรวจสอบสถานะห้องพักอีกครั้ง
    $sql_check_room = "SELECT current_status FROM rooms WHERE room_id = '$room_id'";
    $result_check_room = $conn->query($sql_check_room);
    
    if ($result_check_room && $result_check_room->num_rows == 1) {
        $room_status = $result_check_room->fetch_assoc()['current_status'];
        
        if ($room_status != 'Vacant') {
            $message = "❌ Error: ห้องพักนี้ไม่ว่าง กรุณาเลือกห้องอื่น";
        } else {
            // 2. INSERT ข้อมูลสัญญาเช่า
            $sql_insert = "INSERT INTO leases (tenant_id, room_id, start_date, end_date, monthly_rent, status) 
                           VALUES ('$tenant_id', '$room_id', '$start_date', " . ($end_date ? "'$end_date'" : "NULL") . ", '$monthly_rent', 'Active')";
            
            if ($conn->query($sql_insert)) {
                $new_lease_id = $conn->insert_id;
                
                // 3. อัปเดตตาราง rooms (สถานะห้อง, ผู้เช่าปัจจุบัน, ค่าเช่า)
                $sql_update_room = "UPDATE rooms SET 
                                    current_status = 'Occupied', 
                                    current_tenant_id = '$tenant_id', 
                                    monthly_rent = '$monthly_rent' 
                                    WHERE room_id = '$room_id'";
                
                if ($conn->query($sql_update_room)) {
                    $message = "✅ สร้างสัญญาเช่าสำเร็จแล้ว (ID: $new_lease_id) และอัปเดตสถานะห้องพักเรียบร้อย";
                    
                    // เคลียร์ค่าที่เลือกไว้
                    $selected_room_id = ''; 
                } else {
                     $message = "❌ สร้างสัญญาเช่าสำเร็จ แต่ Error ในการอัปเดตสถานะห้องพัก: " . $conn->error;
                }
            } else {
                $message = "❌ Error ในการสร้างสัญญา: " . $conn->error;
            }
        }
    } else {
        $message = "❌ ไม่พบข้อมูลห้องพักที่เลือก";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สร้างสัญญาเช่า</title>
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
        <h2>📜 สร้างสัญญาเช่าใหม่</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>
        
        <form action="create_lease.php" method="POST">
            
            <label for="tenant_id">เลือกผู้เช่า:</label>
            <select id="tenant_id" name="tenant_id" required>
                <option value="">-- เลือกผู้เช่า (ต้องเพิ่มผู้เช่าก่อน) --</option>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?php echo $tenant['tenant_id']; ?>">
                        <?php echo $tenant['first_name'] . ' ' . $tenant['last_name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p style="font-size: 0.8em; margin: 5px 0 15px 0;">*หากยังไม่มีผู้เช่า กรุณาไปที่เมนู 'จัดการผู้เช่า' เพื่อเพิ่ม</p>

            <label for="room_id">เลือกห้องพัก (สถานะว่าง):</label>
            <select id="room_id" name="room_id" required>
                <option value="">-- เลือกห้องพัก (ต้องเป็นห้องว่าง) --</option>
                <?php foreach ($rooms as $room): ?>
                    <option value="<?php echo $room['room_id']; ?>" 
                            <?php if ($room['room_id'] == $selected_room_id) echo 'selected'; ?>>
                        ห้อง <?php echo $room['room_number']; ?> (ชั้น <?php echo $room['floor']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($rooms)): ?>
                <p style="color: red; font-weight: bold;">*ไม่มีห้องพักสถานะ 'ว่าง' ในขณะนี้</p>
            <?php endif; ?>

            <label for="start_date">วันที่เริ่มต้นสัญญา:</label>
            <input type="date" id="start_date" name="start_date" required value="<?php echo $default_start_date; ?>">

            <label for="end_date">วันที่สิ้นสุดสัญญา (เว้นว่างได้ถ้าไม่กำหนด):</label>
            <input type="date" id="end_date" name="end_date">

            <label for="monthly_rent">ค่าเช่ารายเดือน (฿):</label>
            <input type="number" id="monthly_rent" name="monthly_rent" step="0.01" min="0" required>

            <input type="submit" value="สร้างสัญญาเช่า">
        </form>
    </div>
</body>
</html>