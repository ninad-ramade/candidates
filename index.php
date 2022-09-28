<?php 
//ini_set('display_errors', 1);
include_once 'config.php';
session_start();
$email = !empty($_GET['ce']) ? base64_decode($_GET['ce']) : '';
$mode = !empty($_GET['m']) ? base64_decode($_GET['m']) : '';
$id = !empty($_GET['id']) ? base64_decode($_GET['id']) : '';
$accessBy = !empty($email) ? 'Candidate' : 'Admin';
$education = ['B.E./B.Tech', 'B.Sc', 'M.Tech', 'M.Com', 'B.Com', 'BCA', 'MBA'];
$skills = getSkills();
$locations = ['Hyderabad', 'Banglore', 'Mumbai', 'Noida', 'Delhi', 'Calcutta', 'Chennai', 'Coimbatore', 'Gurgoan', 'Pune', 'NCR'];
$companies = ['Deloitte', 'TCS', 'CAP GEMINI', 'Tech Mahindra', 'HCL', 'WIPRO', 'LUMEN', 'EVOKE TECHNOLOGIES', 'MPHASIS', 'L & T', 'Hexaware', 'None'];
$services = getServices();
$vservices = getVendorServices();
foreach($services as $key => &$service) {
    foreach($vservices as $vkey => $vservice) {
        if($service['serviceId'] == $vservice['serviceId']) {
            $service['vservices'][] = $vservice;
        }
    }
}
function getSkills($skill = null) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM skills";
    if(!empty($skill)) {
        $sql .= " WHERE LOWER(skill) = '" . strtolower($skill) . "'";
    } else {
        $sql .= " ORDER BY skill ASC";
    }
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
function getServices() {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM services ORDER by serviceName ASC";
    $result = $db->query($sql);
    $services = [];
    if ($result->num_rows < 1) {
        return $services;
    }
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $db->close();
    return $services;
}
function getVendorServices() {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM vservices ORDER by vendorServiceName ASC";
    $result = $db->query($sql);
    $services = [];
    if ($result->num_rows < 1) {
        return $services;
    }
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $db->close();
    return $services;
}
function getCandidate($id) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM candidates LEFT JOIN availed_services on candidates.id = availed_services.candidateId WHERE id = " . $id;
    $result = $db->query($sql);
    $i = 0;
    while($row = $result->fetch_assoc()) {
        if($i == 0) {
            $candidate = $row;
        }
        $candidate['services'][] = $row['serviceId'];
        $candidate['vservices'][] = $row['vserviceId'];
        $i++;
    }
    $db->close();
    return $candidate;
}
function getCandidateByEmail($email) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM candidates LEFT JOIN availed_services on candidates.id = availed_services.candidateId LEFT JOIN services on availed_services.serviceId = services.serviceId WHERE email = '" . $email . "'";
    $result = $db->query($sql);
    $i = 0;
    while($row = $result->fetch_assoc()) {
        if($i == 0) {
            $candidate = $row;
        }
        $candidate['services'][] = ['serviceId' => $row['serviceId'], 'discounted' => ($row['tcount'] - $row['scount']) > 0 ? 1 : 0];;
        $candidate['vservices'][] = $row['vserviceId'];
        $i++;
    }
    $db->close();
    return $candidate;
}
function saveSkill($skill) {
    $db = new mysqli(servername, username, password, dbname);
    $existingSKill = getSkills($skill);
    if(!empty($existingSKill) && count($existingSKill) > 0) {
        echo 'Skill ' .$skill. ' already exists.';
    } else {
        $sql = "INSERT INTO skills (skill) VALUES ('" . $skill . "')";
        if ($db->query($sql) === TRUE) {
            $sql = "UPDATE skills set groupParent = " . $db->insert_id . " WHERE id = " . $db->insert_id;
            $db->query($sql);
            echo "New skill added successfully";
        } else {
            echo "Error: " . $sql . "<br>" . $db->error;
        }
    }
    $db->close();
}
function buildServicesData($data, $candidateId) {
    $availedServices = [];
    if(!empty($data['services'])) {
        foreach($data['services'] as $service) {
            if(!empty($data['vservices'][$service])) {
                foreach($data['vservices'][$service] as $vservice) {
                    $serviceData = [];
                    $serviceData['candidateId'] = $candidateId;
                    $serviceData['serviceId'] = $service;
                    $serviceData['vserviceId'] = $vservice;
                    $availedServices[] = $serviceData;
                }
            } else {
                $serviceData = [];
                $serviceData['candidateId'] = $candidateId;
                $serviceData['serviceId'] = $service;
                $serviceData['vserviceId'] = null;
                $availedServices[] = $serviceData;
            }
        }
    }
    return $availedServices;
}
function saveCandidateData($data, $accessBy) {
    $postData = $data;
    $data['education'] = !empty($data['education']) ? implode(', ', $data['education']) : '';
    $data['skills'] = !empty($data['skills']) ? implode(', ', $data['skills']) : '';
    //$data['subskills'] = !empty($data['subskills']) ? implode(', ', $data['subskills']) : '';
    $data['currentLocation'] = !empty($data['currentLocation']) ? implode(', ', $data['currentLocation']) : '';
    $data['preferredLocation'] = !empty($data['preferredLocation']) ? implode(', ', $data['preferredLocation']) : '';
    $data['status'] = !empty($data['candidateId']) ? 'Updated by ' . $accessBy : 'Created';
    $db = new mysqli(servername, username, password, dbname);
    unset($data['submit']);
    if(empty($data['resume'])) {
        unset($data['resume']);
    }
    unset($data['services']);
    unset($data['vservices']);
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
        $candidateId = !empty($data['candidateId']) ? $data['candidateId'] : $db->insert_id;
        $servicesData = buildServicesData($postData, $candidateId);
        $sql = "DELETE FROM availed_services WHERE candidateId = " . $candidateId;
        $db->query($sql);
        if(!empty($servicesData)) {
            $sql = "INSERT INTO availed_services (candidateId, serviceId, vserviceId) VALUES ";
            $serviceValues = [];
            foreach($servicesData as $sdata) {
                $serviceValues[] = '(' .$sdata['candidateId']. ', ' . $sdata['serviceId'] . ', ' . (!empty($sdata['vserviceId']) ? $sdata['vserviceId'] : 'NULL') . ')';
            }
            $sql .= implode(", ", $serviceValues);
            $db->query($sql);
        }
        echo "Profile " . (!empty($data['candidateId']) ? 'updated' : 'created') . " successfully";
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
            $fileUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . baseurl . $target_file;
            $_POST['resume'] = $fileUrl;
        }
        saveCandidateData($_POST, $accessBy);
    }
} else if(!empty($_POST['email'])) {
    echo json_encode(getCandidateByEmail($_POST['email']));exit;
}
if(!empty($id)) {
    $candidateDetails = getCandidate($id);
}
include 'header.php';
include 'menu.php';
?>
<script>
var discountSelected = 0;
function getCandidate(email) {
	var xhr = new XMLHttpRequest();
	xhr.open("POST", 'index.php', true);
	xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	xhr.responseType = "json";
	xhr.onreadystatechange = function() {
        if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
            var candidateData = Object.entries(this.response);
            candidateData.forEach(function(e){
            if(e[0] == 'skills' || e[0] == 'currentLocation' || e[0] == 'preferredLocation') {
            	var values = e[1].split(", ");
        		values.forEach(function(value){
        			document.querySelector("#" + e[0] + " option[value='" + value + "']").setAttribute("selected", "selected");
        		});
            } else if(e[0] == 'servingNotice') {
            	var noticeId = e[1] == '1' ? 'servingNoticeYes' : 'servingNoticeNo';
            	document.querySelector("#" + noticeId).setAttribute('checked', 'checked');
            } else if(e[0] == 'resume') {
            	var resumeElement = document.createElement('a');
            	resumeElement.setAttribute('href', e[1]);
            	resumeElement.setAttribute('target', 'blank');
            	var resumeParts = e[1].split('/');
            	var resumeFile = resumeParts[resumeParts.length - 1];
            	resumeElement.innerHTML = resumeFile;
            	document.getElementById('resume').after(resumeElement);
            } else if(e[0] == 'services') {
            	e[1].forEach(function(value){
            		if(value != 0 && value != null) {
            			document.querySelector("#service_" + value.serviceId).setAttribute('checked', 'checked');
            		}
            		if(value.discounted == 1) {
            			discountSelected++;
            		}
            	});
            } else if(e[0] == 'vservices') {
            	e[1].forEach(function(value){
            		if(value != 0 && value != null) {
            			document.querySelector("#vservice_" + value).setAttribute('checked', 'checked');
            			document.querySelector("#vservice_" + value).parentNode.parentNode.parentNode.style.display = 'block';
            		}
            	});
            }
            else if (document.getElementById(e[0]) != null){
            	document.getElementById(e[0]).value = e[1];
           	}
            });
            var discounted = document.querySelectorAll('.discounted');
        	if(discountSelected >= 2) {
        		discounted.forEach(function(e){
        			if(e.checked == false) {
        				e.setAttribute('disabled', 'disabled');
        			}
        		});
        	}
        }
	}
	xhr.send("email=" + email);
}
function displayDrilldown(id, checked, discountRemaining) {
	if(discountRemaining > 0) {
		if(checked) {
			discountSelected++;
		} else {
			discountSelected--;
		}
	}
	var discounted = document.querySelectorAll('.discounted');
	if(discountSelected >= 2) {
		discounted.forEach(function(e){
			if(e.checked == false) {
				e.setAttribute('disabled', 'disabled');
			}
		});
	} else {
		discounted.forEach(function(e){
			if(e.checked == false) {
				e.removeAttribute('disabled', 'disabled');
			}
		});
	}
 	var drilldownUl = document.querySelector("#" + id).parentNode.parentNode.children[1];
 	if(typeof drilldownUl != 'undefined') {
     	if(drilldownUl.style.display == 'block') {
    		drilldownUl.style.display = 'none';
    	} else {
    		drilldownUl.style.display = 'block';
    	}
    }
}
</script>
<?php if(empty($email) && $mode != 'new'){ ?>
<form method="post" action="index.php">
<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="newSkill">Add Skill</label>
	</div>
	<div class="col-lg-3">
		<input type="text" class="form-control" name="newSkill" id="newSkill" />
	</div>
	<div class="col-lg-2">
		<input type="submit" name="submit" value="Submit" class="btn btn-primary"/>
	</div>
</div>
</form>
<?php } ?>
<h3>Candidate Info</h3>
<form method="post" class="candidateForm" action="index.php?ce=<?php echo !empty($_GET['ce']) ? $_GET['ce'] : ''; ?>&id=<?php echo !empty($_GET['id']) ? $_GET['id'] : ''; ?>&m=<?php echo !empty($_GET['m']) ? $_GET['m'] : ''; ?>" enctype="multipart/form-data">
<div class="row">
    <div class="col-lg-3">
    	Press Ctrl and select for multiple options.
    </div>
</div>
<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="email">Email</label>
	</div>
	<div class="col-lg-3">
		<input required type="text" name="email" id="email" class="form-control" value="<?php echo !empty($candidateDetails) ? $candidateDetails['email'] : $email; ?>" <?php echo !empty($email) ? 'readonly' : 'onblur="getCandidate(this.value)"'; ?>/>
	</div>
</div>
<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="name">Name</label>
	</div>
	<div class="col-lg-3">
		<input type="text" name="name" class="form-control" id="name" value="<?php echo !empty($candidateDetails) ? $candidateDetails['name'] : ''; ?>" />
	</div>
</div>
<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="mobile">Mobile No.</label>
	</div>
	<div class="col-lg-3">
		<input required type="text" name="mobile" class="form-control" id="mobile" value="<?php echo !empty($candidateDetails) ? $candidateDetails['mobile'] : ''; ?>" />
	</div>
</div>
<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="education">Education</label>
	</div>
	<div class="col-lg-3">
        <select id="education" name="education[]" class="form-control" multiple="multiple">
        <option value="">Select</option>
        <?php foreach($education as $edu) { ?>
        <option value="<?php echo $edu; ?>" <?php echo !empty($candidateDetails) ? (in_array($edu, explode(", ", $candidateDetails['education'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $edu; ?></option>
        <?php } ?>
        </select>
    </div>
</div>
<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="skills">Skills/Keywords</label>
	</div>
	<div class="col-lg-3">
        <select required id="skills" name="skills[]" class="form-control" multiple="multiple">
        <option value="">Select</option>
        <?php foreach($skills as $eachskill) { ?>
        <option value="<?php echo $eachskill['skill']; ?>" <?php echo !empty($candidateDetails) ? (in_array($eachskill['skill'], explode(", ", $candidateDetails['skills'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
        <?php } ?>
        </select>
  	</div>
</div>
<?php /* ?>
<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="subskills">Sub Skills</label>
	</div>
	<div class="col-lg-3">
        <select id="subskills" name="subskills[]" class="form-control" multiple="multiple">
        <option value="">Select</option>
        <?php foreach($skills as $eachskill) { ?>
        <option value="<?php echo $eachskill['skill']; ?>" <?php echo !empty($candidateDetails) ? (in_array($eachskill['skill'], explode(", ", $candidateDetails['subskills'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $eachskill['skill']; ?></option>
        <?php } ?>
        </select>
  	</div>
</div>
<?php */ ?>
<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="overallExperience">Overall Exp</label>
	</div>
	<div class="col-lg-3">
        <select id="overallExperience" class="form-control" name="overallExperience">
        <option value="">Select</option>
        <option value="0-3" <?php echo !empty($candidateDetails) ? ('0-3' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>0-3</option>
        <option value="4-7" <?php echo !empty($candidateDetails) ? ('4-7' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>4-7</option>
        <option value="8-10" <?php echo !empty($candidateDetails) ? ('8-10' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>8-10</option>
        <option value=">10" <?php echo !empty($candidateDetails) ? ('>10' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>>10</option>
        </select>
 	</div>
</div>

<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="relevantExperience">Relevant Exp</label>
	</div>
	<div class="col-lg-3">
        <select id="relevantExperience" class="form-control" name="relevantExperience">
        <option value="">Select</option>
        <option value="0-3" <?php echo !empty($candidateDetails) ? ('0-3' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>0-3</option>
        <option value="4-7" <?php echo !empty($candidateDetails) ? ('4-7' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>4-7</option>
        <option value="8-10" <?php echo !empty($candidateDetails) ? ('8-10' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>8-10</option>
        <option value=">10" <?php echo !empty($candidateDetails) ? ('>10' == $candidateDetails['overallExperience'] ? 'selected="selected"' : '') : ''; ?>>>10</option>
        </select>
  	</div>
</div>

<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="currentLocation">Current Loc</label>
	</div>
	<div class="col-lg-3">
        <select id="currentLocation" class="form-control" name="currentLocation[]" multiple="multiple">
        <option value="">Select</option>
        <?php foreach($locations as $location) { ?>
        <option value="<?php echo $location; ?>" <?php echo !empty($candidateDetails) ? (in_array($location, explode(", ", $candidateDetails['currentLocation'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $location; ?></option>
        <?php } ?>
        </select>
  	</div>
</div>

<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="preferredLocation">Preferred Loc</label>
	</div>
	<div class="col-lg-3">
        <select id="preferredLocation" class="form-control" name="preferredLocation[]" multiple="multiple">
        <option value="">Select</option>
        <?php foreach($locations as $location) { ?>
        <option value="<?php echo $location; ?>" <?php echo !empty($candidateDetails) ? (in_array($location, explode(", ", $candidateDetails['preferredLocation'])) ? 'selected="selected"' : '') : ''; ?>><?php echo $location; ?></option>
        <?php } ?>
        <option value="Any">Any</option>
        </select>
  	</div>
</div>


<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="currentCompany">Current Company</label>
	</div>
	<div class="col-lg-3">
        <select id="currentCompany" class="form-control" name="currentCompany">
        <option value="">Select</option>
        <?php foreach($companies as $company) { ?>
        <option value="<?php echo $company; ?>" <?php echo !empty($candidateDetails) ? ($company == $candidateDetails['currentCompany'] ? 'selected="selected"' : '') : ''; ?>><?php echo $company; ?></option>
        <?php } ?>
        </select>
   	</div>
</div>

<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="currentCtc">Current CTC (Lacs)</label>
	</div>
	<div class="col-lg-3">
		<input type="text" name="currentCtc" class="form-control" id="currentCtc" value="<?php echo !empty($candidateDetails) ? $candidateDetails['currentCtc'] : ''; ?>" />
	</div>
</div>

<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="expectedCtc">Exp CTC (Lacs)</label>
	</div>
	<div class="col-lg-3">
		<input type="text" name="expectedCtc" class="form-control" id="expectedCtc" value="<?php echo !empty($candidateDetails) ? $candidateDetails['expectedCtc'] : ''; ?>" />
	</div>
</div>

<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="noticePeriod">Notice Period</label>
	</div>
	<div class="col-lg-3">
        <select id="noticePeriod" name="noticePeriod" class="form-control">
        <option value="">Select</option>
        <option value="15" <?php echo !empty($candidateDetails) ? ('15' == $candidateDetails['noticePeriod'] ? 'selected="selected"' : '') : ''; ?>>0-15 Days</option>
        <option value="30" <?php echo !empty($candidateDetails) ? ('30' == $candidateDetails['noticePeriod'] ? 'selected="selected"' : '') : ''; ?>>30 Days</option>
        <option value="60" <?php echo !empty($candidateDetails) ? ('60' == $candidateDetails['noticePeriod'] ? 'selected="selected"' : '') : ''; ?>>60 Days</option>
        <option value=">60" <?php echo !empty($candidateDetails) ? ('>60' == $candidateDetails['noticePeriod'] ? 'selected="selected"' : '') : ''; ?>>>60 Days</option>
        </select>
    </div>
</div>
<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="servingNotice">Serving Notice Period?</label>
	</div>
	<div class="col-lg-3">
		<label><input type="radio" name="servingNotice" id="servingNoticeYes" value="1" <?php echo !empty($candidateDetails) ? (1 == $candidateDetails['servingNotice'] ? 'checked' : '') : ''; ?> /> Yes</label>
		<label><input type="radio" name="servingNotice" id="servingNoticeNo" value="2" <?php echo !empty($candidateDetails) ? (2 == $candidateDetails['servingNotice'] ? 'checked' : '') : ''; ?> /> No</label>
	</div>
</div>

<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="resume">Upload Resume</label>
	</div>
	<div class="col-lg-3">
        <input type="file" name="resume" id="resume" class="form-control" />
        <?php if(!empty($candidateDetails) && !empty($candidateDetails['resume'])) { ?>
        <a href="<?php echo $candidateDetails['resume']; ?>" target="blank" ><?php echo pathinfo($candidateDetails['resume'], PATHINFO_BASENAME); ?></a>
        <?php } ?>
   	</div>
</div>

<div class="row">
	<div class="col-lg-1">
		<label class="control-label" for="resume">Services (Only 2 free services)</label>
	</div>
	<div class="col-lg-3 checkboxScroll">
	<ul>
	<?php $discounted = 0; foreach($services as $key => $service) { 
	    if(!empty($candidateDetails) && ($service['tcount'] - $service['scount'] > 0) && in_array($service['serviceId'], $candidateDetails['services'])) {
	        $discounted++;
	    }
	?>
	<li><label class="control-label"><input type="checkbox" name="services[]" class="<?php echo ($service['tcount'] - $service['scount']) > 0 ? 'discounted' : ''; ?>" onchange="displayDrilldown(this.id, this.checked, '<?php echo $service['tcount'] - $service['scount']; ?>')" id="service_<?php echo $service['serviceId']; ?>" value="<?php echo $service['serviceId']; ?>" <?php echo !empty($candidateDetails) && in_array($service['serviceId'], $candidateDetails['services']) ? 'checked="checked"' : ''; ?> <?php echo !empty($candidateDetails) && $discounted >= 2 && ($service['tcount'] - $service['scount']) > 0 && !in_array($service['serviceId'], $candidateDetails['services']) ? 'disabled="disabled"' : ''; ?> /> <?php echo $service['serviceName']; ?></label>
	<?php if($service['drilldown'] == 'Y') { ?>
		<ul class="vservice-ul" <?php echo !empty($candidateDetails) && in_array($service['serviceId'], $candidateDetails['services']) ? 'style="display:block;"' : ''; ?>>
		<?php foreach($service['vservices'] as $key => $vservice) {?>
			<li><label class="control-label"><input type="checkbox" name="vservices[<?php echo $service['serviceId'];?>][]" id="vservice_<?php echo $vservice['vserviceId']; ?>" value="<?php echo $vservice['vserviceId']?>" <?php echo !empty($candidateDetails) && in_array($vservice['vserviceId'], $candidateDetails['vservices']) ? 'checked="checked"' : ''; ?> /> <?php echo $vservice['vendorServiceName']; ?></label></li>
		<?php } ?>
		</ul>
	<?php } ?>
	</li>
	<?php } ?>
	</ul>
   	</div>
</div>
<input type="hidden" id="id" name="candidateId" value="<?php echo !empty($candidateDetails) ? $candidateDetails['id'] : ''; ?>" />

<div class="row">
	<div class="col-lg-1"><input type="submit" name="submit" value="Submit" class="btn btn-primary"/></div>
</div>
</form>
<?php include 'footer.php'; ?>