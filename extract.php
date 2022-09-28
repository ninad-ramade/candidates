<?php
//ini_set('display_errors', 1);
include_once 'config.php';
session_start();
$resumeDir = 'profiles/unprocessed';
$processedResumeDir = 'profiles/processed';
$files = array_diff(scandir($resumeDir), ['.', '..']);
$allFiles = [];
$protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';

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
    $db->close();
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
function getLocationsFromContent($content) {
    $locations = ['Hyderabad', 'Banglore', 'Mumbai', 'Noida', 'Delhi', 'Calcutta', 'Chennai', 'Coimbatore', 'Gurgoan', 'Pune', 'NCR'];
    $resumeLocations = [];
    foreach($locations as $location) {
        $resumeLocations[] = !empty(strstr(strtolower($content), strtolower($location))) ? $location : '';
    }
    return array_values(array_filter($resumeLocations));
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
        header('Location: ' . $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'extract.php');
    }
} else if ($_POST['submit'] == 'Reprocess All') {
    $processedFiles = array_diff(scandir($processedResumeDir), ['.', '..']);
    $allowedExtensions = ['doc', 'docx'];
    foreach($processedFiles as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if(!in_array(strtolower($ext), $allowedExtensions)) {
            continue;
        }
        rename($processedResumeDir .'/'. $file, $resumeDir .'/'. $file);
        header('Location: ' . $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'extract.php');
    }
}
else {
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
        $locations = getLocationsFromContent($content);
        $currentLocation = !empty($locations) ? $locations[0] : '';
        $preferredLocations = !empty($locations) && count($locations) > 1 ? implode(", ", $locations) : '';
        if(!empty($email)) {
            $name = explode("@", $email);
            $resume = mysqli_real_escape_string($db, $protocol . "://" . $_SERVER['SERVER_NAME'] . baseurl . $processedResumeDir ."/". $file);
            $sql = "SELECT * FROM candidates WHERE email = '" . $email . "'";
            $result = $db->query($sql);
            $existingCandidate = mysqli_fetch_assoc($result);
            if(!empty($existingCandidate)) {
                $sql = "UPDATE candidates SET name = '" . $name[0] . "', mobile = '" . $phone . "', skills = '" . $skill . "', subskills = '" . $skill . "', currentLocation = '" . $currentLocation . "', preferredLocation = '" . $preferredLocations . "', resume = '" . $resume . "', status = 'Created' WHERE email = '" . $email . "'";
            } else {
                $sql = "INSERT INTO candidates (name, mobile, email, skills, subskills, currentLocation, preferredLocation, resume, status) VALUES ('" . $name[0] . "', '".$phone."', '".$email."', '".$skill."', '".$skill."', '".$currentLocation."', '".$preferredLocations."', '" . $resume . "', 'Created')";
            }
            try {
                if($db->query($sql) === TRUE) {
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
    echo 'Extracted all profiles successfully.';
    $db->close();
}
include 'header.php';
include 'menu.php';
 ?>
<h3>Extract Profiles</h3>
<form action="extract.php" method="post" enctype="multipart/form-data">
	<div class="row">
		<div class="col-lg-1">
			<label>Upload to unprocessed</label>
		</div>
		<div class="col-lg-2">
			<input type="file" name="resume" class="form-control" />
		</div>
		<div class="col-lg-5">
			<input type="submit" name="submit" class="btn btn-primary" value="Upload and process"> (Only doc, docx and zip allowed)
		</div>
	</div>
	<div class="row">
		<div class="col-lg-2">
			<input type="submit" name="submit" class="btn btn-primary" value="Reprocess All">
		</div>
	</div>
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
<tr><td><a href="<?php echo $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'profiles/unprocessed/' .$file['name']; ?>" target="blank"><?php echo $file['name']; ?></a></td>
<td><?php echo !empty($file['email']) ? $file['email'] : ''; ?></td>
<td><?php echo !empty($file['phone']) ? $file['phone'] : ''; ?></td>
<td><?php echo !empty($file['skills']) ? $file['skills'] : ''; ?></td>
<td><?php echo !empty($file['status']) ? $file['status'] : 'Pending'; ?></td></tr>
<?php } ?>
</table>
<?php include 'footer.php'; ?>