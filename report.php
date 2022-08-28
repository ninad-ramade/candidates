<?php
//ini_set('display_errors', 1);
include_once 'config.php';
$candidates = getCandidates();
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
        $sql .= ' WHERE (' . implode(" AND ", $where) . ')';
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
$columns = !empty($candidates) ? array_keys($candidates[0]) : [];
$skills = getSkills();
$locations = ['Hyderabad', 'Banglore', 'Mumbai', 'Noida', 'Delhi', 'Calcutta', 'Chennai', 'Coimbatore', 'Gurgoan', 'Pune', 'NCR'];
if(!empty($_POST['submit'])) {
    if($_POST['submit'] == 'Reset') {
        $_POST = $data = [];
    } else {
        $data = $_POST;
        unset($data['submit']);
    }
    $candidates = getCandidates($data);
}
?>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl; ?>">Resume Form</a>
<h3>Resume List</h3>
<form action="report.php" method="post">
<div><label for="skills">Skills</label>
<select id="skills" name="skills[]" multiple="multiple">
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
<option value="Any">Any</option>
</select>

<label for="preferredLocation">Salary Range (Lacs)</label>
<input name="salaryFrom" id="salaryFrom" type="text" placeholder="From" value="<?php echo $_POST['salaryFrom']; ?>" />
<input name="salaryTo" id="salaryTo" type="text" placeholder="To" value="<?php echo $_POST['salaryTo']; ?>" />
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
</tr>
<?php } ?>
</table>