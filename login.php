<?php
include_once 'config.php';
session_start();
if(isset($_POST['submit'])) {
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM users WHERE username = '" . $username . "'";
    $result = $db->query($sql);
    if ($result->num_rows < 1) {
        echo 'Incorrect Username.';
    }
    while($row = $result->fetch_assoc()) {
        $user = $row;
    }
    if($user['password'] != $password) {
        echo 'Incorrect Password';
    }
    unset($user['password']);
    $db->close();
    $_SESSION['user'] = $user;
    header('Location: http://' . $_SERVER['SERVER_NAME'] . baseurl);
    exit;
} else if(!empty($_SESSION['user'])) {
    unset($_SESSION['user']);
    session_destroy();
    echo 'Logged out.';
}
include 'header.php';
?>
<div class="container-fluid">
<div class="row">
	<div class="section"><img class="logo" alt="RapidTech" src="assets/logo.png"></div>
	<div class="section">
    	<div class="loginBrand">RAPID Jobs</div>
        <div class="loginWrapper col-lg-6">
            <form method="post" action="login.php">
                <div class="form-group">
                    <label class="control-label">Username</label>
                    <input type="text" name="username" id="username" required class="form-control" />
                </div>
                <div class="form-group">
                    <label class="control-label">Password</label>
                   	<input type="password" name="password" id="password" required class="form-control" />
                </div>
                <div class="form-group">
                	<input type="submit" name="submit" value="Login" class="btn btn-primary" />
                </div>
            </form>
        </div>
  	</div>
</div>
<?php include 'footer.php'; ?>