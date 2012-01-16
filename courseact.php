<?php
/**
 * Course actions 
 * This page show what actions a user can take with a course
 * Examples: Recommend a friend, VIew in Courses & Quals, Frind a study buddy
 * @package cpfa
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
/**
 * App includes
 */
 require_once 'include.php';

 /** Handles interaction with FB API */
 $fbplatform = new FBPlatform();

 $explanation = "";
 // construct user object
 $appuser = $fbplatform->getFBUser();

 $course = null;
 $cc_link = null; // course code link, used in building URLs
 
 $course_code = $_GET['cc'];
 $course = new Course($course_code);
 $course->load();
 $cc_link = $course->course_code;
 // if course code not recognised display error and exit
 if ( $course->last_modified_utc == 0 ) {
   echo $fbplatform->getTemplate('coursenotfound');
   exit(); 	
 }
 
 // TODO Look out for an OpenLearn course and vary actions accordingly.
 if ( $course->classification == Course::CLASS_OPENLEARN ) {
 	// change course actions to look at parent
    $vars = array( 'course_code' => $course->course_code,
                   'title' => $course->short_title,
                   'openlearn_href' => OPENLEARN_HOME,
                   'openlearn_logo_url' => FB_APP_CALLBACK_URL.'images/openlearn_icon.png');
    $explanation = $fbplatform->getTemplate('courseactopenlearn', $vars);
    
   	$course = new Course($course->parent_course_code);
    $course->load();    
    $cc_link = $course->course_code;
 }
 // number of friends on course
 $num_friends_on_course = sizeof( $appuser->getFriendsOnCourse($course->course_code) );
 $num_openlearn_resources = sizeof( $course->getOpenLearnResources() );
 $num_comments = sizeof( $course->getCommentIds() );

 $actions = array();
 $rowlimit = 2;
 // course information button
 $actions[] = $course->getCourseInfoURL();
 $qualorcourse = $course->isQualification() ? "qualification" : "course" ;
 // friends studying this course button
 $actions[] = array('title' => sprintf('Your friends on this %s (%d)', $qualorcourse, $num_friends_on_course), 
                    'href' => 'coursefriends.php?cc='.$cc_link , 
                    'description' => sprintf('See which of your friends are studying this %s', $qualorcourse));
 if ( $course->activelink ) {
   // find a study buddy
   $actions[] = array('title' => 'Find a new study buddy', 
                    'href' => 'studybuddy.php?cc='.$cc_link , 
                    'description' => 'Find somebody from your friends list or network that might make a good study buddy');
 

   // recommend to a friend
   $actions[] = array('title' => 'Recommend to a friend', 
                    'href' => 'courserecommend.php?cc='.$cc_link , 
                    'description' => sprintf('Would one of your friends enjoy this %s? Why not send them a message to recommend it to them.', $qualorcourse));
 
  
 }
 // openlearn
 $actions[] = array('title' => 'OpenLearn ('.$num_openlearn_resources.')', 
                    'href' => 'openlearn.php?cc='.$cc_link , 
                    'description' => 'View resources on OpenLearn for this course.');
 
 $actions[] = array('title' => 'Comments Wall ('.$num_comments.')', 
                    'href' => 'coursecomment.php?cc='.$cc_link ,
                    'description' => sprintf("Read what other people have said about this %s and ".
                                     "add comments yourself.", $qualorcourse));

                    
 $toptext = "<h1>".$course->course_code." ".$course->full_title. "</h1>".
           
            "<p>What would you like to do?</p>";
 echo $fbplatform->renderTop(false, $toptext);
 
 // TODO render who recommended
 echo "<div class='panel  topborderpanel'>"; 
 echo $explanation;
 echo "<table class='pushbutton'>";
 echo "<tr>";
 $itemcount = 0;
 foreach ($actions as $action) {
   
   if ($itemcount == $rowlimit) {
       echo "</tr><tr>";
       $itemcount = 0;
   }
   $target = isset($action['target']) ? " target='".$action['target']."'" : "";
   if (!empty($action['href'])) {
      printf("<td class='whitepanel'><h3><a href='%s'%s>%s</a></h3>%s</td>",
        $action['href'],
        $target,
        $action['title'],
        $action['description']);
   }
   else {
   	printf("<td class='whitepanel'><h3>%s</h3>%s</td>",
        $action['title'],
        $action['description']);
   }
   $itemcount++;
 }
 if ( $itemcount < $rowlimit ) {
    echo str_repeat("<td></td>", $rowlimit - $itemcount );	
 }

 echo "</tr>";
 echo "</table>";
 
 // render the "people who did this also did that"
 $alsocourses = $course->getAlsoStudied();
 $entrytype = $course->isQualification() ? "qualification" : "course";
 if ( empty($alsocourses) ) {
   printf("<p>Sorry, no information is available on other %ss studied by people on this %s.",
     $entrytype,$entrytype);
 }
 else {
   printf("<p>People who studied this %s also studied:<ul>", $entrytype);
 }
 foreach ($alsocourses as $alsocourse) {
   $also_url = $alsocourse->getCourseInfoURL();
   $logo = '';
   if ( $alsocourse->classification == Course::CLASS_OPENLEARN ) {
  	  $logo = sprintf(" <a href='%s' target='_blank'><img src='%s' title='OpenLearn' /></a>",
  	   	             OPENLEARN_HOME,
  	   	             FB_APP_CALLBACK_URL.'images/openlearn_icon.png');
   }
   printf("<li><a href='courseact.php?cc=%s' title='%s'>%s %s</a>%s</li>",
           $alsocourse->course_code,
           'Clcik here for things to do',
           $alsocourse->course_code,
           $alsocourse->full_title,
           $logo); 
 }
 echo "</ul></p></div>";
echo $fbplatform->renderBottom();
?>