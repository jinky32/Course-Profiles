<?php
/**
 * Show OpenLearn options for a course
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
/**
 * App includes
 */
 require_once 'include.php';
 
 $fbplatform = new FBPlatform();

 // construct user object
 $appuser = $fbplatform->getFBUser();
 
 $course = array();
 
 $course_code = $_GET['cc'];
 $course = new Course($course_code);
 $course->load();
 // if course code not recognised display error and exit
 if ( $course->last_modified_utc == 0 ) {
   echo $fbplatform->getTemplate('coursenotfound');
   exit(); 	
 }
  
 $toptext = sprintf( "<h1><a href='courseact.php?cc=%s' title='Click here for things to do'>%s %s</a></h1>",
                   $course->course_code,
                   $course->course_code,
                   $course->full_title);
 echo $fbplatform->renderTop(false, $toptext);
 echo "<div class='panel  topborderpanel'>";
 $vars = array( 'openlearn_img_url' => FB_APP_CALLBACK_URL.'images/openlearn_small.png',
                'openlearn_home' => OPENLEARN_HOME,
                'openlearn_space_home' => OPENLEARN_SPACE_HOME);
 echo $fbplatform->getTemplate('openlearn', $vars);
 
 $ol_resources = $course->getOpenLearnResources();
 if ( sizeof( $ol_resources ) == 0 ) {
 	echo "<div class='explanation'><div class='message'>Sorry! No OpenLearn resources were found for this course";
 	echo "</div>Check back soon though as OpenLearn is constantly being improved and";
 	echo " added to.</div>";
 	
 }
 else {
   $ol_courses = array();
   foreach ($ol_resources as $ol_resource) {
     $ol_course = array();
     $ol_course['unit_code'] = $ol_resource->course_code;
     $ol_course['title'] = $ol_resource->full_title;
     $ol_course['action'] = "Coming soon...";
     if ($ol_resource->activelink) {
     	$ol_resource_url = $ol_resource->getCourseInfoURL();
     	$ol_course['action'] = sprintf("<a href='%s' target='_blank'>View now!</a>",
     	                               $ol_resource_url['href']);
     }
     $ol_courses[] = $ol_course;	
   }
 
   $vars  = array( 'course_code' => $course->course_code, 'ol_courses' => $ol_courses );
   echo $fbplatform->getTemplate( 'openlearn_courselist', $vars);
   echo "</div>";
}
echo $fbplatform->renderBottom();
?>