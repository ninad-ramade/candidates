<html>
<head>
<title>Application</title>
</head>
<body>
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
$sql = "SELECT applications.*, users.email as adminEmail FROM applications LEFT JOIN users ON applications.emailSentBy = users.id WHERE applications.id = " . $applicationId;
$result = $db->query($sql);
$application = mysqli_fetch_assoc($result);
$sql = "UPDATE applications set appliedOn = '" . date('Y-m-d H:i:s') . "', status = 'Applied' WHERE id = " . $applicationId;
if(!$db->query($sql) === TRUE) {
    echo 'Application failed. Please contact HR.';
    exit;
}
$sql = "SELECT recruiter.emailid FROM recruiter LEFT JOIN vendors ON vendors.recruiter_id = recruiter.recruiter_id WHERE vendors.id = " . $application['vendorId'];
$result = $db->query($sql);
$recruiterEmail = mysqli_fetch_assoc($result);
$sql = "SELECT resume FROM candidates WHERE id = " . $application['candidateId'];
$result = $db->query($sql);
$candidate = mysqli_fetch_assoc($result);
var_dump((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'], pathinfo($candidate['resume'], PATHINFO_DIRNAME));exit;
$subjectArray = explode(' ', $application['subject']);
$subjectArray[0] = 'Applied';
$subject = implode(" ", $subjectArray);
$recipient = $recruiterEmail['emailid'];
$mail = new PHPMailer();
$mail->IsHTML(true);
$mail->AddAddress($recipient);
$mail->SetFrom($application['email'], $application['email']);
$mail->addCC($application['adminEmail']);
$mail->AddReplyTo($application['email']);
$mail->Subject = $subject;
$content = 'Hi,<br/><br/>I am interested for this position.<br/><br/>Thanks';
$mail->MsgHTML($content);
if(!$mail->Send()) {
    echo 'Application failed as email not sent. Please contact HR.';
    exit;
}
echo 'Applied Successfully. You may close this window.';
?>
</body>
</html>