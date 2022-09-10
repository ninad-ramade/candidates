<?php
//ini_set('display_errors', 1);
include_once 'config.php';
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
if($_POST['submit'] == 'Upload and process') {
    $allowedFiles = ['doc', 'docx', 'zip'];
    $_POST['resume'] = '';
    if(!empty($_FILES['resume']['name'])) {
        $resume = $_FILES['resume'];
        $ext = pathinfo($resume['name'], PATHINFO_EXTENSION);
        if(!in_array($ext, $allowedFiles)) {
            echo 'Invalid file type. Please upload ' . implode(', ', $allowedFiles) . ' only.';
            exit;
        }
        $target_file = 'profiles/unprocessed/' . basename($resume["name"]);
        if(!move_uploaded_file($resume["tmp_name"], $target_file)) {
            echo 'Files uploa failed.';
            exit;
        }
        if(strtolower($ext) == 'zip') {
            $zip = new ZipArchive;
            $zipFile = $zip->open($target_file);
            if ($zipFile === true) {
                $zip->extractTo('profiles/unprocessed/');
                $zip->close();
                unlink($target_file);
            }
        }
        echo 'Files uploaded successfully. Reloading...';
        header('Location: http://' . $_SERVER['SERVER_NAME'] . baseurl . 'extract.php');
    }
} else {
    $db = new mysqli(servername, username, password, dbname);
    $allowedExtensions = ['doc', 'docx'];
    foreach($files as $file) {
        $content = '';
        $eachFile = [];
        $eachFile['name'] = $file;
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if(!in_array(strtolower($ext), $allowedExtensions)) {
            continue;
        }
        $filePath = $resumeDir . '/' . $file;
        $content = preg_replace("/[^a-zA-Z0-9\s+@.:]+/", "", file_get_contents($filePath));
        if($ext == 'docx') {
            $content = read_docx($filePath);
        }
        $email = getEmailFromContent($content);
        $phone = getPhoneFromContent($content);
        $skill = implode(", ", getSkillFromContent($content));
        if(!empty($email)) {
            $name = explode("@", $email);
            $sql = "INSERT INTO candidates (name, mobile, email, skills, subskills, resume, status) VALUES ('" . $name[0] . "', '".$phone."', '".$email."', '".$skill."', '".$skill."', 'http://" . $_SERVER['SERVER_NAME'] . baseurl . $processedResumeDir ."/". $file . "', 'Created')";
            try {
                if($db->query($sql) === TRUE) {
                    /* if(sendEmail($email, $db->insert_id)) {
                        $eachFile['status'] = 'Email sent';
                    } */
                    $eachFile['status'] = 'Created';
                    rename($resumeDir .'/'. $file, $processedResumeDir .'/'. $file);
                } else {
                    echo $db->error;
                }
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
        $eachFile['email'] = $email;
        $eachFile['phone'] = $phone;
        $eachFile['skills'] = $skill;
        $allFiles[] = $eachFile;
    }
    $db->close();
}
 ?>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl; ?>">Resume Form</a>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl . 'report.php'; ?>">Resume List</a>
<form action="extract.php" method="post" enctype="multipart/form-data">
	<label>Upload to unprocessed</label> <input type="file" name="resume" />
	<input type="submit" name="submit" value="Upload and process"> (Only doc, docx and zip allowed)
</form>
<!-- <form action="extract.php" method="post">
	<input type="submit" name="submit" value="Start Extraction">
</form> -->
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