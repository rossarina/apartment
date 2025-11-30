<?php
include 'config.php'; 
include 'header.php'; 

$message = ""; 
$tenant_id = 0;
$first_name = '';
$last_name = '';
$phone = '';
$email = '';
$form_title = 'เพิ่มข้อมูลผู้เช่าใหม่';

// --- A. การจัดการฟอร์ม POST (บันทึก/อัปเดตข้อมูล) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $tenant_id_post = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
    $first_name_post = $conn->real_escape_string($_POST['first_name']);
    $last_name_post = $conn->real_escape_string($_POST['last_name']);
    $phone_post = $conn->real_escape_string($_POST['phone']);
    $email_post = $conn->real_escape_string($_POST['email']);

    // 1. ตรวจสอบ Email ซ้ำซ้อน
    if (!empty($email_post)) {
        $sql_check = "SELECT tenant_id FROM tenants WHERE email = '$email_post' AND tenant_id != $tenant_id_post";
        $result_check = $conn->query($sql_check);
        
        if ($result_check && $result_check->num_rows > 0) {
            $message = "❌ Error: ที่อยู่อีเมลนี้ถูกใช้โดยผู้เช่ารายอื่นแล้ว";
            goto end_post_processing;
        }
    }
    
    if ($tenant_id_post > 0) {
        // 2. อัปเดตข้อมูลผู้เช่าเดิม (UPDATE)
        $sql = "UPDATE tenants SET 
                first_name = '$first_name_post', 
                last_name = '$last_name_post', 
                phone = '$phone_post', 
                email = '$email_post' 
                WHERE tenant_id = $tenant_id_post";
        $success_msg = "✅ แก้ไขข้อมูลผู้เช่า $first_name_post $last_name_post สำเร็จแล้ว";
    } else {
        // 2. เพิ่มผู้เช่าใหม่ (INSERT)
        $sql = "INSERT INTO tenants (first_name, last_name, phone, email) 
                VALUES ('$first_name_post', '$last_name_post', '$phone_post', '$email_post')";
        $success_msg = "✅ เพิ่มข้อมูลผู้เช่า $first_name_post $last_name_post เข้าสู่ระบบสำเร็จ";
    }

    if (isset($sql) && $conn->query($sql) === TRUE) {
        $message = $success_msg;
        if ($tenant_id_post == 0) {
            $first_name = $last_name = $phone = $email = '';
        }
    } else {
        $message = "❌ Error ในการบันทึกข้อมูล: " . $conn->error;
    }
}

end_post_processing: 

// --- B. การจัดการฟอร์ม GET (ดึงข้อมูลผู้เช่าเพื่อแก้ไข) ---
if (isset($_GET['id']) && $_SERVER["REQUEST_METHOD"] != "POST") { 
    $tenant_id = (int)$_GET['id'];
    $sql_fetch = "SELECT first_name, last_name, phone, email FROM tenants WHERE tenant_id = $tenant_id";
    $result_fetch = $conn->query($sql_fetch);

    if ($result_fetch && $result_fetch->num_rows == 1) {
        $tenant_data = $result_fetch->fetch_assoc();
        $first_name = $tenant_data['first_name'];
        $last_name = $tenant_data['last_name'];
        $phone = $tenant_data['phone'];
        $email = $tenant_data['email'];
        $form_title = 'แก้ไขข้อมูลผู้เช่า: ' . $first_name . ' ' . $last_name;
    } else {
        $message = "⚠️ ไม่พบข้อมูลผู้เช่าที่ต้องการแก้ไข";
        $tenant_id = 0; 
        $form_title = 'เพิ่มข้อมูลผู้เช่าใหม่';
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
        .form-container { max-width: 500px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="text"] { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
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

        <form action="add_tenant.php" method="POST">
            <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
            
            <label for="first_name">ชื่อจริง:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>

            <label for="last_name">นามสกุล:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>

            <label for="phone">เบอร์โทรศัพท์:</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
            <small style="color: gray;">(ตัวอย่าง: 08XXXXXXXX)</small>

            <label for="email">อีเมล:</label>
            <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <small style="color: gray;">(จำเป็นสำหรับแจ้งบิลทางอิเล็กทรอนิกส์)</small>

            <input type="submit" value="<?php echo ($tenant_id > 0) ? 'บันทึกการแก้ไขผู้เช่า' : 'เพิ่มผู้เช่าใหม่'; ?>">
            
            <a href="tenant_management.php" style="display: block; text-align: center; margin-top: 15px;">กลับไปหน้าจัดการผู้เช่า</a>
        </form>
    </div>
</body>
</html>