<?php
/**
 * Help pages
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */

/**
 * App includes
 */
 require_once 'include.php';
 
 $fbplatform = new FBPlatform();

 // construct user object
 $appuser = $fbplatform->getFBUser();
 $toptext = "<h1>Help Me!</h1>";
 echo $fbplatform->renderTop(false, $toptext);
 $vars = array( 'tab_completed' => "<a href='".FB_CANVAS_PAGE_URL."index.php?mode=".COURSE_PAST."'>Completed Courses</a>",
                'tab_current' => "<a href='".FB_CANVAS_PAGE_URL."index.php?mode=".COURSE_PRESENT."'>Current Courses</a>",
                'tab_future' => "<a href='".FB_CANVAS_PAGE_URL."index.php?mode=".COURSE_FUTURE."'>Future Courses</a>",
                'tab_recommend' => "<a href='".FB_CANVAS_PAGE_URL."index.php?mode=".COURSE_RECOMMEND."'>Recommendations</a>",
                'courses_quals_link' => "<a href='".COURSES_QUALS_URL."' target='_blank'>Courses and Qualifications</a>",
                'openlearn_home' => "<a href='".OPENLEARN_HOME."' target='_blank'>OpenLearn</a>",
                'discussion_board_url' => FB_APP_ABOUT );
 echo $fbplatform->getTemplate('help', $vars);
echo $fbplatform->renderBottom();
 ?>
