<?php
/**
 * User Class
 * Handles function related to a user
 * @package cpfa
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */ 
 class User extends CPFABase {
   /** Internal reference ID for user, as used in database */
   protected $internal_uid;
   /** Array of preferences for user */
   protected $preferences;
   /** A short line of text about the user by the user */
   public $about;
   /** Time about text was last updated - uses UTC timezone */
   public $about_last_updated_utc;
   /** If the user also has the My OU Story app installed this is the user's internal ID on that app */
   public $myoustory_uid;
 	
   /**
 	 * Constructor
 	 */
   function __construct() {
     parent::__construct();
     $this->internal_uid = 0;
     $this->preferences = array();
     $this->about = 'I am an OU distance learner!';
     $this->about_last_updated_utc = 0;  
     $this->myoustory_uid = 0;   
   }
   
   /**
    * Gets Internal reference ID for user, as used in database
    * @return int Internal ID to app
    */
   public function getInternalUid() {
     return $this->internal_uid;   	
   }
  
   /**
    * Reads preferences from user table.
    *
    * Currently this returns the OU region and the sharing privacy option.
    *
    * @return array Array of preferences with entry for each column in user table
    */
   function getPreferences() {
	 $sql = "SELECT ou_region, fb_priv_studybuddy ".
	        "FROM user ".
	        "WHERE internal_uid = ?";
	 $params = array($this->getInternalUid());
	 $results = $this->doSQL($sql, $params);
	 return $results[0];	
   }
  
  /**
   * Save user preferences
   * @param $preferences array Preferences to change key/value pairs
   * @return True if successful
   */
  function savePreferences($preferences) {
  	$sql = "UPDATE user SET ";
  	$sql .= implode("= ?, ", array_keys($preferences));
	$sql .= " = ?, last_modified_utc = UTC_TIMESTAMP() WHERE internal_uid = ?";
	$params = array_values($preferences);
	$params[] = $this->getInternalUid();
 	return $this->doSQL($sql, $params);
  }
  
  /**
   * Returns a courselist object containing this user's courses and quals
   * @return CourseList List of courses for this user
   */
  function getCourseList() {
    $courselist = new CourseList($this->internal_uid);
    $courselist->load();	
    return $courselist;
  }
  
  /**
   * Update the about tag line 
   * @param string $about New text for the about tag line
   */
  public function updateAbout($about) {
    $sql = "UPDATE user SET about = ?, about_last_updated_utc = UTC_TIMESTAMP() WHERE internal_uid = ?";
    $params = array($about, $this->internal_uid);
    $this->doSQL($sql, $params);
    $this->about = $about;	
  }
}
?>