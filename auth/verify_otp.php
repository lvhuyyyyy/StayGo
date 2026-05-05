<?php
require_once '../config/database.php';
require_once __DIR__ . '/../includes/security.php';
include '../includes/header.php';

$email = $_GET['email'];

if(isset($_POST['otp'])){

$otp = $_POST['otp'];

$sql="SELECT * FROM users
WHERE email='$email'
AND otp_code='$otp'
AND otp_expire > NOW()";

$result=$conn->query($sql);

if($result->num_rows>0){

header("Location: reset_password.php?email=$email");

}else{

$msg="OTP sai ho?c h?t h?n";

}

}
?>

<link rel="stylesheet" href="/assets/css/otp.css">

<h2>Nhập mã xác nhận</h2>

<p>Xác nhận thời hạn trong vùng 15 phút</p>

<div id="timer">15:00</div>

<form method="POST">
    <?= csrf_field() ?>

<div class="otp">

<input maxlength="1" class="otp-input">
<input maxlength="1" class="otp-input">
<input maxlength="1" class="otp-input">
<input maxlength="1" class="otp-input">

</div>

<input type="hidden" name="otp" id="otp">

<button type="submit">Xác nhận</button>

</form>

<p><?php if(isset($msg)) echo $msg;?></p>

<script>

const inputs=document.querySelectorAll(".otp-input");

inputs.forEach((input,index)=>{

input.addEventListener("keyup",(e)=>{

if(e.target.value && index<3){

inputs[index+1].focus();

}

let code="";

inputs.forEach(i=>code+=i.value);

document.getElementById("otp").value=code;

});

});

let time=900;

setInterval(()=>{

let min=Math.floor(time/60);
let sec=time%60;

document.getElementById("timer").innerHTML=
min+":"+(sec<10?"0":"")+sec;

time--;

},1000);

</script>

<?php include 'includes/footer.php'; ?>



