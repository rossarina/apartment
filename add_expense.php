<?php
include 'config.php';
include 'header.php';

$message = "";
$default_expense_date = date('Y-m-d');

// --- C. การจัดการฟอร์ม POST (บันทึกรายจ่าย) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $expense_date = $conn->real_escape_string($_POST['expense_date']);
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $amount = $conn->real_escape_string($_POST['amount']);
    $vendor = $conn->real_escape_string($_POST['vendor']); // ดึงค่า vendor

    // INSERT ข้อมูลรายจ่ายใหม่ (รวม vendor)
    $sql_insert = "INSERT INTO expenses (expense_date, category, description, amount, vendor) 
                   VALUES ('$expense_date', '$category', '$description', '$amount', '$vendor')";
    
    if ($conn->query($sql_insert)) {
        $message = "✅ บันทึกรายจ่ายเรียบร้อยแล้ว";
    } else {
        $message = "❌ Error ในการบันทึกรายจ่าย: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกรายการรายจ่ายใหม่</title>
    <?php echo $style_alerts; ?>
    <style>
        .form-container { max-width: 500px; margin: 30px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container label { display: block; margin-top: 10px; font-weight: bold; }
        .form-container input[type="date"], .form-container input[type="text"], .form-container input[type="number"], .form-container select, .form-container textarea { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-container input[type="submit"] { background-color: #ff9800; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .form-container input[type="submit"]:hover { background-color: #e68900; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="form-container">
        <h2>➕ บันทึกรายการรายจ่ายใหม่</h2>
        
        <?php
        if (!empty($message)) {
            $class = (strpos($message, '✅') !== false) ? 'message-success' : 'message-error';
            echo "<p class='$class'>$message</p>";
        }
        ?>
        
        <form action="add_expense.php" method="POST">
            
            <label for="expense_date">วันที่เกิดรายจ่าย:</label>
            <input type="date" id="expense_date" name="expense_date" required value="<?php echo $default_expense_date; ?>">
            
            <label for="category">หมวดหมู่:</label>
            <select id="category" name="category" required>
                <option value="">-- เลือกหมวดหมู่ --</option>
                <option value="ซ่อมบำรุง">ซ่อมบำรุง</option>
                <option value="ค่าสาธารณูปโภค">ค่าสาธารณูปโภค</option>
                <option value="อุปกรณ์">อุปกรณ์</option>
                <option option="ค่าใช้จ่ายทั่วไป">ค่าใช้จ่ายทั่วไป</option>
            </select>

            <label for="description">รายละเอียด:</label>
            <textarea id="description" name="description" rows="3" required></textarea>

            <label for="amount">จำนวนเงิน:</label>
            <input type="number" id="amount" name="amount" step="0.01" min="0" required>
            
            <label for="vendor">ผู้รับเงิน/ผู้จำหน่าย (ถ้ามี):</label>
            <input type="text" id="vendor" name="vendor">

            <input type="submit" value="บันทึกรายจ่าย">
        </form>
    </div>
</body>
</html>