<?php
$servername = "localhost"; 
$username = "root";       // **ใส่ชื่อผู้ใช้ MySQL ของคุณ**
$password = "";           // **ใส่รหัสผ่าน MySQL ของคุณ**
$dbname = "apartment_db"; // **ใส่ชื่อฐานข้อมูลที่คุณสร้างไว้**

// สร้างการเชื่อมต่อ (using mysqli)
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8"); // ตั้งค่ารองรับภาษาไทย

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>