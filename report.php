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
function getCandidates($filterData = []) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM candidates";
    global $where;
    $where = [];
    if(!empty(array_filter($filterData))) {
        foreach($filterData as $filter => $value) {
            $innerWhere = [];
            if(!empty($value)) {
                if($filter == 'salaryFrom' || $filter == 'salaryTo') {
                    $innerWhere = $filter == 'salaryFrom' ? 'expectedCtc >= ' . $value : 'expectedCtc <= ' . $value;
                    array_push($where, '('. $innerWhere . ')');
                } else {
                    foreach($value as $eachval) {
                        $innerWhere[] = $filter . ' like "%'. $eachval .'%"';
                    }
                    array_push($where, '('. implode(" OR ", $innerWhere) . ')');
                }
            }
        }
    }
    if(!empty($where)) {
        $sql .= ' WHERE (' . implode(" AND ", array_filter($where)) . ')';
    }
    $result = $db->query($sql);
    $candidates = [];
    if ($result->num_rows < 1) {
        return $candidates;
    }
    while($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    return $candidates;
}
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
function getVendors() {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM vendors ORDER BY name ASC";
    $result = $db->query($sql);
    $vendors = [];
    if ($result->num_rows < 1) {
        return $vendors;
    }
    while($row = $result->fetch_assoc()) {
        $vendors[] = $row;
    }
    return $vendors;
}
function sendEmail($email, $name, $id) {
    $fromEmail = 'rapid.jobs12@gmail.com';
    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->Mailer = "smtp";
    //$mail->SMTPDebug  = 1;
    $mail->SMTPAuth   = TRUE;
    $mail->SMTPSecure = "tls";
    $mail->Port       = 587;
    $mail->Host       = "smtp.gmail.com";
    $mail->Username   = $fromEmail;
    $mail->Password   = "howzfglpuhfruwjy";
    $mail->IsHTML(true);
    $mail->AddAddress($email, $name);
    $mail->setFrom($fromEmail, "RTJobs");
    $mail->AddReplyTo($fromEmail, "RTJobs");
    $mail->Subject = "RT Jobs Candidature";
    $content = 'Hi, ' . $name . ',<br/><br/>Please click below link to fill up your resume details for better opportunities from RAPID Jobs.<br/><br/><a href="http://' . $_SERVER['SERVER_NAME'] . baseurl . '?ce=' . base64_encode($email) . '&id=' . base64_encode($id) . '" target="blank">Click Here</a><br/><br/>Thanks<br/><br/>RT Jobs';
    $mail->MsgHTML($content);
    if(!$mail->Send()) {
        return false;
    }
    return true;
}
function sendCustomEmail($email, $name, $applicationId, $subject, $body) {
    $fromEmail = 'rapid.jobs12@gmail.com';
    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->Mailer = "smtp";
    //$mail->SMTPDebug  = 1;
    $mail->SMTPAuth   = TRUE;
    $mail->SMTPSecure = "tls";
    $mail->Port       = 587;
    $mail->Host       = "smtp.gmail.com";
    $mail->Username   = $fromEmail;
    $mail->Password   = "howzfglpuhfruwjy";
    $mail->IsHTML(true);
    $mail->AddAddress($email, $name);
    $mail->setFrom($fromEmail, "RTJobs");
    $mail->AddReplyTo($fromEmail, "RTJobs");
    $mail->Subject = $subject;
    $content = 'Hi, ' . $name . ',<br/><br/>' . $body . '<br/><br/><a href="http://' . $_SERVER['SERVER_NAME'] . baseurl . 'apply.php?id=' . base64_encode($applicationId) . '" target="blank">Apply Now</a><br/><br/>Thanks<br/><br/>RT Jobs';
    $mail->MsgHTML($content);
    if(!$mail->Send()) {
        return $mail->ErrorInfo;
    }
    return true;
}
$candidates = [];
$skills = getSkills();
$vendors = getVendors();
$locations = ['Hyderabad', 'Banglore', 'Mumbai', 'Noida', 'Delhi', 'Calcutta', 'Chennai', 'Coimbatore', 'Gurgoan', 'Pune', 'NCR'];
if(!empty($_POST['submit'])) {
    if($_POST['submit'] == 'Reset') {
        $_POST = $data = [];
        header('Location: http://' . $_SERVER['SERVER_NAME'] . baseurl . 'report.php');
    }
    else {
        $data = $_POST;
        unset($data['submit']);
        unset($data['customBody']);
        unset($data['vendor']);
        $candidates = getCandidates($data);
        if($_POST['submit'] == 'Send email to candidates to update') {
            foreach ($candidates as $candidate) {
                if(sendEmail($candidate['email'], $candidate['name'], $candidate['id'])) {
                    $db = new mysqli(servername, username, password, dbname);
                    $sql = "UPDATE candidates SET status = 'Email sent' WHERE id = " . $candidate['id'];
                    $db->query($sql);
                }
            }
            echo 'Email sent successfully';
        } else if($_POST['submit'] == 'Send custom email') {
            $failedEmails = [];
            foreach ($candidates as $candidate) {
                $db = new mysqli(servername, username, password, dbname);
                $subject = "Profile for " . implode(", ", $data['skills']);
                if(!empty($data['overallExperience'])) {
                    $subject .= " with " . implode(", ", $data['overallExperience']) . " Years experience";
                }
                if(!empty($data['preferredLocation'])) {
                    $subject .= " at " . implode(", ", $data['preferredLocation']) . " location";
                }
                $sql = "INSERT INTO applications (vendorId, candidateId, email, emailSentOn, subject, status) VALUES (" . $_POST['vendor'] . ", " . $candidate['id'] . ", '" . $candidate['email'] . "', '" . date('Y-m-d H:i:s') . "', '" . $subject . "', 'Email sent')";
                if($db->query($sql) === TRUE) {
                    $customEmailResponse = sendCustomEmail($candidate['email'], $candidate['name'], $db->insert_id, $subject, $_POST['customBody']);
                    if($customEmailResponse !== true) {
                        $failedEmails[] = $candidate['email'] . ' Error: ' . $customEmailResponse;
                    }
                }
            }
            if(!empty($failedEmails)) {
                echo 'Could not send custom email to ' . implode(", ", $failedEmails);
            } else {
                echo 'All custom emails sent successfully';
            }
        }
    }
    $columns = !empty($candidates) ? array_keys($candidates[0]) : [];
}
?>
<script>
function validateCustomEmail(e) {
	if(document.getElementById("customBody").value == '') {
		alert('Please enter email body.');
		e.preventDefault();
		return false;
	}
	if(document.getElementById("vendor").value == '') {
		alert('Please select vendor.');
		e.preventDefault();
		return false;
	}
	return true;
}
</script>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl; ?>">Resume Form</a>

<form action="report.php" method="post">
<input type="submit" name="submit" value="Send email to candidates to update" />
<div>
    <textarea id="customBody" name="customBody" placeholder="Email body"></textarea>
    <select id="vendor" name="vendor">
    	<option value="">Select</option>
    	<?php foreach($vendors as $vendor) { ?>
    		<option value="<?php echo $vendor['id']; ?>"><?php echo $vendor['name']; ?></option>
    	<?php } ?>
    </select>
    <input type="submit" name="submit" onclick="validateCustomEmail(event)" value="Send custom email" />
</div>
<h3>Resume List</h3>
<div><label for="skills">Skills</label>
<select required id="skills" name="skills[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($skills as $eachskill) { ?>
<option value="<?php echo $eachskill['skill']; ?>" <?php echo !empty($_POST['skills']) ? (in_array($eachskill['skill'], $_POST['skills']) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
<?php } ?>
</select>
<label for="subskills">Sub Skills</label>
<select id="subskills" name="subskills[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($skills as $eachskill) { ?>
<option value="<?php echo $eachskill['skill']; ?>" <?php echo !empty($_POST['subskills']) ? (in_array($eachskill['skill'], $_POST['subskills']) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
<?php } ?>
</select>
<label for="overallExperience">Overall Exp</label>
<select id="overallExperience" name="overallExperience[]" multiple="multiple">
<option value="">Select</option>
<option value="0-3" <?php echo !empty($_POST['overallExperience']) ? (in_array('0-3', $_POST['overallExperience']) ? 'selected="selected"' : '') : ''; ?>>0-3</option>
<option value="4-7" <?php echo !empty($_POST['overallExperience']) ? (in_array('4-7', $_POST['overallExperience']) ? 'selected="selected"' : '') : ''; ?>>4-7</option>
<option value="8-10" <?php echo !empty($_POST['overallExperience']) ? (in_array('8-10', $_POST['overallExperience']) ? 'selected="selected"' : '') : ''; ?>>8-10</option>
<option value=">10" <?php echo !empty($_POST['overallExperience']) ? (in_array('>10', $_POST['overallExperience']) ? 'selected="selected"' : '') : ''; ?>>>10</option>
</select>

<label for="relevantExperience">Relevant Exp</label>
<select id="relevantExperience" name="relevantExperience[]" multiple="multiple">
<option value="">Select</option>
<option value="0-3" <?php echo !empty($_POST['relevantExperience']) ? (in_array('0-3', $_POST['relevantExperience']) ? 'selected="selected"' : '') : ''; ?>>0-3</option>
<option value="4-7" <?php echo !empty($_POST['relevantExperience']) ? (in_array('4-7', $_POST['relevantExperience']) ? 'selected="selected"' : '') : ''; ?>>4-7</option>
<option value="8-10" <?php echo !empty($_POST['relevantExperience']) ? (in_array('8-10', $_POST['relevantExperience']) ? 'selected="selected"' : '') : ''; ?>>8-10</option>
<option value=">10" <?php echo !empty($_POST['relevantExperience']) ? (in_array('>10', $_POST['relevantExperience']) ? 'selected="selected"' : '') : ''; ?>>>10</option>
</select>

<label for="preferredLocation">Preferred Loc</label>
<select id="preferredLocation" name="preferredLocation[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($locations as $location) { ?>
<option value="<?php echo $location; ?>" <?php echo !empty($_POST['preferredLocation']) ? (in_array($location, $_POST['preferredLocation']) ? 'selected="selected"' : '') : ''; ?>><?php echo $location; ?></option>
<?php } ?>
<option value="">Any</option>
</select>
<label for="preferredLocation">Status</label>
<select id="status" name="status[]" multiple="multiple">
	<option value="">Select</option>
	<option value="Created">Created</option>
	<option value="Email sent">Email sent</option>
	<option value="Updated by Candidate">Updated by Candidate</option>
	<option value="Updated by Admin">Updated by Admin</option>
</select>
<label for="preferredLocation">Salary Range (Lacs)</label>
<input name="salaryFrom" id="salaryFrom" type="text" placeholder="From" value="<?php echo !empty($_POST['salaryFrom']) ? $_POST['salaryFrom'] : ''; ?>" />
<input name="salaryTo" id="salaryTo" type="text" placeholder="To" value="<?php echo !empty($_POST['salaryTo']) ? $_POST['salaryTo'] : ''; ?>" />
</div>
<div><input type="submit" name="submit" value="Search" /><input type="submit" name="submit" value="Reset" /></div>
</form>
<table>
<tr>
	<?php foreach($columns as $column){ ?>
	<th><?php echo ucwords(strtolower($column)); ?></th>
	<?php } ?>
</tr>
<?php foreach($candidates as $candidate) { ?>
<tr>
	<?php foreach($columns as $column){ ?>
	<td><?php 
	if($column == 'servingNotice') {
	    echo !empty($candidate[$column]) ? ($candidate[$column] == 1 ? 'Yes' : 'No') : '';
	} else {
	    echo $column == 'resume' ? '<a href="'. $candidate[$column]. '" target="blank" >'. pathinfo($candidate[$column], PATHINFO_BASENAME) .'</a>' : $candidate[$column];
	}
    ?></td>
	<?php } ?>
	<td><a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl . '?id=' . base64_encode($candidate['id']); ?>" class="button">Edit</a></td>
</tr>
<?php } ?>
</table>