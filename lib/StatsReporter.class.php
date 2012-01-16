<?php
require_once('include.php');

/**
 * Stats reporter class
 * Gets statistical data from the database and converts to XML for onward transmission to Google docs
 * @package cpfa
 */
class StatsReporter extends CPFABase {
 	private $xml;
 	
  /**
   * Constructor 
   */ 
  public function __construct() {
 	  parent::__construct();
    $this->xml = new SimpleXMLElement('<cpfastats></cpfastats>');
    date_default_timezone_set ( 'UTC' );
  }
   
  /** 
   * Add the contents of a results array to the XML for this object
   * @param Array $results Array to convert to rows, no keys, each entry will be a row
   * @param string $reportname name of report to add
   */   
  public function resultsAsXML($results, $reportname) {
    $report = $this->xml->addChild('report');
    $report->addAttribute('type',$reportname);
    foreach ($results as $key => $row) {
      $entry = $report->addChild('row');
      $entry->addAttribute('number', $key);
      foreach($row as $name => $value) {
        $entry->addChild($name, $value);
      }
    }
  }
   
  /**
   * Top Ten Courses
   */
  public function rptTopTenCourses() {
    $sql = "SELECT c.course_code, c.short_title, count( * ) AS declared ".
           "FROM `user_course` uc ".
           "JOIN `course` c ON c.course_code = uc.course_code ".
           "GROUP BY c.course_code, c.short_title ".
           "ORDER BY declared DESC ".
           "LIMIT 0 , 10 ";
    $results = $this->doSQL($sql);
    $this->resultsAsXML($results, 'toptencourses');
  }
   
  /**
   * User registrations by day
   */
  public function rptUserRegByDay() {
    // extra care is taken here to always work in UTC 
   
    // make template array
    // get start date for report
    $sql = "SELECT min(date(facebook_add_utc)) as start_date ".
           "FROM user ".
           "WHERE facebook_add_utc != 0";
    $result = $this->doSQL($sql,null);
    $startdate = strtotime($result[0]['start_date']);
    $change = array();
     
    // load in add and remove numbers
    $sql = "SELECT  date(facebook_add_utc) as facebook_add_date, ".
           "date(facebook_remove_utc) as facebook_remove_date ".
           "FROM user ".            
           "WHERE facebook_add_utc != 0";
    $results = $this->doSQL($sql);
    foreach($results as $row) {   
      if ( substr($row['facebook_add_date'],0,4) != '0000' ) {
        $change[$row['facebook_add_date']]['adds']++;
      }
      if ( substr($row['facebook_remove_date'],0,4) != '0000' ) { 
        $change[$row['facebook_remove_date']]['removes']++;       
      }
    }
    // fill in gaps and work out total users
    $numdays = ceil((time() - $startdate)/86400);
    $totalusers = 0;
    for($i=0;$i<$numdays;$i++) {
      $newdate = mktime(0,0,0,date('n',$startdate),date('j',$startdate)+$i,date('Y',$startdate));
      $newkey = date('Y-m-d', $newdate );
      // check date exists
      if ( ! isset($change[$newkey]) ){
        $change[$newkey] = array();
      }
      // users added
      if ( isset($change[$newkey]['adds']) ) {
        $totalusers += $change[$newkey]['adds'];
      }       
      else {
        $change[$newkey]['adds'] = 0;
      }
      // users removed
      if ( isset($change[$newkey]['removes']) ) {
        $totalusers -= $change[$newkey]['removes'];
      }       
      else {
        $change[$newkey]['removes'] = 0;
      }    
      // totalusers
      $change[$newkey]['totalusers'] = $totalusers;
    }   
    ksort($change);
    // convert to format for xml
    $results = array();
    foreach($change as $key => $value)
    {
      $line = array();
      $line['date_utc'] = $key;
      $line['adds'] = $value['adds'];
      $line['removes'] = $value['removes'];
      $line['totalusers'] = $value['totalusers'];
      $results[] = $line;
    }
     
    // add to XML
    $this->resultsAsXML($results, 'userregbyday');  
  }
   
  /**
    * Users to courses
    */
  public function userCourse() {
    $status_text = array( 
       COURSE_PAST => 'Completed',
       COURSE_PRESENT => 'Current',
       COURSE_FUTURE => 'Future',
       COURSE_RECOMMEND => 'Recommended');
    $sql = sprintf("SELECT internal_uid, course_code, status, mopi ".
   	         "FROM user_course ".
   	         "WHERE status IN (%s) ".
   	         "ORDER BY internal_uid",
   	         implode(',', array_keys($status_text)));
    $results = $this->doSQL($sql);
    $masked_id = 0;
    $last_internal_uid = 0;
    $anon_results = array();
    foreach ($results as $row) {
    	$line = array();
      if ( $row['internal_uid'] != $last_internal_uid ) {
        $masked_id++;
        $last_internal_uid = $row['internal_uid'];
      }
      $line['user_id'] = $masked_id;
      $line['course_code'] = strtoupper($row['course_code']);
      $line['status'] = $status_text[$row['status']];
      $line['mopi'] = $row['mopi'];
      $anon_results[] = $line;
    }
    $this->resultsAsXML($anon_results, 'usercourse');
  }
   
  /**
   * Course report
   * Not implemented.
   */
  public function courseReport() {} 
   
  /**
   * Return the xml object as a string
   * @return string xml
   */
  public function asXML() {
    return $this->xml->asXML();	
  }
 }
 
 ?>