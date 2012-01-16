<?php
/** 
 * Class representing a course
 * 
 * @package cpfa
 * 
 * @author Liam Green-Hughes <liam.greenhughes@open.ac.uk>
 * 
 */
class Course extends CPFABase {
  public $course_code;
  public $short_title;
  public $full_title;
  public $activelink;
  public $course_type;
  public $OUCourseLevel;
  public $classification;
  public $parent_course_code;
  public $last_modified_utc;
  public $university = null;
  public $url;
  public $is_openlearn = false;
   
  // classification types
  /** Constant representing an OU module */
  const CLASS_OU_CORRES = 1;
  /** Constant representing an OpenLearn unit */
  const CLASS_OPENLEARN = 2;
  /** Constant representing an OU qualification */
  const CLASS_OU_QUAL   = 3;
  
  /**
   * Constructor
   * @param string $course_code Module code (e.g. T171)
   * @param string $university This was added in case app was changed to work for other universities. Currently it can be ignored.
   */
  function __construct($course_code, $university='open.ac.uk') {
 	  parent::__construct();
    $this->course_code = $course_code;
    $this->university = $university;
    $this->last_modified_utc = 0;
  }
   
  /**
	 * Gets information URL for a course
	 * @return string URL of clickable link to course information
	 */
  public function getCourseInfoURL() {
    $url = $this->url;
    if (empty($url)) {
      $url = str_replace('%course_code%', 
	             $this->course_code, 
	            ($this->activelink ? COURSE_INFO_URL : COURSE_OLD_INFO_URL));
	  }
	  else {
	    $url = $url."?LKCAMPAIGN=FBA01"; 
	  } 
	            
	  if ( $this->activelink ) {
	    // work out the level part of the info url
	    // undergraduate is default
	    $level = "undergraduate";
	      
	    // postgraduate
	    if ($this->OUCourseLevel == 'M') {
	      $level = "postgraduate";
	    }
	          
	    if ( $this->classification == Course::CLASS_OU_CORRES ) {
	      // cpd courses start with a G
	      if ( substr($this->course_code, 0, 1) == 'G') {
	        $level = "professional-skills"; 
	      }          
	      if (empty($this->url)) {
	        $href = str_replace('%course_code%', $this->course_code, COURSE_INFO_URL);
	        $href = str_replace('%level%', $level, $href);
        }
        else {
          $href = $this->url."?LKCAMPAIGN=FBA01";
        }
	      $retval = array('title' => 'Course Details',
                    'href' => $href,
                    'target' => '_blank',
                    'description' => 'View information on The Open University website about this course.');
      }
      else if ( $this->classification == Course::CLASS_OPENLEARN ) {
	      if (empty($this->url)) {
	        $href = sprintf(OPENLEARN_URL, $this->course_code);
        }
        else {
          $href = $this->url."?LKCAMPAIGN=FBA01";
        }  
        $retval = array('title' => 'View on OpenLearn', 
                 'href' => $href,
                 'target' => '_blank',
                 'description' => 'View or enrol on this course on OpenLearn');
      }
      else if ( $this->classification == Course::CLASS_OU_QUAL ) {
	      if (empty($this->url)) {
	        $href = str_replace('%course_code%', $this->course_code, QUAL_INFO_URL);
	        $href = str_replace('%level%', $level, $href);
        }
        else {
          $href = $this->url."?LKCAMPAIGN=FBA01";
        }  
	      $retval = array('title' => 'Qualification Details', 
          'href' => $href,
          'target' => '_blank',
          'description' => 'View information on The Open University website about this qualification.');
        }		
	    }
	    else {
	      if (empty($this->url)) {
	        $href = str_replace('%course_code%', $this->course_code, COURSE_OLD_INFO_URL);
        }
        else {
          $href = $this->url."?LKCAMPAIGN=FBA01";
        }     
	      $retval = array('title' => 'Course Details', 
                    'href' => $href,
                    'target' => '_self',
                    'description' => 'Sorry, no course details are available as this course has ended.');			
      }
	    return $retval;
   }
   
   /**
    * Returns a list of available units on openlearn for a course
    * @param string $course_code Course code to look up resources for
    * @return array OpenLearn resources
    */
   public function getOpenLearnResources() {
     $retval = array();
     $sql = sprintf("SELECT course_code, short_title, full_title, course_type, OUCourseLevel, ".
             "activelink, parent_course_code, classification, url, last_modified_utc ".
             "FROM course ".
             "WHERE `course_code` LIKE '%s' ".
             "  AND classification = %s ".
             "ORDER BY course_code ",
             $this->course_code.'%',
             Course::CLASS_OPENLEARN);
     $params = array();
    	
     $results = $this->doSQL($sql, $params);
     foreach( $results as $row) {
       $course = new Course($row['course_code']);
       $course->short_title = htmlentities($row['short_title']);
       $course->full_title = htmlentities($row['full_title']);
       $course->activelink = $row['activelink'];
       $course->course_type = $row['course_type'];
       $course->OUCourseLevel = $row['OUCourseLevel'];
       $course->parent_course_code = $row['parent_course_code'];
       $course->classification = $row['classification'];
       $course->url = $row['url']; 
       $course->last_modified_utc = $row['last_modified_utc']; 
       $retval[] = $course;	
     }
     return $retval;
   }
   
   /**
    * Returns all comment ids for a course code
    * @param string $course_code Course code to look up
    * @return array of comment Ids
    */
   public function getCommentIds() {
     $sql = "SELECT comment_id ".
            "FROM course_comment ".
            "WHERE course_code = ? ".
            "ORDER BY last_modified_utc DESC ";
     $params = array( $this->course_code );
     $results = array_keys($this->doSQL( $sql, $params, 'comment_id' ));
     return $results;    	
   }
   
   /** 
    * Adds a comments to a course comment page
    * @param int $internal_uid Id of user making comment
    * @param string $rawcomment comment as entered by user - will be filtered
    * @return bool true if comment added successfully
    */
    public function addComment( $internal_uid, $rawcomment ) {
      $retval = false;
    
      // add comment to table
      $comment = htmlentities( $rawcomment );
      if ( trim($comment) != "") {
        $comment = substr($comment, 0, COMMENT_MAX_LENGTH);
        // if a bad language filter is required it would go here
        $sql = "INSERT INTO course_comment (course_code, internal_uid, comment, last_modified_utc) ".
               "VALUES(?,?,?, UTC_TIMESTAMP())";
        $params = array( $this->course_code, $internal_uid, $comment );
        if ( $this->doSQL( $sql, $params ) ) {
           $retval = true;          
        }   
      }
      return $retval;	
    }
    
   /**
    * Retrieves comment information
    * @param array comment_ids Comment Ids to get
    * @return array Course comment information
    */
   public function getComments( $comment_ids ) {
     	$retval = array();
    	if ( sizeof( $comment_ids) == 0 ) {
    	  return $retval;	
    	}
      	$sql = sprintf( "SELECT cc.comment_id, cc.course_code, cc.internal_uid, ".
      	                "u.facebook_uid, cc.comment, cc.last_modified_utc ".
      	                "FROM course_comment cc ".
      	                "JOIN user u ON cc.internal_uid = u.internal_uid ".
      	                "WHERE cc.comment_id IN (%s) ".
      	                "ORDER BY cc.last_modified_utc DESC ",
      	                implode(',',$comment_ids));
      	                
      	$results = $this->doSQL( $sql, null, 'comment_id' );
      	foreach ($results as $row) {
      	  $result = array();
      	  $result['course_code'] = $row['course_code'];
      	  $result['internal_uid'] = $row['internal_uid'];
      	  $result['facebook_uid'] = $row['facebook_uid'];
      	  $result['comment'] = $row['comment'];
      	  $result['last_modified_utc'] = $row['last_modified_utc'];	
      	  $retval[$row['comment_id']] = $result;	
      	}
    	return $retval;
    }
    
    /**
     * Generate FBML for a course comments wall
     * @param object $fbplatform FBPlatform object
     * @param string $course_code Course code to get wall for
     * @return string FBML for course wall
     */
    public function renderCourseComments($fbplatform) {
       $retval = "";
       date_default_timezone_set ( 'UTC' );
       $appuser = $fbplatform->getFBUser();
       $user_timezone = $appuser->getInfo('timezone');
       $comment_ids = $this->getCommentIds();
       $comments = $this->getComments( $comment_ids );
       if ( sizeof($comments) == 0 ) {
         $retval .= $fbplatform->getTemplate('commentsno');	
       }
       else {
         foreach ($comments as $comment) {
         	 $comment_user = $fbplatform->facebook->api('/'.$comment['facebook_uid']."?fields=name,link,picture");
         	 // If user cannot see that FB user then render in some default info
         	 // TODO make this controllable by priv settings?
         	 if (!isset($comment_user['name'])) {
         	 	 $comment_user['name'] = "A Course Profiles user";
         	 }
        	 $comment_time = strtotime($comment['last_modified_utc']);
           	 // convert from UTC to user's timezone
           	 $comment_time = mktime ( date('G',$comment_time) + $user_timezone['0']['timezone'],
           	                          date('i',$comment_time),
           	                          date('s',$comment_time),
           	                          date('n',$comment_time),
           	                          date('j',$comment_time),
           	                          date('Y',$comment_time) );
             $vars = array( 'course_code' => $comment['course_code'],
      	                    'internal_uid' => $comment['internal_uid'],
      	                    'facebook_uid' => $comment['facebook_uid'],
      	                    'commenter_name' => $comment_user['name'],
      	                    'commenter_link' => $comment_user['link'],
      	                    'commenter_picture' => $comment_user['picture'],
      	                    'comment' => str_replace("\n", "<br />", $comment['comment']),
      	                    'comment_time' => strftime('%c', $comment_time));	
      	     $retval .= $fbplatform->getTemplate('commentitem', $vars);              
      	 }
       } 
       
       return $retval;
    }
    
    /** 
     * Load up course from database 
     */
    public function load() {
      $retval = array();
      $sql = "SELECT course_code, short_title, full_title, course_type, OUCourseLevel, ".
             "activelink, parent_course_code, classification, url, last_modified_utc ".
             "FROM course ".
             "WHERE course_code = ?";
      $results = $this->doSQL( $sql, array($this->course_code), 'course_code');
      foreach ($results as $row) {
      	 $this->course_code = htmlentities($row['course_code']);
         $this->short_title = htmlentities($row['short_title']);
         $this->full_title = htmlentities($row['full_title']);
         $this->activelink = $row['activelink'];
         $this->course_type =$row['course_type'];
         $this->OUCourseLevel = $row['OUCourseLevel'];
         $this->parent_course_code =$row['parent_course_code'];
         $this->classification = $row['classification'];
         $this->url = $row['url'];
         $this->last_modified_utc = $row['last_modified_utc'];
      }
    	
    }
    
    /**
     * Returns whether this item is a qualification rather than a course
     * @return boolean True if qualification, otherwise false
     */
    public function isQualification() {
      return ( $this->classification == Course::CLASS_OU_QUAL );
    }
    
    
    /**
     * Experimental - Get a 'people who studied this course also studied'
     * @return Array array of course objects that other people studied
     */
     public function getAlsoStudied() {
       $retval = array();
       // first pass - get user ids of other people on this course
       $sql = "SELECT internal_uid FROM user_course WHERE course_code = ?";
       $params = array($this->course_code);
       $user_ids = array_keys($this->doSQL($sql, $params, 'internal_uid'));
       if ( ! empty($user_ids) ) {
         // second pass get course codes for these users
         $sql = sprintf("SELECT course_code, count(internal_uid) AS number ".
              "FROM user_course ".
              "WHERE internal_uid IN (%s) ".
              " AND  course_code != ? ".
              "GROUP BY course_code ".
              "ORDER BY number DESC ".
              "LIMIT 0,5" , implode(',', $user_ids) ); 
         $results = $this->doSQL($sql, array($this->course_code));
       
         foreach ($results as $row) {
           $course = new Course($row['course_code']);
           $course->load();
           $retval[] = $course;	
         }
       }
       return $retval;
     }
 }
?>