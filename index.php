<?php 
define('servername', 'localhost');
define('username', 'root');
define('password', 'root');
define('dbname', 'jobs');
$email = !empty($_GET['ce']) ? base64_decode($_GET['ce']) : '';
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

function saveCandidateData($data) {
    $data['education'] = !empty($data['education']) ? implode(', ', $data['education']) : '';
    $data['skills'] = !empty($data['skills']) ? implode(', ', $data['skills']) : '';
    $data['subskills'] = !empty($data['subskills']) ? implode(', ', $data['subskills']) : '';
    $data['currentLocation'] = !empty($data['currentLocation']) ? implode(', ', $data['currentLocation']) : '';
    $data['preferredLocation'] = !empty($data['preferredLocation']) ? implode(', ', $data['preferredLocation']) : '';
    $db = new mysqli(servername, username, password, dbname);
    unset($data['submit']);
    $sql = "INSERT INTO candidates (" . implode(", ", array_keys($data)) . ") VALUES ('" . implode("', '", array_values($data)) . "')";
    
    if ($db->query($sql) === TRUE) {
        echo "New record created successfully";
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
            $target_file = 'profiles/' . basename($resume["name"]);
            move_uploaded_file($resume["tmp_name"], $target_file);
            $fileUrl = 'http://' . $_SERVER['SERVER_NAME'] . '/'. $target_file;
            $_POST['resume'] = $fileUrl;
        }
        saveCandidateData($_POST);
    }
}
?>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . '/report.php'; ?>">Resume List</a>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . '/extract.php'; ?>">Extract emails</a>
<form method="post" action="index.php">
<div><label for="newSkill">Add Skill</label><input type="text" name="newSkill" id="newSkill" /><input type="submit" name="submit"/></div>
</form>
<h3>Candidate Info</h3>
<form method="post" action="index.php?ce=<?php echo !empty($_GET['ce']) ? $_GET['ce'] : ''; ?>" enctype="multipart/form-data">
<div><label for="name">Name</label><input type="text" name="name" id="name" /></div>
<div><label for="mobile">Mobile No.</label><input type="text" name="mobile" id="mobile" /></div>
<div><label for="mobile">Email</label><input type="text" name="email" id="email" value="<?php echo $email; ?>" /></div>

<div><label for="education">Education</label>
<select id="education" name="education[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($education as $edu) { ?>
<option value="<?php echo $edu; ?>"><?php echo $edu; ?></option>
<?php } ?>
</select></div>

<div><label for="skills">Skills</label>
<select id="skills" name="skills[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($skills as $eachskill) { ?>
<option value="<?php echo $eachskill['skill']; ?>"><?php echo $eachskill['skill']; ?></option>
<?php } ?>
</select></div>

<div><label for="subskills">Sub Skills</label>
<select id="subskills" name="subskills[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($skills as $eachskill) { ?>
<option value="<?php echo $eachskill['skill']; ?>"><?php echo $eachskill['skill']; ?></option>
<?php } ?>
</select></div>

<div><label for="overallExperience">Overall Exp</label>
<select id="overallExperience" name="overallExperience">
<option value="">Select</option>
<option value="0-3">0-3</option>
<option value="4-7">4-7</option>
<option value="8-10">8-10</option>
<option value=">10">>10</option>
</select></div>

<div><label for="relevantExperience">Relevant Exp</label>
<select id="relevantExperience" name="relevantExperience">
<option value="">Select</option>
<option value="0-3">0-3</option>
<option value="4-7">4-7</option>
<option value="8-10">8-10</option>
<option value=">10">>10</option>
</select></div>

<div><label for="currentLocation">Current Loc</label>
<select id="currentLocation" name="currentLocation[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($locations as $location) { ?>
<option value="<?php echo $location; ?>"><?php echo $location; ?></option>
<?php } ?>
</select></div>

<div><label for="preferredLocation">Preferred Loc</label>
<select id="preferredLocation" name="preferredLocation[]" multiple="multiple">
<option value="">Select</option>
<?php foreach($locations as $location) { ?>
<option value="<?php echo $location; ?>"><?php echo $location; ?></option>
<?php } ?>
</select></div>

<div><label for="currentCompany">Current Company</label>
<select id="currentCompany" name="currentCompany">
<option value="">Select</option>
<?php foreach($companies as $company) { ?>
<option value="<?php echo $company; ?>"><?php echo $company; ?></option>
<?php } ?>
</select></div>

<div><label for="currentCtc">Current CTC (Lacs)</label><input type="text" name="currentCtc" id="currentCtc" /></div>
<div><label for="expectedCtc">Exp CTC (Lacs)</label><input type="text" name="expectedCtc" id="expectedCtc" /></div>

<div><label for="noticePeriod">Notice Period</label>
<select id="noticePeriod" name="noticePeriod">
<option value="">Select</option>
<option value="15">0-15 Days</option>
<option value="30">30 Days</option>
<option value="60">60 Days</option>
<option value=">60">>60 Days</option>
</select></div>

<div>
<label for="servingNotice">Serving Notice Period?</label>
<label><input type="radio" name="servingNotice" id="servingNoticeYes" value="1" /> Yes</label>
<label><input type="radio" name="servingNotice" id="servingNoticeNo" value="2" /> No</label>
</div>

<div>
<label for="resume">Upload Resume</label>
<input type="file" name="resume" id="resume" />
</div>

<div><input type="submit" name="submit"/></div>
</form>