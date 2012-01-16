<?php
/**
 * Displays list of all courses for profile owner.
 * Thos provides the code for the "tab" on the user's profile
 * @package cpfa
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
/**
 * App includes
 */
require_once 'include.php';

/**
 * Processes groups of courses or qualifications into a format suitable for allmycourses template
 */
function processItems($items, $group) {
  // headings to be used in display
  $headings = array(  'quals' =>   array( COURSE_PAST  => 'I have completed...',
                                        COURSE_PRESENT => 'I am working towards....',
                                        COURSE_FUTURE => 'I am considering taking...'),
                    'courses' => array( COURSE_PAST => ' I have completed...', 
                                        COURSE_PRESENT => 'I am currently studying...', 
                                        COURSE_FUTURE => 'I am thinking about studying...' ),
              );
  $rr_coursegroup = array();
  foreach ($items as $status => $courselistitems) {
    $course_group = array();
    $course_group['status_header'] = $headings[$group][$status];
    foreach ( $courselistitems as $courselistitem ) {
      $course_record = array();
      $course = $courselistitem['course'];
      $course_info_url = $course->getCourseInfoURL();
      $course_record['url_title'] = $course_info_url['title'];
      $course_record['url_href'] = $course_info_url['href'];
      $course_record['url_target'] = $course_info_url['target'];
      $course_record['course_code'] = $course->course_code;
      $course_record['logo'] = '';
      if ( $course->classification == Course::CLASS_OPENLEARN ) {
  	   	$course_record['logo'] = sprintf(" <a href='%s' target='_blank'><img src='%s' title='OpenLearn' /></a>",
  	   	                       OPENLEARN_HOME,
  	   	                       FB_APP_CALLBACK_URL.'images/openlearn_icon.png');
  	  }
      $course_record['course_title'] = $course->full_title;
      $course_record['mopi'] = $courselistitem['mopi'];
      $course_group['rr_course_records'][] = $course_record;
    }
    $rr_coursegroup[] = $course_group;	
  }		
  return $rr_coursegroup;	
}

$fbplatform = new FBPlatform(true);

// construct user object

$fb_profile_id = isset($_GET['id']) ? $_GET['id'] : $_POST['fb_sig_profile_user'];
// exit if no profile id
if (empty($fb_profile_id)) {
  exit(); 
}
// TODO: trap bad values
// construct user object for target user
$targetuser = new FBUser( $fb_profile_id, $fbplatform->facebook );
         
// get list of courses target user is on

$courselist = $targetuser->getCourseList();
$courselistitems = $courselist->toArray();

// sort by quals then courses
$user_candq = array('quals' => array('candq_title' => 'Qualifications', 'items' => array()), 
                    'courses' => array('candq_title' => 'Courses', 'items' => array()));
                    
foreach ($courselistitems as $courselistitem) {
	$candq_key = $courselistitem['course']->isQualification() ? 'quals' : 'courses';
	$user_candq[$candq_key]['items'][$courselistitem['status']][] = $courselistitem;
}

// build array of items to be rendered by template
$rr_candq = array();
foreach ($user_candq as $group => $details) {
  $rr_candq_entry = array();
  $rr_candq_entry['candq_title'] = $details['candq_title'];
  $rr_candq_entry['rr_coursegroup'] = processItems($details['items'], $group);
  
  if ( ! empty($details['items']) ) {	
    $rr_candq[] = $rr_candq_entry;
  }
}

echo $fbplatform->renderTop($fb_profile_id, '', true, true);
// render the courses with a template
$vars = array( 'rr_candq' => $rr_candq,
               'facebook_uid' => $fb_profile_id,
               'canvas_page_url' => FB_CANVAS_PAGE_URL );
echo $fbplatform->getTemplate('allmycourses', $vars);
?>