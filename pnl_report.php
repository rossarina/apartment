<?php
include 'config.php';
include 'header.php';

// ----------------------------------------------------
// 1. กำหนดค่าเริ่มต้นสำหรับรายงาน
// ----------------------------------------------------
$current_year = date('Y');
$current_month = date('m');

// รับค่าจากฟอร์ม POST หรือใช้ค่าเริ่มต้น
$selected_year = isset($_POST['report_year']) ? $conn->real_escape_string($_POST['report_year']) : $current_year;
$selected_month = isset($_POST['report_month']) ? $conn->real_escape_string($_POST['report_month']) : $current_month;

$total_income = 0;
$total_expense = 0;
$income_data = [];
$expense_data = [];

// ----------------------------------------------------
// 2. ดึงข้อมูลรายรับทั้งหมดตามเดือนและปีที่เลือก
// ----------------------------------------------------
$sql_income = "
    SELECT 
        p.payment_date, p.amount_paid, r.room_number, t.first_name
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
    }
}

// ----------------------------------------------------
// 3. ดึงข้อมูลรายจ่ายทั้งหมดตามเดือนและปีที่เลือก
// ----------------------------------------------------
$sql_expense = "
    SELECT 
        expense_date, category, description, amount, vendor
    FROM expenses
    WHERE YEAR(expense_date) = '$selected_year' 
      AND MONTH(expense_date) = '$selected_month'
    ORDER BY expense_date ASC";

$result_expense = $conn->query($sql_expense);
if ($result_expense && $result_expense->num_rows > 0) {
    while ($row = $result_expense->fetch_assoc()) {
        $expense_data[] = $row;
        $total_expense += $row['amount'];
    }
}

// ----------------------------------------------------
// 4. คำนวณกำไร/ขาดทุนสุทธิ
// ----------------------------------------------------
$net_profit = $total_income - $total_expense;
$profit_status = ($net_profit >= 0) ? 'กำไร' : 'ขาดทุน';


// เตรียมชื่อเดือนสำหรับแสดงผล
$month_name_th = [
    '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
    '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
    '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
];
$report_month_name = $month_name_th[$selected_month];
$year_options = range($current_year, $current_year - 5); 

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานกำไรขาดทุน</title>
    <?php echo $style_alerts; ?>
    <style>
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .filter-form { display: flex; gap: 15px; align-items: flex-end; margin-bottom: 20px; }
        .filter-form label { font-weight: bold; }
        .filter-form select, .filter-form button { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; }
        .filter-form button { background-color: #3f51b5; color: white; cursor: pointer; }
        
        .summary-box { padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .summary-profit { background-color: #e8f5e9; border: 2px solid #4CAF50; }
        .summary-loss { background-color: #ffebee; border: 2px solid #f44336; }
        .summary-box h3 { margin-top: 0; color: #333; }
        .summary-box p { font-size: 1.5em; font-weight: bold; }
        .profit-text { color: #2e7d32; }
        .loss-text { color: #d32f2f; }
        
        .section-header { background-color: #f2f2f2; padding: 8px; margin-top: 20px; border-radius: 4px; font-weight: bold; }
        .table-income th { background-color: #e6ffe6; }
        .table-expense th { background-color: #ffe6e6; }
        .text-income { color: #2e7d32; font-weight: bold; }
        .text-expense { color: #d32f2f; font-weight: bold; }
    </style>
</head>
<body>
    
    <?php echo $nav_menu; ?>

    <div class="container">
        <h2>📊 รายงานกำไรขาดทุน (Profit & Loss)</h2>
        <p>รายงานนี้แสดงรายรับรวมหักด้วยรายจ่ายรวม เพื่อคำนวณหาผลกำไรสุทธิประจำเดือน</p>
        
        <form action="pnl_report.php" method="POST" class="filter-form">
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

        <div class="summary-box <?php echo ($net_profit >= 0) ? 'summary-profit' : 'summary-loss'; ?>">
            <h3>สรุปผลประกอบการประจำเดือน: <?php echo $report_month_name . ' ' . $selected_year; ?></h3>
            <p>
                <?php echo $profit_status; ?> สุทธิ: 
                <span class="<?php echo ($net_profit >= 0) ? 'profit-text' : 'loss-text'; ?>">
                    <?php echo number_format(abs($net_profit), 2); ?> บาท
                </span>
            </p>
        </div>
        
        <div class="section-header">รายรับรวม (Total Income): <?php echo number_format($total_income, 2); ?> บาท</div>
        <table class="table-income">
            <thead>
                <tr>
                    <th>วันที่รับเงิน</th>
                    <th>ห้องที่</th>
                    <th>ผู้เช่า</th>
                    <th>จำนวนเงินที่ชำระ (฿)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($income_data)): ?>
                    <tr><td colspan="4" style="text-align: center;">ไม่มีรายการรายรับที่บันทึกไว้ในเดือนนี้</td></tr>
                <?php else: ?>
                    <?php foreach ($income_data as $data): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($data['payment_date'])); ?></td>
                            <td><?php echo $data['room_number']; ?></td>
                            <td><?php echo $data['first_name']; ?></td>
                            <td class="text-income"><?php echo number_format($data['amount_paid'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right" style="font-weight: bold;">ยอดรวมรายรับ:</td>
                    <td style="font-weight: bold;"><?php echo number_format($total_income, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="section-header">รายจ่ายรวม (Total Expense): <?php echo number_format($total_expense, 2); ?> บาท</div>
        <table class="table-expense">
            <thead>
                <tr>
                    <th>วันที่จ่าย</th>
                    <th>หมวดหมู่</th>
                    <th>รายละเอียด</th>
                    <th>ผู้รับเงิน</th>
                    <th>จำนวนเงินที่จ่าย (฿)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expense_data)): ?>
                    <tr><td colspan="5" style="text-align: center;">ไม่มีรายการรายจ่ายที่บันทึกไว้ในเดือนนี้</td></tr>
                <?php else: ?>
                    <?php foreach ($expense_data as $data): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($data['expense_date'])); ?></td>
                            <td><?php echo $data['category']; ?></td>
                            <td><?php echo $data['description']; ?></td>
                            <td><?php echo $data['vendor']; ?></td>
                            <td class="text-expense"><?php echo number_format($data['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right" style="font-weight: bold;">ยอดรวมรายจ่าย:</td>
                    <td style="font-weight: bold;"><?php echo number_format($total_expense, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>