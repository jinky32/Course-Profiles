<?php
/**
 * Finds a potential study buddy for a user
 * @package cpfa
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 *
 */
 class StudyBuddyFinder extends CPFABase {
 	private $fbplatform;
 	private $appuser;
 	private $request_timestamp_utc;
 	private $university;
 	private $course;
 	private $cached_profiles;
 	
 	/**
 	 * Class constructor
 	 *
 	 * @param FBPlatform $fbplatform Object that handles communication with Facebook API
 	 * @param FBUser $appuser Object representing the current user of the application
 	 * @param Course $course The course that the user and any potential study buddy should be on
 	 */
 	function __construct($fbplatform,$appuser, $course ) {
 	  parent::__construct();
 	  $this->fbplatform = $fbplatform;
 	  $this->appuser = $appuser;	
 	  $this->university = new University('open.ac.uk');
 	  $this->course = $course;  
 	  $this->cached_profiles = array();
 	}
 	
 	/**
 	 * Tidies up cached data. This should be called when finished with the class instance.
 	 *
 	 * This deletes cached data generated in order to comply
 	 * with Facebook policies.
 	 */
 	function close() {
      // delete cache of data to avoid break FB T&C
      $sql = "DELETE FROM studybuddy_cache WHERE subject_internal_uid = ? AND request_timestamp_utc = ? ";
      $params = array($this->appuser->getInternalUid(), $this->request_timestamp_utc);
      $this->doSQL( $sql, $params ); 		
 	}
 	
 	/**
    * Score two sets of fields with delimited values for similarity
    * The score will be out of max score
    * @param string $field1 First field to compare
    * @param string $field2 Second field to compare
    * @param int $maxscore Maximum possible score
    * @return int Score out of maxscore
    */
  public function scoreField($field1, $field2, $maxscore) {
 	  $score = 0;
 	  $field1_values = preg_split('/[\n;,]/', $field1);
 	  $field2_values = preg_split('/[\n;,]/', $field2);
 	
 	  // find largest field
 	  $max_hits = min( sizeof($field1_values), sizeof($field2_values) );
 	
 	  // look at matches
 	  $hits = sizeof( array_intersect( $field1_values, $field2_values ) );
 	
 	  // score
 	  if ($hits > $max_hits)
 		  $retval = $maxscore;
 	  else {
 	    $retval = round( ($hits/$max_hits) * $maxscore );	
 	  }
 	  return $score;
  }
   
   /**
    * Find out if the specified search scope excludes the potential match
    * @param int $search_scope The search scope specified in the search form
    * @param boolean $is_friend Set to true if potential match is a friend of user
    * @param boolean $is_network Set to true if potential match shares a network with the user
    * @return boolean True is excluded by this search scope, otherwise false
    */
   function is_excluded_search_scope($search_scope, $is_friend, $is_network) {
     $retval = ( $search_scope == PRIVACY_ME
 	          || ( $search_scope == PRIVACY_FRIENDS && ! $is_friend )
 	          || ( $search_scope == PRIVACY_NETWORK && ! $is_friend && !$is_network ));
	   return $retval;
   }
   
   /**
    * Find out if the privacy settings for the potential match exclude this user
    * @param int $fb_priv_studybuddy The privacy setting from preferences
    * @param boolean $is_friend Set to true if potential match is a friend of user
    * @param boolean $is_network Set to true if potential match shares a network with the user
    * @return boolean True is excluded by privacy setting, otherwise false
    */  
   function is_excluded_privacy($fb_priv_studybuddy, $is_friend, $is_network ) {
 	 $retval = ( $fb_priv_studybuddy == PRIVACY_ME
 	            || ( $fb_priv_studybuddy == PRIVACY_FRIENDS && ! $is_friend )
 	            || ( $fb_priv_studybuddy == PRIVACY_NETWORK && ! $is_friend && !$is_network ));
   	 return $retval;
   }
   
   /**
    * See if a user and a potential match share a common network
    * @param array $user_affiliations array Affiliations structure: Array of users, Array of affiliations, Array of affiliation items
    * @param array $friend_affiliations array Affiliations structure: Array of affiliation items
    * @return boolean TRUE if match
    */
   function in_network($user_affiliations, $friend_affiliations) {
     $retval = false;
     if ( ! empty( $user_affiliations ) && ! empty( $friend_affiliations )) {
     	$person1_affiliations = array();
        $person2_affiliations = array();
        
        foreach ($user_affiliations as $user_affiliations_item) {
          if ( ! empty( $user_affiliations_item['affiliations'] ) ) {
            foreach( $user_affiliations_item['affiliations'] as $affiliations ) {
              $person1_affiliations[] = $affiliations['nid'];
            }	
          }
        }
        foreach ($friend_affiliations as $friend_affiliations_item) {
   	      $person2_affiliations[] = $friend_affiliations_item['nid'];
        }
        $retval = sizeof( array_intersect( $person1_affiliations, $person2_affiliations) > 0);       
     }
     return $retval; 	
   }
    
    
   /**
    * Takes the social data for the user and potential match and attempts to score
    * The scoring levels are configuarable from the config file
    * @param array $subject_user_data FB data for the app user
    * @param array $target_user_data FB data for potential match
    * @return int Score for match
    */
   function findStudyBuddyScore($subject_user_data, $target_user_data) {
 	 // each category can be 10 points
 	 $score = 0;
     // timezone
     $timezone_diff = abs($subject_user_data['timezone'] - $target_user_data['timezone']);
     if ( $timezone_diff < SB_WEIGHTING_TIMEZONE ) {
        $score += ( SB_WEIGHTING_TIMEZONE - $timezone_diff );
     }
    
     // commented out as may not compy with ou policy

    
     // commented out as may not comply with ou policy
//    // birthday
//    // work out year if there
//    $subject_year = substr($subject_user_data['birthday'], -4);
//    $target_year = substr($target_user_data['birthday'], -4);
//    if ( $subject_year != "" && $target_year != "") {
//        $agediff = abs( $subject_year - $target_year );
//        if ( $agediff < 21 ) {
//         	if ( $agediff > 15 )
//         		$score += 1;
//            else if ($agediff > 10 )
//                $score += 2;
//            else if ($agediff > 8 )
//                $score += 3;
//            else if ($agediff > 6 )
//                $score += 4;
//            else 
//                $score += (11-$agediff);	
//        }	
//    }
    
     // add a few points if they might be a dating match!
     if ( $subject_user_data['relationship_status'] == 'Single' && $target_user_data['relationship_status'] == 'Single') {
       // they are both single so lets check target is what user is looking for
       if ( is_array($subject_user_data['meeting_sex']) 
            && in_array($target_user_data['sex'],$subject_user_data['meeting_sex'])) {
           // find out if they are both looking for relationship
           if ( is_array($subject_user_data['meeting_for']) 
                && is_array($target_user_data['meeting_for']) ) {
                $rela_a = array('A Relationship', 'Dating');
                $rela_b  = array('Random Play', 'Whatever I can get');
                if ( sizeof(array_intersect($rela_a, $subject_user_data['meeting_for'])) > 0
                     && sizeof(array_intersect($rela_a, $target_user_data['meeting_for'])) > 0 ) {
                    // direct hit on people looking for relationship
                    $score += 10;                     	
                }
                else if ( sizeof(array_intersect($rela_b, $subject_user_data['meeting_for'])) > 0
                     && sizeof(array_intersect($rela_b, $target_user_data['meeting_for'])) > 0 ) {
                    // direct hit on people looking for fun
                    $score += 5;                     	
                }
                else if ( sizeof(array_intersect($rela_a, $subject_user_data['meeting_for'])) > 0
                     && sizeof(array_intersect($rela_b, $target_user_data['meeting_for'])) > 0 ) {
                    // mix of seriousness, maybe a chance
                    $score += 2;                     	
                }
                else if ( sizeof(array_intersect($rela_b, $subject_user_data['meeting_for'])) > 0
                     && sizeof(array_intersect($rela_a, $target_user_data['meeting_for'])) > 0 ) {
                    // mix of seriousness, maybe a chance
                    $score += 2;                     	
                }
           }                   	
       }  	
     }
       
     // wall count (max ten points)
     if ( $target_user_data['wall_count'] > 5 )
       $score += SB_WEIGHTING_WALLCOUNT;
     else {
       $score += $target_user_data['wall_count'];	
     }
    
     // notes count (max ten points)
     if ( $target_user_data['notes_count'] > 5 )
       $score += SB_WEIGHTING_NOTESCOUNT;
     else {
       $score += $target_user_data['notes_count'];	
     } 
    
     // activities
     $score += $this->scoreField($subject_user_data['activities'], $target_user_data['activities'], SB_WEIGHTING_ACTIVITIES);      
     // movies
     $score += $this->scoreField($subject_user_data['movies'], $target_user_data['movies'], SB_WEIGHTING_MOVIES);
     // tv
     $score += $this->scoreField($subject_user_data['tv'], $target_user_data['tv'], SB_WEIGHTING_TV);
     // interests
     $score += $this->scoreField($subject_user_data['interests'], $target_user_data['interests'], SB_WEIGHTING_INTERESTS);
     // music 
     $score += $this->scoreField($subject_user_data['music'], $target_user_data['music'], SB_WEIGHTING_MUSIC);
     // books
     $score += $this->scoreField($subject_user_data['books'], $target_user_data['books'], SB_WEIGHTING_BOOKS);
    
     return $score;
   }
   
   /**
    * Process the request
    * @param int search_scope The search scope specified in the search form
    * @param array $search_regions University regions to look for a study buddy
    */
   function processRequest($search_scope, $search_regions) {
     // parse values from search form
   	 $search_scope = in_array($search_scope, array_keys($this->appuser->getPrivacyOptions())) ?
   	                $search_scope : PRIVACY_EVERYBODY;
   	 $alloncourse = $this->findAllOnCourse($search_scope, $search_regions);
   	 
   	 if (sizeof($alloncourse) > 0) {
   	   $this->processPossibleMatches($search_scope, $alloncourse);	
   	 }
   }
   
   /**
    * Finds details held on Course profiles for each user inserts them to the studybuddy_cache table 
    * for processing. Excludes those with a fb_priv_studybuddy of PRIVACY_ME
    * @param int search_scope The search scope specified in the search form
    * @param array $search_regions University regions to look for a study buddy
    */
   protected function findAllOnCourse($search_scope, $search_regions) {
   	 $regions = array('R00');
   	 if ( is_array($search_regions) ) {
   		$regions = array();
   		foreach ( $search_regions as $region_entry ) {
   		   if ( in_array($region_entry, array_keys($this->university->getRegions()) ) ) {
   		      $regions[] = $region_entry;	
   		   }	
   		}
   	 }
     
   	 // find matching records from db and put into cache
   	 $this->request_timestamp_utc = gmdate ( 'Y-m-d H:i:s' );
   	 $sql = sprintf("INSERT INTO studybuddy_cache (subject_internal_uid, request_timestamp_utc, internal_uid, ".
   	       "ou_region, facebook_uid, fb_priv_studybuddy, mopi, status, is_friend, is_network, course_classification, exclude_row) ".
   	       "SELECT DISTINCT :subject_internal_uid, :request_timestamp_utc, u.internal_uid, u.ou_region, u.facebook_uid, u.fb_priv_studybuddy, uc.mopi, uc.status, 0 AS is_friend, 0 AS is_network, c.classification AS course_classification, 1 as exclude_row ".
   	       "FROM user u ".
   	       "JOIN user_course uc ON u.internal_uid = uc.internal_uid ".
   	       "JOIN course c ON uc.course_code = c.course_code ".
   	       "WHERE u.facebook_remove_utc = 0 ".
   	       " AND u.fb_priv_studybuddy != :privacy_me ".
   	       " AND u.internal_uid != :internal_uid ".
   	       " AND (( uc.course_code = :course_code AND c.classification = :class_ou_corres)".
   	       "      OR (uc.course_code LIKE '%s' AND c.classification = :class_openlearn)) ".
   	       " AND uc.status IN (%s) ",
   	       $this->course->course_code.'_%',
   	       implode(',', array(COURSE_PAST, COURSE_PRESENT, COURSE_FUTURE)));
   	                         
   	 // filter by region if required      
     if ( $regions[0] != 'R00' ) {
     	$sql .= sprintf(" AND u.ou_region IN ('%s')", implode( "','", $regions));
   	 }
   	 
   	 // CLIP AT 30 TO REDUCE PERFORMANCE PROBLEMS
   	 $sql .= " ORDER BY RAND() ".
          "LIMIT 30 ";  
   	 
   	 $params = array(':subject_internal_uid' => $this->appuser->getInternalUid(), 
   	                 ':request_timestamp_utc' => $this->request_timestamp_utc, 
   	                 ':privacy_me' => PRIVACY_ME,
   	                 ':internal_uid' =>$this->appuser->getInternalUid(), 
   	                 ':course_code' => $this->course->course_code,
   	                 ':class_ou_corres' => Course::CLASS_OU_CORRES,
   	                 ':class_openlearn' => Course::CLASS_OPENLEARN);
   	 $cache_row_id = $this->doSQL($sql, $params);

   	 // get the friend ids
   	 $sql = "SELECT row_id, internal_uid, facebook_uid, fb_priv_studybuddy ".
   	       "FROM studybuddy_cache ".
   	       "WHERE subject_internal_uid = ? AND request_timestamp_utc = ? ";
   	 $params = array($this->appuser->getInternalUid(), $this->request_timestamp_utc);
   
 
   	 $alloncourse = $this->doSQL( $sql, $params, 'facebook_uid' );
   	 
   	 return $alloncourse;	
   }
   
   /**
    *
    * @param $search_scope
    * @param $alloncourse
    */
   protected function processPossibleMatches($search_scope, $alloncourse) {
   	 // get data from facebook for each person and subject user
   	 $userstoget = array_keys($alloncourse);
   	 $userstoget[] = $this->appuser->getFacebookUid();
   	 $fql = sprintf( "SELECT uid, name, profile_url, ".
   	       "affiliations, timezone,  birthday, sex, ".
   	       "hometown_location, meeting_sex, meeting_for, relationship_status, ".
   	       "current_location, activities, interests, ".
   	       "music, tv, movies, books, about_me, hs_info, education_history, ".
   	       "notes_count, wall_count, status, is_blocked ".
   	       "FROM user ".
   	       "WHERE uid IN ('%s')",
   	       implode("', '", $userstoget)
   	       );
     // picture, link, religion,political, work_history,
   	 $fb_data = $this->fbplatform->doFQL($fql);
   	 
   	 // extract data for subject user from query (this saves a call to the api)
   	 $user_data = array();
   	 $fb_data_userkey = 0;
   	 $i = 0;
   	  $appuser_facebook_uid = $this->appuser->getFacebookUid();
   	  // a foreach loop is faster than the while loop it seems!
   	  foreach( $fb_data as $fb_row ) {
   	  	// skip row if is_blocked flag set
   	  	if ($fb_row['is_blocked']) {
   	  		continue;
   	  	}
   	  	
   	    if ( $fb_row['uid'] == $appuser_facebook_uid ) {
   	      $user_data = $fb_row;
   	      //break;
   	    }
   	    else {
   	    	$this->cached_profiles[$fb_row['uid']]['name'] = $fb_row['name'];
   	    	$this->cached_profiles[$fb_row['uid']]['picture'] = $fb_row['picture'];
   	    	$this->cached_profiles[$fb_row['uid']]['link'] = $fb_row['profile_url'];
   	    }
   	  }
//   	 while ($fb_data_userkey == 0) {
//   		if ($fb_data[$i]['uid'] == $appuser_facebook_uid) {
//   		  	$fb_data_userkey = $i;
//   		}
//   		else {
//   	  	  $i++;
//   	  	}
//   	 }
   	 //$user_data = $fb_data[$fb_data_userkey];
   	 //unset($fb_data[$fb_data_userkey]);
   	 if ( empty($fb_data) ) {
   		$fb_data = array();
   	 } 
   	  
   	 	
   	 // load up the friends
   	 $availfriends = $this->appuser->getAppUsingFriends();
   	 // load up affiliations (networks)
   	 $affiliations = $this->appuser->getInfo('affiliations');
   	 // process each potential match
   	 foreach ($fb_data as $fb_row) {
   	   // skip update if data for current user
   	   if ($fb_row['uid'] == $appuser_facebook_uid ) {
   	     continue;	
   	   }
   	   
   	   // get friendship and networks
   	   
   	   
   	   
   	   // work out exlusions based on privacy settings
   	   
   	   
   	   // if allowed get rest of information and score
   	   
   	   
   	   
   	   
   	   $sql = "UPDATE studybuddy_cache SET name = :name, affiliations = :affiliations, ".
   	         "timezone = :timezone, religion = :religion, birthday = :birthday, sex = :sex, ".
   	         "hometown_location = :hometown_location, meeting_sex = :meeting_sex, ".
   	         "meeting_for = :meeting_for, relationship_status = :relationship_status, ".
   	         "political = :political, current_location = :current_location, activities = :activities, ".
   	         "interests = :interests, music = :music, tv = :tv, movies = :movies, books = :books, ".
   	         "about_me = :about_me, hs_info = :hs_info, education_history = :education_history, ".
   	         "work_history = :work_history, notes_count = :notes_count, wall_count = :wall_count, ".
   	         "status_message = :status_message, is_friend = :is_friend, is_network = :is_network, ".
   	         "exclude_row = :exclude_row, score = :score ".
   	   "WHERE subject_internal_uid = :subject_internal_uid ".
   	   "AND request_timestamp_utc = :request_timestamp_utc ".
   	   "AND facebook_uid = :facebook_uid ";
   	   $params = array();
   	   $params[':name'] = $fb_row['name'];
   	   $params[':affiliations'] = serialize($fb_row['affiliations']);
   	   $params[':timezone'] = $fb_row['timezone'];
   	   $params[':religion'] = $fb_row['religion'];
   	   $params[':birthday'] = $fb_row['birthday'];
   	   $params[':sex'] = $fb_row['sex'];
   	   $params[':hometown_location'] = serialize($fb_row['hometown_location']);
   	   $params[':meeting_for'] = serialize($fb_row['meeting_for']);
   	   $params[':meeting_sex'] = serialize($fb_row['meeting_sex']); // can we use this?
   	   $params[':relationship_status'] = $fb_row['relationship_status'];
   	   $params[':political'] = "not used"; // $fb_row['political'];
   	   $params[':current_location'] = serialize($fb_row['current_location']);
   	   $params[':activities'] = serialize($fb_row['activities']);
   	   $params[':interests'] = serialize($fb_row['interests']);
   	   $params[':music'] = serialize($fb_row['music']);
   	   $params[':tv'] = serialize($fb_row['tv']);
   	   $params[':movies'] = serialize($fb_row['movies']);
   	   $params[':books'] = serialize($fb_row['books']);
   	   $params[':about_me'] = $fb_row['about_me'];
   	   $params[':hs_info'] = serialize($fb_row['hs_info']);
   	   $params[':education_history'] = serialize($fb_row['education_history']);
   	   $params[':work_history'] = "not used"; // serialize($fb_row['work_history']);
   	   $params[':notes_count'] = $fb_row['notes_count'];
   	   $params[':wall_count'] = $fb_row['wall_count'];
   	   $params[':status_message'] = serialize($fb_row['status']);
   	   // is a friend?
   	   $is_friend = in_array($fb_row['uid'], $availfriends);
   	   $params[':is_friend'] = $is_friend;
   	   // share a common network?
   	   $is_network = $this->in_network($affiliations, $fb_row['affiliations']);
       $params[':is_network'] = $is_network;
       // exclude row from further processing? (search scope or match privacy settings)
       $exclude_row = ( $this->is_excluded_search_scope($search_scope, $is_friend, $is_network)
                                  || $this->is_excluded_privacy($alloncourse[$fb_row['uid']]['fb_priv_studybuddy'], $is_friend, $is_network ));       
       $params[':exclude_row'] = $exclude_row;
       $params[':score'] = $exclude_row ? 0 : $this->findStudyBuddyScore($user_data, $fb_row);
   	  
   	   // where params
   	   $params[':subject_internal_uid'] = $this->appuser->getInternalUid();
   	   $params[':request_timestamp_utc'] = $this->request_timestamp_utc;
   	   $params[':facebook_uid'] = $fb_row['uid'];
   	  
   	   $this->doSQL($sql, $params);	
   	 }	
  }
 
  /**
   * Generates HTML fragment for studybuddy results
   * @return string HTML fragment
   */
  function renderResult( ) {
  	$retval = "";
  	
 	  $sql = "SELECT sc.internal_uid, sc.facebook_uid, sc.mopi, sc.status, sc.status_message, ".
 	         "sc.is_friend, sc.is_network, sc.ou_region, sc.name, sc.course_classification ".
 	         "FROM studybuddy_cache sc ".
 	         "WHERE subject_internal_uid = :subject_internal_uid ".
   	       "  AND request_timestamp_utc = :request_timestamp_utc ".
   	       "  AND exclude_row = 0 ".
   	       "ORDER BY is_friend DESC, is_network DESC, score DESC ";
   	$params = array();
   	$params[':subject_internal_uid'] = $this->appuser->getInternalUid();
   	$params[':request_timestamp_utc'] = $this->request_timestamp_utc;
   	$matches = $this->doSQL($sql, $params, 'facebook_uid');

   	if ( sizeof($matches) == 0 ) {
      $retval .= sprintf("<div class='explanation'><div class='message'>Sorry, we could not find any potential study buddies for you!</div>".
         "<b>Why not:</b><ul>".
         "<li><a href='%s'>Recommend</a> this course to a friend</a></li>".
         "<li>Find a <a href='%s'>study buddy</a></li></ul> </div>",
         'courserecommend.php?cc='.$this->course->course_code,
         'studybuddy.php?cc='.$this->course->course_code);	
    }
    else {
    	$retval .= "<div class='explanation'><div class='message'>Here are some possible study buddies for you:</div>Why not send them a message?</div>";
    }
    $regions = $this->university->getRegions();
    foreach ($matches as $facebook_uid => $details) {
       $retval .= "<table class='whitepanel'><tr>";
       $retval .= "<td rowspan='3' style='width:60px'>";
       $retval .= sprintf("<a href='%s' target='_top'><img src='http://graph.facebook.com/%s/picture?type=square' alt='%s' /></a>",$this->cached_profiles[$facebook_uid]['link'], $facebook_uid, $this->cached_profiles[$facebook_uid]['name']);
       $retval .= "</td>";
       $retval .= "<td><b>";
       //$retval .= $this->cached_profiles[$facebook_uid]['name'];
       $retval .= sprintf("<a href='%s' target='_top'>%s</a>",$this->cached_profiles[$facebook_uid]['link'], $this->cached_profiles[$facebook_uid]['name']);    
       //$retval .= "<fb:if-can-see uid='$facebook_uid' what='profile'>";
       //$retval .= " <fb:name uid='$facebook_uid' shownetwork='true' linked='true'/>";
       //$retval .= "<fb:else>";
       //$retval .= " <fb:name uid='$facebook_uid' shownetwork='true' linked='false'/>"; 
       //$retval .= "</fb:else>"; 
       //$retval .= "</fb:if-can-see>"; 
       $retval .= "</b>";
       $year = "";
       if ($details['mopi'] != '') {
  	     $year = trim(substr($details['mopi'], 0, 4));
       }  
       switch ( $details['status'] ) {
  	      case COURSE_PAST:
  	         if ( $year == "" ) {
  	            $retval .= " has studied ".$this->course->course_code;
  	         }
  	         else {
  	  	        $retval .= " studied ".$this->course->course_code." in ".$year;
  	         }
  	         break;
  	      case COURSE_FUTURE:
  	         $retval .= " is planning to study ".$this->course->course_code;
  	         if ( $year != "" ) {
  	           $retval .= " in ".$year;
  	         }
  	         break;
  	      default:
  	         $retval .= " is currently studying ".$this->course->course_code;  	  
       }
       // if on openlearn signal as such
       if ( $details['course_classification'] == Course::CLASS_OPENLEARN ) {
         $retval .= sprintf(" through <a href='%s' target='_blank'>OpenLearn</a>",
  	   	                       OPENLEARN_HOME);  	
       }
       
       $retval .= "</td><td class='search_act_divider'>";
       // send message link
       $retval .= "<a href='http://www.facebook.com/message.php?id=";
       $retval .= $facebook_uid;
       $retval .= "&subject=";
       $retval .= urlencode($this->course->course_code." ".$this->course->full_title);
       $retval .= "&msg=";
       $retval .= urlencode("Hi, I'm taking \"".$this->course->course_code." ".$this->course->full_title.
             "\" and I was wondering whether you'd be interested in chatting about it?");
       $retval .= "' target='_top'>Send Message</a>";
       
      // $retval .= sprintf("<a href=\"javascript: sendMessage(%d, \"%s\", \"%s\")\">Send Message</a>",
      //                     $facebook_uid, 
      //                     urlencode($this->course->course_code." ".$this->course->full_title),
      //                     urlencode("Hi, I'm taking ".$this->course->course_code." ".$this->course->full_title.
      //                     " and I was wondering whether you'd be interested in chatting about it?"));
       //$retval .= $facebook_uid;
       //$retval .= "&subject=";
       //$retval .= urlencode($this->course->course_code." ".$this->course->full_title);
       //$retval .= "&msg=";
       //$retval .= urlencode("Hi, I'm taking \"".$this->course->course_code." ".$this->course->full_title.
       //      "\" and I was wondering whether you'd be interested in chatting about it?");
       //$retval .= "'>Send Message</a>";
       $retval .= "</td></tr>";
  
       $retval .= "<tr><td>";
       if ( $details['ou_region'] != 'R00' )
          $retval .= $regions[$details['ou_region']];
       
       $retval .= "</td><td>";
       // poke link
       //$retval .= "<a href='http://www.facebook.com/poke.php?id=";
       //$retval .= $facebook_uid;
       //$retval .= "' target='_top'>Poke!</a>"; // <fb:pronoun uid='".$facebook_uid."' capitalize='true' objective='true' />!</a>";
       $retval .= "</td></tr>";
       $retval .= "<tr><td></td>";
       if ( ! $details['is_friend'] ) {
           $retval .= "<td class='search_act_divider'>";
           // add to friends
           $retval .= "<a href='http://www.facebook.com/addfriend.php?id=";
           $retval .= $facebook_uid;
           $retval .= "' >Add to friends</a></td>";
       }
       else {
       	   $retval .= "<td>&nbsp;</td>";
       }
       $retval .= "</tr></table>";  
    }
    return $retval;
   }
 }
?>