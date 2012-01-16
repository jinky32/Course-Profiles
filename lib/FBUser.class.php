<?php
require_once('User.class.php');

// define privacy settings
/** Everyone can see that feature */
define("PRIVACY_EVERYBODY", 1); 
/** Only networks and friends can see */
define("PRIVACY_NETWORK", 2); 
/** Only friends can see */
define("PRIVACY_FRIENDS", 3); 
/** Only user can see */
define("PRIVACY_ME", 4); 

/**
 * A Facebook user
 *
 * Handles functionality related to a Facebook user such as finding facebook friends.
 * @package cpfa
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
class FBUser extends User {
	/** Facebook ID for user */
  public $facebook_uid;
  /** Facebook API library object */
  public $facebook;
  /** FB user profile information */
  private $_user_profile;
  
  /**
   * Create new instance
   * @param int $facebook_uid Facebook id for user
   * @param Facebook $facebook Facebook object
   * @param bool $notaddapp If true don't treat user as having added the app
   */
  function __construct($facebook_uid, $facebook, $notaddapp=false) {
    parent::__construct();
    $this->facebook_uid = $facebook_uid;
    $this->facebook = $facebook;
    // get the user profile
    $this->_user_profile = $this->facebook->api('/me');
    
    
    // attempt to get internal uid
    $sql = "SELECT facebook_uid, internal_uid, facebook_add_utc, about, about_last_updated_utc FROM user WHERE facebook_uid =? AND facebook_remove_utc = 0";
    $results = $this->doSQL($sql, array($this->facebook_uid), 'facebook_uid');
    if ( isset($results[$this->facebook_uid]) ) {
       $this->internal_uid = $results[$this->facebook_uid]['internal_uid'];
       if ($results[$this->facebook_uid]['about'] != '') {
         $this->about = $results[$this->facebook_uid]['about'];
         $this->about_last_updated_utc = $results[$this->facebook_uid]['about_last_updated_utc'];
       }
       // check facebook add time is populated as user might be here as result of recommendation
       if ( $results[$this->facebook_uid]['facebook_add_utc'] == 0 ) {
           $sql = "UPDATE user SET facebook_add_utc = UTC_TIMESTAMP() ".
                "WHERE facebook_uid =? AND facebook_remove_utc = 0 ";
           $this->doSQL($sql, array($this->facebook_uid));
       }
    }
    else {
      // if not found then register
      if ( $notaddapp ) {
        $sql = "INSERT INTO user (facebook_uid, first_registration_utc, last_modified_utc) VALUES(?, UTC_TIMESTAMP(), UTC_TIMESTAMP())";
        $params = array($this->facebook_uid);
  	$this->internal_uid = $this->doSQL( $sql, $params );     	
      }
      else {
        $sql = "INSERT INTO user (facebook_uid, first_registration_utc, facebook_add_utc, last_modified_utc) VALUES(?,UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())";
        $params = array($this->facebook_uid);
  	    $this->internal_uid = $this->doSQL( $sql, $params );
  	  }
    }
    // Find out if we are on "my ou story" as well
  	$sql = "SELECT internal_uid ".
  	       "FROM myoustory_user ".
  	       "WHERE facebook_uid =? AND facebook_remove_utc = 0";
  	$params = array( $this->facebook_uid );
    $results = $this->doSQL($sql, $params);
    foreach ($results as $row) {
    	$this->myoustory_uid = $row['internal_uid'];
    }   
  }

  /**
   * Object destruction. Currently no action is taken here.
   */
  function __destruct() {}
  
  /** 
   * Returns the Facebook ID for this user
   * @return int ID number identifying this user on Facebook
   */
  public function getFacebookUid() {
    return $this->facebook_uid;	
  }
  
  ///**
  //  * Get a list of user's friends
  //  * @deprecated May not work
  //  * @return array List of facebook uids
  //  */
  // public function getFriends() {
  // 	 return $this->facebook->api_client->friends_get();
  // }
 
   /**
    * Get a list of friends of user who have this app
    * @todo Recode this to use Graph API
    * @return array List of facebook uids
    */
   public function getAppUsingFriends() {
   	 // SEE: http://stackoverflow.com/questions/2785093/facebook-friends-getappusers-using-graph-api
   	 // get friends from Graph API
   	 
   	 // remove friends not registered in app
   	 
   	 $result = $this->facebook->api(array('method' => 'friends.getAppUsers'));
   	 $retval = empty($result) ? array() : $result;
     return $retval;	
   }
   
//    /**
//     * Render the fbml for the profile page
//     * @deprecated Will be phased out in 2010 to match FB platform changes
//     */
//    function dec_renderProfileFBML() { 
//      // make sure we only have a max of six courses
//      $status_courses = array('quals' => array(), 'courses' => array());
//      $courselist = $this->getCourseList();
//      $courses = $courselist->toArray();
     
//      $rr_coursegroup = array();

//      $headings = array( 'quals' => array( COURSE_PAST  => 'I have completed...',
//                                         COURSE_PRESENT => 'I am working towards....',
//                                         COURSE_FUTURE => 'I am considering taking...'),
//                     'courses' => array( COURSE_PAST => ' I have completed...', 
//                                         COURSE_PRESENT => 'I am currently studying...', 
//                                         COURSE_FUTURE => 'I am thinking about studying...' ),
//      );     
//      // sort by type, status
//      foreach ($courses as $record_id => $courselistitem) {
//        $entry_type = $courselistitem['course']->isQualification() ? 'quals' : 'courses';
//        if ( in_array($courselistitem['status'], array_keys( $headings[$entry_type] ) ))        
//          $status_courses[$entry_type][$courselistitem['status']][] = $record_id;	        
//      }

//      $output_courses = array('quals' => array(), 'courses' => array());

//      // pick two random records for each of past, present, future to show
//      foreach ($status_courses as $entry_type => $entry_group) {
//        foreach ($entry_group as $status => $record_ids) {
//          if (sizeof( $record_ids ) > 1) {
//   	       $id_1 = rand(0, sizeof( $record_ids ) -1 );
//            $output_courses[$entry_type][$status][] = $record_ids[ $id_1 ];
//            $id_2 = 0;
//            // make sure second is not same as first
//            do {
//   	         $id_2 = rand(0, sizeof( $record_ids ) -1 );
//            } while ($id_2 == $id_1);
//            $output_courses[$entry_type][$status][] = $record_ids[ $id_2 ];	
//          }
//          else {
//   	        $output_courses[$entry_type][$status][] = $record_ids[ 0 ];
//          }
//        }
//      }
 
//      // build course output list
//      foreach ( $output_courses as $entry_type => $entry_group) {
//        foreach ( $entry_group as $status => $record_ids ) {
//          $course_group = array();
//      	   $course_group['status_header'] = $headings[$entry_type][$status];
//      	   foreach ( $record_ids as $record_id ) {
//      	     $course_record = array();
//      	     $courselistitem = $courselist->getEntry($record_id);
//      	     $course = $courselistitem['course'];     	  
//      	     $course_info_url = $course->getCourseInfoURL();
//      	     $course_record['url_title'] = $course_info_url['title'];
//            $course_record['url_href'] = $course_info_url['href'];
//            $course_record['url_target'] = $course_info_url['target'];
//      	     $course_record['course_code'] = $course->course_code;
//      	     $course_record['course_title'] = $course->short_title;
//      	     $course_record['logo'] = ''; 
//      	     if ( $course->classification == Course::CLASS_OPENLEARN ) {
//      	       $course_record['logo'] = sprintf(" <a href='%s' target='_blank'><img src='%s' ".
//      	  	                          "title='OpenLearn' /></a>",
//   	   	                            OPENLEARN_HOME,
//   	   	                            FB_APP_CALLBACK_URL.'images/ol_profile.png');
//      	     }
//      	     $course_record['mopi'] = $courselistitem['mopi'];
//      	     $course_group['rr_course_records'][] = $course_record;
//      	   }
//          $rr_coursegroup[] = $course_group;	
//        }
//      }
           
//      $vars = array( 'about' => $this->about,
//                     'rr_coursegroup' => $rr_coursegroup,
//                     'canvas_page_url' => FB_CANVAS_PAGE_URL,
//                     'app_callback_url' => FB_APP_CALLBACK_URL,
//                     'facebook_uid' => $this->facebook_uid );
//      $fbml = $this->getTemplate('profile', $vars);
//      // render a link to course profile under photo
//      $fbml_profile_actions = sprintf("<fb:profile-action url='%sallmycourses.php'>View <fb:name uid='%s' firstnameonly='true' possessive='true' useyou='false' /> Course Profile</fb:profile>",
//      FB_CANVAS_PAGE_URL, $this->facebook_uid);
//      //$x = $this->facebook->api_client->profile_setFBML(NULL, $this->facebook_uid, $fbml, $fbml_profile_actions);
//      $x = $this->facebook->api_client->profile_setFBML(NULL, $this->facebook_uid, $fbml, null, null, $fbml);
  
//      // update user table with the time this profile was updates
//      $sql = "UPDATE user SET facebook_profilegen_utc = UTC_TIMESTAMP() WHERE facebook_uid = ?";
//      $params = array($this->getFacebookUid());
//      $this->doSQL($sql, $params);
//  }
 
 /**
  * Returns the Facebook UID, name, link and picture for 
  * FB friends of the user who use this app and are on the 
  * same module.
  *
  * @param string $course_code Module code (e.g. T171)
  * @return array Array structure with a row for each friend with id, pic, link, and name
  */
 public function getFriendsOnCourse($course_code) {
   $availfriends = $this->getAppUsingFriends();
   $sql = sprintf("SELECT u.internal_uid, u.facebook_uid, uc.mopi, uc.status, ".
                  "c.classification, uc.last_modified_utc ".
        "FROM `user` u ".
        "JOIN `user_course` uc ON u.internal_uid = uc.internal_uid ".
        "JOIN `course` c ON uc.course_code = c.course_code ".
        "WHERE u.facebook_uid IN ('%s') ".
        "AND u.facebook_remove_utc = 0 ".
        "AND ((uc.course_code = ? AND classification = ? ) ".
        " OR (uc.course_code LIKE '%s' AND classification = ?))".
   	    " AND uc.status IN (%s) ",
        implode("','",$availfriends),
        $course_code.'_%',
        implode(',', array(COURSE_PAST, COURSE_PRESENT, COURSE_FUTURE)));
   $params = array( $course_code, Course::CLASS_OU_CORRES, Course::CLASS_OPENLEARN );
   $results = $this->doSql( $sql, $params, 'facebook_uid');
   $matches = array();
   // get details for user from GRAPH API
   foreach ($results as $facebook_uid => $details) {
   	  $friend = $this->facebook->api('/'.$facebook_uid."/?fields=name,link,picture");
      $matches[$facebook_uid] = $details;
      $matches[$facebook_uid]['name'] = $friend['name'];
      $matches[$facebook_uid]['link'] = $friend['link'];
      $matches[$facebook_uid]['picture'] = $friend['picture'];
   }
   return $matches;
 }
 
 
 
  /**
   * Get possible options for privacy
   * @return array key = Privacy option value = label
   */
  public function getPrivacyOptions($possessive=false) {
	$privacy_options = array( PRIVACY_EVERYBODY => "Everybody",
	                          PRIVACY_NETWORK => "My networks and friends",
	                          PRIVACY_FRIENDS => "Only my friends",
	                          PRIVACY_ME => $possessive ? "Nobody" : "Only me");
	return $privacy_options;
  }
  
  /**
   * Obtains an attribute from the user's FB profile
   * @param string Name of attribute to fetch
   * @return mixed Value of attribute
   */
  function getInfo($attribute) {
    //return $this->facebook->api_client->users_getInfo($this->facebook_uid, $attribute);	
    return $this->_user_profile[$attribute];
  }
  
  /**
   * Returns details about the link to My OU Story
   * @return array Contains two elements: available - whether link enabled in my ou story and inked - whether link enabled in this app
   */   
  public function getMyOUStoryLink() {
  	$retval = array('available' => TRUE, 'linked' => TRUE);
  	// Find out if available from  My OU Story
  	$sql = "SELECT fb_priv_cpfalink ".
	       "FROM myoustory_user ".
	       "WHERE internal_uid = ?";
	  $params = array($this->myoustory_uid);
	  $results = $this->doSQL($sql, $params);
	  $retval['available'] = $results[0]['fb_priv_cpfalink'];
  	
  	// Setting in this app
  	$sql = "SELECT fb_priv_myoustorylink ".
	       "FROM user ".
	       "WHERE internal_uid = ?";
	  $params = array($this->getInternalUid());
	  $results = $this->doSQL($sql, $params);
	  $retval['linked'] = $results[0]['fb_priv_myoustorylink'];  
  	return $retval;
  }
  
  /**
   * Returns the name of the user as queried from facebook
   * @param boolean $useyou (Default false) No longer used
   * @param boolean $possessive (Default false) No longer used
   * @param boolean $linked (Default false) No longer used
   * @return string Name of user
   */
  public function getUserProfileName($useyou='false', $possessive='false', $linked='false') {
  	return $this->_user_profile['name'];
  }
}

?>
