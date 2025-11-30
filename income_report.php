<?php
include 'config.php';
include 'header.php';

$message = "";

// ----------------------------------------------------
// 1. กำหนดค่าเริ่มต้นสำหรับรายงาน (เดือนปัจจุบัน/ปีปัจจุบัน)
// ----------------------------------------------------
$current_year = date('Y');
$current_month = date('m');

// รับค่าจากฟอร์ม POST หรือใช้ค่าเริ่มต้น
$selected_year = isset($_POST['report_year']) ? $conn->real_escape_string($_POST['report_year']) : $current_year;
$selected_month = isset($_POST['report_month']) ? $conn->real_escape_string($_POST['report_month']) : $current_month;

$income_data = [];
$total_income = 0;
$total_rent = 0; // สมมติว่ายอดรวมคือค่าเช่าหลัก (รวมค่าน้ำค่าไฟที่จ่ายมาด้วย)

// ----------------------------------------------------
// 2. ดึงข้อมูลรายรับทั้งหมดตามเดือนและปีที่เลือก
// ----------------------------------------------------

$sql_income = "
    SELECT 
        p.payment_date, p.amount_paid,
        i.invoice_id, i.issue_date,
        l.monthly_rent,
        r.room_number,
        t.first_name, t.last_name
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.invoice_id
    JOIN leases l ON i.lease_id = l.lease_id
    JOIN rooms r ON l.room_id = r.room_id
    JOIN tenants t ON l.tenant_id = t.tenant_id
    WHERE YEAR(p.payment_date) = '$selected_year' 
      AND MONTH(p.payment_date) = '$selected_month'
    ORDER BY p.payment_date ASC";

$result_income = $conn->query($sql_income);

if ($result_income && $result_income->num_rows > 0) {
    while ($row = $result_income->fetch_assoc()) {
        $income_data[] = $row;
        $total_income += $row['amount_paid'];
        // Note: ในระบบนี้เราสมมติว่า amount_paid คือยอดรวมค่าเช่า + ค่าน้ำไฟ
    }
}

$conn->close();

// เตรียมชื่อเดือนสำหรับแสดงผล
$month_name_th = [
    '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
    '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
    '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
];
$report_month_name = $month_name_th[$selected_month];

// เตรียมรายการปีสำหรับ Filter
$year_options = range($current_year, $current_year - 5); // แสดงปีปัจจุบันและย้อนหลัง 5 ปี
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานรายรับ</title>
    <?php echo $style_alerts; ?>
    <style>
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .summary-box { background-color: #e6ffe6; border: 2px solid #4CAF50; padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .summary-box h3 { color: #2e8b57; margin-top: 0; }
        .summary-box p { font-size: 1.2em; font-weight: bold; }
        .filter-form { display: flex; gap: 15px; align-items: flex-end; margin-bottom: 20px; }
        .filter-form label { font-weight: bold; }
        .filter-form select, .filter-form button { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; }
        .filter-form button { background-color: #007bff; color: white; cursor: pointer; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="container">
        <h2>💰 รายงานรายรับรวม (Revenue Report)</h2>
        
        <form action="income_report.php" method="POST" class="filter-form">
            <div>
                <label for="report_month">เดือน:</label>
                <select id="report_month" name="report_month" required>
                    <?php foreach ($month_name_th as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php if ($num == $selected_month) echo 'selected'; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="report_year">ปี:</label>
                <select id="report_year" name="report_year" required>
                    <?php foreach ($year_options as $year): ?>
                        <option value="<?php echo $year; ?>" <?php if ($year == $selected_year) echo 'selected'; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">ดูรายงาน</button>
        </form>

        <hr>

        <div class="summary-box">
            <h3>สรุปรายรับประจำเดือน: <?php echo $report_month_name . ' ' . $selected_year; ?></h3>
            <p>รวมรายรับทั้งหมด: <?php echo number_format($total_income, 2); ?> บาท</p>
        </div>

        <h3>รายละเอียดการชำระเงิน</h3>
        <table>
            <thead>
                <tr>
                    <th>วันที่รับเงิน</th>
                    <th>ใบแจ้งหนี้ #</th>
                    <th>ห้องที่</th>
                    <th>ผู้เช่า</th>
                    <th>จำนวนเงินที่ชำระ (฿)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($income_data)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">ไม่มีรายการรายรับที่บันทึกไว้ในเดือนนี้</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($income_data as $data): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($data['payment_date'])); ?></td>
                            <td><?php echo $data['invoice_id']; ?></td>
                            <td><?php echo $data['room_number']; ?></td>
                            <td><?php echo $data['first_name'] . ' ' . $data['last_name']; ?></td>
                            <td><?php echo number_format($data['amount_paid'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right" style="font-weight: bold;">ยอดรวมทั้งหมด:</td>
                    <td style="font-weight: bold;"><?php echo number_format($total_income, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>