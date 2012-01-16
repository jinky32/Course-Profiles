<?php
/**
 * Post application remove page
 * This page is called by facebook when the application is removed from a user profile.
 * See: http://wiki.developers.facebook.com/index.php/Post-Remove_URL
 * The page is pinged rather than displayed so no output is possible.
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
/**
 * App includes
 */
require_once 'include.php';

$fbplatform = new FBPlatform();
//$sig = '';
//ksort($_POST);
//foreach ($_POST as $key => $val) {
//    if ( $key != 'fb_sig') {
//    	$sig .= substr($key, 7) . '=' . $val;
//	}
//}

//$sig .= FB_API_SECRET;
//$verify = md5($sig);
//if ($verify == $_POST['fb_sig']) {
//   // Update your database to note that fb_sig_user has removed the application
//    $sql = "UPDATE user SET facebook_remove_utc = UTC_TIMESTAMP() WHERE facebook_uid = ?";
//    $params = array( $_POST['fb_sig_user'] );
//    $fbplatform->doSQL($sql, $params);
//} else {
//    // TODO Log the IP and request for future reference 
//}

// PHP SDK now takes care of parsing a signed request securely.
// See: http://developers.facebook.com/docs/authentication/signed_request/
$facebook_uid = $fbplatform->facebook->getUser();
if ($facebook_uid != 0) {
	// Update your database to note that fb_sig_user has removed the application
	$sql = "UPDATE user SET facebook_remove_utc = UTC_TIMESTAMP() WHERE facebook_uid = ? AND facebook_remove_utc = 0";
	$params = array( $facebook_uid );
	$fbplatform->doSQL($sql, $params);
}

?>