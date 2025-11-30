<?php
include 'config.php'; 
include 'header.php'; // **เชื่อมเมนู**

// 1. กำหนดคำสั่ง SQL สำหรับดึงข้อมูลรายจ่าย
$sql = "SELECT expense_date, category, description, amount, vendor FROM expenses ORDER BY expense_date DESC";

// 2. รันคำสั่ง SQL
$result = $conn->query($sql);

// 3. เตรียมตัวแปรสำหรับเก็บยอดรวมทั้งหมด
$total_expense = 0; 
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานรายจ่าย</title>
    <?php echo $style_alerts; // เพิ่ม CSS Alerts ?>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total-row td { font-weight: bold; background-color: #e0e0e0; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; // **แสดงเมนูนำทาง** ?>

    <h2>📋 รายงานสรุปรายการรายจ่าย</h2>
    
    <table>
        <thead>
            <tr>
                <th>วันที่</th>
                <th>หมวดหมู่</th>
                <th>รายละเอียด</th>
                <th class="text-right">จำนวนเงิน (บาท)</th>
                <th>ผู้รับเงิน/ผู้จำหน่าย</th>
            </tr>
        </thead>
        <tbody>
            
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $total_expense += $row["amount"];
                    
                    echo "<tr>";
                    echo "<td>" . $row["expense_date"] . "</td>";
                    echo "<td>" . $row["category"] . "</td>";
                    echo "<td>" . $row["description"] . "</td>";
                    echo "<td class='text-right'>" . number_format($row["amount"], 2) . "</td>";
                    echo "<td>" . $row["vendor"] . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5'>ยังไม่มีข้อมูลรายจ่ายที่บันทึกไว้</td></tr>";
            }
            ?>
            
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" class="text-right">ยอดรวมรายจ่ายทั้งหมด:</td>
                <td class="text-right"><?php echo number_format($total_expense, 2); ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>

<?php
$conn->close();
?>