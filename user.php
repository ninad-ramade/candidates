<?php
//ini_set('display_errors', 1);
include_once 'config.php';
session_start();
if($_SESSION['user']['readOnlyAccess'] == 1) {
    echo 'Unauthorized access!';exit;
}
function getUser($email) {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM users WHERE email = '" . $email . "'";
    $result = $db->query($sql);
    $user = [];
    if ($result->num_rows < 1) {
        return $user;
    }
    while($row = $result->fetch_assoc()) {
        $user = $row;
    }
    $db->close();
    return $user;
}
$userId = '';
if(isset($_POST['submit'])) {
    $email = $_POST['email'];
    $name = $_POST['name'];
    $password = md5($_POST['password']);
    $existingUser = getUser($email);
    $db = new mysqli(servername, username, password, dbname);
    if(!empty($existingSKill) && count($existingSKill) > 0) {
        echo 'User with email ' .$email. ' already exists.';
    } else {
        $sql = "INSERT INTO users (username, email, name, password, createdOn, createdBy, readOnlyAccess) VALUES ('" . $email . "', '" . $email . "', '" . $name . "', '" . $password . "', '" . date('Y-m-d H:i:s') . "', '" . $userId . "', " . $_POST['readOnlyAccess'] . ")";
        if ($db->query($sql) === TRUE) {
            echo "User created successfully";
        } else {
            echo "Error: " . $sql . "<br>" . $db->error;
        }
    }
    $db->close();
}
?>
<script>
function validatePassword(e) {
	if(document.getElementById("password").value != '' && document.getElementById("password2").value != '' && document.getElementById("password").value != document.getElementById("password2").value) {
		alert('Password and Re-typed password are not same.');
		e.preventDefault();
		return false;
	}
}
</script>
<?php 
include 'header.php';
include 'menu.php'; ?>
<h3>Create User</h3>
<form method="post" class="candidateForm" action="user.php">
	<div class="row">
		<div class="col-lg-1">
			<label for="name">Name</label>
		</div>
		<div class="col-lg-3">
			<input type="text" name="name" class="form-control" id="name" required />
		</div>
	</div>
	<div class="row">
		<div class="col-lg-1">
			<label for="email">Email</label>
		</div>
		<div class="col-lg-3">
			<input type="email" name="email" class="form-control" id="email" required />
		</div>
	</div>
	<div class="row">
		<div class="col-lg-1">
			<label for="password">Password</label>
		</div>
		<div class="col-lg-3">
			<input type="password" name="password" class="form-control" id="password" required />
		</div>
	</div>
	<div class="row">
		<div class="col-lg-1">
			<label for="password2">Re-type password</label>
		</div>
		<div class="col-lg-3">
			<input type="password" name="password2" class="form-control" id="password2" required />
		</div>
	</div>
	<div class="row">
		<div class="col-lg-1">
			<label for="email">Read only acccess?</label>
		</div>
		<div class="col-lg-3">
			<label for="readOnlyAccessYes"><input type="radio" name="readOnlyAccess" id="readOnlyAccessYes" value="1" required /> Yes</label>
			<label for="readOnlyAccessNo"><input type="radio" name="readOnlyAccess" id="readOnlyAccessNo" value="2" checked required /> No</label>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-1">
			<input type="submit" name="submit" class="btn btn-primary" onclick="validatePassword(event)" />
		</div>
	</div>
</form>