<?php 
//ini_set('display_errors', 1);
include_once 'config.php';
$email = !empty($_GET['ce']) ? base64_decode($_GET['ce']) : '';
$id = !empty($_GET['id']) ? base64_decode($_GET['id']) : '';
$accessBy = !empty($email) ? 'Candidate' : 'Admin';
$education = ['B.E./B.Tech', 'B.Sc', 'M.Tech', 'M.Com', 'B.Com', 'BCA', 'MBA'];
$skills = getSkills();
$locations = ['Hyderabad', 'Banglore', 'Mumbai', 'Noida', 'Delhi', 'Calcutta', 'Chennai', 'Coimbatore', 'Gurgoan', 'Pune', 'NCR'];
$companies = ['Deloitte', 'TCS', 'CAP GEMINI', 'Tech Mahindra', 'HCL', 'WIPRO', 'LUMEN', 'EVOKE TECHNOLOGIES', 'MPHASIS', 'L & T', 'Hexaware', 'None'];
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
function getCandidate($id) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM candidates WHERE id = " . $id;
    $result = $db->query($sql);
    return mysqli_fetch_assoc($result);
}
function saveSkill($skill) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "INSERT INTO skills (skill) VALUES ('" . $skill . "')";
    if ($db->query($sql) === TRUE) {
        echo "New skill added successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $db->error;
    }
    $db->close();
}

function saveCandidateData($data, $accessBy) {
    $data['education'] = !empty($data['education']) ? implode(', ', $data['education']) : '';
    $data['skills'] = !empty($data['skills']) ? implode(', ', $data['skills']) : '';
    $data['subskills'] = !empty($data['subskills']) ? implode(', ', $data['subskills']) : '';
    $data['currentLocation'] = !empty($data['currentLocation']) ? implode(', ', $data['currentLocation']) : '';
    $data['preferredLocation'] = !empty($data['preferredLocation']) ? implode(', ', $data['preferredLocation']) : '';
    $data['status'] = !empty($data['candidateId']) ? 'Updated by ' . $accessBy : 'Created';
    $db = new mysqli(servername, username, password, dbname);
    unset($data['submit']);
    if(empty($data['resume'])) {
        unset($data['resume']);
    }
    if(!empty($data['candidateId'])) {
        $sql = 'UPDATE candidates SET ';
        $set = [];
        foreach($data as $key => $value) {
            if($key != 'candidateId') {
                $set[] .= $key . '="' .$value. '"';
            }
        }
        $sql .= implode(", ", $set);
        $sql .= ' WHERE id = ' . $data['candidateId'];
    } else {
        unset($data['candidateId']);
        $sql = "INSERT INTO candidates (" . implode(", ", array_keys($data)) . ") VALUES ('" . implode("', '", array_values($data)) . "')";
    }
    
    if ($db->query($sql) === TRUE) {
        echo "New record " . (!empty($data['candidateId']) ? 'updated' : 'created') . " successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $db->error;
    }
    $db->close();
}

if(isset($_POST['submit'])) {
    if(isset($_POST['newSkill']) && !empty($_POST['newSkill'])) {
        saveSkill(trim($_POST['newSkill']));
    } else {
        $allowedFiles = ['doc', 'docx', 'pdf'];
        $_POST['resume'] = '';
        if(!empty($_FILES['resume']['name'])) {
            $resume = $_FILES['resume'];
            $ext = pathinfo($resume['name'], PATHINFO_EXTENSION);
            if(!in_array($ext, $allowedFiles)) {
                echo 'Invalid file type. Please upload ' . implode(', ', $allowedFiles) . ' only.';
                exit;
            }
            $target_file = 'profiles/processed/' . basename($resume["name"]);
            move_uploaded_file($resume["tmp_name"], $target_file);
            $fileUrl = 'http://' . $_SERVER['SERVER_NAME'] . baseurl . $target_file;
            $_POST['resume'] = $fileUrl;
        }
        saveCandidateData($_POST, $accessBy);
    }
}
if(!empty($id)) {
    $candidateDetails = getCandidate($id);
}
?>
<?php if(empty($email)) { ?>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl . 'report.php'; ?>">Resume List</a>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl . 'extract.php'; ?>">Extract Profiles</a>
<form method="post" action="index.php">
<div><label for="newSkill">Add Skill</label><input type="text" name="newSkill" id="newSkill" /><input type="submit" name="submit"/></div>
</form>
<?php } ?>
<h3>Candidate Info</h3>
<form method="post" action="index.php?ce=<?php echo !empty($_GET['ce']) ? $_GET['ce'] : ''; ?>&id=<?php echo !empty($_GET['id']) ? $_GET['id'] : ''; ?>" enctype="multipart/form-data">
<div><label for="name">Name</label><input type="text" name="name" id="name" value="<?php echo !empty($candidateDetails) ? $candidateDetails['name'] : ''; ?>" /></div>
<div><label for="mobile">Mobile No.</label><input required type="text" name="mobile" id="mobile" value="<?php echo !empty($candidateDetails) ? $candidateDetails['mobile'] : ''; ?>" /></div>
<div><label for="mobile">Email</label><input required type="text" name="email" id="email" value="<?php echo !empty($candidateDetails) ? $candidateDetails['email'] : $email; ?>" /></div>

<div><label for="education">Education</label>
<select id="education" name="education[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($education as $edu) { ?>
<option value="<?php echo $edu; ?>" <?php echo !empty($candidateDetails) ? (in_array($edu, explode(", ", $candidateDetails['education'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $edu; ?></option>
<?php } ?>
</select></div>

<div><label for="skills">Skills</label>
<select required id="skills" name="skills[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($skills as $eachskill) { ?>
<option value="<?php echo $eachskill['skill']; ?>" <?php echo !empty($candidateDetails) ? (in_array($eachskill['skill'], explode(", ", $candidateDetails['skills'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
<?php } ?>
</select></div>

<div><label for="subskills">Sub Skills</label>
<select id="subskills" name="subskills[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($skills as $eachskill) { ?>
<option value="<?php echo $eachskill['skill']; ?>" <?php echo !empty($candidateDetails) ? (in_array($eachskill['skill'], explode(", ", $candidateDetails['subskills'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
<?php } ?>
</select></div>

<div><label for="overallExperience">Overall Exp</label>
<select id="overallExperience" name="overallExperience">
<option value="">Select</option>
<option value="0-3" <?php echo !empty($candidateDetails) ? ('0-3' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>0-3</option>
<option value="4-7" <?php echo !empty($candidateDetails) ? ('4-7' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>4-7</option>
<option value="8-10" <?php echo !empty($candidateDetails) ? ('8-10' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>8-10</option>
<option value=">10" <?php echo !empty($candidateDetails) ? ('>10' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>>10</option>
</select></div>

<div><label for="relevantExperience">Relevant Exp</label>
<select id="relevantExperience" name="relevantExperience">
<option value="">Select</option>
<option value="0-3" <?php echo !empty($candidateDetails) ? ('0-3' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>0-3</option>
<option value="4-7" <?php echo !empty($candidateDetails) ? ('4-7' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>4-7</option>
<option value="8-10" <?php echo !empty($candidateDetails) ? ('8-10' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>8-10</option>
<option value=">10" <?php echo !empty($candidateDetails) ? ('>10' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>>10</option>
</select></div>

<div><label for="currentLocation">Current Loc</label>
<select id="currentLocation" name="currentLocation[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($locations as $location) { ?>
<option value="<?php echo $location; ?>" <?php echo !empty($candidateDetails) ? (in_array($location, explode(", ", $candidateDetails['currentLocation'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $location; ?></option>
<?php } ?>
</select></div>

<div><label for="preferredLocation">Preferred Loc</label>
<select id="preferredLocation" name="preferredLocation[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($locations as $location) { ?>
<option value="<?php echo $location; ?>" <?php echo !empty($candidateDetails) ? (in_array($location, explode(", ", $candidateDetails['preferredLocation'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $location; ?></option>
<?php } ?>
<option value="Any">Any</option>
</select></div>

<div><label for="currentCompany">Current Company</label>
<select id="currentCompany" name="currentCompany">
<option value="">Select</option>
<?php foreach($companies as $company) { ?>
<option value="<?php echo $company; ?>" <?php echo !empty($candidateDetails) ? ($company == $candidateDetails['currentCompany'] ? 'selected="selected"' : '') : ''; ?>><?php echo $company; ?></option>
<?php } ?>
</select></div>

<div><label for="currentCtc">Current CTC (Lacs)</label><input type="text" name="currentCtc" id="currentCtc" value="<?php echo !empty($candidateDetails) ? $candidateDetails['currentCtc'] : ''; ?>" /></div>
<div><label for="expectedCtc">Exp CTC (Lacs)</label><input type="text" name="expectedCtc" id="expectedCtc" value="<?php echo !empty($candidateDetails) ? $candidateDetails['expectedCtc'] : ''; ?>" /></div>

<div><label for="noticePeriod">Notice Period</label>
<select id="noticePeriod" name="noticePeriod">
<option value="">Select</option>
<option value="15" <?php echo !empty($candidateDetails) ? ('15' == $candidateDetails['noticePeriod'] ? 'selected="selected"' : '') : ''; ?>>0-15 Days</option>
<option value="30" <?php echo !empty($candidateDetails) ? ('30' == $candidateDetails['noticePeriod'] ? 'selected="selected"' : '') : ''; ?>>30 Days</option>
<option value="60" <?php echo !empty($candidateDetails) ? ('60' == $candidateDetails['noticePeriod'] ? 'selected="selected"' : '') : ''; ?>>60 Days</option>
<option value=">60" <?php echo !empty($candidateDetails) ? ('>60' == $candidateDetails['noticePeriod'] ? 'selected="selected"' : '') : ''; ?>>>60 Days</option>
</select></div>

<div>
<label for="servingNotice">Serving Notice Period?</label>
<label><input type="radio" name="servingNotice" id="servingNoticeYes" value="1" <?php echo !empty($candidateDetails) ? (1 == $candidateDetails['servingNotice'] ? 'checked' : '') : ''; ?> /> Yes</label>
<label><input type="radio" name="servingNotice" id="servingNoticeNo" value="2" <?php echo !empty($candidateDetails) ? (2 == $candidateDetails['servingNotice'] ? 'checked' : '') : ''; ?> /> No</label>
</div>

<div>
<label for="resume">Upload Resume</label>
<input type="file" name="resume" id="resume" />
<?php if(!empty($candidateDetails) && !empty($candidateDetails['resume'])) { ?>
<a href="<?php echo $candidateDetails['resume']; ?>" target="blank" ><?php echo pathinfo($candidateDetails['resume'], PATHINFO_BASENAME); ?></a>
<?php } ?>
</div>
<input type="hidden" id="candidateId" name="candidateId" value="<?php echo !empty($candidateDetails) ? $candidateDetails['id'] : ''; ?>" />
<div><input type="submit" name="submit"/></div>
</form>