<?php
require_once('Course.class.php');

// Define status codes
/** Course is completed */
define("COURSE_PAST", 1);
/** Course is currently in progress */
define("COURSE_PRESENT", 2);
/** User is thinking of doing course */
define("COURSE_FUTURE", 3); 
/** Course is to be deleted for user */
define("COURSE_REMOVE", 4);
/** Suggested Courses */
define("COURSE_RECOMMEND", 5); 

/**
 * Represents a list of courses for a user
 * 
 * @package cpfa
 * 
 * @author Liam Green-Hughes <liam.greenhughes@open.ac.uk>
 */
class CourseList extends CPFABase {
	// array of course objects
	private $courses;
	// user id this course list relates to
	private $internal_uid;
	
	/**
	 * Constructs an instance of this class
	 * Initialises internal variables 
	 * @param int $internal_uid Internal User id of person this course list relates to
	 */
	public function __construct($internal_uid) {
		parent::__construct();
		$this->internal_uid = $internal_uid;
		$this->courses = array();		
	}
	
	/**
	 * Reads list of courses for rhe user from database into object
	 */
	public function load() {
	  $sql = "SELECT uc.record_id, c.course_code, uc.mopi, uc.status, c.course_code, ".
	         "c.short_title, c.full_title, c.activelink, c.course_type, c.parent_course_code, ".
	         "c.classification, c.last_modified_utc AS course_last_modified_utc, uc.last_modified_utc ".
           "FROM user_course uc ".
           "JOIN course c ON uc.course_code = c.course_code ".
           "WHERE uc.internal_uid = ? ".
           "ORDER BY uc.status, uc.mopi DESC, c.short_title ";
   $params = array($this->internal_uid);
    
   $results =  $this->doSQL($sql, $params, 'record_id');
   foreach ($results as $row) {
     $line = array();
     // load course
     $course = new Course($row['course_code']);
     $course->short_title = htmlentities($row['short_title']);
     $course->full_title = htmlentities($row['full_title']);
     $course->activelink = $row['activelink'];
     $course->course_type = $row['course_type'];
     $course->parent_course_code = $row['parent_course_code'];
     $course->classification = $row['classification'];
     $course->last_modified_utc = $row['course_last_modified_utc'];
     $line['course'] = $course;
     // load user course details
     $line['mopi'] = $row['mopi'];
     $line['status'] = $row['status'];
     $line['last_modified_utc'] = $row['last_modified_utc'];
     // load into array
     $this->courses[$row['record_id']] =	$line;
   }            
 }
	
	///**
	// * @deprecated Originally intended to return true if course listingin 
	// * limit is reached but this functionaity was never implemented.
	// */
	//public function isFull() {}
	
	/**
	 * Returns list of courses as array
	 * key is record_id and values are fields for course record
	 * @return Array Course records
	 */
	public function toArray() {
	  return $this->courses;
	}
	
	/**
	 * Gets a specific record id
	 * @param int $record_id Entry in course for user to return
	 * @return object Course object for that user course entry
	 */
	public function getEntry($record_id) {
	  return $this->courses[$record_id];
	}
	
	/**
	 * Returns an entry for a course
	 * @param string $course_code Course code to return entries for e.g. A103
	 * @param string $mopi Only return record ids for a specified MOPI. Set to false to ignore.
	 * @return array Array of record_ids for user course entries
	 */
	public function getEntryIdsForCourse($course_code,$mopi=false) {
      $retval = array();
      $sql = "SELECT record_id FROM user_course WHERE internal_uid = ? AND course_code = ? ";
      $params = array($this->internal_uid, $course_code);
      if ($mopi) {
      	$sql .= "AND mopi = ?";
      	$params[] = $mopi;
      }
      
      $results = $this->doSQL($sql, $params);
      foreach ($results as $row) {
        $retval[] = $row['record_id'];	
      }
      return $retval;	  	
	}
		
	/**
    * Changes a status code on a course, e.g. from present to past 
    * @param int $record_id record number for user to course
    * @param int $newStatus status code to update to
    * @return bool true if SQL command successful 
    */
  public function changeCourseStatus($record_id, $newStatus) {
    $sql = "UPDATE user_course SET status = ? ".
         "WHERE internal_uid = ? AND record_id = ? ";
    $params = array($newStatus, $this->internal_uid, $record_id);
    $retval = $this->doSQL($sql, $params);
    $this->courses[$record_id]['status'] = $newStatus;
    return $retval;
  }
    
  /**
   * Adds a user to a course
   * @param string $course_code Course code to add
   * @param string $mopi Mopi to assign (if provided)
   * @param int $status Status code (current, future etc) - see constants
   * @return int record id of new course record
   */
  public function addCourse($course_code, $mopi, $status) {
    $retval = false;
    // add course
    $sql = "INSERT INTO user_course (internal_uid, course_code, mopi, status, last_modified_utc) VALUES(?,?,?,?,UTC_TIMESTAMP())";
    $params = array($this->internal_uid, $course_code, $mopi, $status);
    $retval = $this->doSQL($sql, $params);
    // reload course list to get details
    $this->load();
    return $retval;
  }
    
  /**
   * Add entry to course recommendations table if recommended by a friend
   * @param string $course_code Code of course being recommended
   * @param int $from_internal_uid Internal uid of user making the recommendation
   */
  function addWhoRecommends( $course_code, $from_internal_uid ) {
  	$sql = "INSERT INTO course_recommend( from_internal_uid, to_internal_uid, course_code, last_modified_utc) ".
           "VALUES( ?, ?, ?, UTC_TIMESTAMP())";
    $params = array( $from_internal_uid, $this->internal_uid, $course_code);
    $this->doSQL($sql, $params);     	
  }
    
  /**
   * Removes an association between a user and a course
   * @param int $record_id User course entry id to drop
   * @return bool True on success, false on failure
   */
  public function dropCourse($record_id) {
    $retval = false;
    $sql = "DELETE FROM user_course WHERE internal_uid = ? AND record_id = ?";
    $params = array($this->internal_uid, $record_id);
    $retval = $this->doSQL($sql, $params);
    unset($this->courses[$record_id]);
    return $retval;
  }
      
  /**
    * Tests if user has a course code in their course list
    * @param string $course_code Course code id to test
    * @param string $mopi Specific prentation to look for. Set to false to ignore. Default false.
    * @return bool True if course code present
    */
  public function hasCourse($course_code, $mopi=false) {
    $retval = false;
    $sql = "SELECT * FROM user_course WHERE internal_uid = ? AND course_code = ? ";
    $params = array($this->internal_uid, $course_code);
    if ($mopi) {
    	$sql .= "AND mopi = ?";
    	$params[] = $mopi;
    }
    
    $results = $this->doSQL($sql, $params);
    $retval = sizeof( $results ) > 0;
    return $retval;
  }
    
  /**
   * Handles the mock-AJAX requests to add courses from the main application page
   * @param FBPlatform $fbplatform Instance of FBPlatform class to handle communication with Facebook API
   * @param FBUser $appuser FBUser object instance representing user of app
   * @param string $course_code Course code to add
   * @param string $mopi Mopi to assign (if provided)
   * @param int $mode Mode constant (past, present, future) to add record for
   * @return string FBML fragment containing success/fail message and new course listing FBML
   */
  function processCourseRequest($fbplatform, $appuser, $course_code, $mopi, $mode) {
    $course_code = strtoupper(trim($course_code));
    $mopi = strtoupper(trim($mopi));
    $mopi_ok = $mopi == "" || preg_match("/[1-2][0-9]{3}[A-L]?/", $mopi ); // check mopi is recognised format
 
   // check for an openlean course
   $matches = array();
   //if ( preg_match("/([A-Za-z]+[0-9]{2,})_[0-9]+/", $course_code, $matches) ) {
    	  //$course_code = $matches[1];
       //$is_openlearn = TRUE;	
   //} 
       
   // get course
   $course_details = new Course($course_code);
   $course_details->load();
   // build fbml based on action taken
   $retval = "";
   if ( $course_details->last_modified_utc == 0 ) {
   	 $retval .= $fbplatform->getTemplate('coursenotfound');
   }
   else if (!$mopi_ok) {
   	 $retval .= $fbplatform->getTemplate('invalidmopi');
   }
   else if ( $this->hasCourse($course_code, $mopi) ) {
  	 $tabnames = array(
  	         COURSE_PAST => "Completed", 
  	         COURSE_PRESENT => "Current", 
  	         COURSE_FUTURE => "Future",
  	         COURSE_RECOMMEND => "Recommended");
  	 $entry_ids = $this->getEntryIdsForCourse($course_code,$mopi=false);
  	 $present_stati = array();
  	 foreach ($entry_ids as $entry_id) {
  	  	$present_stati[] = '<b>'.$tabnames[$this->courses[$entry_id]['status']].'</b>';	
  	 }
  	 $tab_desc = CPFABase::conjoin( array_unique($present_stati), 'and', 'tab', 'tabs' );
  	 $retval .= sprintf("<div class='error'><div class='message'>You already have %s %s!</div>".
         "The details you gave have already been entered on to the %s  You can ". 
         "move an item between these tabs by clicking on the links on the right of ".
         "the item name.</div>",
         $course_details->course_code,
         $course_details->short_title,	
         $tab_desc); 
   }
   else if ($new_record_id = $this->addCourse($course_code, $mopi, $mode)){
     $retval .= "<div class='success'>";
     $retval .= "<div class='message'>Course Added!</div>";
  	 $retval .= "<table>";
  	 $retval .= sprintf("<tr><td>%s</td><td>%s</td></tr>", $course_code, $course_details->full_title);  
  	 $retval .= "</table>";
  	 $retval .= "</div>";
  	      
  	 // publish story to news feed  
  	 $retval .= $fbplatform->generateCourseAddNewsItem($appuser, $course_details, $mode);
   }
   else {
  	 $retval .= "<div class='error'>".
           "<div class='message'>An error occurred!</div>".
           "Sorry we have had a problem and could not add the details you gave. Please try again later.</div>";
   }
   return $retval;
 }
}
?>
