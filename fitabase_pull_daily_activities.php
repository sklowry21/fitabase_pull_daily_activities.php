<?php
/**
 * PLUGIN NAME: fitabase_pull_daily_activities.php
 * DESCRIPTION: This pulls daily activity information from the fitabase database using the fitabase API and loads data into the REDCap database.
 * VERSION:     1.0
 * AUTHOR:      Sue Lowry - University of Minnesota
 * Changes:
 */

// REDCap authentication not applicable
#define("NOAUTH", true);
// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";
$subscription_key = array('Ocp-Apim-Subscription-Key: ********************************'); // This is the http header for the API

// Set the variables that contain the REDCap field names
$fb_prof_name_fld = 'fb_research_number';
$from_date_fld = 'acc_start';
$to_date_fld = 'acc_end';
$Date_fld = 'day';
$LightlyActiveMinutes_fld = 'acc_light_day';
$FairlyActiveMinutes_fld = 'acc_mod_day';
$VeryActiveMinutes_fld = 'acc_vig_day';
$SedentaryMinutes_fld = 'acc_sed_day';
$TotalSteps_fld = 'acc_steps_day';
$AverageHoursWorn_fld = 'acc_hoursperday';
$TotalActiveAverage_fld = 'average_minutes_total';
$LightlyActiveAverage_fld = 'averagelight_intensity';
$FairlyActiveAverage_fld = 'averagemoderate_intensity';
$VeryActiveAverage_fld = 'averagevigorous_intensity';
$SedentaryAverage_fld = 'averagesedentary_intensity';
$TotalStepsAverage_fld = 'dailystepcount';

// Set the project ids
$prod_pid = 6149;
$test_pid = 7553;
$pid = $prod_pid;
if ($project_id == $test_pid) { $pid = $test_pid; }
$log_pid = $pid;
// Set the event ids
$prod_fb_prof_name_event_id = 18907;
$test_fb_prof_name_event_id = 19276;
$test_fb_prof_name_event_id = 21769;
$fb_prof_name_event_id = $prod_fb_prof_name_event_id;
if ($project_id == $test_pid) { $fb_prof_name_event_id = $test_fb_prof_name_event_id; }

// Set debugLog flag and eol variable
$debugLog = false;
#$debugLog = true;
$eol = PHP_EOL;
$eol = '<br/>' . PHP_EOL;

// OPTIONAL: Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

if ($debugLog) { LogWrite("", $log_pid); }

function LogWrite($logText, $logPID) {
    $eol = PHP_EOL;
    $eol2 = '<br/>' . PHP_EOL;
    $logFile = dirname(__FILE__). DIRECTORY_SEPARATOR . "fitabase_".$logPID.".log";

    if ($fd = @fopen($logFile, "a") ) {
        fwrite($fd, date("Y-m-d H:i:s",time()) . ": $logText " . $eol);
        print date("Y-m-d H:i:s",time()) . ": $logText" . $eol2;
        fclose($fd);
    }
}

if (isset($_GET['debugLog'])) { 
    $debugLog = ($_GET['debugLog'] = "true");
}        

if ($debugLog) foreach ($_GET as $key => $value) { LogWrite("GET['$key']=" . $value, $log_pid); }
if ($debugLog) foreach ($_POST as $key => $value) { LogWrite("POST['$key']=" . $value, $log_pid); }

// Restrict this plugin to a specific REDCap project (in case user's randomly find the plugin's URL)
if ($project_id != $pid) {
	LogWrite("This plugin is only accessible from project # $prod_pid or $test_pid, not $project_id.", $log_pid);
	exit;
}
// The record ID and event ID must be passed in
if (!$_GET['record'] > '' or !$_GET['event'] > '') {
	LogWrite("You can only run this from a record-specific and event-specific page.", $log_pid);
	exit;
}
$record_id = $_GET['record'];
$event_name = $_GET['event'];
$instance = $_GET['instance'];
$event_id = REDCap::getEventIdFromUniqueEvent ( $event_name );
if ($debugLog) { LogWrite("event_id: $event_id, instance: $instance", $log_pid); }

// Use the record_id and the event that was specified to hold the profile name to get the fitabase profile name
if ($debugLog) { LogWrite("pid: $pid, record_id: $record_id, fb_prof_name_fld: $fb_prof_name_fld, fb_prof_name_event_id: $fb_prof_name_event_id", $log_pid); }
$data = REDCap::getData($pid, 'array', array($record_id), array($fb_prof_name_fld), $fb_prof_name_event_id);
if ($debugLog) { LogWrite("data[$record_id]: ".print_r($data[$record_id], true), $log_pid); }
if ($debugLog) { 
	foreach ($data as $rec => $arr) { LogWrite("rec: $rec", $log_pid); 
		foreach ($arr as $rpt => $events) { LogWrite("rpt: $rpt, events: ".print_r($events, true), $log_pid);
			foreach ($events as $event => $rpts) {LogWrite("event: $event", $log_pid); }
				foreach ($rpts as $dummy) { foreach ($dummy as $rptid => $flds) { LogWrite("rptid: $rptid, flds: ".print_r($flds, true), $log_pid);
					foreach ($flds as $fld => $val) { LogWrite("fld: $fld, val: $val", $log_pid); }
				}
			}
		}
	} 
}
$fb_prof_name_val = "";
if ($debugLog) { LogWrite("rpts: ".print_r($data[$record_id]["repeat_instances"][$fb_prof_name_event_id][""], true), $log_pid); }
foreach ($data[$record_id]["repeat_instances"][$fb_prof_name_event_id][""] as $rpt => $flds) {
	if ($debugLog) { LogWrite("rpt: $rpt, flds: ".print_r($flds, true), $log_pid); }
	if ($debugLog) { LogWrite("flds[$fb_prof_name_fld]: ".$flds[$fb_prof_name_fld], $log_pid); }
	if ($flds[$fb_prof_name_fld] > "") { $fb_prof_name_val = $flds[$fb_prof_name_fld]; }
}
if ($fb_prof_name_val == '') {
        LogWrite("The $fb_prof_name_fld field has not yet been entered and saved.", $log_pid);
        exit;
}

// Use the record_id and event to get the date range for which to get data
$data = REDCap::getData($pid, 'array', array($record_id), array($from_date_fld, $to_date_fld), $event_id);
if ($debugLog) { LogWrite("data[$record_id]: ".print_r($data[$record_id], true), $log_pid); }
/* What is returned is different if it's a repeating instrument or event than if it's not */
if ( is_array($data[$record_id]["repeat_instances"])) {
	$flds = $data[$record_id]["repeat_instances"][$event_id][""][$instance];
		if ($debugLog) { LogWrite("flds['".$from_date_fld."']: ".$flds[$from_date_fld], $log_pid); }
		if ($debugLog) { LogWrite("flds['".$to_date_fld."']: ".$flds[$to_date_fld], $log_pid ); }
		$from_date_val = $flds[$from_date_fld];
		$to_date_val = $flds[$to_date_fld];
} else {
	if ($debugLog) { foreach ($data as $rec => $arr) { foreach ($arr as $event => $flds) { foreach ($flds as $fld => $val) { LogWrite("fld: $fld, val: $val", $log_pid); }}} }
	if ($debugLog) { LogWrite("rec: $rec, event: $event", $log_pid); }
	if ($debugLog) { LogWrite("flds['".$from_date_fld."']: ".$flds[$from_date_fld], $log_pid); }
	if ($debugLog) { LogWrite("flds['".$to_date_fld."']: ".$flds[$to_date_fld], $log_pid ); }
	$from_date_val = $data[$record_id][$event_id][$from_date_fld];
	$to_date_val = $data[$record_id][$event_id][$to_date_fld];
}
// If either date is blank then we cannot proceed
if ($from_date_val == '') {
        LogWrite("The $from_date_fld field has not yet been entered and saved.", $log_pid);
}
if ($to_date_val == '') {
        LogWrite("The $to_date_fld field has not yet been entered and saved.", $log_pid);
}
if ($from_date_val == '' or $to_date_val == '') {
        exit;
}


// Use the fitabase API to find the ProfileId for which "Name" is $fb_prof_name_val
$profiles_url = "https://api.fitabase.com/v1/Profiles/";
$profiles = curl_api($profiles_url, $subscription_key);
#echo 'Profiles: ' . $eol . print_r($profiles, true) . $eol;
/* Here is what one profile object looks like
    [3] => stdClass Object
        (
            [ProfileId] => e3a269f0-2cc6-4585-9fdc-111074a16596
            [CreatedDate] => 2017-10-31T19:31:03.033
            [Name] => 015
        )
*/
$profile_id = "";
foreach ($profiles as $profile) {
#    print "profile: " . $eol . print_r($profile, true) . $eol;
#    print "profile->Name: " . $profile->Name . $eol;
#    print "profile->ProfileId: " . $profile->ProfileId . $eol;
    if ($profile->Name == $fb_prof_name_val) { 
        $profile_id = $profile->ProfileId; 
        break; 
    }
}
if ($debugLog) { LogWrite("profile_id: $profile_id", $log_pid); }

// Use the fitabase API to find the last sync timestamp for $profile_id
$last_sync_url = "https://api.fitabase.com/v1/Sync/Latest/$profile_id";
$last_sync = curl_api($last_sync_url, $subscription_key);
$last_sync_date = $last_sync->SyncDate;
if ($debugLog) { LogWrite( "last_sync_date: $last_sync_date", $log_pid); }


// Use the fitabase API to find the daily activity for each of the days in the date range
$daily_activity_url = "https://api.fitabase.com/v1/DailyActivity/$profile_id/$from_date_val/$to_date_val/";
$days = curl_api($daily_activity_url, $subscription_key);
#echo '<pre>Daily Activity: ' . $eol . print_r($days, true) . "</pre>" . $eol;
/* Here is what the first element of the returned array looks like
    [0] => stdClass Object
        (
            [ActivityDate] => 2017-10-28T00:00:00
            [TotalDistance] => 2.4100000858307
            [TrackerDistance] => 2.4100000858307
            [LoggedActivitiesDistance] => 0
            [VeryActiveDistance] => 0
            [ModeratelyActiveDistance] => 0
            [LightActiveDistance] => 0.98000001907349
            [SedentaryActiveDistance] => 0
            [VeryActiveMinutes] => 0
            [FairlyActiveMinutes] => 0
            [LightlyActiveMinutes] => 66
            [SedentaryMinutes] => 1217
            [TotalSteps] => 3523
            [Calories] => 1822
            [Floors] => 0
            [CaloriesBMR] => 1368
            [MarginalCalories] => 121
            [RestingHeartRate] => 61
            [GoalCaloriesOut] => 500
            [GoalDistance] => 1.61
            [GoalFloors] => 2
            [GoalSteps] => 2500
        )
*/

/* Show a link for going back to the prevous screen */
print '<a href="javascript:history.go(-1)"><= Go back and remember to set invalid dates (if any) and save the form to save the calculated fields</a><br/><br/>';
/* Display a table with the data that is being updated */
print '<table border="2">
 <tr>
  <th style="padding:5px;">Record</th>
  <th style="padding:5px;">Event</th>
  <th style="padding:5px;">Date</th>
  <th style="padding:5px;">Light</th>
  <th style="padding:5px;">Moderate</th>
  <th style="padding:5px;">Vigorous</th>
  <th style="padding:5px;">Sedentary</th>
  <th style="padding:5px;">Steps</th>
  <th style="padding:5px;">Fields Updated</th>
 </tr>';
$days_of_activity = 0;
$minutes_total = 0;
$light_intensity = 0;
$moderate_intensity = 0;
$vigorous_intensity = 0;
$sedentary_intensity = 0;
$stepcount = 0;
foreach ($days as $day_num => $day) {
    $day_suffix = '' + ($day_num + 1);
    $ActivityDate = substr($day->ActivityDate, 0, 10);;
    $LightlyActiveMinutes = $day->LightlyActiveMinutes;
    $FairlyActiveMinutes = $day->FairlyActiveMinutes;
    $VeryActiveMinutes = $day->VeryActiveMinutes;
    $SedentaryMinutes = $day->SedentaryMinutes;
    $TotalSteps = $day->TotalSteps;
    if ($debugLog) { LogWrite("<br><br>Day $day_num, day_suffix = $day_suffix", $log_pid); 
                     LogWrite("ActivityDate: $ActivityDate", $log_pid); 
                     LogWrite("LightlyActiveMinutes: $LightlyActiveMinutes", $log_pid); 
                     LogWrite("FairlyActiveMinutes: $FairlyActiveMinutes", $log_pid); 
                     LogWrite("VeryActiveMinutes: $VeryActiveMinutes", $log_pid); 
                     LogWrite("SedentaryMinutes: $SedentaryMinutes", $log_pid); 
                     LogWrite("TotalSteps: $TotalSteps", $log_pid); }
    $days_of_activity += 1;
    $minutes_total += $LightlyActiveMinutes + $FairlyActiveMinutes + $VeryActiveMinutes;
    $light_intensity += $LightlyActiveMinutes;
    $moderate_intensity += $FairlyActiveMinutes;
    $vigorous_intensity += $VeryActiveMinutes;
    $sedentary_intensity += $SedentaryMinutes;
    $stepcount += $TotalSteps;

    // Save the data to the current event
    $data = array();
    $data[$record_id][$event_id] = array( $Date_fld.$day_suffix => $ActivityDate
                                        , $LightlyActiveMinutes_fld.$day_suffix => $LightlyActiveMinutes 
                                        , $FairlyActiveMinutes_fld.$day_suffix => $FairlyActiveMinutes
                                        , $VeryActiveMinutes_fld.$day_suffix => $VeryActiveMinutes
                                        , $SedentaryMinutes_fld.$day_suffix => $SedentaryMinutes
                                        , $TotalSteps_fld.$day_suffix => $TotalSteps
                                        , $TotalSteps_fld.$day_suffix => $TotalSteps);
    $response = REDCap::saveData($pid, 'array', $data);
    if (count($response['errors'])   > 0) { LogWrite("Errors: ".$response['errors'], $log_pid) ; foreach ($response['errors'] as $msg) { LogWrite($msg, $log_pid); } }
    if (count($response['warnings']) > 0) { LogWrite("Warnings: ".$response['warnings'], $log_pid) ; foreach ($response['warnings'] as $msg) { LogWrite($msg, $log_pid); } }
    #if ($debugLog) { LogWrite("Ids: ".print_r($response['ids']), $log_pid); }
    #if ($debugLog) { LogWrite( "We just updated {$response['item_count']} field(s).", $log_pid); }
    print ' <tr>
  <td style=padding:5px;text-align:right;">'.$record_id.'</td>
  <td style=padding:5px;text-align:right;">'.$event_name.'</td>
  <td style=padding:5px;text-align:right;">'.$ActivityDate.'</td>
  <td style=padding:5px;text-align:right;">'.$LightlyActiveMinutes.'</td>
  <td style=padding:5px;text-align:right;">'.$FairlyActiveMinutes.'</td>
  <td style=padding:5px;text-align:right;">'.$VeryActiveMinutes.'</td>
  <td style=padding:5px;text-align:right;">'.$SedentaryMinutes.'</td>
  <td style=padding:5px;text-align:right;">'.$TotalSteps.'</td>
  <td style=padding:5px;text-align:right;">'.$response['item_count'].'</td>
 </tr>';
}
$average_minutes_total = $minutes_total / $days_of_activity;
$averagelight_intensity = $light_intensity / $days_of_activity;
$averagemoderate_intensity = $moderate_intensity / $days_of_activity;
$averagevigorous_intensity = $vigorous_intensity / $days_of_activity;
$averagesedentary_intensity = $sedentary_intensity / $days_of_activity;
$average_hours_worn = round((($light_intensity + $moderate_intensity + $vigorous_intensity + $sedentary_intensity) / 60) / $days_of_activity, 2);
$dailystepcount = $stepcount / $days_of_activity;

print '</table>';

print '<br/><br/><a href="javascript:history.go(-1)"><= Go back and remember to set invalid dates (if any) and save the form to save the calculated fields</a>';

function curl_api($in_url, $in_header) {
    // Use curl to call the API
    $curl_h = curl_init($in_url);
    // Set the header to hold the subscription key
    curl_setopt($curl_h, CURLOPT_HTTPHEADER, $in_header);
    // Set the option to return the result to a string variable
    curl_setopt($curl_h, CURLOPT_RETURNTRANSFER, true);
    // Execute the API
    $json_result = curl_exec($curl_h);
    // Decode the json result
    $result = json_decode($json_result);
    return $result;
}

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

