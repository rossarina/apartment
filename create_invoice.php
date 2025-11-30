<?php
include 'config.php'; 
include 'header.php'; 

// --- 1. กำหนดอัตราต่อหน่วยที่นี่ ---
$electric_rate = 8.00; // 8.00 บาทต่อหน่วย (ปรับเปลี่ยนได้ตามต้องการ)
$water_rate = 20.00;    // 20.00 บาทต่อหน่วย (ปรับเปลี่ยนได้ตามต้องการ)
// ---------------------------------

$message = ""; 
$leases = [];
$selected_lease_id = '';

// --- ฟังก์ชันสำหรับดึงค่ามิเตอร์ล่าสุดและก่อนหน้า ---
function get_meter_readings($conn, $room_id, $reading_date) {
    // 1. ดึงมิเตอร์ล่าสุด (ที่ใกล้เคียงหรือก่อนวันที่ออกบิล)
    $sql_latest = "SELECT electric_unit, water_unit, reading_date FROM meter_readings 
                   WHERE room_id = '$room_id' AND reading_date <= '$reading_date' 
                   ORDER BY reading_date DESC LIMIT 1";
    $result_latest = $conn->query($sql_latest);
    $latest = $result_latest && $result_latest->num_rows > 0 ? $result_latest->fetch_assoc() : ['electric_unit' => 0, 'water_unit' => 0, 'reading_date' => date('Y-m-d')];

    // 2. ดึงมิเตอร์ก่อนหน้า (มิเตอร์ที่ 2 ที่เก่าที่สุด)
    $sql_previous = "SELECT electric_unit, water_unit FROM meter_readings 
                     WHERE room_id = '$room_id' AND reading_date < '{$latest['reading_date']}' 
                     ORDER BY reading_date DESC LIMIT 1";
    $result_previous = $conn->query($sql_previous);
    $previous = $result_previous && $result_previous->num_rows > 0 ? $result_previous->fetch_assoc() : ['electric_unit' => 0, 'water_unit' => 0];

    return ['latest' => $latest, 'previous' => $previous];
}

// --- A. ดึงข้อมูลสัญญาเช่าที่กำลังใช้งานอยู่ ---
$sql_leases = "SELECT 
                l.lease_id, r.room_number, t.first_name, t.last_name 
               FROM leases l
               JOIN rooms r ON l.room_id = r.room_id
               JOIN tenants t ON l.tenant_id = t.tenant_id
               WHERE l.status = 'Active'
               ORDER BY r.room_number ASC";
$result_leases = $conn->query($sql_leases);
if ($result_leases && $result_leases->num_rows > 0) {
    while($row = $result_leases->fetch_assoc()) { $leases[] = $row; }
}

if (isset($_GET['lease_id'])) { $selected_lease_id = (int)$_GET['lease_id']; }


// --- B. การจัดการฟอร์ม POST (สร้างใบแจ้งหนี้) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $lease_id = $conn->real_escape_string($_POST['lease_id']);
    $issue_date = $conn->real_escape_string($_POST['issue_date']);
    $due_date = $conn->real_escape_string($_POST['due_date']);
    
    // 1. ดึงข้อมูลสัญญาเช่าเพื่อหาค่าเช่าและ room_id
    $sql_lease_data = "SELECT room_id, monthly_rent FROM leases WHERE lease_id = '$lease_id'";
    $result_lease_data = $conn->query($sql_lease_data);

    if ($result_lease_data && $result_lease_data->num_rows == 1) {
        $lease_data = $result_lease_data->fetch_assoc();
        $room_id = $lease_data['room_id'];
        $monthly_rent = $lease_data['monthly_rent'];
        $total_amount = $monthly_rent;
        
        // 2. ดึงค่ามิเตอร์เพื่อคำนวณค่าน้ำค่าไฟ
        $meter_data = get_meter_readings($conn, $room_id, $issue_date);
        
        $ele_usage = $meter_data['latest']['electric_unit'] - $meter_data['previous']['electric_unit'];
        $water_usage = $meter_data['latest']['water_unit'] - $meter_data['previous']['water_unit'];

        // ป้องกันค่าติดลบ
        $ele_usage = max(0, $ele_usage);
        $water_usage = max(0, $water_usage);

        $electric_charge = $ele_usage * $electric_rate;
        $water_charge = $water_usage * $water_rate;

        // คำนวณยอดรวม
        $total_amount += $electric_charge + $water_charge;

        // 3. INSERT ข้อมูลใบแจ้งหนี้ใหม่
        $sql_insert = "INSERT INTO invoices (lease_id, issue_date, due_date, total_amount, status) 
                       VALUES ('$lease_id', '$issue_date', '$due_date', '$total_amount', 'Pending')";
        
        if ($conn->query($sql_insert)) {
            $new_invoice_id = $conn->insert_id;
            // header("Location: view_invoice.php?id=$new_invoice_id"); 
            // exit();
            $message = "✅ สร้างใบแจ้งหนี้หมายเลข $new_invoice_id เรียบร้อยแล้ว ยอดรวม: " . number_format($total_amount, 2) . " บาท";
        } else {
            $message = "❌ Error ในการบันทึกใบแจ้งหนี้: " . $conn->error;
        }

    } else {
        $message = "❌ ไม่พบข้อมูลสัญญาเช่าที่ระบุ";
    }
}

// กำหนดวันที่เริ่มต้นสำหรับฟอร์ม (วันออกบิล)
$default_issue_date = date('Y-m-d');
// กำหนดวันครบกำหนดชำระ (เช่น 7 วันหลังจากออกบิล)
$default_due_date = date('Y-m-d', strtotime('+7 days'));

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ออกใบแจ้งหนี้ค่าเช่า</title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 500px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="date"], .form-container select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .form-container input[type="submit"]:hover { background-color: #0056b3; }
        .rate-info { margin-top: 15px; padding: 10px; background-color: #f2f2f2; border-radius: 4px; font-size: 0.9em; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2>📝 ออกใบแจ้งหนี้ค่าเช่า</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>
        
        <div class="rate-info">
            <p><strong>อัตราค่าบริการปัจจุบัน:</strong></p>
            <ul>
                <li>⚡️ ค่าไฟ: <?php echo number_format($electric_rate, 2); ?> บาท/หน่วย</li>
                <li>💧 ค่าน้ำ: <?php echo number_format($water_rate, 2); ?> บาท/หน่วย</li>
            </ul>
            <p style="font-size: 0.8em; margin-top: 5px;">*ยอดรวมจะคำนวณจาก (ค่าเช่า) + (เลขมิเตอร์ล่าสุด - เลขมิเตอร์ก่อนหน้า) * อัตราต่อหน่วย</p>
        </div>

        <form action="create_invoice.php" method="POST">
            
            <label for="lease_id">เลือกห้องพัก (สัญญาที่ใช้งาน):</label>
            <select id="lease_id" name="lease_id" required>
                <option value="">-- เลือกห้อง --</option>
                <?php foreach ($leases as $lease): ?>
                    <option value="<?php echo $lease['lease_id']; ?>" 
                            <?php if ($lease['lease_id'] == $selected_lease_id) echo 'selected'; ?>>
                        ห้อง <?php echo $lease['room_number']; ?> (ผู้เช่า: <?php echo $lease['first_name'] . ' ' . $lease['last_name']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="issue_date">วันที่ออกบิล:</label>
            <input type="date" id="issue_date" name="issue_date" required value="<?php echo $default_issue_date; ?>">

            <label for="due_date">วันครบกำหนดชำระ:</label>
            <input type="date" id="due_date" name="due_date" required value="<?php echo $default_due_date; ?>">

            <input type="submit" value="สร้างใบแจ้งหนี้">
        </form>
    </div>
</body>
</html>