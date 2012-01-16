<?php


$facebook_config['debug'] = false; // see http://wiki.developers.facebook.com/index.php/Gotchas#Client_Library_Gotchas

/**
 * Class to encapsulate relationship between app and the Facebook platform
 * @package cpfa
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
class FBPlatform extends CPFABase {
  // container for facebook object
  public $facebook;
  private $old_error_handler;
  private $no_error_redirect;
  private $sessionHandler;
  
  // create new instance of platform object and instatiate facebook obkect at same time
  function __construct($no_error_redirect=false) {
  	parent::__construct();
  	$this->no_error_redirect = $no_error_redirect;
  	// set up session handler
  	$this->sessionHandler = SessionHandler::getSessionHandler();
  	// connect to facebook
  	$config = array();
    $config['appId'] = FB_APP_ID;
    $config['secret'] = FB_API_SECRET;
    $this->facebook = new Facebook($config);
   
    
    //$this->facebook = new Facebook( FB_API_KEY, FB_API_SECRET );
    $this->old_error_handler = set_error_handler(array($this, "errorHandler"));
    
  }
  
  /**
   * Error handler 
   *
   * Handles errors by logging and then redirecting FB user to an error screen.
   * See: @link http://www.php.net/manual/en/function.set-error-handler.php
   * @param int $errno The level of the error raised
   * @param string $errstr The error message
   * @param string $errfile The filename that the error was raised in
   * @param int $errline The line number the error was raised at
  */
  function errorHandler($errno, $errstr, $errfile, $errline) {
  	  $errortype = array (
                E_ERROR              => 'Error',
                E_WARNING            => 'Warning',
                E_PARSE              => 'Parsing Error',
                E_NOTICE             => 'Notice',
                E_CORE_ERROR         => 'Core Error',
                E_CORE_WARNING       => 'Core Warning',
                E_COMPILE_ERROR      => 'Compile Error',
                E_COMPILE_WARNING    => 'Compile Warning',
                E_USER_ERROR         => 'User Error',
                E_USER_WARNING       => 'User Warning',
                E_USER_NOTICE        => 'User Notice',
                E_STRICT             => 'Runtime Notice',
                E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
                );
      $message = sprintf("Error: %s (%d)  File: %s  Line: %d Message: %s",
      $errortype[$errno], $errno, $errfile, $errline, $errstr );
      $error_log = str_replace( '%date%', gmdate('Ymd'), ERROR_LOG );
      $message_text = sprintf("[%s] %s\n", gmdate('D, d M Y H:i:s e'), $message);
      
      $this->writeToLog($errortype[$errno], $errno, $errfile, $errline, $errstr);

      //error_log( $message_text, 3, $error_log );
      if ( $errno != E_USER_NOTICE && $errno != E_NOTICE && $errno != E_STRICT ) {
      	if ( DEVELOPER_MODE || $this->no_error_redirect) {
      	  die("Sorry an error has occurred. Please try again later.");
      	}
      	else {
          $this->facebook->redirect("error.php");
        }
      }
  }
  
  
 /**
  * Obtains a valid Facebook user object for app user
  *
  * This function ensure that the user is logged into the app. If
  * they are not they are redirected to a page asking them to log
  * into the app and give it the permissions it needs. The Facebook
  * OAuth process is handled here.
  *
  * @return FBUser An object representing the current logged in user
  */
 function getFBUser() {
   // Make sure in a FB frame - this is needed when signing in and doing OAUth steps
   $facebook_user = null;
   
   // Authorisation URL. The user will be directed here if they have not added the app. Note - this also contains the permissions needed.
   $auth_url = "https://www.facebook.com/dialog/oauth?client_id=" 
           . FB_APP_ID . "&redirect_uri=" . urlencode(FB_CANVAS_PAGE_URL) . "&scope=publish_stream,user_interests,user_notes,user_relationships";

   // Check request for FB passing auth ingo and try to extract user from that
   // For more info see: http://developers.facebook.com/docs/authentication/signed_request/
   if (isset($_REQUEST["signed_request"])) {
     $signed_request = $_REQUEST["signed_request"];
     list($encoded_sig, $payload) = explode('.', $signed_request, 2); 
     $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

     if (empty($data["user_id"])) {
       echo("<script> top.location.href='" . $auth_url . "'</script>");
       exit();
     }
     else {
       $facebook_user = new FBUser( $data["user_id"], $this->facebook );
     } 
   }
   // Otherwise try to start a new session with the app
   else {
       try {
          $facebook_user = new FBUser( $this->facebook->getUser(), $this->facebook );
       }
       catch (Exception $e) {
       	    // Not logged in
 	       	$this->handleException($e, false);
 	       	$vars = array("auth_url" => $auth_url);
 	       	echo $this->getTemplate('notloggedin', $vars);
  	   }
   }  	   
   // handle user saying NO
   if (isset($_REQUEST['error'])) {
   	 echo "<div class='explanation'>";
   	 echo "<div class='message'>";
   	 echo "Cancelled starting ".APP_NAME;
   	 echo "</div>";
   	 echo "Details from Facebook:<br/>";
   	 echo "<mono>";
   	 echo "Error: ".$_REQUEST['error']."<br/>";
   	 echo "Reason: ".$_REQUEST['error_reason']."<br/>";
   	 echo "Description: ".$_REQUEST['error_description']."<br/>";
     echo "</mono>";
     echo "<a target='_top' href='".$auth_url."'>Click here if you would like to try again</a>";
     echo "</div>";     
     exit();
   }
   
   return $facebook_user;
 }
   
 
 /**
  * Produces HTML for top of page and emits stylesheet
  * Enter description here ...
  * @param int $userid If set will cause an HTML fragment to be added with "Course Profile for <user name>" to be added. Value should be Facebook user id.
  * @param string $toptext Extra text and HTML to show as part of top of page
  * @param boolean $nomenu If True will suppress top menu from being included
  * @param boolean $nowebstats If True include code for Google Analytics will not be included in the returned HTML
  * @return string HTML fragment
  */
 function renderTop($userid=false, $toptext=null, $nomenu=false, $nowebstats=false) {
   $retval = "<!DOCTYPE html>\n<html>\n<head>\n";
   // if not running in a frame then redirect so it is
   $retval .= "<script  type=\"text/javascript\"> \n";
   $retval .= " if (window.location == window.parent.location) {\n";
 	 $retval .= "   window.location.href = '".FB_CANVAS_PAGE_URL."';\n";
   $retval .= " }\n";
   $retval .= "</script>\n";
   //$retval .= "<div id='fb-root'></div>\n";
   $retval .= "<script src='//connect.facebook.net/en_US/all.js'></script>\n";
   $retval .= "</head>\n<body class='fbapp'>\n";
   $retval .= "<div id=\"fb-root\"></div>";
   $retval .= "<script>\n";
   $retval .= "  FB.init({";
   $retval .= "    appId  : '".FB_APP_ID."',\n";
   $retval .= "    status : true, \n";
   $retval .= "    cookie : true, \n";
   $retval .= "    xfbml  : true, \n";
   $retval .= "    oauth  : true \n";
   $retval .= "  });\n";
   $retval .= "</script>\n";
   //TODO handle NOSCRIPT
    
   // google analytics
   if ( ENABLE_WEBSTATS && ! $nowebstats) {
  	 // $retval .= "<fb:google-analytics uacct='UA-2597815-1'>";
  	 $retval .= "<script type=\"text/javascript\">";
     $retval .= "  var _gaq = _gaq || [];";
     $retval .= "_gaq.push(['_setAccount', 'UA-2597815-1'])";
     $retval .= " _gaq.push(['_trackPageview']);";
     $retval .= " (function() { ";
     $retval .= "   var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;";
     $retval .= "   ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';";
     $retval .= "   var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);";
     $retval .= " })();";
     $retval .= "</script>";
   }
   if ( is_null($toptext) ) {
  	 $toptext = "Use this application to let your friends know which courses you are studying!"; 	
   }
   if (!$nomenu) {
     $retval .= "<div class='dashboard'>";
     $retval .= sprintf("<img src='%s/images/coursepicon.png' class='appicon' />", FB_APP_CALLBACK_URL);
     $retval .= "<a href='index.php' class='divider appname'>Course Profiles</a>";
     //$retval .= "<a href='invite.php' class='divider'>Invite your friends!</a>";
     $retval .= "<a href='preferences.php' class='divider'>My preferences</a>";
     $retval .= "<a href='tos.php'>Terms of Service</a>";
     $retval .= "<a class='help' href=\"help.php\">Help Me!</a>";
     $retval .= "</div>";
   }
   $retval .= "<style>";
   $retval .= file_get_contents('courseprofiles.css');
   $retval .= "</style>";
   if ( $userid ) {
     $fbuser = new FBUser($userid, $this->facebook);
     $retval .= "<div id='tabheading'>";
     //$retval .= "<h2>Course Profile for <fb:name uid='$userid' useyou='false' possessive='false' linked='false' /></h2>";
     $retval .= sprintf("<h2>Course Profile for %s</h2>", $fbuser->getUserProfileName());
     $retval .= "<span id='tababout'>".$fbuser->about."</span></div>";
     if ($nomenu) {
       $retval .= "<p id='tabsuggestadd'>";
       $retval .= $this->getRandomAppAddSuggestion()."</p>";
     }
   }
   if (DEVELOPER_MODE) {
     $retval .= "<h4>*** APPLICATION IS IN DEVELOPER MODE ***</h4>";	
   }
   //$retval .= "<h2>Course Profiles</h2>";
   if ($toptext != '') {
     $retval .= "<div class='strapline'>";
     $retval .= "<p>".$toptext;
     $retval .= "</p></div>";
   }
   return $retval;	
 }  
 
 /**
  * Gets closing HTML tags for a page
  * @return string <code></body></html></code>
  */
 function renderBottom() {
 	 $retval = "</body></html>";
 	 return $retval;
 }
  
 /**
  * Renders a form to search for a course
  * @param $searchPrompt string Text to display to user as preamble to search form
  * @param $mode int Whether course is past, present or future (see constants defined above)
  * @return string FBML of search form
  */
 function renderCourseSearchForm($searchPrompt, $mode) {
   $vars = array( 'search_prompt' => $searchPrompt,
 	              'app_callback_url' => FB_APP_CALLBACK_URL,
 	              'mode' => $mode);
   return $this->getTemplate('coursesearch', $vars);
 }
 
 /**
 * Renders course listing for canvas page (NOT allmycourses)
 * @param int $internal_uid Internal id for user
 * @param int $mode Render past, present or future courses
 */
 function renderCourseList($courselist, $mode) {
   $dcourses = array('quals' => array(), 'courses' => array());
   // obtain only the courses that match the required status and
   // construct an array grouped by qualification or course, year, course object
   foreach ( $courselist->toArray() as $record_id => $courselistitem ) {
     if ( $courselistitem['status'] == $mode ) {
       $year = substr(trim($courselistitem['mopi']),0, 4);
  	   $year = $year == "" ? "Year not known" : $year;
       if ( $courselistitem['course']->isQualification() ) {
          $dcourses['quals'][$year][$record_id] = $courselistitem;	  
       }
       else {
       	  $dcourses['courses'][$year][$record_id] = $courselistitem;	
       }
       //$dcourses[$record_id] = $course;	
    }
    // sort by year
    ksort($dcourses['quals']);
    ksort($dcourses['courses']);
   }
   $output = "";
  
   if ( empty($dcourses['quals']) && empty($dcourses['courses']) ) {
     $output .= "<tr><th>No courses or qualifications</th></tr>";	
   }
   
   // print out the courses array
   foreach($dcourses as $courseorqual => $items) {
   	 $output.= "<table class='fullcourse whitepanel'>";
   	 $candqheader = "<tr><th class='candq_divider' colspan='7'>%s</th></tr>";
   	 if ( !empty($items) )
   	   $output .= sprintf($candqheader, $courseorqual == 'quals' ? "Qualifications" : "Courses");
   	 foreach ($items as $year => $courseitems) {
   	   $output .= sprintf("<tr><th class='date_divider' colspan='7'>%s</th></tr>", $year);	
   	   foreach ( $courseitems as $record_id => $courseitem ) {
   	     // work out action links
  	     $action_links = array( 
  	       COURSE_PAST => sprintf("<a href='confirm.php?ri=%d&act=%d&mode=%d' title='Click here to mark this as completed'>Completed</a>",  $record_id, COURSE_PAST, $mode),
  	       COURSE_PRESENT => sprintf("<a href='confirm.php?ri=%d&act=%d&mode=%d' title='Click here to mark this as current'>Current</a>",  $record_id, COURSE_PRESENT, $mode),
  	       COURSE_FUTURE => sprintf("<a href='confirm.php?ri=%d&act=%d&mode=%d' title='Click here to mark this as future or planned'>Future</a>",  $record_id, COURSE_FUTURE, $mode),
  	       COURSE_REMOVE => sprintf("<a href='confirm.php?ri=%d&act=%d&mode=%d' title='Click here to remove this entry'>Remove</a>",  $record_id, COURSE_REMOVE, $mode),
  	     );
  	     // remove current mode link
         unset( $action_links[$mode] );  	
         // render the details
         $course = $courseitem['course'];
         // build logo for course
         $logo = '';
  	     if ( $course->classification == Course::CLASS_OPENLEARN ) {
  	   	   $logo = sprintf(" <a href='%s' target='_blank'><img src='%s' title='OpenLearn' /></a>",
  	   	                       OPENLEARN_HOME,
  	   	                       FB_APP_CALLBACK_URL.'images/openlearn_icon.png');
  	     }
  	     $output .= sprintf("<tr><td class='course_code'>%s</td><td class='course_divider'>".
  	       "<a href='%scourseact.php?cc=%s' title='Click here for things to do'>%s</a>".
           "%s</td><td class='course_divider mopi'>%s</td><td class='course_divider actions'>%s</td></tr>", 
           $course->course_code,
           FB_APP_CALLBACK_URL,
           $course->course_code,
           $course->full_title,
           $logo,
           $courseitem['mopi'],
           implode("&nbsp;|&nbsp;", $action_links)
         );         	
   	   }
   	 }
   	 $output .= "</table>";   	
   }
   
   return $output;  
 }	
 
 /**
 * Renders HTML for a set of options
 * Does not include select element
 * @param $options array Key/value pair for option
 * @param $selectedKey Selected option value
 * @return string HTML for options
 */
 public function renderOptionSet($options, $selectedKey="") {
	$retval = "";
	foreach ($options as $key => $value) {
		$selected = $key == $selectedKey ? " selected='true'" : "";
		$retval .= sprintf("<option value='%s'%s>%s</option>", $key, $selected, $value);
	}
	return $retval;
 }
 
 /**
  * Execute an FQL query on Facebook
  * @param string $fql FQL to execute
  * @return result
  */
 public function doFQL($fql) {	
   	$param  =   array(
       'method'     => 'fql.query',
        'query'     => $fql,
      'callback'    => ''
    );
   return $this->facebook->api($param);	 
 }
 
 ///**
 // * Send a notification on Facebook platform
 // * @deprecated May not work anymore.
 // * @param int64 $to Facebook user id pf recipient
 // * @param string $fbml Markup of message
 // * @param boolean $invite deprecated Whether the message is an invitation
 // * @return true on success
 // */
 //public function sendNotification($to, $fbml, $invite=false) {
 //  return $this->facebook->api_client->notifications_send($to, $fbml, $invite);
 //}
 
 /**
  * Renders a text input box with a text drop down starting prompt
  *
  * Generates the HTML for a control that consists of two parts. The first
  * is a drop down box giving some starting words for a sentence. The second
  * an input text box for free text. See the preferences page for an example 
  * of usage.
  *
  * @param array $promptOptions List of text items to appear in drop down boxes. These should be the start of sentences.
  * @param string $controlname HTML name attribute
  * @param string $current_value Current string for item. This will be split between drop down and text input elements.
  * @return string HTML fragment for control.
  */
 public function getPromptedInputBox( $promptOptions, $controlname, $current_value) {
   $retval = "<table class='promptinput'><tr>";
   $remainder = $current_value;
   // first render drop down box
   $retval .= sprintf("<td><select name='%s1'>", $controlname);
   foreach ($promptOptions as $prompt) {
     $selected = '';
     $matches = array();
     if ( preg_match('/^'.$prompt.' (.*)$/', $current_value, $matches) ) {
     	 $selected = 'selected';
     	 $remainder = $matches[1];
     } 
     $retval .= sprintf("<option value='%s' %s>%s</option>", $prompt, $selected, $prompt);	
   }
     
   $retval .= "</select></td>";
     
   // then put rest of statement in a text box
   $retval .= sprintf("<td><input type='text' class='promptinputbox' maxlength='140' name='%s2' value='%s' /></td>", $controlname, $remainder);
   $retval .= "</tr></table>";
   return $retval;	
 }
  
 /**
  * Generate a news feed for an item when a course is added
  * @param FBUser $fbuser FBUser object representing person who has added course
  * @param Course $course Course object representing course that has been added
  * @return string HTML fragment for news item
  */
 public function generateCourseAddNewsItem($fbuser, $course, $mode) {
 	 $course_info_url = $course->getCourseInfoURL();
 	 $courseorqual = $course->isQualification() ? 'qual' : 'course';
   $modewords = array( 'course' => array(COURSE_PAST => 'I have studied', 
 	                    COURSE_PRESENT => 'I am studying',
 	                    COURSE_FUTURE => 'I am thinking of studying'),
 	                    'qual' => array(COURSE_PAST => 'I have completed', 
 	                    COURSE_PRESENT => 'I am working towards',
 	                    COURSE_FUTURE => 'I am thinking of taking')); 
  	        
   $wall_message = sprintf("%s %s %s ",
                             $modewords[$courseorqual][$mode],
                             $course->course_code,
                             $course->short_title);
          
   // add words "on OpenLearn" if an openlearn course
   $wall_message .= $course->classification == Course::CLASS_OPENLEARN ? "on OpenLearn" : "at The Open University";
   $wall_message .= ".";
   $caption = '';
   if ( DEVELOPER_MODE ) {
     //$body_general .= " *** Please ignore this message, it is just a test ***";
     $caption .= " *** Please ignore this message, it is just a test ***";
   }
   // try to publish action to feed, if the limit is exceeded then just catch exception
   //echo "<script lanuage='javascript'>";
   //printf("var actionLinks = [{ 'text': 'View %s', 'href': '%s'}];", $course->course_code, $course_info_url['href']);
   //printf("Facebook.streamPublish('%s', null, actionLinks, null, 'Would you like to tell your friends about your studies?');", $wall_message);
   //echo "</script>";
   $vars = array( 'name' => $course->course_code.' '.$course->short_title,
                  'link' => $course_info_url['href'],
                  'caption' => $caption,
                  'picture' => FB_APP_CALLBACK_URL.'/images/storypic.jpg',
                  'access_token' => $this->facebook->getAccessToken(),
                  'description' => $wall_message,
                );
   return $this->getTemplate('publishnewcoursepopup', $vars);
 }
   
 /** 
  * Generate FBML for a suggestion to add Course Profiles to a user profile (for tab)
  * e.g. "What OU Courses are you studying?"
  * @return String FBML for suggestion
  */
 public function getRandomAppAddSuggestion() {
 	 $retval = '';
 	 
 	 $feeds = array(
 	   0 => array( 'fbml' => "<a href='%url%'>What OU courses have <b>you</b> studied?</a>", 'page' => 'index.php'),
 	   1 => array( 'fbml' => "<a href='%url%'>Find a study buddy for <b>your</b> course or qualification with course profiles</a>", 'page' => 'index.php'),
 	   2 => array( 'fbml' => "<a href='%url%'>Get this tab for your profile!</a>", 'page' => 'index.php'),  	 
 	 ); 
   	 
   	 $msgfound = false; 	 
   	 do {
   	 	$msg = $feeds[mt_rand(0,2)];
   	 	$msgfound = true;
   	 } while (!$msgfound);
   	 
   	 // encode message
   	 $retval = $msg['fbml'];
  	 $retval = str_replace('%url%', FB_CANVAS_PAGE_URL.$msg['page'],  $retval);
   	 return $retval;   	
   }

}

?>