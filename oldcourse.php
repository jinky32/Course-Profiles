<?php
/**
 * Page shown when user goes into a course and clicks "Course details" for a course with no information URL
 * (i.e. a course not offered anymore)
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

$course = null;
 
$course_code = $_GET['cc'];
$course = new Course($course_code);
$course->load();
// if course code not recognised display error and exit
if ( $course->last_modified_utc == 0 ) {
  echo $fbplatform->getTemplate('coursenotfound');
  exit(); 	
}
 
// render this page
 $toptext = "<h1>".$course->course_code." ".$course->full_title. "</h1>";


$output = $fbplatform->renderTop(false, $toptext);
$vars = array( 'courses_quals_url' => COURSES_QUALS_URL,
               'ok_action' => 'courseact.php',
               'course_code' => $course_code );

$output .= $fbplatform->getTemplate('oldcourse', $vars);
$output .= $fbplatform->renderBottom();
echo $output;

?> 
