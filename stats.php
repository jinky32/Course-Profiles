<?php
/**
 * Statistics page
 * Allows live export of anonymised stats data to google docs.
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
/**
 * App includes
 */
 require_once('include.php');
 require_once('lib/StatsReporter.class.php');
 
 $report = $_GET['report'];
 $givenkey = $_GET['k'];
 if ( ! in_array($report, array('toptencourses','userregbyday','usercourse','coursereport','all')) 
      || $givenkey != STATS_KEY ) {
    exit();
 }
 
 $stats = new StatsReporter();
 if ( $report == 'all' || $report == 'toptencourses' )
    $stats->rptTopTenCourses();
 if ( $report == 'all' || $report == 'userregbyday' )
    $stats->rptUserRegByDay();
 if ( $report == 'all' || $report == 'usercourse' )
    $stats->userCourse();
 if ( $report == 'all' || $report == 'coursereport' )
    $stats->courseReport();
    
 header('Content-Type: text/xml');
 echo $stats->asXML();
 

?>