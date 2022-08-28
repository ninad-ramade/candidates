<?php
include_once 'config.php';
$candidates = getCandidates();
function getCandidates() {
    $db = new mysqli(servername, username, password, dbname);
    $sql = "SELECT * FROM candidates";
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
$columns = !empty($candidates) ? array_keys($candidates[0]) : [];
?>
<a href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . baseurl; ?>">Resume Form</a>
<h3>Resume List</h3>
<table>
<tr>
	<?php foreach($columns as $column){ ?>
	<th><?php echo ucwords(strtolower($column)); ?></th>
	<?php } ?>
</tr>
<?php foreach($candidates as $candidate) { ?>
<tr>
	<?php foreach($columns as $column){ ?>
	<td><?php echo $candidate[$column]; ?></td>
	<?php } ?>
</tr>
<?php } ?>
</table>