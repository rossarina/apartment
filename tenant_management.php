<?php
include 'config.php';
include 'header.php';

$message = "";

// --- A. การจัดการการลบผู้เช่า ---
if (isset($_GET['delete_id']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
    $delete_id = (int)$_GET['delete_id'];
    
    // 1. ตรวจสอบว่าผู้เช่ามีสัญญาเช่าที่ยังใช้งานอยู่หรือไม่
    $sql_check_lease = "SELECT lease_id FROM leases WHERE tenant_id = '$delete_id'";
    $result_check = $conn->query($sql_check_lease);
    
    if ($result_check && $result_check->num_rows > 0) {
        $message = "❌ ไม่สามารถลบผู้เช่ารายนี้ได้! เนื่องจากยังมีสัญญาเช่าที่ผูกอยู่ (" . $result_check->num_rows . " สัญญา). กรุณาจัดการสัญญาเช่าก่อน.";
    } else {
        // 2. ดำเนินการลบ
        $sql_delete = "DELETE FROM tenants WHERE tenant_id = '$delete_id'";
        
        if ($conn->query($sql_delete) === TRUE) {
            $message = "✅ ลบข้อมูลผู้เช่าสำเร็จแล้ว";
        } else {
            // หากมีข้อผิดพลาด Foreign Key 
            $message = "❌ Error ในการลบข้อมูล: " . $conn->error;
        }
    }
}

// --- B. ดึงข้อมูลผู้เช่าทั้งหมดมาแสดง ---
$sql = "SELECT tenant_id, first_name, last_name, phone, email, date_added FROM tenants ORDER BY tenant_id DESC";
$result = $conn->query($sql);


/* --- DEBUGGING CODE START --- */
// เพื่อช่วยในการตรวจสอบว่า SQL ทำงานหรือไม่
if ($result !== false && $result->num_rows > 0) {
    // Reset pointer เพื่อให้ loop ใน HTML ทำงานได้
    $result->data_seek(0);
} elseif ($result === false) {
    $message = "❌ SQL Query ล้มเหลว: " . $conn->error;
}
/* --- DEBUGGING CODE END --- */


$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลผู้เช่า</title>
    <?php echo $style_alerts; ?>
    <style>
        .table-container { margin: 30px auto; max-width: 1000px; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .data-table th { background-color: #f2f2f2; }
        .btn-action { padding: 5px 10px; margin-right: 5px; text-decoration: none; border-radius: 3px; font-size: 14px; }
        .btn-edit { background-color: #ffc107; color: #333; }
        .btn-delete { background-color: #dc3545; color: white; }
        .header-section { display: flex; justify-content: space-between; align-items: center; }
        .btn-add { background-color: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="table-container">
        <div class="header-section">
            <h2>👤 ข้อมูลผู้เช่าทั้งหมด</h2>
            <a href="add_tenant.php" class="btn-add">➕ เพิ่มผู้เช่าใหม่</a>
        </div>

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

        <?php if ($result && $result->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ชื่อ - นามสกุล</th>
                        <th>เบอร์โทรศัพท์</th>
                        <th>อีเมล</th>
                        <th>วันที่เพิ่มข้อมูล</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // ต้องใช้ $result->data_seek(0); ถ้ามีการเรียก $result->num_rows ก่อนหน้านี้
                    // แต่ในโค้ด debugging เราได้ทำแล้ว
                    while($row = $result->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?php echo $row['tenant_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($row['date_added'])); ?></td>
                            <td>
                                <a href="add_tenant.php?id=<?php echo $row['tenant_id']; ?>" class="btn-action btn-edit">แก้ไข</a>
                                <a href="tenant_management.php?delete_id=<?php echo $row['tenant_id']; ?>" 
                                   class="btn-action btn-delete" 
                                   onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผู้เช่ารายนี้? **หากมีสัญญาเช่าผูกอยู่จะไม่สามารถลบได้**');">
                                    ลบ
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; margin-top: 50px;">⚠️ ยังไม่มีข้อมูลผู้เช่าในระบบ กรุณาเพิ่มผู้เช่าใหม่</p>
        <?php endif; ?>
    </div>
    
</body>
</html>