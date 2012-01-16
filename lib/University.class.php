<?php
require_once('Course.class.php');
 
/**
 * Class representing a University
 * i.e. a collection of available courses and other options at uni level
 *
 * This class was started in case the Course Profiles app rolled out to
 * other universities. In the end it was not, but if it ever is the 
 * abstraction for university concepts can occur here.
 * @package cpfa
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
class University extends CPFABase {
  /**
   * Constructor
   * @param $university_name string Currently only open.ac.uk will work
   */
  function __construct($university_name) {
 	  parent::__construct();
  }	
   
  /**
   * Returns array of OU regions
   * @return array key = region code value = region name
   */
  function getRegions() {
	  $retval = array('R00' => 'Not set',
	  	'R01' => 'Region 1: London',
	  	'R02' => 'Region 2: South',
	  	'R03' => 'Region 3: South West',
	  	'R04' => 'Region 4: West Midlands',
	  	'R05' => 'Region 5: East Midlands',
	  	'R06' => 'Region 6: East',
	  	'R07' => 'Region 7: Yorkshire',
	  	'R08' => 'Region 8: North West',
	  	'R09' => 'Region 9: North',
	  	'R10' => 'Region 10: Wales ',
	  	'R11' => 'Region 11: Scotland',
	  	'R12' => 'Region 12: Ireland',
	  	'R13' => 'Region 13: South East');
    return $retval;	
  }
}
?>