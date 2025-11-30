<?php
// กำหนด HTML/CSS สำหรับเมนูและแจ้งเตือน
$nav_menu = '
<nav style="background-color: #333; padding: 10px 0; margin-bottom: 20px;">
    <ul style="list-style-type: none; margin: 0; padding: 0; overflow: hidden; display: flex; justify-content: flex-start; flex-wrap: wrap;">
        
        <li style="margin-right: 20px;"><a href="dashboard.php" style="color: white; text-decoration: none; padding: 10px 15px; display: block;">🏠 Dashboard</a></li>
        
        <li style="margin-right: 20px;"><a href="room_management.php" style="color: white; text-decoration: none; padding: 10px 15px; display: block;">🏨 จัดการห้องพัก</a></li>
        <li style="margin-right: 20px;"><a href="add_room.php" style="color: yellow; text-decoration: none; padding: 10px 15px; display: block;">+ เพิ่มห้องใหม่</a></li>
        
        <li style="margin-right: 20px;"><a href="tenant_management.php" style="color: white; text-decoration: none; padding: 10px 15px; display: block;">👥 จัดการผู้เช่า</a></li>
        <li style="margin-right: 20px;"><a href="create_lease.php" style="color: white; text-decoration: none; padding: 10px 15px; display: block;">📜 สร้างสัญญา</a></li>
        
        <li style="margin-right: 20px;"><a href="create_invoice.php" style="color: white; text-decoration: none; padding: 10px 15px; display: block;">📝 ออกใบแจ้งหนี้</a></li>
        <li style="margin-right: 20px;"><a href="update_payment.php" style="color: white; text-decoration: none; padding: 10px 15px; display: block;">💵 บันทึกชำระเงิน</a></li>
        
        <li style="margin-right: 20px;"><a href="add_meter_reading.php" style="color: white; text-decoration: none; padding: 10px 15px; display: block;">💡 บันทึกมิเตอร์</a></li>
        <li style="margin-right: 20px;"><a href="add_expense.php" style="color: white; text-decoration: none; padding: 10px 15px; display: block;">➕ บันทึกรายจ่าย</a></li>
        
        <li style="margin-right: 20px;"><a href="income_report.php" style="color: orange; text-decoration: none; padding: 10px 15px; display: block;">📊 รายงานรายรับ</a></li>
        <li style="margin-right: 20px;"><a href="expense_report.php" style="color: white; text-decoration: none; padding: 10px 15px; display: block;">📋 รายงานรายจ่าย</a></li>
        
    </ul>
</nav>
';

// กำหนด CSS พื้นฐานสำหรับข้อความแจ้งเตือน
$style_alerts = '
<style>
    .message-success { padding: 10px; border: 1px solid #4CAF50; background-color: #e6ffe6; color: #4CAF50; margin-bottom: 15px; font-weight: bold; }
    .message-error { padding: 10px; border: 1px solid #f44336; background-color: #ffe6e6; color: #f44336; margin-bottom: 15px; font-weight: bold; }
    .message-warning { padding: 10px; border: 1px solid #ff9800; background-color: #fff3e0; color: #ff9800; margin-bottom: 15px; font-weight: bold; }
    .text-right { text-align: right; }
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
    nav ul li a:hover { background-color: #575757; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>
';
?>