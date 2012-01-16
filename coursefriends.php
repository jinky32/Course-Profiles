<?php
/**
 * Friends on same course page
 * Shows someone which of their friends is on the same course and offers chance to get in contact
 * @package cpfa
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 *
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
 
 $toptext = sprintf( "<h1><a href='courseact.php?cc=%s' title='Click here for things to do'>%s %s</a></h1>".
                     "<p>These friends are studying the same %s as you</p>",
                   $course->course_code,  
                   $course->course_code,
                   $course->full_title,
                   $course->isQualification() ? "qualification" : "course");
 echo $fbplatform->renderTop(false, $toptext);

 // TODO Pages for lots of friends
 
 // find out friends of user who have this app
 $matches = $appuser->getFriendsOnCourse($course->course_code);


 echo "<div class='panel  topborderpanel'>"; 
 if ( sizeof($matches) == 0 ) {
   $vars = array( 'qualorcourse' => $course->isQualification() ? "qualification" : "course",
                  'course_recommend_url' => FB_CANVAS_PAGE_URL.'courserecommend.php?cc='.$course->course_code,
                  'study_buddy_url' => FB_CANVAS_PAGE_URL.'studybuddy.php?cc='.$course->course_code );
   echo $fbplatform->getTemplate('coursefriendszero', $vars); 
 }
 foreach ($matches as $facebook_uid => $details) {
  echo "<table class='whitepanel'><tr>";
  //echo "<td rowspan='2' style='width:60px'><fb:profile-pic linked='true' uid='$facebook_uid' size='square' /></td>";
 // echo "<td><b><fb:name uid='$facebook_uid' shownetwork='true' linked='true'/></b>";
  printf("<td rowspan='2' style='width:60px'><a href='%s'><img src='%s' alt='%s' /></a></td>", $details['link'], $details['picture'], $details['name']);
  printf("<td><b><a href='%s'>%s</a></b>", $details['link'], $details['name']);
  $year = "";
  if ($details['mopi'] != '') {
  	$year = substr($details['mopi'], 0, 4);
  }  
  switch ( $details['status'] ) {
  	case COURSE_PAST:
  	  if ( $year == "" ) {
  	    echo " has studied ".$course->course_code;
  	  }
  	  else {
  	  	echo " studied ".$course->course_code." in ".$year;
  	  }
  	  break;
  	case COURSE_FUTURE:
  	  echo " is planning to study ".$course->course_code;
  	  if ( $year == "" ) {
  	    echo " in ".$year;
  	  }
  	  break;
  	default:
  	  echo " is currently studying ".$course->course_code;  	  
  }
  if ( $details['classification'] == Course::CLASS_OPENLEARN ) {
    printf(" through <a href='%s' target='_blank'>OpenLearn</a>",
  	   	                       OPENLEARN_HOME);  	
  }
  echo "</td><td>";
  // send message link
  echo "<a target='_top' href='http://www.facebook.com/message.php?id=";
  echo $facebook_uid;
  echo "&subject=";
  echo urlencode($course->course_code." ".$course->full_title);
  echo "'>Send Message</a>";
  //$message_href = sprintf("http://www.facebook.com/message.php?id=%d&subject=%s", $facebook_uid, urlencode($course->course_code." ".$course->full_title)); 
  //printf("<a href=\"javascript:top.location.href='%s';\">Send message</a>", $message_href); 
  echo "</td></tr>";
  
  echo "<tr><td></td><td>";
  // poke link
  //echo "<a href='http://www.facebook.com/poke.php?id=";
  //echo $facebook_uid;
  //echo "' >Poke <fb:pronoun uid='".$facebook_uid."' capitalize='true' objective='true' />!</a>";
  echo "</td></tr></table>";  
}
echo "</div>";
echo $fbplatform->renderBottom();


?>