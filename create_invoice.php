<?php
include 'config.php';
include 'header.php';

$message = "";
$rooms = [];
$meter_readings = [];
$invoice_date = date('Y-m-d');
$due_date = date('Y-m-d', strtotime('+7 days'));

// ----------------------------------------------------
// A. ดึงอัตราค่าบริการจากตาราง settings
// ----------------------------------------------------
$electric_rate = 7.00; // ค่าเริ่มต้นถ้าดึงไม่ได้
$water_rate = 20.00;   // ค่าเริ่มต้นถ้าดึงไม่ได้

$sql_settings = "SELECT setting_key, setting_value FROM settings";
$result_settings = $conn->query($sql_settings);

if ($result_settings && $result_settings->num_rows > 0) {
    while($row = $result_settings->fetch_assoc()) {
        if ($row['setting_key'] == 'electric_rate') {
            $electric_rate = (float)$row['setting_value'];
        }
        if ($row['setting_key'] == 'water_rate') {
            $water_rate = (float)$row['setting_value'];
        }
    }
}


// ----------------------------------------------------
// B. ดึงรายการห้องที่มีผู้เช่า (Occupied)
// ----------------------------------------------------
$sql_rooms = "SELECT 
                r.room_id, r.room_number, r.monthly_rent,
                t.first_name, t.last_name,
                l.lease_id
              FROM rooms r
              JOIN leases l ON r.room_id = l.room_id
              JOIN tenants t ON r.current_tenant_id = t.tenant_id
              WHERE r.current_status = 'Occupied' AND l.status = 'Active'";
$result_rooms = $conn->query($sql_rooms);

if ($result_rooms && $result_rooms->num_rows > 0) {
    while ($row = $result_rooms->fetch_assoc()) {
        $rooms[$row['room_id']] = $row;
    }
}

// ----------------------------------------------------
// C. ดึงเลขมิเตอร์ล่าสุดของแต่ละห้อง
// ----------------------------------------------------
if (!empty($rooms)) {
    foreach (array_keys($rooms) as $room_id) {
        // ดึงเลขมิเตอร์ล่าสุด
        $sql_meter = "SELECT reading_date, electric_unit, water_unit 
                      FROM meter_readings 
                      WHERE room_id = '$room_id' 
                      ORDER BY reading_date DESC LIMIT 1";
        $result_meter = $conn->query($sql_meter);
        
        if ($result_meter && $result_meter->num_rows > 0) {
            $meter_readings[$room_id] = $result_meter->fetch_assoc();
        } else {
            // ถ้าไม่มีการจดมิเตอร์เลย ให้เป็น 0.00 และวันที่ N/A
            $meter_readings[$room_id] = ['reading_date' => 'N/A', 'electric_unit' => 0.00, 'water_unit' => 0.00];
        }
    }
}

// ----------------------------------------------------
// D. การจัดการฟอร์ม POST (สร้างใบแจ้งหนี้) - **อัปเดตแล้ว**
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // รับค่าจากฟอร์ม
    $lease_id = $conn->real_escape_string($_POST['lease_id']);
    $room_id = $conn->real_escape_string($_POST['room_id']);
    $issue_date = $conn->real_escape_string($_POST['issue_date']);
    $due_date = $conn->real_escape_string($_POST['due_date']);
    $rental_amount = (float)$conn->real_escape_string($_POST['rental_amount']);
    
    $current_e_unit = (float)$conn->real_escape_string($_POST['current_e_unit']); 
    $current_w_unit = (float)$conn->real_escape_string($_POST['current_w_unit']);
    $last_e_unit = (float)$conn->real_escape_string($_POST['last_e_unit']);
    $last_w_unit = (float)$conn->real_escape_string($_POST['last_w_unit']);
    
    // ตรวจสอบว่าเลขมิเตอร์ถูกต้อง
    if ($current_e_unit < $last_e_unit || $current_w_unit < $last_w_unit) {
        $message = "❌ Error: เลขมิเตอร์ใหม่ต้องมากกว่าหรือเท่ากับเลขมิเตอร์ล่าสุด";
    } else {
        // คำนวณหน่วยที่ใช้และค่าใช้จ่าย
        $electric_unit_used = $current_e_unit - $last_e_unit;
        $water_unit_used = $current_w_unit - $last_w_unit;
        $electric_cost = $electric_unit_used * $electric_rate;
        $water_cost = $water_unit_used * $water_rate;
        
        // รวมยอดทั้งหมด
        $total_amount = $rental_amount + $electric_cost + $water_cost;

        // 1. INSERT ใบแจ้งหนี้หลักใหม่
        $sql_insert = "INSERT INTO invoices (lease_id, issue_date, due_date, total_amount, status)
                       VALUES ('$lease_id', '$issue_date', '$due_date', '$total_amount', 'Pending')";
        
        if ($conn->query($sql_insert)) {
            $last_invoice_id = $conn->insert_id;
            
            // 2. INSERT รายละเอียดใบแจ้งหนี้ (invoice_details)
            $items_to_insert = [];

            // a. ค่าเช่า
            $items_to_insert[] = [
                'description' => 'ค่าเช่าประจำเดือน',
                'amount' => $rental_amount,
                'type' => 'Rent'
            ];
            
            // b. ค่าไฟฟ้า
            $items_to_insert[] = [
                'description' => "ค่าไฟฟ้า ({$electric_unit_used} หน่วย @ {$electric_rate} บาท)",
                'amount' => $electric_cost,
                'type' => 'Electric'
            ];
            
            // c. ค่าน้ำประปา
            $items_to_insert[] = [
                'description' => "ค่าน้ำประปา ({$water_unit_used} หน่วย @ {$water_rate} บาท)",
                'amount' => $water_cost,
                'type' => 'Water'
            ];

            $details_success = true;
            foreach ($items_to_insert as $item) {
                $desc = $conn->real_escape_string($item['description']);
                $amt = $item['amount'];
                $type = $conn->real_escape_string($item['type']);

                $sql_detail = "INSERT INTO invoice_details (invoice_id, item_description, item_amount, item_type)
                               VALUES ('$last_invoice_id', '$desc', '$amt', '$type')";
                
                if (!$conn->query($sql_detail)) {
                    $details_success = false;
                    break;
                }
            }
            
            if ($details_success) {
                $message = "✅ สร้างใบแจ้งหนี้ #{$last_invoice_id} สำหรับห้อง {$rooms[$room_id]['room_number']} เรียบร้อยแล้ว ยอดรวม: " . number_format($total_amount, 2) . " บาท";
            } else {
                // หากบันทึกรายละเอียดไม่ได้ ควรยกเลิกใบแจ้งหนี้หลักด้วย
                $conn->query("DELETE FROM invoices WHERE invoice_id = '$last_invoice_id'");
                $message = "❌ Error: สร้างใบแจ้งหนี้หลักได้ แต่บันทึกรายละเอียดไม่ได้ กรุณาลองใหม่อีกครั้ง";
            }
            
        } else {
            $message = "❌ Error ในการสร้างใบแจ้งหนี้: " . $conn->error;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ออกใบแจ้งหนี้ใหม่</title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 700px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="date"], .form-container input[type="number"], .form-container select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #f44336; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .form-container input[type="submit"]:hover { background-color: #d32f2f; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .detail-box { border: 1px solid #eee; padding: 15px; margin-top: 15px; border-radius: 4px; background-color: #f9f9f9; }
        .detail-box p { margin: 5px 0; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2>📝 ออกใบแจ้งหนี้ใหม่</h2>
        <p style="color: blue;">*อัตราค่าไฟ: <?php echo number_format($electric_rate, 2); ?> บ./หน่วย | ค่าน้ำ: <?php echo number_format($water_rate, 2); ?> บ./หน่วย</p>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>
        
        <form action="create_invoice.php" method="POST">
            
            <label for="room_select">เลือกห้องพัก:</label>
            <select id="room_select" name="room_id" required onchange="updateRoomDetails(this.value)">
                <option value="">-- เลือกห้องพักที่มีผู้เช่า --</option>
                <?php foreach ($rooms as $room_id => $room): ?>
                    <option 
                        value="<?php echo $room_id; ?>" 
                        data-lease-id="<?php echo $room['lease_id']; ?>"
                        data-rent="<?php echo number_format($room['monthly_rent'], 2, '.', ''); ?>"
                        data-e-unit="<?php echo $meter_readings[$room_id]['electric_unit']; ?>"
                        data-w-unit="<?php echo $meter_readings[$room_id]['water_unit']; ?>"
                        data-meter-date="<?php echo ($meter_readings[$room_id]['reading_date'] != 'N/A' ? date('d/m/Y', strtotime($meter_readings[$room_id]['reading_date'])) : 'N/A'); ?>"
                    >
                        ห้อง <?php echo $room['room_number']; ?> (<?php echo $room['first_name'] . ' ' . $room['last_name']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" id="lease_id_input" name="lease_id" required>

            <div class="detail-box" id="room_details">
                <p><strong>ผู้เช่า:</strong> <span id="tenant_name">-</span></p>
                <p><strong>ค่าเช่าพื้นฐาน:</strong> <span id="base_rent">-</span> บาท</p>
                <p><strong>เลขมิเตอร์ล่าสุด (ไฟฟ้า):</strong> <span id="last_e_unit_span">-</span> หน่วย</p>
                <p><strong>เลขมิเตอร์ล่าสุด (น้ำ):</strong> <span id="last_w_unit_span">-</span> หน่วย</p>
                <p style="font-size: 0.8em; color: #666;">วันที่จดมิเตอร์ล่าสุด: <span id="last_meter_date">-</span></p>
            </div>

            <div class="grid-3">
                <div>
                    <label for="issue_date">วันที่ออกใบแจ้งหนี้:</label>
                    <input type="date" id="issue_date" name="issue_date" required value="<?php echo $invoice_date; ?>">
                </div>
                <div>
                    <label for="due_date">วันครบกำหนดชำระ:</label>
                    <input type="date" id="due_date" name="due_date" required value="<?php echo $due_date; ?>">
                </div>
                <div>
                    <label for="rental_amount">ค่าเช่าเดือนนี้:</label>
                    <input type="number" id="rental_amount" name="rental_amount" step="0.01" min="0" required readonly>
                </div>
            </div>
            
            <h3>รายการค่าสาธารณูปโภค (กรอกเลขมิเตอร์ปัจจุบัน)</h3>
            
            <input type="hidden" id="last_e_unit_input" name="last_e_unit" value="0.00">
            <input type="hidden" id="last_w_unit_input" name="last_w_unit" value="0.00">
            
            <div class="grid-3">
                <div>
                    <label for="last_e_unit_display">มิเตอร์ไฟฟ้าล่าสุด (หน่วย):</label>
                    <input type="number" id="last_e_unit_display" step="0.01" readonly style="background-color: #eee;">
                </div>
                <div>
                    <label for="current_e_unit">เลขมิเตอร์ไฟฟ้าปัจจุบัน:</label>
                    <input type="number" id="current_e_unit" name="current_e_unit" step="0.01" min="0" required placeholder="เลขมิเตอร์ใหม่">
                </div>
                <div>
                    <label for="e_calc">หน่วยที่ใช้ (ไฟฟ้า):</label>
                    <input type="number" id="e_calc" readonly style="background-color: #ffcccc; color: red;">
                </div>
            </div>

            <div class="grid-3">
                <div>
                    <label for="last_w_unit_display">มิเตอร์น้ำล่าสุด (หน่วย):</label>
                    <input type="number" id="last_w_unit_display" step="0.01" readonly style="background-color: #eee;">
                </div>
                <div>
                    <label for="current_w_unit">เลขมิเตอร์น้ำปัจจุบัน:</label>
                    <input type="number" id="current_w_unit" name="current_w_unit" step="0.01" min="0" required placeholder="เลขมิเตอร์ใหม่">
                </div>
                <div>
                    <label for="w_calc">หน่วยที่ใช้ (น้ำ):</label>
                    <input type="number" id="w_calc" readonly style="background-color: #ffcccc; color: red;">
                </div>
            </div>

            <input type="submit" value="ออกใบแจ้งหนี้">
        </form>
    </div>

    <script>
        const roomSelect = document.getElementById('room_select');
        const leaseIdInput = document.getElementById('lease_id_input');
        const baseRentSpan = document.getElementById('base_rent');
        const rentalAmountInput = document.getElementById('rental_amount');
        const tenantNameSpan = document.getElementById('tenant_name');
        const lastEUnitSpan = document.getElementById('last_e_unit_span');
        const lastWUnitSpan = document.getElementById('last_w_unit_span');
        const lastMeterDateSpan = document.getElementById('last_meter_date');
        
        // Input fields สำหรับการคำนวณและส่งค่า
        const currentEUnitInput = document.getElementById('current_e_unit');
        const currentWUnitInput = document.getElementById('current_w_unit');
        const lastEUnitDisplay = document.getElementById('last_e_unit_display');
        const lastWUnitDisplay = document.getElementById('last_w_unit_display');
        const lastEUnitHidden = document.getElementById('last_e_unit_input');
        const lastWUnitHidden = document.getElementById('last_w_unit_input');
        const eCalc = document.getElementById('e_calc');
        const wCalc = document.getElementById('w_calc');
        
        let currentLastEUnit = 0;
        let currentLastWUnit = 0;

        function updateRoomDetails(roomId) {
            const selectedOption = roomSelect.options[roomSelect.selectedIndex];
            
            if (roomId) {
                const leaseId = selectedOption.getAttribute('data-lease-id');
                const rent = selectedOption.getAttribute('data-rent');
                const eUnit = parseFloat(selectedOption.getAttribute('data-e-unit'));
                const wUnit = parseFloat(selectedOption.getAttribute('data-w-unit'));
                const meterDate = selectedOption.getAttribute('data-meter-date');
                const tenantText = selectedOption.text;
                const tenantNameMatch = tenantText.match(/\((.*?)\)/);
                const tenantName = tenantNameMatch ? tenantNameMatch[1] : '-';

                leaseIdInput.value = leaseId;
                tenantNameSpan.textContent = tenantName;
                baseRentSpan.textContent = parseFloat(rent).toFixed(2);
                rentalAmountInput.value = rent;
                
                // สำหรับแสดงผลใน Detail Box
                lastEUnitSpan.textContent = eUnit.toFixed(2);
                lastWUnitSpan.textContent = wUnit.toFixed(2);
                lastMeterDateSpan.textContent = meterDate;
                
                // สำหรับกรอกค่ามิเตอร์ (Readonly)
                lastEUnitDisplay.value = eUnit;
                lastWUnitDisplay.value = wUnit;
                
                // สำหรับส่งค่ามิเตอร์ล่าสุดไปประมวลผลใน PHP (Hidden)
                lastEUnitHidden.value = eUnit;
                lastWUnitHidden.value = wUnit;
                
                currentLastEUnit = eUnit; // ใช้ใน JS calculation
                currentLastWUnit = wUnit; // ใช้ใน JS calculation

                // เคลียร์ค่ามิเตอร์ปัจจุบันและหน่วยที่ใช้
                currentEUnitInput.value = '';
                currentWUnitInput.value = '';
                eCalc.value = 0.00;
                wCalc.value = 0.00;
                
            } else {
                // Reset ค่าทั้งหมด
                leaseIdInput.value = '';
                tenantNameSpan.textContent = '-';
                baseRentSpan.textContent = '-';
                rentalAmountInput.value = '';
                lastEUnitSpan.textContent = '-';
                lastWUnitSpan.textContent = '-';
                lastMeterDateSpan.textContent = '-';
                
                lastEUnitDisplay.value = 0.00;
                lastWUnitDisplay.value = 0.00;
                lastEUnitHidden.value = 0.00;
                lastWUnitHidden.value = 0.00;
                
                currentLastEUnit = 0;
                currentLastWUnit = 0;
                currentEUnitInput.value = '';
                currentWUnitInput.value = '';
                eCalc.value = 0.00;
                wCalc.value = 0.00;
            }
        }

        // **ฟังก์ชันที่แก้ไขให้ยืดหยุ่นขึ้น (ป้องกันการพิมพ์ไม่ได้)**
        function calculateUnit(currentInput, lastUnit, calcOutput) {
            const currentValue = currentInput.value.trim();
            let unitUsed = 0.00;
            
            // 1. ถ้าช่องกรอกว่าง หรือเป็นแค่จุดทศนิยม ให้รีเซ็ตค่าที่ใช้เป็น 0 (อนุญาตให้พิมพ์ได้)
            if (currentValue === '' || currentValue === '.') {
                calcOutput.value = unitUsed.toFixed(2);
                return;
            }

            const current = parseFloat(currentValue);
            
            // 2. ถ้าเป็นตัวเลข และมากกว่าหรือเท่ากับเลขล่าสุด
            if (!isNaN(current) && current >= lastUnit) {
                unitUsed = current - lastUnit;
            } 
            // 3. ถ้าเป็นตัวเลข แต่น้อยกว่าเลขล่าสุด (แจ้งเตือนแต่ไม่ลบค่าทิ้ง)
            else if (!isNaN(current) && current < lastUnit) {
                alert("❌ เลขมิเตอร์ใหม่ (" + current + ") ต้องมากกว่าหรือเท่ากับเลขมิเตอร์ล่าสุด (" + lastUnit + ")!");
                unitUsed = 0.00;
            } 

            calcOutput.value = unitUsed.toFixed(2);
        }

        // Event Listeners สำหรับคำนวณหน่วยที่ใช้
        currentEUnitInput.addEventListener('input', () => {
            calculateUnit(currentEUnitInput, currentLastEUnit, eCalc);
        });

        currentWUnitInput.addEventListener('input', () => {
            calculateUnit(currentWUnitInput, currentLastWUnit, wCalc);
        });

        // เรียกใช้ครั้งแรกเมื่อโหลดหน้าเพื่อแสดงรายละเอียดห้องที่เลือก
        updateRoomDetails(roomSelect.value);

    </script>
</body>
</html>