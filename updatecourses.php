<?php
/**
 * Update course/module and OpenLearn information from Open Univerity Linked Data store
 *
 * Last Revised: November 2011
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */

/** Requires includes for app */
require_once 'include.php';
 
/**
 * Perform an HTTP request
 * @param string $url Web address of resource rquired
 * @return string Text of response
 */
function request($url){
  // is curl installed?
  if (!function_exists('curl_init')){
    die('CURL is not installed!');
  }
  // get curl handle
  $ch= curl_init();
  // set request url
  curl_setopt($ch, CURLOPT_URL, $url);

  // return response, don't print/echo
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  curl_close($ch);

  return $response;
}
 
/**
 * Passes the SPARQL provided to the OU endpoint and parses the results  
 * @param string $sparql Text of SPARQL query to execute
 * @return array Result set
 */
function do_LD_Query($sparql) {
  $requestURL = 'http://data.open.ac.uk/query?query='.urlencode($sparql);
  $response = request($requestURL);
  // container for our data
  $data = array();

  // initialise SimpleXML object and load it with data
  $xml = simplexml_load_string($response);
  if ($xml === FALSE) {
    return $response;
  }
  // get the <results> element
  $results = $xml->results;
  // loop through <result> elements and extract values
  if (isset($results->result)) {
    foreach($results->result as $result) {
      $line = array();
      foreach ($result->binding as $binding) {
        if (isset($binding->uri)) {
          $line[(string) $binding["name"]] = (string) $binding->uri;
        }
        else {
          // could pick up xsd data type for right cast
          $line[(string) $binding["name"]] = (string) $binding->literal;
        }
      }
      $data[] = $line;
    }
  }
  return $data;
}

/**
 * Import course list from Linked Data store into import table
 * @param FBPlatform $fbplatform Object to handle communication with Facebook API and local db
 */
function get_ld_import($fbplatform) {
  $sparql = <<<STOP
PREFIX aiiso: <http://purl.org/vocab/aiiso/schema#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX saou: <http://data.open.ac.uk/saou/ontology#>
PREFIX mlo: <http://purl.org/net/mlo/>

SELECT DISTINCT ?code ?name ?type ?OUCourseLevel ?url
FROM <http://data.open.ac.uk/context/course>
WHERE {
  ?course aiiso:code ?code .
  ?course aiiso:name ?name .
  ?course rdf:type ?type .
  ?course saou:OUCourseLevel ?OUCourseLevel .
  ?course mlo:url ?url
}    
ORDER BY ?code     
STOP;
  $results = do_LD_Query($sparql);
  // remove duplicates caused by having more than one type
  $output = array();
  $i=1;
  foreach ($results as $result) {
    $output[$result['code']] = $result; 
  }
     
  foreach ($output as $row) {
    $sql = "INSERT INTO course_import(course_code, short_title, full_title, course_type, OUCourseLevel, url) ".
           "VALUES (?,?,?, 'OC', ?, ?)";
    // convert special chars to html entities, data is in UTF8, so state this to avaoid default
    $htmlname = htmlentities($row['name'], ENT_COMPAT, "UTF-8");
    $params = array($row['code'], $htmlname, $htmlname, $row['OUCourseLevel'], $row['url']);
    $fbplatform->doSQL($sql, $params); 
  } 
    
}

/**
 * Work out new courses to be added and show as a table for the user 
 * @param FBPlatform $fbplatform Object to handle communication with Facebook API and local db
 */
function get_proposed_additions_table($fbplatform) {
  $retval = "<table><tr><th>Change Number</th><th>Course Code</th><th>Title</th><th>OUCourseLevel</th></tr>";
  $sql = "SELECT ci.course_code, ci.short_title, ci.full_title, ci.OUCourseLevel, ci.course_type, 1,UTC_TIMESTAMP() ".
         "FROM course_import ci ".
         "LEFT JOIN course c ON ci.course_code = c.course_code ".
         "WHERE c.course_code IS NULL ".
         "ORDER BY ci.course_code";
  $results = $fbplatform->doSQL( $sql, null, 'course_code' );
  $counter=1;
  if (is_array($results)) {
    foreach ($results as $row) {
      $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>", $counter++, $row['course_code'], $row['short_title'], $row['OUCourseLevel']);
    }
  }
  $retval .= "</table>";
  if (sizeof($results) == 0) {
    $retval .= "<div class='explanation'><div class='message'>There are no proposed additions. Click <i>Skip</i>.</div>";
    $retval .= "</div>";	
  }
  return $retval;
}

/**
 * Work out courses with changed details and show as a table for the user  
 * @param FBPlatform $fbplatform Object to handle communication with Facebook API and local db
 */
function get_proposed_changes_table($fbplatform) {
  $retval = "<table><tr><th>Change Number</th><th>Course Code</th><th>From</th><th>To</th><tr>";
  $sql = "SELECT c.course_code, c.short_title AS from_title, ci.short_title AS to_title, c.OUCourseLevel AS from_OUCourseLevel, ci.OUCourseLevel AS to_OUCourseLevel, c.url AS from_url, ci.url AS to_url ".
         "FROM `course_import` ci ".
         "JOIN course c ON ci.course_code = c.course_code ".
         "WHERE c.short_title != ci.short_title OR c.OUCourseLevel != ci.OUCourseLevel OR c.url != ci.url ".
         "ORDER BY c.course_code";
  $counter=1;
  $results = $fbplatform->doSQL( $sql );

  if (is_array($results)) {
    foreach ($results as $row) {
      // show title change if any
      if ($row['from_title'] != $row['to_title']) {
        $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>", $counter++, $row['course_code'], $row['from_title'], $row['to_title']);
      }
      // show OUCourseLevel change if any
      if ($row['from_OUCourseLevel'] != $row['to_OUCourseLevel']) {
        $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>", $counter++, $row['course_code'], $row['from_OUCourseLevel'], $row['to_OUCourseLevel']);
      }
      // show URL change if any
      if ($row['from_url'] != $row['to_url']) {
        $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>", $counter++, $row['course_code'], $row['from_url'], $row['to_url']);
      }
    }
  }  
  $retval .= "</table>";
  if (sizeof($results) == 0) {
    $retval .= "<div class='explanation'><div class='message'>There are no proposed changes. Click <i>Skip</i>.</div>";
    $retval .= "</div>";	
  }
  return $retval;
}


/**
 * Get list of OpenLearn modules from Linked Data store 
 * @param FBPlatform $fbplatform Object to handle communication with Facebook API and local db
 */
function get_ld_openlearn($fbplatform) {
  $sparql = <<<STOP
PREFIX rdfns: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX openlearn: <http://data.open.ac.uk/openlearn/ontology/>
PREFIX dc: <http://purl.org/dc/terms/>
PREFIX aiiso: <http://purl.org/vocab/aiiso/schema#>

SELECT ?openlearn ?title ?relatesToCourse ?course_code ?url 
WHERE {
   ?openlearn rdfns:type openlearn:OpenLearnUnit .
   ?openlearn dc:title ?title .
   ?openlearn openlearn:relatesToCourse ?relatesToCourse .
   ?openlearn <http://dbpedia.org/property/url> ?url .
   ?relatesToCourse aiiso:code ?course_code 
}
ORDER BY ?course_code     
STOP;

  $results = do_LD_Query($sparql);
  $output = array();
  $i=1;
  // Fiddle OpenLearn code
  foreach ($results as $result) {
    $result['openlearn'] = strtoupper(str_replace("http://data.open.ac.uk/openlearn/", "", $result['openlearn']));
    $output[$result['openlearn']] = $result;
  }
  // Insert rows into import table   
  foreach ($output as $row) {
    $sql = "INSERT INTO openlearn_courses_import(unit_code, title, parent_course_code, url) ".
           "VALUES (?,?,?,?)";
    // convert special chars to html entities, data is in UTF8, so state this to avaoid default
    $htmlname = htmlentities($row['title'], ENT_COMPAT, "UTF-8");
    $params = array($row['openlearn'], $htmlname, $row['course_code'], $row['url']);
    $fbplatform->doSQL($sql, $params); 
  } 
}

/**
 * Work out new OpenLearn courses and return as table for user
 * @param FBPlatform $fbplatform Object to handle communication with Facebook API and local db
 */
function get_proposed_openlearn_additions_table($fbplatform) {
  $retval = "<table><tr><th>Change Number</th><th>OpenLearn Course Code</th><th>Title</th><th>Parent Course Code</th></tr>";
  $sql = "SELECT oci.unit_code, oci.title, oci.parent_course_code ".
         "FROM openlearn_courses_import oci ".
         "LEFT JOIN course c ON oci.unit_code = c.course_code ".
         "WHERE c.course_code IS NULL";
  $results = $fbplatform->doSQL( $sql, null, 'unit_code' );
  $counter=1;
  if (is_array($results)) {
    foreach ($results as $row) {
      $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>", $counter++, $row['unit_code'], $row['title'], $row['parent_course_code']);
    }
  }
  $retval .= "</table>";
  if (sizeof($results) == 0) {
    $retval .= "<div class='explanation'><div class='message'>There are no proposed OpenLearn additions. Click <i>Skip</i>.</div>";
    $retval .= "</div>";	
  }
  return $retval;
}
   
/**
 * Work out changes to OpenLearn courses and return as table for user
 * @param FBPlatform $fbplatform Object to handle communication with Facebook API and local db
 */
function get_proposed_openlearn_changes_table($fbplatform) {           
  $retval = "<table><tr><th>Change Number</th><th>OpenLearn Course Code</th><th>From</th><th>To</th><tr>";
  $sql = "SELECT c.course_code, c.short_title AS from_title, oci.title AS to_title, c.url AS from_url, oci.url AS to_url ".
         "FROM `openlearn_courses_import` oci ".
         "JOIN course c ON oci.unit_code = c.course_code ".
         "WHERE c.short_title != oci.title OR c.url != oci.url ";
  $counter=1;
  $results = $fbplatform->doSQL( $sql );

  if (is_array($results)) {
    foreach ($results as $row) {
      // show title change if any
      if ($row['from_title'] != $row['to_title']) {
        $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>", $counter++, $row['course_code'], $row['from_title'], $row['to_title']);
      }
      if ($row['from_url'] != $row['to_url']) {
        $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>", $counter++, $row['course_code'], $row['from_url'], $row['to_url']);
      }     
    }
  }  
  $retval .= "</table>";
  if (sizeof($results) == 0) {
    $retval .= "<div class='explanation'><div class='message'>There are no proposed changes. Click <i>Skip</i>.</div>";
    $retval .= "</div>";	
  }
  return $retval;
}
 
/**
 * Import qualification list from Linked Data store  
 * @param FBPlatform $fbplatform Object to handle communication with Facebook API and local db
 */
function get_qualifications_import($fbplatform) {
  $sparql = <<<STOP
PREFIX rdf-syntax-ns: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX aiiso: <http://purl.org/vocab/aiiso/schema#>
PREFIX dc: <http://purl.org/dc/terms/>
PREFIX mlo: <http://purl.org/net/mlo/>

SELECT DISTINCT ?code ?title ?url
WHERE {
   ?qualification rdf-syntax-ns:type <http://purl.org/net/mlo/qualification> .
   ?qualification aiiso:code ?code .
   ?qualification dc:title ?title .
   ?qualification mlo:url ?url
}    
STOP;
  $results = do_LD_Query($sparql);
  // remove duplicates caused by having more than one type
  $output = array();
  $i=1;
  foreach ($results as $result) {
    $output[$result['code']] = $result; 
  }
     
  foreach ($output as $row) {
    $sql = "INSERT INTO quals_import(qual_code, activelink, title, url) ".
           "VALUES (?,1,?,?)";
    // convert special chars to html entities, data is in UTF8, so state this to avaoid default
    $htmlname = htmlentities($row['title'], ENT_COMPAT, "UTF-8");
    $params = array($row['code'], $htmlname, $row['url']);
    $fbplatform->doSQL($sql, $params); 
  } 
    
}

/**
 * Work out new courses to be added and show as a table for the user 
 * @param FBPlatform $fbplatform Object to handle communication with Facebook API and local db
 */
function get_proposed_qualifications_additions_table($fbplatform) {
  $retval = "<table><tr><th>Change Number</th><th>Code</th><th>Title</th><th>Link</th></tr>";
  $sql = "SELECT qi.qual_code, qi.title, qi.url ".
         "FROM quals_import qi ".
         "LEFT JOIN course c ON qi.qual_code = c.course_code ".
         "WHERE c.course_code IS NULL ".
         "ORDER BY qi.qual_code";
  $results = $fbplatform->doSQL( $sql, null, 'qual_code' );
  $counter=1;
  if (is_array($results)) {
    foreach ($results as $row) {
      $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td><a href='%s' target='_blank'>%s</a></td></tr>", $counter++, $row['qual_code'], $row['title'], $row['url'], $row['url']);
    }
  }
  $retval .= "</table>";
  if (sizeof($results) == 0) {
    $retval .= "<div class='explanation'><div class='message'>There are no proposed additions. Click <i>Skip</i>.</div>";
    $retval .= "</div>";	
  }
  return $retval;
}
 
/**
 * Work out courses with changed details and show as a table for the user  
 * @param FBPlatform $fbplatform Object to handle communication with Facebook API and local db
 */
function get_proposed_qualifications_changes_table($fbplatform) {
  $retval = "<table><tr><th>Change Number</th><th>Course Code</th><th>From</th><th>To</th><tr>";
  $sql = "SELECT c.course_code, c.short_title AS from_title, qi.title AS to_title, c.url AS from_url, qi.url AS to_url ".
         "FROM `quals_import` qi ".
         "JOIN course c ON qi.qual_code = c.course_code ".
         "WHERE c.short_title != qi.title OR c.url != qi.url ";
  $counter=1;
  $results = $fbplatform->doSQL( $sql );

  if (is_array($results)) {
    foreach ($results as $row) {
      // show title change if any
      if ($row['from_title'] != $row['to_title']) {
        $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>", $counter++, $row['course_code'], $row['from_title'], $row['to_title']);
      }
      // show uRL change if any
      if ($row['from_url'] != $row['to_url']) {
        $retval .= sprintf("<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>", $counter++, $row['course_code'], $row['from_url'], $row['to_url']);
      }
    }
  }  
  $retval .= "</table>";
  if (sizeof($results) == 0) {
    $retval .= "<div class='explanation'><div class='message'>There are no proposed changes. Click <i>Skip</i>.</div>";
    $retval .= "</div>";	
  }
  return $retval;
}
  
/*
 * main code
 */
$steps = array("1. Query LD course information", "2. Proposed Additions", "3. Proposed Changes", "4. Query OpenLearn information", "5. OpenLearn Additions", "6. OpenLearn Changes", "7. Query qualification information", "8. Qualification additions", "9. Qualification changes", "10. Finish");

$output = '';
$message = '';
$stepno = isset($_POST['stepno']) ? $_POST['stepno'] : 0; 

if (!is_numeric($stepno) || $stepno < 0 || $stepno > sizeof($steps)-1) {
  $stepno = 0; 
}

// construct user object
$fbplatform = new FBPlatform();

// construct user object
$appuser = $fbplatform->getFBUser();
 
// throw out if not lgh
// TODO implement a wider mechanism for this
if ($appuser->getFacebookUid() != 724450863) {
   $output .=  "<div class='error'>";
   $output .=  "<div class='message'>Not authorised!</div>";
   $output .=  "</div>";	
   echo $output;
   exit(); 
}
$toptext = "<h1>[Admin] Update course list from data.open.ac.uk</h1><br />";
$htmlsteps = array();
for ($i=0; $i<sizeof($steps); $i++) {
  if ($i == $stepno) {
    $htmlsteps[] = sprintf("<b>%s</b>", $steps[$i]);
  }
  else {
    $htmlsteps[] = sprintf("%s", $steps[$i]);
  }
} 
$toptext .= implode("&nbsp;&gt;&nbsp;", $htmlsteps);
//$toptext .= "<p>This will query the OU Linked Data store for the latest course information.</p>";
$output .=  $fbplatform->renderTop(false, $toptext);
  
// perform actions for last step and show proposed actions for this step
if ($stepno == 0) {
  $output .= "<p>Click confirm to query the OU Linked Data store for information on courses.";
  $output .= " This will update the information in the update table. Clicking skip will use ";
  $output .= "existing informaiton in the update table.</p>";
}
// Get LD info for courses
else if ($stepno == 1) { 
  if (isset($_POST['confirm'])) {
    // delete course import table
    $fbplatform->doSQL("DELETE FROM course_import");
    // Query for new courses and load into import table
    get_ld_import($fbplatform);
  }
  else {
    $output .= "<div class='explanation'><div class='message'>Linked data import skipped as requested.</div>";
    $output .= "</div>";	
  }
  
  // Show proposed additions for next step
  $output .= "<h2>Proposed Additions</h2>";
  $output .= get_proposed_additions_table($fbplatform);
}
// Additions for courses
else if ($stepno == 2) { 
  if (isset($_POST['confirm'])) {
    // do changes
    $sql ="INSERT INTO course (course_code, short_title, full_title, course_type, OUCourseLevel, classification, url, last_modified_utc) ";
    $sql .="SELECT ci.course_code, ci.short_title, ci.full_title, ci.course_type, ci.OUCourseLevel, 1, ci.url, UTC_TIMESTAMP() ";
    $sql .="FROM course_import ci ";
    $sql .="LEFT JOIN course c ON ci.course_code = c.course_code ";
    $sql .="WHERE c.course_code IS NULL ";
    $fbplatform->doSQL( $sql );
    $output .=  "<div class='success'>";
    $output .=  "<div class='message'>Proposed additions committed!</div>";
    $output .=  "</div>";	
  }
  else {
    $output .= "<div class='explanation'><div class='message'>Proposed additions skipped as requested.</div>";
    $output .= "</div>";	
  }
  
  // Show proposed changes for next step
  $output .= "<h2>Proposed Changes</h2>";  
  $output .= get_proposed_changes_table($fbplatform);
}
// Changes to courses
else if ($stepno == 3) {
  if (isset($_POST['confirm'])) {
    // do changes
    $sql ="UPDATE course c JOIN course_import ci ON ci.course_code = c.course_code ";
    $sql .="SET c.short_title = ci.short_title, ";
    $sql .="    c.full_title = ci.full_title, ";
    $sql .="    c.course_type = ci.course_type, ";
    $sql .="    c.OUCourseLevel = ci.OUCourseLevel, ";
    $sql .="    c.url = ci.url ";
    $sql .="WHERE ci.short_title != c.short_title OR ci.OUCourseLevel != c.OUCourseLevel OR c.url != ci.url ";
    $fbplatform->doSQL( $sql );    
    $output .=  "<div class='success'>";
    $output .=  "<div class='message'>Proposed changes committed!</div>";
    $output .=  "</div>";	
  }
  else {
    $output .= "<div class='explanation'><div class='message'>Proposed changes skipped as requested.</div>";
    $output .= "<div>";	
  }
  
  // Ask if user wants to import OpenLearn information
  $output .= "<h2>Query OpenLearn information</h2>"; 
  $output .= "<p>Click confirm to query for OpenLearn course information from the Linked data store.</p>";  

}
// Query for OpenLearn data
else if ($stepno == 4) {
  if (isset($_POST['confirm'])) {
    $fbplatform->doSQL("DELETE FROM openlearn_courses_import");
    get_ld_openlearn($fbplatform);
  }
  else {
    $output .= "<div class='explanation'><div class='message'>Querying Linked Data information skipped as requested.</div>";
    $output .= "</div>";	
  }
  
   // Show proposed OpenLeatn additions for next step
   $output .= "<h2>OpenLearn additions</h2>"; 
   $output .= get_proposed_openlearn_additions_table($fbplatform);
    
}
// OpenLearn additions
else if ($stepno == 5) {
  if (isset($_POST['confirm'])) {
    // do openlearn changes changes
    $sql ="INSERT INTO course (course_code, short_title, full_title, parent_course_code, classification, url, last_modified_utc) ";
    $sql .="SELECT oci.unit_code, oci.title, oci.title, oci.parent_course_code, 2, oci.url, UTC_TIMESTAMP() ";
    $sql .="FROM openlearn_courses_import oci ";
    $sql .="LEFT JOIN course c ON oci.unit_code = c.course_code ";
    $sql .="WHERE c.course_code IS NULL ";
    $fbplatform->doSQL( $sql );
    $output .=  "<div class='success'>";
    $output .=  "<div class='message'>Proposed additions committed!</div>";
    $output .=  "</div>";	
  }
  else {
    $output .= "<div class='explanation'><div class='message'>Proposed OpenLearn additions skipped as requested.</div>";
    $output .= "</div>";	
  }
  
  // Show proposed OpenLearn changes for next step
  $output .= "<h2>OpenLearn changes</h2>";  
  $output .= get_proposed_openlearn_changes_table($fbplatform);
   
}
// OpenLearn changes
else if ($stepno == 6) {
  if (isset($_POST['confirm'])) {
    // do changes
    $sql ="UPDATE course c JOIN openlearn_courses_import oci ON oci.unit_code = c.course_code ";
    $sql .="SET c.short_title = oci.title, ";
    $sql .="    c.full_title = oci.title, ";
    $sql .="    c.url = oci.url ";
    $sql .="WHERE oci.title != c.short_title OR oci.url != c.url ";
    $fbplatform->doSQL( $sql );    
    $output .=  "<div class='success'>";
    $output .=  "<div class='message'>Proposed changes committed!</div>";
    $output .=  "</div>";	
  }
  else {
    $output .= "<div class='explanation'><div class='message'>Proposed OpenLearn changes skipped as requested.</div>";
    $output .= "</div>";	
  }
  
  // Ask if user wants to import qualifications info
  $output .= "<h2>Query qualifications</h2>";  
  $output .= "<p>Click confirm to query the OU Linked Data store for information on <b>qualifications</b>.";
  $output .= " This will update the information in the update table. Clicking skip will use ";
  $output .= "existing informaiton in the update table.</p>";
   
}
// Query qualifications
else if ($stepno == 7) {
   if (isset($_POST['confirm'])) {
    // delete course import table
    $fbplatform->doSQL("DELETE FROM quals_import");
    get_qualifications_import($fbplatform);
  }
  else {
    $output .= "<div class='explanation'><div class='message'>Linked data import skipped as requested.</div>";
    $output .= "</div>";	
  }
  
  // Show proposed qualification additions for next step
  $output .= "<h2>Qualifications additions</h2>";
  $output .= get_proposed_qualifications_additions_table($fbplatform);
}
// Query additions
else if ($stepno == 8) {
  if (isset($_POST['confirm'])) {
    // do changes
    $sql = "INSERT INTO course (course_code, short_title, full_title, url, classification, last_modified_utc) ";
    $sql .= "SELECT qi.qual_code, qi.title, qi.title, qi.url, 3, UTC_TIMESTAMP() ";
    $sql .= "FROM quals_import qi ";
    $sql .= "LEFT JOIN course c ON qi.qual_code = c.course_code ";
    $sql .= "WHERE c.course_code IS NULL ";
    $fbplatform->doSQL( $sql );
    $output .=  "<div class='success'>";
    $output .=  "<div class='message'>Proposed additions committed!</div>";
    $output .=  "</div>";	
  }
  else {
    $output .= "<div class='explanation'><div class='message'>Proposed additions skipped as requested.</div>";
    $output .= "</div>";	
  }
  
  // Show proposed qualifications changes for next step
  $output .= "<h2>Qualifications Changes</h2>";
  $output .= get_proposed_qualifications_changes_table($fbplatform);
}
// Qualification changes
else if ($stepno == 9) {
    if (isset($_POST['confirm'])) {
    // do changes
    $sql ="UPDATE course c JOIN quals_import qi ON qi.qual_code = c.course_code ";
    $sql .="SET c.short_title = qi.title, ";
    $sql .="    c.full_title = qi.title, ";
    $sql .="    c.url = qi.url ";
    $sql .="WHERE qi.title != c.short_title OR qi.url != c.url ";
    $fbplatform->doSQL( $sql );    
    $output .=  "<div class='success'>";
    $output .=  "<div class='message'>Proposed changes committed!</div>";
    $output .=  "</div>";	
  }
  else {
    $output .= "<div class='explanation'><div class='message'>Proposed changes skipped as requested.</div>";
    $output .= "</div>";	
  }
  $output .= "<p>End.</p>"; 
  
}

// Show step on screen 
$stepno++;
if ($stepno < sizeof($steps)) {
	$output .= "<div class='editor-buttonset'>";
  $output .= "<form action='updatecourses.php' method='post'>";
  $output .= sprintf("<input type='hidden' name='stepno' value='%d' />", $stepno);
  //$output .= "<input type='hidden' name='answer' value='skip' />";
  $output .= "<input type='submit' name='skip' class='inputsubmit' value='Skip' />&nbsp;&nbsp;";
  $output .= "<input type='submit' name='confirm' class='inputsubmit' value='Confirm' />";
  $output .= "</form>";
  $output .= "</div>";
}
$output .= $fbplatform->renderBottom();
echo $output;