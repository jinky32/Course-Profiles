<?php
/**
 * Page to ask user to confirm a status change action for a course.
 * @package cpfa
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
/**
 * App includes
 */
require_once 'include.php';


// construct user object
$fbplatform = new FBPlatform();

// construct user object
$appuser = $fbplatform->getFBUser();
// get list of courses user is on
$courselist = new CourseList($appuser->getInternalUid());
$courselist->load();

// make sure user logged in to FB
//ensureLogin();

$record_id = $_GET['ri'];
$act = $_GET['act'];
$mode = $_GET['mode'];

$wording = "";

$course_record = $courselist->getEntry($record_id);
$course = $course_record['course'];
$coursetext = $course->course_code." ".$course->short_title;
if ( trim($course_record['mopi']) != "" ) {
  $coursetext .= " (".$course_record['mopi'].")"; 	
}

switch( $act ) {
  case COURSE_PAST:
    $wording = "move <i>%s</i> to your completed courses?";
    break;
  case COURSE_PRESENT:
    $wording = "move <i>%s</i> to your current courses?";
    break;  
  case COURSE_FUTURE:
    $wording = "move <i>%s</i> to your future courses?";
    break;    
  case COURSE_REMOVE:
    $wording = "remove <i>%s</i>?";
    break;    
}

$wording = sprintf($wording, $coursetext);
echo $fbplatform->renderTop();
$vars = array( 'wording' => $wording,
               'action_page' => 'index.php',
               'record_id' => $record_id,
               'act' => $act,
               'mode' => $mode);
echo $fbplatform->getTemplate('areyousure',$vars);
echo $fbplatform->renderBottom();               
               
?>
