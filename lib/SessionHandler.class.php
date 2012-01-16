<?php 
 /**
  * PHP Custom session handler
  * Stores session information in a database table rather than in a file
  * @package cpfa
  * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
  *
  */
class SessionHandler extends CPFABase {
  private static $sessionHandler;
  /**
   * USE getSessionHandler() not the constructor
   */
  public function __construct()  {
  	parent::__construct();
	// set up session handler
	
  }
	 
  /**
	* @see CPFABase::__destruct()
	*/
  public function __destruct() {
    //parent::__destruct();
	//$this->cpfabase->writeToLog("Info", 0, basename(__FILE__), 21, "Session handler destruct called for session id: ".session_id());
  }
	 
  /**
	* Gets nw or existing instance of the SessionHandler
	* @return SessionHandler 
	*/
  public static function getSessionHandler() {
    if (!isset(self::$sessionHandler)) {
      self::$sessionHandler = new SessionHandler();
      //self::$sessionHandler->writeToLog("Info", 0, basename(__FILE__), 29, "Initialising session handler for session id: ".session_id());
	}
	else {
	  //self::$sessionHandler->writeToLog("Info", 0, basename(__FILE__), 29, "Session handler already set for session id: ".session_id());
	}
	session_set_save_handler(
      array(self::$sessionHandler, "sess_open"),
      array(self::$sessionHandler, "sess_close"),
      array(self::$sessionHandler, "sess_read"),
      array(self::$sessionHandler, "sess_write"),
      array(self::$sessionHandler, "sess_destroy"),
      array(self::$sessionHandler, "sess_gc")
    );
	session_start();
	return self::$sessionHandler; 
  }
	 
  /**
   * Handler for a PHP session being started.
   *
   * Handles a call by PHP to open a session. This function
   * does not do anything with the call, just returns a boolean
   * true as required.
   *
   * @param string $save_path PHP will pass the name of a directory here were session data can be saved. It is ignored as we are saving to a database.
   * @param string $session_name Unique identifier for the session. This is not used in this function.
   * @return bool True
   */
  public function sess_open($save_path, $session_name)
  {
    return(true);
  }

  /**
   * Handler for a PHP session being closed
   *
   * Handler for a PHP session being closed. This function
   * does not do anything with the call, just returns a boolean
   * true as required.
   *
   * @return bool True
   */
  public function sess_close()
  {
    return(true);
  }

  /**
   * Read data from a session
   * 
   * Session data will be read from the database table.
   *
   * @param string $id Unique identifier for session.
   */
  public function sess_read($id)
  {
	$retval = "";
	$sql = "SELECT session_data FROM courseprofiles_session WHERE session_id = ?";
	$results = $this->doSQL($sql, array($id));  
    foreach ($results as $row) {
  	  $retval = $row['session_data'];
    }
    return $retval;
  }

  /**
   * Store session data in database
   *
   * @param string $id Unique identifier for session.
   * @param string $sess_data Complete serialised data for session.
   * @return bool True if data was successfully written to database.
   */
  public function sess_write($id, $sess_data)
  {
    $sql = "INSERT INTO courseprofiles_session (session_id, modified, session_data) VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE modified = ?, session_data = ?";
    $params = array($id, time(), $sess_data, time(), $sess_data);
    return $this->doSQL($sql, $params) !== FALSE;
  }

  /**
   * Called when PHP has finished with a session and wants to delete it.
   *
   * @param string $id Unique identifier for session.
   * @return bool True is session successfully deleted from database
   */ 
  public function sess_destroy($id)
  {
    $sql = "DELETE FROM courseprofiles_session WHERE session_id = ?";
    return $this->doSQL($sql, array($id)) !== FALSE;
  }

  /**
   * Called when PHP is garbage collecting sessions
   *
   * @param int $maxlifetime Sessions older than this value (in seconds) will be deleted.
   * @return bool True if Delete command ran successfully.
   */
  public function sess_gc($maxlifetime)
  {
    $sql = "DELETE FROM courseprofiles_session WHERE modified + ? < ?";
    $paramns = array($maxlifetime, time());
    $this->doSQL($sql, $params);
    return true;
  }
}