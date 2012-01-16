<?php
/**
 * Find a study buddy page
 *
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
/**
 * App includes
 */
 require_once 'include.php';

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
 $user_prefs = $appuser->getPreferences();
 
 $toptext = sprintf( "<h1><a href='courseact.php?cc=%s' title='Click here for things to do'>%s %s</a></h1>".
                     "<h2>Find a study buddy</h2>",
                   $course->course_code,
                   $course->course_code,
                   $course->full_title);
 echo $fbplatform->renderTop(false, $toptext); 
 
 echo "<div class='panel  topborderpanel'>";
 // first thing to do is render form
 if (!isset($_POST['search_scope'])) {
 	$university = new University('open.ac.uk');
 	$region_opts = $university->getRegions();
	$region_opts['R00'] = 'All Regions';
	$search_opts = $appuser->getPrivacyOptions();
 	unset($search_opts[PRIVACY_ME]);
 	$vars = array( 'course_code' => $course->course_code,
 	               'search_opts' => $fbplatform->renderOptionSet($search_opts,$user_prefs['fb_priv_studybuddy'] ),
 	               'region_opts' => $fbplatform->renderOptionSet($region_opts,$user_prefs['ou_region'] ));
 	echo $fbplatform->getTemplate('searchsb', $vars);
 }
 else {
 	 $studybuddyfinder = new StudyBuddyFinder( $fbplatform, $appuser, $course );
 	 $studybuddyfinder->processRequest($_POST['search_scope'],$_POST['search_regions']);
 	 echo $studybuddyfinder->renderResult();
 	 $studybuddyfinder->close();
 }
 echo "</div>";
 echo $fbplatform->renderBottom();
?>


