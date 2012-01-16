<?php
/**
 * Privacy settings page
 *
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
/**
 * App includes
 */
require_once 'include.php';
$output = '';
$message = '';
// construct user object
$fbplatform = new FBPlatform();

// construct user object
$appuser = $fbplatform->getFBUser();

$university = new University('open.ac.uk');
 
$toptext = "<h1>My Preferences</h1>";
$toptext .= "<p>You can change some aspects of this application from here to better suit your needs.".
    " You can also change your privacy settings.</p>";
$output .=  $fbplatform->renderTop(false, $toptext);
 
 
// save preferences if have been changed
$savepreferences = array();
// check for region change
if (isset($_POST['ou_region']) && in_array($_POST['ou_region'], array_keys($university->getRegions()))) {
 	$savepreferences['ou_region'] = $_POST['ou_region'];
}
// check for study buddy privacy change
// names beginning with "fb_" are reserved for use by facebook so we must translate
if ( isset($_POST['priv_studybuddy']) && in_array($_POST['priv_studybuddy'], array_keys($appuser->getPrivacyOptions())) ) {
 	$savepreferences['fb_priv_studybuddy'] = $_POST['priv_studybuddy'];
}
// my ou story link
if ( isset($_POST['priv_myoustorylink']) && in_array($_POST['priv_myoustorylink'], array(0,1)) ) {
 	$savepreferences['fb_priv_myoustorylink'] = $_POST['priv_myoustorylink'];
}

if( sizeof($savepreferences) > 0 ) {
 	if ($appuser->savePreferences($savepreferences)) {
		// saved successfully
 		$message = "<div class='success'><div class='message'>Preferences Saved!</div>".
 		     "Your preferences have been updated. <a href='index.php?mode=".COURSE_PRESENT."'>".
 		     "Click here</a> to return to your course profiles.</div>";
 	}
  else {
    // an error occurred  
    $message = "<div class='error'><div class='message'>Sorry you preferences could not be saved at this time</div>".
      "Please try again later</div>";
  }
}
$user_prefs=$appuser->getPreferences();

// update about line
if ( isset($_POST['about2']) && trim($_POST['about2']) != '') {
  $about = htmlentities($_POST['about1']).' '.htmlentities($_POST['about2']);
  $appuser->updateAbout($about);	
}

// build privacy options from My OU Story if user has this installed
if ( $appuser->myoustory_uid != 0 ) {
  $myoustorylink = $appuser->getMyOUStoryLink();
  $myoustorylinkcontrol = "";
  if ( ! $myoustorylink['available'] ) {
    $myoustorylinkcontrol .= sprintf("Please note: ".
                           "The privacy settings in My OU Story are currently blocking ".
                           "Course Profiles from being able to link to it. Please allow access from ".
                           "the <a href='%s'>My OU Story preferences</a> page.", MYOUSTORY_PREFS_URL);	
  }
  $myoustorylinkcontrol .= sprintf("<label for='priv_myoustorylink'>Link to My OU Story?</label>".
                            "<select name='priv_myoustorylink'>%s</select>",
  $fbplatform->renderOptionSet(array(1 => 'Yes',0 => 'No'), $myoustorylink['linked'] ) );   	
}

// render preferences template
$vars = array('message' => $message,
              'profilestmtcontrol' => $fbplatform->getPromptedInputBox( array('I am', 
		                                             'I am studying to', 
		                                             'I want to be',
		                                             'I am aiming to'),
		                                             'about', 
		                                             $appuser->about),
		      'ouregioncontrol' => $fbplatform->renderOptionSet($university->getRegions(), $user_prefs['ou_region']),
		      'privstudybuddycontrol' => $fbplatform->renderOptionSet($appuser->getPrivacyOptions(TRUE), $user_prefs['fb_priv_studybuddy'] ),
		      'myoustorylinkcontrol' => $myoustorylinkcontrol);
$output .= $fbplatform->getTemplate('preferences', $vars);
$output .= $fbplatform->renderBottom();
echo $output;
?>
