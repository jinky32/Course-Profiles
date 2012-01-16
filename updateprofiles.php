<?php
// /**
//  * Updates Profile boxes
//  * @deprecated
//  *
//  * 20 at a time
//  * Tries to update oldest first
//  * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
//  */
 
//  /** Bring in includes */
//  require_once 'include.php';
//  // check priv key has been passed
//   $givenkey = $_GET['k'];
//  if ( $givenkey != STATS_KEY ) {
//     exit();
//  }
//  // get access to the FBPlatform
//  $fbplatform = new FBPlatform();
 
//  // get 20 users that need updating
//  $sql = "SELECT facebook_uid, `facebook_profilegen_utc` ".
//         "FROM `user` ".
//         "WHERE facebook_remove_utc = 0 ".
//         " AND (`facebook_profilegen_utc` = 0   ".
//         "   OR TIMEDIFF(UTC_TIMESTAMP(),facebook_profilegen_utc) > '24:00:00') ".
//         "ORDER BY `facebook_profilegen_utc` ASC ".
//         "LIMIT 0,20";
//  $results = $fbplatform->doSQL($sql);
//  // update profiles for found rows
//  foreach ($results as $row) {
//    $fbuser = new FBUser($row['facebook_uid'], $fbplatform->facebook);
//    $fbuser->renderProfileFBML();
//  }
 ?>