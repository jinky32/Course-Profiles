<?php
/**
 * Recommend a friend page
 * Invites user to enter names then sends notifications
 * @package cpfa
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

 // render top
 $toptext = sprintf( "<h1><a href='courseact.php?cc=%s' title='Click here for things to do'>%s %s</a></h1>".
                     "<h2>Recommend to a friend</h2>",
                   $course->course_code,
                   $course->course_code,
                   $course->full_title);
 echo $fbplatform->renderTop(false, $toptext);
 
 // who already uses app
// $friendsWithApp = $appuser->getAppUsingFriends();
// get ids to exclude from dialogue
$exclude_ids = array();
$matches = $appuser->getFriendsOnCourse($course->course_code);
foreach ($matches as $match) {
	if ($match['status'] != COURSE_RECOMMEND) {
		$exclude_ids[] = $match['facebook_uid'];
	}
}
 
if (!isset($_REQUEST['request'])) {
  $vars = array(
    'course_code' => $course->course_code,
    'course_title' => $course->full_title, 
    'access_token' => $fbplatform->facebook->getAccessToken(), 
    'exclude_ids' => implode(",", $exclude_ids), 
  ); 
  echo $fbplatform->getTemplate('recommendtofriendselector', $vars);
}
else {
	$successful_suggestions = 0;
	$target_uids = explode(",", $_REQUEST['to']);
	foreach ($target_uids as $target_uid) {
		$target_user = new FBUser( $target_uid, $fbplatform->facebook, true);
    $target_courselist = $target_user->getCourseList();
    if ( ! $target_courselist->hasCourse( $course->course_code ) ) {
    	$target_courselist->addCourse($course->course_code, '', COURSE_RECOMMEND );	
    	$target_courselist->addWhoRecommends( $course->course_code, $appuser->getInternalUid() );
    	$successful_suggestions++;
    }
	}
	$plural = $successful_suggestions > 1 ? "s" : "";
	echo "<div class='success'>";
	printf("<div class='message'>Notification%s sent!</div>", $plural);
	printf("<p>Your friend%s will see your course suggestion under their <b><i>Recommendations</i></b> tab.</p>", $plural);
	echo "</div>";
}
 
// $recommend_fail = array();
// $showTryAgainLater = false;
// // process a recommend friend request
// if (isset($_POST['ids']) ){
// 	 $target_uids = $_POST['ids'];
// 	 // add to target course lists
// 	 
//
// 	 foreach ($target_uids as $target_uid) {
// 	   $target_user = new FBUser( $target_uid, $fbplatform->facebook, true);
// 	   $target_courselist = $target_user->getCourseList();
// 	  
// 	   if ( $target_courselist->hasCourse( $course->course_code ) ) {
// 	   	 $recommend_fail[] = sprintf("<fb:name uid='%s' shownetwork='true' ".
//            "useyou='false' linked='true' /> already has this course in <fb:pronoun uid='%s' ".
//            "possessive='true' /> <a href='allmycourses.php?id=%s'>course profile</a>! ",	
//            $target_uid, $target_uid, $target_uid);
// 	   }
// 	   else {
// 	     $target_courselist->addCourse($course->course_code, '', COURSE_RECOMMEND );	
// 	     $target_courselist->addWhoRecommends( $course->course_code, $appuser->getInternalUid()  );
//       $fbml = sprintf("has recommended %s %s to you using <a href='%s'>Course Profiles</a>! ",
// 	                $course->course_code,
// 	                $course->short_title,
// 	                FB_CANVAS_PAGE_URL."index.php?mode=".COURSE_RECOMMEND);
// 	     $fbml .= in_array($target_uid, $friendsWithApp) ? "Go to" : "Add";
// 	     $fbml .= sprintf( " <a href='%s'>Course Profiles</a> now to view the recommendation, find ".
// 	                    "study buddies and <a href='%s'>try some free related materials from ".
// 	                    "OpenLearn</a>.",
// 	                    FB_CANVAS_PAGE_URL."index.php?mode=".COURSE_RECOMMEND,
// 	                    FB_CANVAS_PAGE_URL."openlearn.php?cc=".$course->course_code);	
// 	     if (isset($_POST['message']) && strlen($_POST['message']) > 3) {
//          $message = htmlentities( $_POST['message'] );
//          $fbml .= sprintf(" <fb:pronoun uid='%s' capitalize='true' ". 
//          "useyou='false' useyou='false' /> said: &quot;%s&quot;.",
//          $user,
//          $message);
//       }
//       //$result = $fbplatform->sendNotification($target_uid, $fbml, false); 
//       if ( $result != "" ) {
//         $recommend_fail[] = sprintf("Could not add to <fb:name uid='%s' shownetwork='true' ".
//            "useyou='false' linked='true' possessive='true'/> course profile due to an error.",	
//            $target_uid);
//         $showTryAgainLater = TRUE;
//       }
//     } 
// 	}
// 	
// 	if ( sizeof( $recommend_fail ) > 0 ) {
// 	  echo "<div class='error'><div class='message'>Sorry some of your recommendations could not be sent</div>";
// 	  echo "<ul><li>";
// 	  echo implode("</li><li>",$recommend_fail );
// 	  echo "</li></ul>";
// 	  if ($showTryAgainLater)
// 	    echo "Please try again later.";
// 	  echo "</div>";
// 	}
// 	else {
// 		echo "<div class='success'><div class='message'>Recommendations successfully sent</div></div>";
// 	}
// }	
 // see: http://developers.facebook.com/docs/reference/fbml/serverFbml/
// echo "<div class='panel  topborderpanel'>"; 
//  echo "<fb:serverFbml  style=\"width: 355px;\" ><script type=\"text/fbml\"><fb:fbml>"; 
// echo "<form method='post' action='".FB_CANVAS_PAGE_URL."courserecommend.php?cc=".$course->course_code."'>";
// echo "<label for='ids'>Recommend to</label>";
//
// echo "<fb:multi-friend-input  name='ids[]' includeme='true' />";
// 
//
// echo "<label for='message'>Message (optional)</label>";
// echo "<textarea name='message' ></textarea>";
// echo "<div class='editor-buttonset'>";
// echo "<input type='submit' value='Send'/>";
// echo "</div>";
// 
// echo "</form>";
//  echo "</fb:fbml></script></fb:serverFbml>"; 
// echo "</div>";
// echo "<fb:serverFbml  style=\"width: 720px; \" ><script type=\"text/fbml\"><fb:fbml>"; 
// echo "<div class='panel  topborderpanel'>"; 
// echo "<fb:editor action='".FB_CANVAS_PAGE_URL."courserecommend.php?cc=".$course->course_code."' labelwidth='100'>";
// echo "<fb:editor-custom label='Recommend to'>";
// echo "<fb:multi-friend-input width='100%' name='ids[]' includeme='true' />";
// echo "</fb:editor-custom>";
// echo "<fb:editor-textarea label='Message (optional)' name='message' />";
// echo "<fb:editor-buttonset>";
// echo "<fb:editor-button value='Send'/>";
// echo "<fb:editor-cancel href='courseact.php?cc=".$course->course_code."'/>";
// echo "</fb:editor-buttonset>";
// 
// echo "</fb:editor>";

// echo "</div>";
//  echo "</fb:fbml></script></fb:serverFbml>"; 
?>
