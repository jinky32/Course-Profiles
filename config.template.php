<?php
/** Course Profiles app configuration (template)
 * Copy this file to config.php and modify values appropriately.
 * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
 * @package cpfa
 */
/**
 * Mode of application should be DEVELOPMENT or PRODUCTION. Affects settings below.
 */ 
define('MODE', 'DEVELOPMENT');

// Mode dependent settings
if (MODE == 'DEVELOPMENT') {
	// Development app settings
	define('FB_APP_ID', 'YOUR DEVELOPMENT FB APP ID');
	define('FB_API_KEY', 'YOUR DEVELOPMENT FB API KEY');
	define('FB_API_SECRET', 'YOUR DEVELOPMENT FB API SECRET');
	define('FB_APP_CALLBACK_URL', 'YOUR DEVELOPMENT SERVER URL');
	define('FB_CANVAS_PAGE_URL', 'YOUR DEVELOPMENT FB CANVAS PAGE');
	// database
	define('DB_DSN', 'mysql:host=[DATABASE HOST];port=3306;dbname=[DATABASE NAME]');
	define('DB_USER', 'YOUR DEVELOPMENT DB USER');
	define('DB_PW', 'YOUR DEVELOPMENT DB PASSWORD');
	// error log (%date% will be replaced by today's date)
	define('ERROR_LOG', 'error_%date%.log');
	// google stats
	define('ENABLE_WEBSTATS', FALSE);
	// flag for developer mode
	define('DEVELOPER_MODE', TRUE);
}
else {
	// Production app settings
	define('FB_APP_ID', 'YOUR PRODUCTION FB APP ID');
	define('FB_API_KEY', 'YOUR PRODUCTION FB API KEY');
	define('FB_API_SECRET', 'YOUR PRODUCTION FB API SECRET');
	define('FB_APP_CALLBACK_URL', 'YOUR PRODUCTION SERVER URL');
	define('FB_CANVAS_PAGE_URL', 'YOUR PRODUCTION FB CANVAS PAGE');
	// database info
	define('DB_DSN', 'mysql:host=[DATABASE HOST];port=3306;dbname=[DATABASE NAME]');
	define('DB_USER', 'YOUR PRODUCTION DB USER');
	define('DB_PW', 'YOUR PRODUCTION DB PASSWORD');
	// error log (%date% will be replaced by today's date)
	define('ERROR_LOG', 'logs/error_%date%.log');
	// google stats
	define('ENABLE_WEBSTATS', TRUE);
	// flag for developer mode
	define('DEVELOPER_MODE', FALSE);
}

// Common settings
define('FB_APP_ABOUT', 'http://www.facebook.com/apps/application.php?api_key='.FB_API_KEY);
// course info url (%course_code% will be replaced by the course code)
//define('COURSE_INFO_URL', 'http://www3.open.ac.uk/courses/bin/p12.dll?C01%course_code%&LKCAMPAIGN=FBA01');
define('COURSE_INFO_URL', 'http://www3.open.ac.uk/study/%level%/course/%course_code%.htm?LKCAMPAIGN=FBA01');
// course info page for inactive courses
define('COURSE_OLD_INFO_URL', FB_APP_CALLBACK_URL.'oldcourse.php?cc=%course_code%');
// general courses and quals page
define('COURSES_QUALS_URL', 'http://www3.open.ac.uk/courses/bin/p12.dll?A02&LKCAMPAIGN=FBA01');
// enable items in FB feed
define('ENABLE_FEED_ITEMS', TRUE);

// maximum number of courses that can be entered
define('MAX_COURSE_ENTRIES', 20);

// url to add app
define('FB_ADD_URL', 'http://www.facebook.com/add.php?api_key='.FB_API_KEY);
// Weightings for study buddy
define('SB_WEIGHTING_TIMEZONE', 10);
define('SB_WEIGHTING_WALLCOUNT', 5);
define('SB_WEIGHTING_NOTESCOUNT', 5);
define('SB_WEIGHTING_ACTIVITIES', 10);
define('SB_WEIGHTING_MOVIES', 10);
define('SB_WEIGHTING_TV', 10);
define('SB_WEIGHTING_INTERESTS', 10);
define('SB_WEIGHTING_MUSIC', 10);
define('SB_WEIGHTING_BOOKS', 10);
// link for openlearn (%s will be replaced by the course code)
define('OPENLEARN_URL', 'http://openlearn.open.ac.uk/course/view.php?name=%s&LKCAMPAIGN=FBA02'); 
define('OPENLEARN_HOME', 'http://www.open.ac.uk/openlearn/home.php?LKCAMPAIGN=FBA02');
define('OPENLEARN_SPACE_HOME', 'http://openlearn.open.ac.uk/?LKCAMPAIGN=FBA02');
define('ANNOUNCEMENT_EXPIRE', '01 Feb 2008 00:00:00 UTC');
define('COMMENT_MAX_LENGTH', 5000);   
define('STATS_KEY', 'cd1a4394566d938fd174f8f5ff69c397');
//define('QUAL_INFO_URL', 'http://www3.open.ac.uk/courses/bin/p12.dll?Q01%course_code%&LKCAMPAIGN=FBA01');
define('QUAL_INFO_URL', 'http://www3.open.ac.uk/study/%level%/qualification/%course_code%.htm&LKCAMPAIGN=FBA01');

define('MYOUSTORY_PREFS_URL', 'http://apps.facebook.com/myoustory/preferences.php');
// location of XCRI-CAP data
define('OU_XCRI_CAP_FEED_URL', 'http://www3.open.ac.uk/study/feeds/ou-xcri-cap.xml');
?>
