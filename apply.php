<?php 
//ini_set('display_errors', 1);
include_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
$applicationId = !empty($_GET['id']) ? base64_decode($_GET['id']) : '';
$db = new mysqli(servername, username, password, dbname);
$sql = "SELECT * FROM applications WHERE id = " . $applicationId;
$result = $db->query($sql);
$application = mysqli_fetch_assoc($result);
$sql = "UPDATE applications set appliedOn = '" . date('Y-m-d H:i:s') . "', status = 'Applied' WHERE id = " . $applicationId;
if(!$db->query($sql) === TRUE) {
    echo 'Application failed. Please contact HR.';
    exit;
}
$subjectArray = explode(' ', $application['subject']);
$subjectArray[0] = 'Applied';
$subject = implode(" ", $subjectArray);
$recipient = 'rapid.jobs12@gmail.com';
$mail = new PHPMailer();
$mail->IsSMTP();
$mail->Mailer = "smtp";
//$mail->SMTPDebug  = 1;
$mail->SMTPAuth   = TRUE;
$mail->SMTPSecure = "tls";
$mail->Port       = 587;
$mail->Host       = "smtp.gmail.com";
$mail->Username   = $recipient;
$mail->Password   = "howzfglpuhfruwjy";
$mail->IsHTML(true);
$mail->AddAddress($recipient);
$mail->SetFrom($application['email'], $application['email']);
$mail->AddReplyTo($application['email']);
$mail->Subject = $subject;
$content = 'Hi,<br/><br/>I am interested for this position.<br/><br/>Thanks';
$mail->MsgHTML($content);
if(!$mail->Send()) {
    echo 'Application failed. Please contact HR.';
    exit;
}
echo 'Applied Successfully. This window will close automatically.';
?>
<script>
 setTimeout(window.close, 3000);
</script>