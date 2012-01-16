<?php
 /**
  * Terms of service page
  *
  * The terms of service are set out in the tos.template file.
  * @package cpfa
  * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
  */
 require_once 'include.php';
 
 $fbplatform = new FBPlatform();

 // construct user object
 $appuser = $fbplatform->getFBUser();
 $toptext = "<h1>Terms of Service</h1>";
 echo $fbplatform->renderTop(false, $toptext);
 echo $fbplatform->getTemplate('tos');
 echo $fbplatform->renderBottom();
?>
