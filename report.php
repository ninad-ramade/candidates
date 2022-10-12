<?php
ini_set('display_errors', 1);
include_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
session_start();
function getCandidates($filterData = []) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM candidates";
    global $where;
    $where = [];
    if(!empty(array_filter($filterData['skills']))) {
        $skills = getSkills($filterData['skills']);
        $skills = getSkills(null, array_column($skills, 'groupParent'));
        $filterData['skills'] = array_column($skills, 'id');
    }
    /* if(!empty($filterData['subskills'])) {
        $skills = getSkills($filterData['subskills']);
        $skills = getSkills(null, array_column($skills, 'groupParent'));
        $filterData['subskills'] = array_column($skills, 'skill');
    } */
    if(!empty(array_filter($filterData))) {
        foreach($filterData as $filter => $value) {
            $innerWhere = [];
            if(!empty($value)) {
                if($filter == 'salaryFrom' || $filter == 'salaryTo') {
                    $innerWhere = $filter == 'salaryFrom' ? 'expectedCtc >= ' . $value : 'expectedCtc <= ' . $value;
                    array_push($where, '('. $innerWhere . ')');
                } else if ($filter == 'overallExperienceFrom' || $filter == 'overallExperienceTo') {
                    $innerWhere = $filter == 'overallExperienceFrom' ? 'overallExperience >= ' . $value : 'overallExperience <= ' . $value;
                    array_push($where, '('. $innerWhere . ')');
                } else if ($filter == 'relevantExperienceFrom' || $filter == 'relevantExperienceTo') {
                    $innerWhere = $filter == 'relevantExperienceFrom' ? 'relevantExperience >= ' . $value : 'relevantExperience <= ' . $value;
                    array_push($where, '('. $innerWhere . ')');
                }
                else {
                    foreach($value as $eachval) {
                        $innerWhere[] = $filter . ' like "%,'. $eachval .',%"';
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
    $sr = 1;
    $resultSkills = [];
    $resultLocations = [];
    $resultQualifications = [];
    while($row = $result->fetch_assoc()) {
        unset($row['subskills']);
        $row['skills'] = array_filter(explode(",", $row['skills']));
        $resultSkills = array_merge($resultSkills, $row['skills']);
        if(!empty($row['currentLocation'])) {
            $row['currentLocation'] = array_filter(explode(",", $row['currentLocation']));
            $row['preferredLocation'] = array_filter(explode(",", $row['preferredLocation']));
            $resultLocations = array_merge($resultLocations, $row['preferredLocation']);
        }
        if(!empty($row['education'])) {
            $row['education'] = array_filter(explode(",", $row['education']));
            $resultQualifications = array_merge($resultQualifications, $row['education']);
        }
        $row = array_merge(['sr' => $sr], $row);
        $candidates[] = $row;
        $sr++;
    }
    if(!empty($resultSkills)) {
        $sql = "SELECT * FROM skills WHERE id IN (" . implode(",", array_unique($resultSkills)) . ")";
        $result = $db->query($sql);
        $finalSkills = [];
        while($row = $result->fetch_assoc()) {
            $finalSkills[$row['id']] = $row['skill'];
        }
    }
    if(!empty($resultLocations)) {
        $sql = "SELECT * FROM locations WHERE id IN (" . implode(",", array_unique($resultLocations)) . ")";
        $result = $db->query($sql);
        $finalLocations = [];
        while($row = $result->fetch_assoc()) {
            $finalLocations[$row['id']] = $row['location'];
        }
    }
    if(!empty($resultQualifications)) {
        $sql = "SELECT * FROM qualifications WHERE id IN (" . implode(",", array_unique($resultQualifications)) . ")";
        $result = $db->query($sql);
        $finalQualifications = [];
        while($row = $result->fetch_assoc()) {
            $finalQualifications[$row['id']] = $row['qualification'];
        }
    }
    $db->close();
    return ['candidates' => $candidates, 'skills' => $finalSkills, 'locations' => $finalLocations, 'qualifications' => $finalQualifications];
}
function getSkills($id = null, $groupParent = null) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM skills";
    if(!empty($id)) {
        $sql .= " WHERE id IN (" . implode(",", array_filter($id)) . ")";
    }
    if(!empty($groupParent)) {
        $sql .= " WHERE groupParent IN (" . implode(",", array_filter($groupParent)) . ")";
    }
    $sql .= " ORDER BY skill ASC";
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
function getLocations($id = null) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM locations";
    if(!empty($id)) {
        $sql .= " WHERE id IN (" . implode(",", array_filter($id)) . ")";
    }
    $sql .= " ORDER BY location ASC";
    $result = $db->query($sql);
    $locations = [];
    if ($result->num_rows < 1) {
        return $locations;
    }
    while($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
    $db->close();
    return $locations;
}
function getQualifications($id = null) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM qualifications";
    if(!empty($id)) {
        $sql .= " WHERE id IN (" . implode(",", array_filter($id)) . ")";
    }
    $sql .= " ORDER BY qualification ASC";
    $result = $db->query($sql);
    $qualifications = [];
    if ($result->num_rows < 1) {
        return $qualifications;
    }
    while($row = $result->fetch_assoc()) {
        $qualifications[] = $row;
    }
    $db->close();
    return $qualifications;
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
    $db->close();
    return $vendors;
}
function sendEmail($email, $name, $id, $body) {
    $fromEmail = $_SESSION['user']['email'];
    $mail = new PHPMailer();
    $mail->setFrom($fromEmail, "RTJobs");
    $mail->AddAddress($email, $name);
    $mail->AddReplyTo($fromEmail, "RTJobs");
    $mail->IsHTML(true);
    $mail->Subject = "RT Jobs Candidature";
    $content = 'Hi, ' . $name . ',<br/><br/>' . $body . '<br/><br/>Please click below link to fill up your resume details for better opportunities from RAPID Jobs.<br/><br/><a href="' . $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . '?ce=' . base64_encode($email) . '&id=' . base64_encode($id) . '" target="blank">Click Here</a><br/><br/>Thanks<br/><br/>RT Jobs';
    $mail->Body = $content;
    if(!$mail->Send()) {
        return false;
    }
    return true;
}
function sendCustomEmail($email, $name, $applicationId, $subject, $body) {
    $fromEmail = $_SESSION['user']['email'];
    $mail = new PHPMailer();
    $mail->setFrom($fromEmail, "RTJobs");
    $mail->AddAddress($email, $name);
    $mail->AddReplyTo($fromEmail, "RTJobs");
    $mail->IsHTML(true);
    $mail->Subject = $subject;
    $content = 'Hi, ' . $name . ',<br/><br/>' . $body . '<br/><br/><a href="' . $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'apply.php?id=' . base64_encode($applicationId) . '" target="blank">Apply Now</a><br/><br/>Thanks<br/><br/>RT Jobs';
    $mail->Body = $content;
    if(!$mail->Send()) {
        return $mail->ErrorInfo;
    }
    return true;
}
$candidates = [];
$candidateSkills = [];
$candidateLocations = [];
$skills = getSkills();
$vendors = getVendors();
$protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
$locations = getLocations();
if(!empty($_POST['submit'])) {
    if($_POST['submit'] == 'Reset') {
        $_POST = $data = [];
        header('Location: ' . $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'report.php');
    }
    else {
        $data = $_POST;
        unset($data['submit']);
        unset($data['customBody']);
        unset($data['vendor']);
        $db = new mysqli(servername, username, password, dbname);
        $candidatesData = getCandidates($data);
        $candidates = $candidatesData['candidates'];
        $candidateSkills = $candidatesData['skills'];
        $candidateLocations = $candidatesData['locations'];
        $candidateQualifications = $candidatesData['qualifications'];
        if($_POST['submit'] == 'Send email to candidates to update') {
            foreach ($candidates as $candidate) {
                if(sendEmail($candidate['email'], $candidate['name'], $candidate['id'], $_POST['customBody'])) {
                    $sql = "UPDATE candidates SET status = 'Email sent' WHERE id = " . $candidate['id'];
                    $db->query($sql);
                }
            }
            echo 'Email sent successfully';
        } else if($_POST['submit'] == 'Send custom email') {
            $failedEmails = [];
            foreach ($candidates as $candidate) {
                $subject = "Profile for " . implode(", ", array_intersect_key($candidateSkills, array_flip($data['skills'])));
                if(!empty($data['overallExperience'])) {
                    $subject .= " with " . implode(", ", $data['overallExperience']) . " Years experience";
                }
                if(!empty($data['preferredLocation'])) {
                    $subject .= " at " . implode(", ", array_intersect_key($candidateLocations, array_flip($data['preferredLocation']))) . " location";
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
        $db->close();
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
function validateEmail(e) {
	if(document.getElementById("customBody").value == '') {
		alert('Please enter email body.');
		e.preventDefault();
		return false;
	}
	return true;
}
</script>
<?php 
include 'header.php';
include 'menu.php'; ?>

<form action="report.php" method="post">
	<div class="row">
        <div class="col-lg-2">
            <textarea id="customBody" name="customBody" class="form-control" placeholder="Email body"></textarea>
       	</div>
       	<div class="col-lg-2">
            <select id="vendor" name="vendor" class="form-control">
            	<option value="">Select</option>
            	<?php foreach($vendors as $vendor) { ?>
            		<option value="<?php echo $vendor['id']; ?>"><?php echo $vendor['name']; ?></option>
            	<?php } ?>
            </select>
       	</div>
       	<div class="col-lg-4">
            <input type="submit" name="submit" class="btn btn-primary" onclick="validateCustomEmail(event)" value="Send custom email" />
            <input type="submit" name="submit" class="btn btn-primary" onclick="validateEmail(event)" value="Send email to candidates to update" />
        </div>
  	</div>
<h3>Resume List</h3>
<div class="row">
	<div class="col-lg-2">
    	<label for="skills">Skills/Keywords</label>
        <select required id="skills" name="skills[]" multiple="multiple" class="form-control">
        <option value="">Select</option>
        <?php foreach($skills as $eachskill) { ?>
        <option value="<?php echo $eachskill['id']; ?>" <?php echo !empty($_POST['skills']) ? (in_array($eachskill['groupParent'], $_POST['skills']) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
        <?php } ?>
        </select>
   	</div>
   	<?php /* ?>
	<div class="col-lg-2">
    	<label for="subskills">Sub Skills</label>
        <select id="subskills" name="subskills[]" multiple="multiple" class="form-control">
        <option value="">Select</option>
        <?php foreach($skills as $eachskill) { ?>
        <option value="<?php echo $eachskill['id']; ?>" <?php echo !empty($_POST['subskills']) ? (in_array($eachskill['groupParent'], $_POST['subskills']) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
        <?php } ?>
        </select>
   	</div>
   	<?php */ ?>
	<div class="col-lg-1">
		<label>Overall Exp</label>
        <input name="overallExperienceFrom" id="overallExperienceFrom" class="form-control" type="number" step="1" min="0" placeholder="From" value="<?php echo !empty($_POST['overallExperienceFrom']) ? $_POST['overallExperienceFrom'] : ''; ?>" />
		<input name="overallExperienceTo" id="overallExperienceTo" class="form-control" type="number" step="1" min="0" placeholder="To" value="<?php echo !empty($_POST['overallExperienceTo']) ? $_POST['overallExperienceTo'] : ''; ?>" />
   	</div>
	<div class="col-lg-1">
        <label for="relevantExperience">Relevant Exp</label>
        <input name="relevantExperienceFrom" id="relevantExperienceFrom" class="form-control" type="number" step="1" min="0" placeholder="From" value="<?php echo !empty($_POST['relevantExperienceFrom']) ? $_POST['relevantExperienceFrom'] : ''; ?>" />
		<input name="relevantExperienceTo" id="relevantExperienceTo" class="form-control" type="number" step="1" min="0" placeholder="To" value="<?php echo !empty($_POST['relevantExperienceTo']) ? $_POST['relevantExperienceTo'] : ''; ?>" />
	</div>
	<div class="col-lg-1">
		<label for="preferredLocation">Preferred Loc</label>
        <select id="preferredLocation" name="preferredLocation[]" multiple="multiple" class="form-control">
        <option value="">Select</option>
        <?php foreach($locations as $location) { ?>
        <option value="<?php echo $location['id']; ?>" <?php echo !empty($_POST['preferredLocation']) ? (in_array($location['id'], $_POST['preferredLocation']) ? 'selected="selected"' : '') : ''; ?>><?php echo $location['location']; ?></option>
        <?php } ?>
        <option value="">Any</option>
        </select>
  	</div>
	<div class="col-lg-2">
		<label for="preferredLocation">Status</label>
    	<select id="status" name="status[]" multiple="multiple" class="form-control">
    	<option value="">Select</option>
    	<option value="Created">Created</option>
    	<option value="Email sent">Email sent</option>
    	<option value="Updated by Candidate">Updated by Candidate</option>
    	<option value="Updated by Admin">Updated by Admin</option>
    	</select>
  	</div>
	<div class="col-lg-2">
    	<label>Salary Range (Lacs)</label>
    	<input name="salaryFrom" id="salaryFrom" class="form-control" type="text" placeholder="From" value="<?php echo !empty($_POST['salaryFrom']) ? $_POST['salaryFrom'] : ''; ?>" />
		<input name="salaryTo" id="salaryTo" class="form-control" type="text" placeholder="To" value="<?php echo !empty($_POST['salaryTo']) ? $_POST['salaryTo'] : ''; ?>" />
	</div>
</div>
<div class="row actionRow">
	<div class="col-lg-2">
		<input type="submit" name="submit" class="btn btn-primary" value="Search" />
		<input type="submit" name="submit" class="btn btn-primary" value="Reset" />
	</div>
</div>
</form>
<div class="row">
<table>
<tr>
	<?php foreach($columns as $column){ if($column == 'id') {continue;} ?>
	<th><?php echo ucwords(strtolower($column)); ?></th>
	<?php } ?>
</tr>
<?php foreach($candidates as $candidate) { ?>
<tr>
	<?php foreach($columns as $column){ if($column == 'id') {continue;}?>
	<td><?php 
	if($column == 'servingNotice') {
	    echo !empty($candidate[$column]) ? ($candidate[$column] == 1 ? 'Yes' : 'No') : '';
	} else if ($column == 'education') {
	    echo !empty($candidate[$column]) ? implode(", ", array_intersect_key($candidateQualifications, array_flip($candidate[$column]))) : '';
	}
	else if ($column == 'skills') {
	    echo implode(", ", array_intersect_key($candidateSkills, array_flip($candidate[$column])));
	} else if ($column == 'currentLocation' || $column == 'preferredLocation') {
	    echo !empty($candidate[$column]) ? implode(", ", array_intersect_key($candidateLocations, array_flip($candidate[$column]))) : '';
	} else {
	    echo $column == 'resume' ? '<a href="'. $candidate[$column]. '" target="blank" >'. pathinfo($candidate[$column], PATHINFO_BASENAME) .'</a>' : $candidate[$column];
	}
    ?></td>
	<?php } ?>
	<td><a class="btn btn-primary" href="<?php echo $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . '?id=' . base64_encode($candidate['id']); ?>" class="button">Edit</a></td>
</tr>
<?php } ?>
</table>
</div>
<?php include 'footer.php'; ?>