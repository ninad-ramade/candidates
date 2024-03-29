<?php
//ini_set('display_errors', 1);
include_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
session_start();
$start = 0;
$limit = 20;
function getCandidates($filterData = [], $start, $limit, $action) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM candidates";
    global $where;
    $where = [];
    if(!empty($filterData['skills']) && !empty(array_filter($filterData['skills']))) {
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
            if($filter == 'subskills')
                continue;
            
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
                    if(is_array($value)) {
                        foreach($value as $eachval) {
                            $innerWhere[] = $filter == 'status' ? $filter . ' IN ("' . implode('","', $value) . '")' : $filter . ' like "%,'. $eachval .',%"';
                        }
                    } else {
                        $innerWhere[] = $filter . ' like "%'. $value .'%"';
                    }
                    $implodeOperator = !empty($filterData['subskills']) ? ' AND ' : ' OR ';
                    array_push($where, '('. implode($implodeOperator, $innerWhere) . ')');
                }
            }
        }
        if($action == 'Send candidate update') {
            $where[] = '(emailSentOn IS NULL OR emailSentOn < now() - interval 1 week)';
        }
    }
    if(!empty($where)) {
        $sql .= ' WHERE (' . implode(" AND ", array_filter($where)) . ')';
    }
    $sql .= ' ORDER BY id DESC';
    $allSql = $sql;
    $allSql .= ' LIMIT ' . $start . ', 5000';
    $countResult = $db->query($allSql);
    $sql .= ' LIMIT ' . $start . ', ' . $limit;
    $result = $db->query($sql);
    $db->close();
    $candidates = [];
    $allCandidates = [];
    if ($result->num_rows < 1) {
        return $candidates;
    }
    $resultSkills = [];
    $resultLocations = [];
    $resultQualifications = [];
    $sr = $start + 1;
    while($row = $result->fetch_assoc()) {
        unset($row['subskills']);
        if(!preg_match("/[a-z]/i", $row['skills'])){
            $row['skills'] = array_filter(explode(",", $row['skills']));
            $resultSkills = array_merge($resultSkills, $row['skills']);
        }
        if(!empty($row['currentLocation']) && !preg_match("/[a-z]/i", $row['currentLocation'])) {
            $row['currentLocation'] = array_filter(explode(",", $row['currentLocation']));
            if(!preg_match("/[a-z]/i", $row['preferredLocation'])) {
                $row['preferredLocation'] = array_filter(explode(",", $row['preferredLocation']));
                $resultLocations = array_merge($resultLocations, $row['preferredLocation']);
            }
        }
        if(!empty($row['education']) && !preg_match("/[a-z]/i", $row['education'])) {
            $row['education'] = array_filter(explode(",", $row['education']));
            $resultQualifications = array_merge($resultQualifications, $row['education']);
        }
        $row = array_merge(['sr' => $sr], $row);
        $candidates[] = $row;
        $sr++;
    }
    if ($countResult->num_rows > 0) {
        while($row = $countResult->fetch_assoc()) {
            unset($row['subskills']);
            if(!preg_match("/[a-z]/i", $row['skills'])){
                $row['skills'] = array_filter(explode(",", $row['skills']));
                $resultSkills = array_merge($resultSkills, $row['skills']);
            }
            if(!empty($row['currentLocation']) && !preg_match("/[a-z]/i", $row['currentLocation'])) {
                $row['currentLocation'] = array_filter(explode(",", $row['currentLocation']));
                if(!preg_match("/[a-z]/i", $row['preferredLocation'])) {
                    $row['preferredLocation'] = array_filter(explode(",", $row['preferredLocation']));
                    $resultLocations = array_merge($resultLocations, $row['preferredLocation']);
                }
            }
            if(!empty($row['education']) && !preg_match("/[a-z]/i", $row['education'])) {
                $row['education'] = array_filter(explode(",", $row['education']));
                $resultQualifications = array_merge($resultQualifications, $row['education']);
            }
            $row = array_merge(['sr' => $sr], $row);
            $allCandidates[] = $row;
            $sr++;
        }
    }
    if(!empty($resultSkills)) {
        $db = new mysqli(servername, username, password, dbname);
        $sql = "SELECT * FROM skills WHERE id IN (" . implode(",", array_unique($resultSkills)) . ")";
        try {
            $result = $db->query($sql);
            $finalSkills = [];
            while($row = $result->fetch_assoc()) {
                $finalSkills[$row['id']] = $row['skill'];
            }
        } catch (mysqli_sql_exception $e) {
        }
        $db->close();
    }
    if(!empty($resultLocations)) {
        $db = new mysqli(servername, username, password, dbname);
        $sql = "SELECT * FROM locations WHERE id IN (" . implode(",", array_unique($resultLocations)) . ")";
        try {
            $result = $db->query($sql);
            $finalLocations = [];
            while($row = $result->fetch_assoc()) {
                $finalLocations[$row['id']] = $row['location'];
            }
        } catch (mysqli_sql_exception $e) {
        }
        $db->close();
    }
    if(!empty($resultQualifications)) {
        $db = new mysqli(servername, username, password, dbname);
        $sql = "SELECT * FROM qualifications WHERE id IN (" . implode(",", array_unique($resultQualifications)) . ")";
        try {
            $result = $db->query($sql);
            $finalQualifications = [];
            while($row = $result->fetch_assoc()) {
                $finalQualifications[$row['id']] = $row['qualification'];
            }
        } catch (mysqli_sql_exception $e) {
        }
        $db->close();
    }
    return ['candidates' => $candidates, 'totalRecords' => $countResult->num_rows, 'allCandidates' => $allCandidates, 'skills' => $finalSkills, 'locations' => $finalLocations, 'qualifications' => $finalQualifications];
}
function getSkills($id = null, $groupParent = null) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM skills";
    if(!empty($id)) {
        $sql .= " WHERE id IN (" . implode(",", array_filter($id)) . ")";
    }
    else if(!empty($groupParent)) {
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
function getStates($id = null) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM states";
    if(!empty($id)) {
        $sql .= " WHERE id IN (" . implode(",", array_filter($id)) . ")";
    }
    $sql .= " ORDER BY state ASC";
    $result = $db->query($sql);
    $states = [];
    if ($result->num_rows < 1) {
        return $states;
    }
    while($row = $result->fetch_assoc()) {
        $states[] = $row;
    }
    $db->close();
    return $states;
}
function getStateByText($state) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM states WHERE LOWER(state) = '" . strtolower($state) . "'";
    $result = $db->query($sql);
    return mysqli_fetch_assoc($result);
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
    $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
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
        return $mail->ErrorInfo;
    }
    return true;
}
function sendCustomEmail($email, $name, $applicationId, $subject, $body) {
    $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
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
$allCandidates = [];
$candidateSkills = [];
$candidateLocations = [];
$skills = getSkills();
$vendors = getVendors();
$protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
$locations = getLocations();
if(!empty($_POST['submit'])) {
    ini_set('max_execution_time', 0);
    ini_set("memory_limit", "-1");
    set_time_limit(0);
    if($_POST['submit'] == 'Reset') {
        $_POST = $data = [];
        header('Location: ' . $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'report.php');
    }
    else {
        $data = $_POST;
        unset($data['submit']);
        unset($data['customBody']);
        unset($data['vendor']);
        unset($data['forceEmail']);
        $start = $data['start'];
        $limit = $data['limit'];
        unset($data['start']);
        unset($data['limit']);
        $candidatesData = getCandidates($data, $start, $limit, $_POST['submit']);
        $candidates = $candidatesData['candidates'];
        $allCandidates = $candidatesData['allCandidates'];
        $candidateSkills = $candidatesData['skills'];
        $candidateLocations = $candidatesData['locations'];
        $candidateQualifications = $candidatesData['qualifications'];
        $db = new mysqli(servername, username, password, dbname);
        if($_POST['submit'] == 'Send candidate update') {
            $processCount = 0;
            ini_set('max_execution_time', 0);
            foreach ($allCandidates as $candidate) {
                if(in_array(172, $candidate['skills']) && !empty($candidate['resume'])) {
                    continue;
                }
                if(empty($candidate['emailSentOn']) || date('Y-m-d H:i:s', strtotime($candidate['emailSentOn'] . ' + 7 Days')) < date('Y-m-d H:i:s') || !empty($_POST['forceEmail'])) {
                    if(sendEmail($candidate['email'], $candidate['name'], $candidate['id'], $_POST['customBody']) === true) {
                        $sql = "UPDATE candidates SET status = 'Email sent', emailSentOn = '" . date('Y-m-d H:i:s') . "' WHERE id = " . $candidate['id'];
                        try {
                            $db->query($sql);
                            $processCount++;
                        } catch (mysqli_sql_exception $e) {
                            echo $sql . ' failed: ' . $e->getMessage();exit;
                        }
                    }
                }
                if($processCount >= 4000 || $processCount == count($allCandidates)) {
                    echo 'Email sent successfully';
                    break;
                }
            }
        } else if($_POST['submit'] == 'Send custom email') {
            ini_set('max_execution_time', 0);
            $failedEmails = [];
            foreach ($allCandidates as $candidate) {
                if(in_array(172, $candidate['skills']) && !empty($candidate['resume'])) {
                    continue;
                }
                $subject = "Profile for " . (!empty($data['skills']) ? implode(", ", array_intersect_key($candidateSkills, array_flip($data['skills']))) : implode(", ", $candidateSkills));
                if(!empty($data['overallExperience'])) {
                    $subject .= " with " . implode(", ", $data['overallExperience']) . " Years experience";
                }
                if(!empty($data['preferredLocation'])) {
                    $subject .= " at " . implode(", ", array_intersect_key($candidateLocations, array_flip($data['preferredLocation']))) . " location";
                }
                $sql = "INSERT INTO applications (vendorId, candidateId, email, emailSentBy, emailSentOn, subject, status) VALUES (" . $_POST['vendor'] . ", " . $candidate['id'] . ", '" . $candidate['email'] . "', " . $_SESSION['user']['id'] . ", '" . date('Y-m-d H:i:s') . "', '" . $subject . "', 'Email sent')";
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
        } else if($_POST['submit'] == 'Import Candidates') {
            $importFile = $_FILES['import'];
            if(!in_array(strtolower(pathinfo($importFile['name'], PATHINFO_EXTENSION)), ['csv', 'xls', 'xlsx'])) {
                echo 'Invalid file type. Only CSV or Excel files are allowed, please select a valid file.';
            } else {
                $fileName = 'Candidate_Import_';
                $date = date('Y_m_d_h_i_s');
                $importPath = 'assets/import/candidates/';
                $fileUrl = $importPath . $fileName . $date;
                if (!move_uploaded_file($importFile['tmp_name'], $fileUrl)) {
                    echo 'File could not be uploaded. Please try again.';
                }
                else {
                    $fileType = \PHPExcel_IOFactory::identify($fileUrl);
                    $reader = \PHPExcel_IOFactory::createReader($fileType)->load($fileUrl);
                    $objWorksheet = $reader->setActiveSheetIndex(0);
                    $highestRow = $objWorksheet->getHighestRow();
                    $highestColumn = $objWorksheet->getHighestColumn();
                    $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn);
                    $result = [];
                    $expectedColumns = ['Name', 'Mobile', 'Email ID', 'Location', 'State', 'Gender', 'Company', 'CTC'];
                    /* Read from csv */
                    for ($row = 1; $row <= $highestRow; $row++) {
                        $fileData = Array();
                        for ($col = 0; $col < $highestColumnIndex; ++$col) {
                            $value = $objWorksheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
                            if($row == 1 && $expectedColumns[$col] != $value) {
                                echo 'Column ' . ($col + 1) . ' should be ' .  $expectedColumns[$col];
                            }
                            else {
                                if(!empty(trim($value))) {
                                    $fileData[str_replace(' ', '', strtolower($expectedColumns[$col]))] = trim($value);
                                }
                            }
                        }
                        $result[] = $fileData;
                    }
                    foreach($result as $key => $candidate) {
                        if($key == 0) {
                            continue;
                        }
                        $locationSql = 'SELECT id FROM locations WHERE LOWER(location) = "' . strtolower($candidate['location']) . '"';
                        $result = $db->query($locationSql);
                        $location = mysqli_fetch_assoc($result);
                        $state = getStateByText($candidate['state']);
                        if(empty($state)) {
                            $errors[] = $candidate['emailid'] . ' failed: Incorrect State.';
                            continue;
                        }
                        $sql = "SELECT * FROM candidates WHERE email = '" . $candidate['emailid'] . "'";
                        $result = $db->query($sql);
                        $existingCandidate = mysqli_fetch_assoc($result);
                        if(!empty($existingCandidate)) {
                            $sql = "UPDATE candidates SET name = '" . $candidate['name'] . "', mobile = '" . $candidate['mobile'] . "', skills = ',172,', currentLocation = '," . $location['id'] . ",', preferredLocation = '," . $location['id'] . ",', currentCompany = '" . $candidate['company'] . "', currentCtc = '" . $candidate['ctc'] . "', stateId = '" . $state['id'] . "', gender = '" . $candidate['gender'] . "' WHERE email = '" . $candidate['emailid'] . "'";
                        } else {
                            $sql = "INSERT INTO candidates (name, mobile, email, skills, currentLocation, preferredLocation, currentCompany, currentCtc, stateId, gender, status) VALUES ('" . $candidate['name'] . "', '" . $candidate['mobile'] . "', '" . $candidate['emailid'] . "', ',172,', '," . $location['id'] . ",', '," . $location['id'] . ",', '" . $candidate['company'] . "', '" . $candidate['ctc'] . "', '" . $state['id'] . "', '" . $candidate['gender'] . "', 'Created')";
                        }
                        $errors = [];
                        try {
                            if($db->query($sql) === TRUE) {
                                $importStatus = 'Success';
                            } else {
                                $errors[] = $candidate['emailid'] . ' failed: ' . $db->error;
                            }
                        } catch (Exception $e) {
                            $errors[] = $candidate['emailid'] . ' failed: ' . $e->getMessage();
                        }
                    }
                    if(!empty($errors)) {
                        echo implode(", ", $errors);
                    }
                }
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
function validateSearch(e) {
	if(document.getElementById("skills").value == '' && document.getElementById("status").value == '' && document.getElementById("email").value == '') {
		alert('Please select a skill or email.');
		e.preventDefault();
		return false;
	}
	return true;
}
function validateImport(e) {
	if(document.getElementById("import").value == '') {
		alert('Please select a csv or excel file.');
		e.preventDefault();
		return false;
	}
	return true;
}
function setPagination(start) {
	document.getElementById('start').value = start;
	document.getElementById('submit').click();
}
</script>
<?php 
include 'header.php';
include 'menu.php'; ?>
<form action="report.php" id="candidateForm" method="post" enctype="multipart/form-data">
<?php if($_SESSION['user']['readOnlyAccess'] == 2) { ?>
	<div class="row">
        <div class="col-lg-2">
            <textarea id="customBody" name="customBody" class="form-control" placeholder="Email body"></textarea>
       	</div>
       	<div class="col-lg-2">
            <select id="vendor" name="vendor" class="form-control js-example-basic-single">
            	<option value="">Select Vendor</option>
            	<?php foreach($vendors as $vendor) { ?>
            		<option value="<?php echo $vendor['id']; ?>"><?php echo $vendor['name']; ?></option>
            	<?php } ?>
            </select>
       	</div>
       	<div class="col-lg-4">
       		<label><input type="checkbox" name="forceEmail" id="forceEmail" /> Force Email</label>
            <input type="submit" name="submit" class="btn btn-primary" onclick="validateCustomEmail(event)" value="Send custom email" />
            <input type="submit" name="submit" class="btn btn-primary" onclick="validateEmail(event)" value="Send candidate update" />
        </div>
        <div class="col-lg-2">
        	<input type="file" name="import" accept=".csv,.xlsx,.xls" id="import" class="form-control" />Only csv, xlsx and xls files are allowed.
        </div>
        <div class="col-lg-1">
        	<input type="submit" name="submit" class="btn btn-success" onclick="validateImport(event)" value="Import Candidates" />
        </div>
        <div class="col-lg-1">
        	<a href="<?php echo $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'assets/import_template.xlsx'; ?>" class="btn btn-success">Template</a>
        </div>
  	</div>
<?php } ?>
<h3>Resume List</h3>
<div class="row">
	<div class="col-lg-2">
    	<label for="skills">Primary Skills/Keywords</label>
        <select id="skills" name="skills[]" multiple="multiple" class="form-control js-example-basic-multiple">
        <?php foreach($skills as $eachskill) { ?>
        <option value="<?php echo $eachskill['id']; ?>" <?php echo !empty($_POST['skills']) ? (in_array($eachskill['groupParent'], $_POST['skills']) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
        <?php } ?>
        </select>
    	<label for="subskills">Secondary Skills</label>
        <select id="subskills" name="subskills[]" multiple="multiple" class="form-control js-example-basic-multiple">
        <option value="">Select</option>
        <?php foreach($skills as $eachskill) { ?>
        <option value="<?php echo $eachskill['id']; ?>" <?php echo !empty($_POST['subskills']) ? (in_array($eachskill['groupParent'], $_POST['subskills']) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
        <?php } ?>
        </select>
   	</div>
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
        <select id="preferredLocation" name="preferredLocation[]" multiple="multiple" class="form-control js-example-basic-multiple">
        <?php foreach($locations as $location) { ?>
        <option value="<?php echo $location['id']; ?>" <?php echo !empty($_POST['preferredLocation']) ? (in_array($location['id'], $_POST['preferredLocation']) ? 'selected="selected"' : '') : ''; ?>><?php echo $location['location']; ?></option>
        <?php } ?>
        <option value="">Any</option>
        </select>
  	</div>
  	<?php if($_SESSION['user']['readOnlyAccess'] == 2) { ?>
	<div class="col-lg-2">
		<label for="preferredLocation">Status</label>
    	<select id="status" name="status[]" multiple="multiple" class="form-control js-example-basic-multiple">
    	<option value="Created">Created</option>
    	<option value="Email sent">Email sent</option>
    	<option value="Updated by Candidate">Updated by Candidate</option>
    	<option value="Updated by Admin">Updated by Admin</option>
    	</select>
  	</div>
  	<?php } ?>
	<div class="col-lg-2">
    	<label>Salary Range (Lacs)</label>
    	<input name="salaryFrom" id="salaryFrom" class="form-control" type="text" placeholder="From" value="<?php echo !empty($_POST['salaryFrom']) ? $_POST['salaryFrom'] : ''; ?>" />
		<input name="salaryTo" id="salaryTo" class="form-control" type="text" placeholder="To" value="<?php echo !empty($_POST['salaryTo']) ? $_POST['salaryTo'] : ''; ?>" />
	</div>
	<div class="col-lg-2">
		<label for="email">Email</label>
		<input id="email" type="text" name="email" class="form-control" value="<?php echo !empty($_POST['email']) ? $_POST['email'] : ''; ?>" />
	</div>
</div>
<div class="row actionRow">
	<div class="col-lg-2">
		<input type="submit" id="submit" name="submit" onclick="validateSearch(event)" class="btn btn-primary" value="Search" />
		<input type="submit" name="submit" class="btn btn-primary" value="Reset" />
	</div>
</div>
<div class="row">
<table>
<tr>
	<?php foreach($columns as $column){ if($column == 'id') {continue;} ?>
	<th><?php echo ucwords(strtolower($column)); ?></th>
	<?php } ?>
</tr>
<?php foreach($candidates as $candidate) { ?>
<tr>
	<?php foreach($columns as $column){ if($column == 'id') {continue;} ?>
	<td><?php 
	if($column == 'servingNotice' || $column == 'it') {
	    echo !empty($candidate[$column]) ? ($candidate[$column] == 1 ? 'Yes' : 'No') : '';
	} else if ($column == 'education') {
	    echo !empty($candidate[$column]) ? implode(", ", array_intersect_key($candidateQualifications, array_flip($candidate[$column]))) : '';
	}
	else if ($column == 'skills') {
	    echo !empty($candidateSkills) ? '<span title="'. implode(", ", array_intersect_key($candidateSkills, array_flip($candidate[$column]))) . '">' . implode(", ", array_intersect_key(array_intersect_key($candidateSkills, array_flip($candidate[$column])), array_flip($_POST['skills']))). (!empty($_POST['subskills']) && !empty(array_intersect_key(array_intersect_key($candidateSkills, array_flip($candidate[$column])), array_flip($_POST['subskills']))) ? ', ' . implode(", ", array_intersect_key(array_intersect_key($candidateSkills, array_flip($candidate[$column])), array_flip($_POST['subskills']))) : '') .',...</span>' : (in_array(172, $data['skills']) && count($data['skills']) == 1 ? 'NONE' : '');
	} else if ($column == 'currentLocation' || $column == 'preferredLocation') {
	    echo !empty($candidate[$column]) ? implode(", ", array_intersect_key($candidateLocations, array_flip($candidate[$column]))) : '';
	} else {
	    echo $column == 'resume' ? '<a href="'. $candidate[$column]. '" target="blank" >'. pathinfo($candidate[$column], PATHINFO_BASENAME) .'</a>' : $candidate[$column];
	}
    ?></td>
	<?php } ?>
	<?php if($_SESSION['user']['readOnlyAccess'] == 2) { ?>
		<td><a class="btn btn-primary" href="<?php echo $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . '?id=' . base64_encode($candidate['id']); ?>" class="button">Edit</a></td>
	<?php } ?>
</tr>
<?php } ?>
</table>
</div>
<div class="row pagination-wrapper">
	<div class="col-lg-2"><?php echo !empty($candidatesData) ? 'Total ' . $candidatesData['totalRecords'] . ' records found.' : ''; ?></div>
	<div class="col-lg-8 pagination">
		<ul class="pagination">
			<?php if($candidatesData['totalRecords'] > $limit) { ?>
				<li class="previous<?php echo $start == 0 ? ' disabled' : ''; ?>"><a href="javascript:void(0)" <?php if($start > 0){ ?>onclick="setPagination(<?php echo $limit - $start; ?>);"<?php } ?>>Previous</a></li>
    			<?php for($page = 0; $page < $candidatesData['totalRecords']/$limit; $page++) {
    			    if($page - ($start/$limit) <= 9) {?>
                		<li class="<?php echo $start/$limit == $page ? 'active' : ''; ?>"><a href="javascript:void(0)" onclick="setPagination(<?php echo $page * $limit; ?>);"><?php echo $page + 1; ?></a></li>
                	<?php } ?>
                <?php } ?>
  				<li class="next<?php echo $candidatesData['totalRecords'] < ($start + $limit) ? ' disabled' : ''; ?>"><a href="javascript:void(0)" <?php if($candidatesData['totalRecords'] > ($start + $limit)) { ?>onclick="setPagination(<?php echo $start + $limit; ?>);"<?php } ?>>Next</a></li>
  			<?php } ?>
        </ul>
  	</div>
</div>
<input name="start" id="start" type="hidden" value="<?php echo $start; ?>" />
<input name="limit" name="limit" type="hidden" value="<?php echo $limit; ?>" />
</form>
<?php include 'footer.php'; ?>