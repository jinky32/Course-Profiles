<?php
/**
 * Invite friends page
 * @deprecated Needs rewrite for new FB platform
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 */
 
///**
// * App includes
 //*/
// require_once 'include.php';



//  // construct user object
//  $fbplatform = new FBPlatform();

//  // construct user object
//  $appuser = $fbplatform->getFBUser();
//  $fbplatform->facebook->require_frame();
 

// $invfbml = htmlentities( sprintf("Share your course profiles! ".
//     "<fb:name uid='".$appuser->getFacebookUid()."' firstnameonly='true' shownetwork='false' /> would like you to add the Course Profiles application so you can tell your friends which OU courses you are studying.".
//     "<fb:req-choice url='http://www.facebook.com/add.php?api_key=%s' label='Add Course Profiles Now!' />", FB_API_KEY) );

// $toptext = "Here are some of your friends who don't have the Course Profiles application yet. ".
//   "Why don't you invite them?";
// echo $fbplatform->renderTop(false, $toptext);
// // add any friends requests passed to this page
// //if (isset($_POST['uid'])) {
// //  sendInvites($_POST['uid']);	
// //}
// // acknowledge requests sent
// if (isset($_GET['sent']) ){
//   if ( 	$_GET['sent'] == 0 ) {
//   	echo "<fb:explanation><fb:message>Invitations cancelled</fb:message></fb:explanation>";
//   }
//   else {
//   	echo "<fb:success><fb:message>Invitations sent!</fb:message></fb:success>";
//   }
// }

// echo "<div class='panel topborderpanel'>";
// $actiontext = "Please select the friends that you would like to invite.";
// echo "<fb:request-form type='courseprofiles' method='POST' action='index.php?mode=".COURSE_PRESENT."&from=invite' content=\"".$invfbml."\" invite='true'>";
// echo "<fb:multi-friend-selector max='20' actiontext='".$actiontext."' showborder='false' rows='5' ";
// echo "exclude_ids='".implode(',',$appuser->getAppUsingFriends())."' />";
// echo "</fb:request-form>";
// echo "</div>";
// echo $fbplatform->renderBottom();
?>