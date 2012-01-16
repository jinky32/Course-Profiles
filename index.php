<?php
/**
 * Main landing page for Course Profiles.
 * 
 * Shows list of courses and tabs for different modes.
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
/**
 * App includes
 */
require_once 'include.php';

// entry point

$fbplatform = new FBPlatform();

// construct user object
$appuser = $fbplatform->getFBUser();

// If not user then there is no point continuing
if (empty($appuser)) {
	exit();
}

// Default mode
$mode = COURSE_PRESENT;

// Delete request notifications if set
if (isset($_REQUEST['request_ids'])) {
	 $mode = COURSE_RECOMMEND;
	//get the request ids from the query parameter
   $request_ids = explode(',', $_REQUEST['request_ids']);
   //for each request_id, build the full_request_id and delete request  
   foreach ($request_ids as $request_id)
   {
      try {
      	 $api_url = sprintf("/%s_%s", $request_id, $appuser->facebook_uid); 
         $delete_success = $fbplatform->facebook->api($api_url,'DELETE');
         //if ($delete_success) {
         //   echo "Successfully deleted ";}
         //else {
         //  echo "Delete failed";}
         }          
      catch (FacebookApiException $e) {
      echo "Oh dear, could not delete the notification.";}
    }
}

//if (!isset($_REQUEST['signed_request'])) {
//	print_r($_REQUEST);
	//header("Location: ".FB_CANVAS_PAGE_URL);
	//exit();
//}

// find out mode requested

// get list of courses user is on
$courselist = $appuser->getCourseList();
// check mode requested is valid
if (isset($_REQUEST['mode'])) {
  if ( in_array($_REQUEST['mode'], array(COURSE_PAST, COURSE_PRESENT, COURSE_FUTURE, COURSE_RECOMMEND))) {
    $mode = $_REQUEST['mode'];	
  }
}



// change status requests (from confirm page)
if ( isset($_POST['act']) && isset($_POST['ri']) ) {
  $act = $_POST['act'];
  $record_id = $_POST['ri'];
  switch( $act ) {
    case COURSE_PAST:
      $courselist->changeCourseStatus($record_id, COURSE_PAST);
      break;
    case COURSE_PRESENT:
      $courselist->changeCourseStatus($record_id, COURSE_PRESENT);
      break;  
    case COURSE_FUTURE:
      $courselist->changeCourseStatus($record_id, COURSE_FUTURE);
      break;    
    case COURSE_REMOVE:
      $courselist->dropCourse($record_id);
      break;    
  }
}

// render profile fbml (will be phased out in 2010 as per FB platform changes
//$appuser->renderProfileFBML();

// render this page
$toptext = "<p>Use this application to tell your friends about your studies. You can ".
  "tell them about the courses or qualificatios that you have completed, what you are currently studying and any ".
  "that you are planning to do or maybe just thinking about doing in the future.<p>".
  "<p>Click on a course or qualification title to discover more possibilities including finding a ".
  "study buddy, discovering related materials on OpenLearn, a comments wall and details for your course.</p>";
//  add course  
if (isset($_REQUEST['coursecode'])) {
   //if (sizeof($courses) < MAX_COURSE_ENTRIES +1) {
   	 // todo change this so cal add from url, e.g. for openlearn
     $toptext .= $courselist->processCourseRequest($fbplatform, $appuser, $_REQUEST['coursecode'], $_REQUEST['mopi'], $mode);
   //}
   //else {
   //  echo "<fb:error><fb:message>Maximum course entry reached!</fb:message>";
   //  echo "Sorry but you have reached the maximum number of courses that can be entered into ";
   //  echo "this application.</fb:error>";
 // }
  //exit;
}

$output = $fbplatform->renderTop(false, $toptext);
// render an announcement if in time
if ( time() < strtotime(ANNOUNCEMENT_EXPIRE) ) {
  echo $fbplatform->getTemplate('announcement');	
}


$output .= "<div class='tabs'>";
$searchPrompt = "";
$tabs = array( 
  COURSE_PAST => array( 'href' => 'index.php?mode='.COURSE_PAST, 'title' => 'Completed', 'search' => 'Add a course that you have already studied or a qualification you have obtained:' ),
  COURSE_PRESENT => array( 'href' => 'index.php?mode='.COURSE_PRESENT, 'title' => 'Current', 'search' => 'Add a course that you are currently studying or a qualification you are working towards:'),
  COURSE_FUTURE => array( 'href' => 'index.php?mode='.COURSE_FUTURE, 'title' => 'Future', 'search' => 'Add a course that you are planning or thinking about studying or a qualification you might work towards:'),
  COURSE_RECOMMEND => array( 'href' => 'index.php?mode='.COURSE_RECOMMEND, 'title' => 'Recommendations', 'search' => ''));
foreach ($tabs as $key => $tab) {
  if ($key == $mode) {
    //$selected = "selected='true'";
    $selected = " tab-item-selected";
  	$searchPrompt = $tab['search'];
  }
  else {
    $selected = "";
  }
  //$output .= sprintf("<fb:tab_item href='%s' title='%s' %s />", $tab['href'], $tab['title'], $selected);
   $output .= sprintf("<a class='tab-item%s' href='%s'>%s</a>", $selected, $tab['href'], $tab['title']);
}
//$output .= "</fb:tabs>";
$output .= "</div>";


$output .= "<div class='panel'>";  
// only show course search form if they have under the maximum of courses
//if (sizeof($courses) < MAX_COURSE_ENTRIES +1) {
if ( $mode != COURSE_RECOMMEND ) {
   $output .= $fbplatform->renderCourseSearchForm($searchPrompt, $mode);
}
else {
   $output .=  $fbplatform->getTemplate('indexrecommend');	
}
// arriving here from invite page
if ( isset($_POST['ids']) && $_GET['from'] == 'invite' ) {
   $plural = sizeof($_POST['ids']) == 1 ? '' : 's';
   $output .=  "<div class='success'>";
   $output .=  "<div class='message'>Invite$plural sent!</div>";
   $output .=  "Thanks for spreading the word about Course Profiles.";
   $output .=  "</div>";	
}
//}
//else {
//   $output .= "<fb:explanation><fb:message>No more courses can be added!</fb:message>We're sorry but you";
//   $output .= " have reached the maximum number of courses that you can enter onto this application.";
//   $output .= "</fb:explanation>";	
//}
$output .= "<div id='preview'>";
$output .= $fbplatform->renderCourseList($courselist, $mode);
$output .= "</div>";
$output .= "</div>";


$output .= $fbplatform->renderBottom();
echo $output;

?>