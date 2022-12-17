<?php 
//ini_set('display_errors', 1);
include_once __DIR__ . '/../config.php';
use PHPMailer\PHPMailer\PHPMailer;
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
ini_set('max_execution_time', 0);
ini_set("memory_limit", "-1");
set_time_limit(0);
$db = new mysqli(servername, username, password, dbname);
$date = date('Y-m-d');
$emailCount = 0;
$logHandle = fopen('cron.log', 'c');
while ($emailCount < 500) {
    $failedEmails = [];
    $requirements = [];
    while (count($requirements) < 1) {
        $sql = "SELECT * FROM vendor_req WHERE reqdate = '" . $date . "' AND Active = 'Y' AND cronStatus = 0";
        $result = $db->query($sql);
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $requirements[] = $row;
            }
            $where = [];
            foreach($requirements as $eachReq) {
                $sql = "UPDATE vendor_req SET cronStatus = 1 WHERE vreqid = " . $eachReq['vreqid'];
                $db->query($sql);
                $skills = array_values(array_filter(explode(",", $eachReq['skills'])));
                $locations = array_values(array_filter(explode(",", $eachReq['worklocation'])));
                foreach($skills as $eachskill) {
                    $where[] = 'skills like "%,'. $eachskill .',%"';
                }
                $sql = "SELECT * FROM candidates WHERE " . implode(" OR ", $where);
                $result = $db->query($sql);
                if ($result->num_rows < 1) {
                    $sql = "UPDATE vendor_req SET cronStatus = 2 WHERE vreqid = " . $eachReq['vreqid'];
                    $db->query($sql);
                    continue;
                } else {
                    $candidates = [];
                    while($row = $result->fetch_assoc()) {
                        $candidates[] = $row;
                    }
                    foreach ($candidates as $candidate) {
                        $resultSkills = array_filter(explode(",", $candidate['skills']));
                        $sql = "SELECT * FROM skills WHERE id IN (" . implode(",", array_unique($resultSkills)) . ")";
                        try {
                            $result = $db->query($sql);
                            $candidateSkills = [];
                            while($row = $result->fetch_assoc()) {
                                $candidateSkills[$row['id']] = $row['skill'];
                            }
                        } catch (mysqli_sql_exception $e) {
                        }
                        $sql = "SELECT * FROM locations WHERE id IN (" . implode(",", array_unique($locations)) . ")";
                        try {
                            $result = $db->query($sql);
                            $candidateLocations = [];
                            while($row = $result->fetch_assoc()) {
                                $candidateLocations[$row['id']] = $row['location'];
                            }
                        } catch (mysqli_sql_exception $e) {
                        }
                        $subject = "Profile for " . (!empty($eachReq['skills']) ? implode(", ", array_intersect_key($candidateSkills, array_flip($skills))) : implode(", ", $candidateSkills));
                        if(!empty($eachReq['overallexp'])) {
                            $subject .= " with " . $eachReq['overallexp'] . " Years experience";
                        }
                        if(!empty($eachReq['worklocation'])) {
                            $subject .= " at " . implode(", ", $candidateLocations) . " location";
                        }
                        $sql = "SELECT * FROM vendor_clients WHERE clientid = " . $eachReq['CLIENTID'];
                        $result = $db->query($sql);
                        $client = mysqli_fetch_assoc($result);
                        $body = $client['clientname'] . ' is looking for candidates with the experience in ' . (!empty($eachReq['skills']) ? implode(", ", array_intersect_key($candidateSkills, array_flip($skills))) : implode(", ", $candidateSkills)) . ', Salary Range: ' . $eachReq['BUDGETFROM'] . ' Lakhs To ' . $eachReq['BUDGETTO'] . ' Lakhs.' . 
                            (!empty($eachReq['JobDescription']) ? '<br/>Job Description: ' . $eachReq['JobDescription'] : '');
                        $customEmailResponse = sendCustomEmail($candidate['email'], $candidate['name'], $db->insert_id, $subject, $body);
                        if($customEmailResponse !== true) {
                            $failedEmails[] = $candidate['email'] . ' Error: ' . $customEmailResponse;
                        } else {
                            $sql = "INSERT INTO applications (vendorId, jobid, candidateId, email, emailSentBy, emailSentOn, subject, status) VALUES (" . $eachReq['vendorid'] . ", " . $eachReq['reqno'] . ", " . $candidate['id'] . ", '" . $candidate['email'] . "', 1, '" . date('Y-m-d H:i:s') . "', '" . $subject . "', 'Email sent')";
                            $db->query($sql);
                            $emailCount++;
                        }
                    }
                }
                $sql = "UPDATE vendor_req SET cronStatus = 2 WHERE vreqid = " . $eachReq['vreqid'];
                $db->query($sql);
            }
        }
        $date = date('Y-m-d', strtotime($date . ' - 1 Day'));
    }
    $prefix = "Timestamp: " . date('Y-m-d H:i:s') . " - Req Date: ". $date ." - ";
    if(!empty($failedEmails)) {
        $log = "Could not send custom email to " . implode(", ", $failedEmails);
    } else {
        $log = "All custom emails sent successfully.";
    }
    fwrite($logHandle, $prefix . $log . "\r\n");
}
$db->close();
exit;
function sendCustomEmail($email, $name, $applicationId, $subject, $body) {
    $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    $fromEmail = 'support@rapidjobs.co.in';
    $mail = new PHPMailer();
    $mail->setFrom($fromEmail, "RTJobs");
    $mail->AddAddress($email, $name);
    $mail->AddReplyTo($fromEmail, "RTJobs");
    $mail->addBCC('ninad.ramade@gmail.com');
    $mail->addBCC('kerlaraju@rapidjobs.co.in');
    $mail->IsHTML(true);
    $mail->Subject = $subject;
    $content = 'Hi, ' . $name . ',<br/><br/>' . $body . '<br/><br/><a href="' . $protocol . '://profiles.rapidjobs.co.in' . baseurl . 'apply.php?id=' . base64_encode($applicationId) . '" target="blank">Apply Now</a><br/><br/>Thanks<br/><br/>RT Jobs';
    $mail->Body = $content;
    if(!$mail->Send()) {
        return $mail->ErrorInfo;
    }
    return true;
}

?>