<?php
include "../config/database.php";
include "../includes/admin_header.php";

if(isset($_POST['submit'])){

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $id = $_SESSION['admin_id'];

    $conn->query("UPDATE admins SET password='$password' WHERE id='$id'");

    echo "Đổi mật khẩu thành công";
}
?>

<form method="POST">

    <input type="password" name="password" placeholder="Mật khẩu mới">

    <button name="submit">Đổi mật khẩu</button>

</form>