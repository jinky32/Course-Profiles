<?php

 require_once 'config.php';
 
 /**
  * Parent class for CPFA classes
  *
  * Provides common functionality for classes in CPFA and
  * also the session handler.
  * @package cpfa
  * @author Liam Green-Hughes <liam.green-hughes@open.ac.uk>
  *
  */
 class CPFABase {

 	// object to interact with facebook and handle sessions
 	//public $facebook;
 	// database handle
 	private $dbh;
 	
 	/**
 	 * Class constructor. Initialises DB connection and session handler
 	 */
 	function __construct() {
 		$this->dbh = new PDO(DB_DSN, DB_USER, DB_PW);
 		// set all timezone operations to UTC for this program
 		date_default_timezone_set('UTC');
 		
 	
 	}
 	
 	/**
 	 * Class destructor. Closes database connection.
 	 */
 	function __destruct() {
 		$this->dbh = false;
 	}
 	
 	/**
     * Execute a SQL statement
     * @param string $sql SQL statement to execute, put question marks in place of arguments
     * @param array $params paremeters for query, should be in same order as required in SQL statement
     * @param string $keyfield Name of column to use as a key for results array, null if not requred
     * @param boolean $logwrite Set to true if writing to the log. This will supress error routines to stop any infinite loops.
     * @return array Results as array
     */
    function doSQL($sql, $params = null, $keyfield = NULL, $logwrite = false) {
      $retval = array();
      try {
        if (!$this->dbh) {
          $this->dbh = new PDO(DB_DSN, DB_USER, DB_PW); 
        }
        $stmt = $this->dbh->prepare($sql);
        $result = is_array($params) ? $stmt->execute($params) : $stmt->execute();
        if ( $result !== FALSE) {
          if ( substr( $sql, 0, 6 ) == "SELECT" ) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      	      if ( is_null($keyfield) ) {
      	        $retval[] = $row;	
      	      }
      	      else {
                $retval[$row[$keyfield]] = $row;
              }
            }
          }
          else if ( substr( $sql, 0, 6 ) == "INSERT" )
          {
            $retval = $this->dbh->lastInsertId();	
          } 
          else {
            $retval = TRUE;	
          }
        }
        else {
          $retval = FALSE;	
          $pdoerror = $stmt->errorInfo();
        
          // we had invalid SQL
          $message = sprintf("SQL Error: SQLSTATE: %s Error no: %s Message: '%s' SQL: %s",
            $pdoerror[0], $pdoerror[1], $pdoerror[2], $sql
          );
          if (!$logwrite) {
            trigger_error( $message, E_USER_ERROR );
          }
        }
      } 
      catch (PDOException $e) {
      	if (!$logwrite) {
          $this->handleException($e, true, $sql);
        }
      }
      return $retval; 
    }
    
    
    /**
     * Handle thrown exceptions - logs error and redirects to error page
     * @param Exception $e Exception object to process
     * @param boolean $goToErrorPage default true redirect user to the error page
     */
    function handleException($e, $goToErrorPage=true, $furtherinfo=false) {	
      $message = sprintf("Exception: Code: %d  File: %s  Line: %d Message: %s",
      $e->getCode(), $e->getFile(), $e->getLine(), $e->getMessage() );
      if ($furtherinfo) {
        $message .= sprintf(" Further information: %s", $futherinfo);	
      }
      // TODO: trigger an error here to use set error handler
      $level = $goToErrorPage ? E_USER_ERROR : E_USER_NOTICE;
      trigger_error( $message, $level );
    }
   
   /**
    * Loads up an template and substitutes placeholder values
    *
    * Simple templating system for generating HTML output. A template
    * file with HTML fragments (or other output) is placed under the
    * /templates directory. Placeholders for values can then be inserted
    * in the form {$foo}. This will be replaced with the values passed
    * for the "foo" key in the $vars array.
    *
    * It is also possible to specify repeating regions (e.g. table rows).
    * In this case the key in $vars will be the name of the repeating region
    * and the value a nested array of values for each iteration of the 
    * section. So a section where a value for "bar" appears in a repeatable 
    * region would look like this:
    * key => foo, values => array( array('foo' => 1), array('foo' => 2) ) and
    * the template might look like this:
    * <code>
    * <ul>
    * {repeatable_region name='foo'}
    *   <li>{$bar}</li>
    * {end_repeatable_region name='foo'}
    * </ul>
    * </code>
    *
    * @param string $template_name Filename of template file to use (should be under /templates and without file extension)
    * @param array $vars substitution variables. If template contains {$foo} it will be replaced with value of $vars['foo'].
    * @return string Template with values substituted.
    */
   function getTemplate($template_name, $vars=array()) {
   	 $filename = sprintf( "templates/%s.template", $template_name );
     $template = file_get_contents( $filename );
     $template = str_replace("\n","",$template);
     foreach ($vars as $key => $value) {
       $template = $this->populateTemplatePlaceholder( $template, $key, $value );
     }
     return $template;
   }
   
   /**
    * @internal
    * Internal function used to build templates. This is called inside a loop
    * to replace one value at a time. May also call itself for repeatable regions.
    *
    * @param string $template Current contents of template
    * @param string $key Name of value to replace
    * @param mixed $value Contents for value, can be a string for simple values or array for repeatable regions.
    * @return string Template with value populated.
    */
   function populateTemplatePlaceholder( $template, $key, $value ) {
     $retval = "";
     if ( is_array($value) ) {
       	 // handle repeatable regions
       	 $matches = array();
       	 $pattern = "/\{repeatable_region name='".$key."'\}(.*)\{end_repeatable_region name='".$key."'\}/";
       	
       	 if (preg_match( $pattern, $template, $matches)) {
       	   $rr_region = "";
       	   
       	   // generate a copy of the repeatable region for each of the region values
       	   foreach ( $value as $subvalues ) {
       	     	//echo "subvalues: ".htmlentities(print_r($subvalues, true))."<br />";
       	     	$sub_rr_region = $matches[1];
       	     	foreach ( $subvalues as $subkey => $subvalue ) {
       	     	  	$sub_rr_region = $this->populateTemplatePlaceholder( $sub_rr_region, $subkey, $subvalue );
       	     	}
       	     	$rr_region .= $sub_rr_region;
       	   	
       	   }
       	   $retval = str_replace($matches[0], $rr_region, $template);
       	 }
       }
       else {
       	 // handle normal substitutions
         $retval = str_replace( '{$'.$key.'}', $value, $template);
       }
     return $retval;	
   }
   
   /**
    * Joins up lists of items in grammatically correct way
    * @param array items Array of strings to join up 
    * @param string joining_item Joining word, e.g. 'and', 'or'
    * @param string single_item Singlular item name e.g. car
    * @param string plural_item Plural item name e.g. cars
    */
   public static function conjoin( $items, $joining_word, $single_item, $plural_item ) {
   	 $retval = "";
   	 if ( ! empty( $items ) ) {
   	   $retval .= array_pop( $items );
   	   if ( empty($items) ) {
   	   	  $retval .= ' '.$single_item;
   	   }
   	   else {
   	     $retval = implode(', ', $items).' '.$joining_word.' '.$retval.' '.$plural_item;	
   	   }
   	 }
   	 
     return $retval;	
   }
   
  /**
   * Add an entry to the log (which is held in the database)
   *
   * This function is normally called by an error handler to 
   * write the details of an error to the courseprofiles_log table.
   *
   * @param string $type Type of error (e.g. 'Error', 'Warning'( 
   * @param int $err_num The level of the error raised
   * @param string $filename Name of file where error occurred
   * @param int $line_no Line number in file where error occurred
   * @param string $message Details of error
   *
   */  
  function writeToLog($type, $err_num, $filename, $line_no, $message) {
  	 $sql = "INSERT INTO courseprofiles_log (time_utc, type, error_number, file, line, message) ".
  	        "VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?)";
      $params = array( $type, $err_num, $filename, $line_no, $message);
      $retval = $this->doSQL($sql, $params, null, true);
  }
 }

?>