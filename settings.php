<?php
include 'config.php';
include 'header.php';

$message = "";
$settings_data = [];

// --- A. การจัดการฟอร์ม POST (อัปเดตค่า) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $electric_rate = $conn->real_escape_string($_POST['electric_rate']);
    $water_rate = $conn->real_escape_string($_POST['water_rate']);

    $update_success = true;

    // อัปเดตอัตราค่าไฟฟ้า
    $sql_update_electric = "UPDATE settings SET setting_value = '$electric_rate' WHERE setting_key = 'electric_rate'";
    if (!$conn->query($sql_update_electric)) {
        $update_success = false;
        $message .= "❌ Error อัปเดตค่าไฟฟ้า: " . $conn->error . "<br>";
    }

    // อัปเดตอัตราค่าน้ำ
    $sql_update_water = "UPDATE settings SET setting_value = '$water_rate' WHERE setting_key = 'water_rate'";
    if (!$conn->query($sql_update_water)) {
        $update_success = false;
        $message .= "❌ Error อัปเดตค่าน้ำ: " . $conn->error . "<br>";
    }

    if ($update_success && empty($message)) {
        $message = "✅ อัปเดตอัตราค่าน้ำและค่าไฟฟ้าเรียบร้อยแล้ว";
    }
}

// --- B. ดึงค่าปัจจุบันมาแสดงผล ---
$sql_select = "SELECT setting_key, setting_value, description FROM settings";
$result_select = $conn->query($sql_select);

if ($result_select && $result_select->num_rows > 0) {
    while($row = $result_select->fetch_assoc()) {
        $settings_data[$row['setting_key']] = $row;
    }
} else {
    // กรณีที่ตาราง settings ไม่มีข้อมูล (ต้องรัน SQL ในขั้นตอนที่ 1 ก่อน)
    $message = "❌ ไม่พบข้อมูลการตั้งค่า กรุณารันโค้ด SQL เพื่อสร้างตาราง 'settings' ก่อน";
}

$conn->close();

// กำหนดค่าเริ่มต้นถ้าดึงจากฐานข้อมูลไม่ได้
$electric_rate_value = $settings_data['electric_rate']['setting_value'] ?? 0.00;
$water_rate_value = $settings_data['water_rate']['setting_value'] ?? 0.00;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตั้งค่าระบบ</title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 500px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="number"] { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .form-container input[type="submit"]:hover { background-color: #45a049; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2>⚙️ การตั้งค่าอัตราค่าบริการ</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>
        
        <form action="settings.php" method="POST">
            
            <label for="electric_rate">อัตราค่าไฟฟ้าต่อหน่วย (บาท):</label>
            <input type="number" id="electric_rate" name="electric_rate" step="0.01" min="0" required 
                   value="<?php echo number_format($electric_rate_value, 2, '.', ''); ?>">
            <p style="font-size: 0.8em; color: #666;">ปัจจุบัน: <?php echo $settings_data['electric_rate']['description'] ?? 'ไม่ระบุ'; ?></p>

            <label for="water_rate">อัตราค่าน้ำประปาต่อหน่วย (บาท):</label>
            <input type="number" id="water_rate" name="water_rate" step="0.01" min="0" required
                   value="<?php echo number_format($water_rate_value, 2, '.', ''); ?>">
            <p style="font-size: 0.8em; color: #666;">ปัจจุบัน: <?php echo $settings_data['water_rate']['description'] ?? 'ไม่ระบุ'; ?></p>

            <input type="submit" value="บันทึกการตั้งค่า">
        </form>
    </div>
</body>
</html>