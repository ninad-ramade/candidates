<?php
session_start();
$protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
if(empty($email) && $mode != 'new') {
    if(empty($_SESSION['user'])) {
        header('Location: ' . $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'login.php');
    } else {
?>
<nav class="navbar navbar-inverse">
  <div class="container-fluid">
    <div class="navbar-header">
      <a class="navbar-brand" href="/"><img alt="RapidTech" src="assets/logo.jpeg"></a>
      <a class="navbar-brand" href="/">RAPID Jobs</a>
    </div>
    <ul class="nav navbar-nav">
    	<li><a href="<?php echo $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'report.php'; ?>">Candidates</a></li>
      	<li><a href="/">Candidate Form</a></li>
		<li><a href="<?php echo $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'extract.php'; ?>">Extract Profiles</a></li>
		<li><a href="<?php echo $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'user.php'; ?>">Create User</a></li>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="<?php echo $protocol . '://' . $_SERVER['SERVER_NAME'] . baseurl . 'login.php'; ?>"><?php echo $_SESSION['user']['name']; ?>, Logout</a></li>
    </ul>
  </div>
</nav>
<?php }} ?>
<div class="container-fluid">