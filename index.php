<?php
require_once 'vendor/autoload.php';

$resumeDir = 'profiles';
$files = array_diff(scandir($resumeDir), ['.', '..']);
$allFiles = [];

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
    return !empty($emails) ? $emails[0][0] : '';
}
function getPhoneFromContent($content) {
    preg_match_all("/^(?:(?:\+|0{0,2})91(\s*[\-]\s*)?\s?|[0]?)?[789]\d{9}$/", $content, $phones);
    $phones = array_filter($phones);
    return !empty($phones) ? $phones[0][0] : '';
}
foreach($files as $file) {
    $content = '';
    $eachFile = [];
    $eachFile['name'] = $file;
    if($_POST['submit'] == 'Submit') {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $filePath = $resumeDir . '/' . $file;
        $content = file_get_contents($filePath);
        $email = getEmailFromContent($content);
        $phone = getPhoneFromContent($content);
        if(empty($email)) {
            if($ext == 'docx') {
                $content = read_docx($filePath);
                $email = getEmailFromContent($content);
            }
        }
        if(empty($phone)) {
            if($ext == 'docx') {
                $content = read_docx($filePath);
                $phone = getPhoneFromContent($content);
            }
        }
        if(!empty($email) && $email == 'ninad.ramade@gmail.com') {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            mail($email, 'Test Email', 'Please click on below link to fill the form.\n<a href="http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] . '/candidateInfo.php" target="blank">Click here</a>', $headers);
            $eachFile['status'] = 'Email sent';
        }
        $eachFile['email'] = $email;
        $eachFile['phone'] = $phone;
    }
    $allFiles[] = $eachFile;
}
 ?>
<form action="/" method="post">
	<input type="submit" name="submit">
</form>
<table>
<tr>
	<td><strong>File</strong></td>
	<td><strong>Email</strong></td>
	<td><strong>Phone</strong></td>
	<td><strong>Status</strong></td></tr>
<?php foreach($allFiles as $file) { ?>
<tr><td><?php echo $file['name']; ?></td>
<td><?php echo !empty($file['email']) ? $file['email'] : ''; ?></td>
<td><?php echo !empty($file['phone']) ? $file['phone'] : ''; ?></td>
<td><?php echo !empty($file['status']) ? $file['status'] : 'Pending'; ?></td></tr>
<?php } ?>
</table>