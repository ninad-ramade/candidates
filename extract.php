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
$resumeDir = 'profiles/unprocessed';
$processedResumeDir = 'profiles/processed';
$files = array_diff(scandir($resumeDir), ['.', '..']);
$allFiles = [];

function getSkills() {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM skills ORDER BY skill ASC";
    $result = $db->query($sql);
    $skills = [];
    if ($result->num_rows < 1) {
        return $skills;
    }
    while($row = $result->fetch_assoc()) {
        $skills[] = $row;
    }
    return $skills;
}
function read_docx($filename){
    
    $striped_content = '';
    $content = '';
    
    if(!$filename || !file_exists($filename)) return false;
    
    $zip = zip_open($filename);
    if (!$zip || is_numeric($zip)) return false;
    
    while ($zip_entry = zip_read($zip)) {
        
        if (zip_entry_open($zip, $zip_entry) == FALSE) continue;
        
        if (zip_entry_name($zip_entry) != "word/document.xml") continue;
        
        $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
        
        zip_entry_close($zip_entry);
    }
    zip_close($zip);
    $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
    $content = str_replace('</w:r></w:p>', "\r\n", $content);
    $striped_content = strip_tags($content);
    
    return $striped_content;
}
function getEmailFromContent($content) {
    preg_match_all("/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i", $content, $emails);
    $emails = filter_var_array($emails, FILTER_VALIDATE_EMAIL);
    $emails = array_filter($emails);
    $email = !empty($emails) ? $emails[0][0] : '';
    $emailParts = explode('.', $email);
    if(strlen($emailParts[count($emailParts)-1]) > 3) {
        $email = substr($email, 0, -1);
    }
    return $email;
}
function getSkillFromContent($content) {
    $allSkills = array_column(getSkills(), 'skill');
    $skills = [];
    foreach($allSkills as $eachSkill) {
        $skills[] = !empty(strstr(strtolower($content), strtolower($eachSkill))) ? $eachSkill : '';
    }
    return array_filter($skills);
}
function getPhoneFromContent($content) {
    preg_match_all("/[+91\s-]+[6-9][0-9]{9}/", $content, $phones);
    $phones = array_filter($phones);
    return !empty($phones) ? $phones[0][0] : '';
}
function sendEmail($email, $id) {
    $emailParts = explode("@", $email);
    $username = $emailParts[0];
    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->Mailer = "smtp";
    //$mail->SMTPDebug  = 1;
    $mail->SMTPAuth   = TRUE;
    $mail->SMTPSecure = "tls";
    $mail->Port       = 587;
    $mail->Host       = "smtp.gmail.com";
    $mail->Username   = "ninad.pegasusone@gmail.com";
    $mail->Password   = "tprpidykjnvrvjwf";
    $mail->IsHTML(true);
    $mail->AddAddress($email, $username);
    $mail->SetFrom("rtjobs@gmail.com", "RTJobs");
    $mail->AddReplyTo("rtjobs@gmail.com", "RTJobs");
    $mail->Subject = "RT Jobs Candidature";
    $content = 'Hi, ' . $username . ',<br/><br/>Please click below link to fill up your resume details for better opportunities from RAPID Jobs.<br/><br/><a href="http://' . $_SERVER['SERVER_NAME'] . baseurl . '?ce=' . base64_encode($email) . '&id=' . base64_encode($id) . '" target="blank">Click Here</a><br/><br/>Thanks<br/><br/>RT Jobs';
    $mail->MsgHTML($content);
    if(!$mail->Send()) {
        return false;
    }
    return true;
}
$db = new mysqli(servername, username, password, dbname);
foreach($files as $file) {
    $content = '';
    $eachFile = [];
    $eachFile['name'] = $file;
    if($_POST['submit'] == 'Submit') {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $filePath = $resumeDir . '/' . $file;
        $content = preg_replace("/[^a-zA-Z0-9\s+@.:]+/", "", file_get_contents($filePath));
        if($ext == 'docx') {
            $content = read_docx($filePath);
        }
        $email = getEmailFromContent($content);
        $phone = getPhoneFromContent($content);
        $skill = implode(", ", getSkillFromContent($content));
        if(!empty($email) && ($email == 'ninad.ramade@gmail.com')) {
            $sql = "INSERT INTO candidates (mobile, email, skills, resume, status) VALUES ('".$phone."', '".$email."', '".$skill."','http://" . $_SERVER['SERVER_NAME'] . baseurl . $processedResumeDir ."/". $file . "', 'Email sent')";
            if($db->query($sql) === TRUE) {
                if(sendEmail($email, $db->insert_id)) {
                    $eachFile['status'] = 'Email sent';
                }
            }
            rename($resumeDir .'/'. $file, $processedResumeDir .'/'. $file);
        }
        $eachFile['email'] = $email;
        $eachFile['phone'] = $phone;
        $eachFile['skills'] = $skill;
    }
    $allFiles[] = $eachFile;
}
$db->close();
 ?>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl; ?>">Resume Form</a>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl . 'report.php'; ?>">Resume List</a>
<form action="extract.php" method="post">
	<input type="submit" name="submit">
</form>
<table>
<tr>
	<td><strong>File</strong></td>
	<td><strong>Email</strong></td>
	<td><strong>Phone</strong></td>
	<td><strong>Skills</strong></td>
	<td><strong>Status</strong></td></tr>
<?php foreach($allFiles as $file) { ?>
<tr><td><?php echo $file['name']; ?></td>
<td><?php echo !empty($file['email']) ? $file['email'] : ''; ?></td>
<td><?php echo !empty($file['phone']) ? $file['phone'] : ''; ?></td>
<td><?php echo !empty($file['skills']) ? $file['skills'] : ''; ?></td>
<td><?php echo !empty($file['status']) ? $file['status'] : 'Pending'; ?></td></tr>
<?php } ?>
</table>