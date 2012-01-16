<?php
/**
 * Comment on a course page 
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

 
 // get the incoming course code
 $course_code = $_GET['cc'];
 $course = new Course($course_code);
 $course->load();
 // if course code not recognised display error and exit
 if ( $course->last_modified_utc == 0 ) {
   echo $fbplatform->getTemplate('coursenotfound');
   exit(); 	
 }
 
 // deal with newly posted comment
 if ( isset($_POST['commenttext']) ) {
   $course->addComment( $appuser->getInternalUid(), $_POST['commenttext'] );
 }
   //$comment_ids = $course->getCommentIds();
   //echo $course->renderCourseComments($fbplatform);
 //}
 //else {
   $toptext = sprintf( "<h1><a href='courseact.php?cc=%s' title='Click here for things to do'>%s %s</a></h1>".
 	                    "<h2>Comments Wall</h2>",
                   $course->course_code,
                   $course->course_code,
                   $course->full_title);
   echo $fbplatform->renderTop(false, $toptext);

   $vars = array( 'comments_page' => FB_APP_CALLBACK_URL.'coursecomment.php?cc='.$course->course_code,
               'commentslist' => $course->renderCourseComments($fbplatform),
               'comment_max_length' => COMMENT_MAX_LENGTH );
   echo $fbplatform->getTemplate('commentspage', $vars);	
// }
 

//echo "<fb:comments xid='".$course['course_code']."'_comments' canpost='true' candelete='false' >";
//echo "<fb:title>Course Comments Wall</fb:title>";
//echo "</fb:comments>";
echo $fbplatform->renderBottom();
?>