<?php
/**
 * @tool		Phoca Server Unzip
 * @copyright	Copyright (C) Jan Pavelka www.phoca.cz (http://www.phoca.cz)
 * @license		http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @based 		Based on the following Joomla! classes
 * @package		Joomla
 * @subpackage	FTP Root, JPath, JLoad, JHelper, JBuffer, JFTP, JFile, JFolder
 * @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */


/***********************************************************************************************
 * 000 Error
 ***********************************************************************************************/
 
class JError 
{
	function raiseWarning($id, $string, $path='') {
		echo ' <span>Error: ' . $string . ', '.$path.'</span>';
		echo '<p><a href="index.php">Back to Main Phoca Server Unzip site</a></p>';
		exit;
	}
}

/***********************************************************************************************
 * 000 FTP root
 ***********************************************************************************************/

class FTPRoot
{
	/**
	 * Find the ftp filesystem root for a given user/pass pair
	 *
	 * @static
	 * @param	string	$user	Username of the ftp user to determine root for
	 * @param	string	$pass	Password of the ftp user to determine root for
	 * @return	string	Filesystem root for given FTP user
	 * @since 1.5
	 */
	function findFtpRoot($user, $pass, $host='127.0.0.1', $port='21')
	{
		//jimport('joomla.client.ftp');
		$ftpPaths = array();
	
		// Connect and login to the FTP server (using binary transfer mode to be able to compare files)
		$ftp =& JFTP::getInstance($host, $port, array('type'=>FTP_BINARY));
		if (!$ftp->isConnected()) {
			return JError::raiseError('31', 'NOCONNECT');
		}
		if (!$ftp->login($user, $pass)) {
			return JError::raiseError('31', 'NOLOGIN');
		}
	
		// Get the FTP CWD, in case it is not the FTP root
		$cwd = $ftp->pwd();
		if ($cwd === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NOPWD');
		}
		$cwd = rtrim($cwd, '/');
	
		// Get list of folders in the CWD
		$ftpFolders = $ftp->listDetails(null, 'folders');
		if ($ftpFolders === false || count($ftpFolders) == 0) {
			return JError::raiseError('SOME_ERROR_CODE', 'NODIRECTORYLISTING');
		}
		
		for ($i=0, $n=count($ftpFolders); $i < $n; $i++) {
			$ftpFolders[$i] = $ftpFolders[$i]['name'];
		}
	
		// Check if Joomla! is installed at the FTP CWD
		$dirList = array('administrator', 'components', 'installation', 'language', 'libraries', 'plugins');
		if (count(array_diff($dirList, $ftpFolders)) == 0) {
			$ftpPaths[] = $cwd.'/';
		}
		
		// Process the list: cycle through all parts of JPATH_SITE, beginning from the end
		$parts		= explode(DS, JPATH_SITE);
		$tmpPath	= '';
		for ($i=count($parts)-1; $i>=0; $i--)
		{
			$tmpPath = '/'.$parts[$i].$tmpPath;
			if (in_array($parts[$i], $ftpFolders)) {
				$ftpPaths[] = $cwd.$tmpPath;
			}
		}
	
		// Check all possible paths for the real Joomla! installation
		$checkValue = file_get_contents(JPATH_BASE.DS.'tool_phoca_server_unzip'.DS.'assets'.DS.'index.html');
		foreach ($ftpPaths as $tmpPath)
		{
			$filePath = rtrim($tmpPath, '/').'/tool_phoca_server_unzip/assets/index.html';
			
			$buffer = null;
			@$ftp->read($filePath, $buffer);
			if ($buffer == $checkValue)
			{
				$ftpPath = $tmpPath;
				break;
			}
		}
		// Close the FTP connection
		$ftp->quit();
	
		// Return the FTP root path
		if (isset($ftpPath)) {
			return $ftpPath;
		} else {
			return JError::raiseError('SOME_ERROR_CODE', 'Unable to autodetect the FTP root folder');
		}
	}
}

 
/***********************************************************************************************
 * 000 PATH
 ***********************************************************************************************/
 
/** boolean True if a Windows based host */
define('JPATH_ISWIN', (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'));
/** boolean True if a Mac based host */
define('JPATH_ISMAC', (strtoupper(substr(PHP_OS, 0, 3)) === 'MAC'));


if (!defined('JPATH_ROOT')) {
	/** string The root directory of the file system in native format */
	define('JPATH_ROOT', JPath::clean(JPATH_SITE));
}
/**
 * A Path handling class
 *
 * @static
 * @package 	Joomla.Framework
 * @subpackage	FileSystem
 * @since		1.5
 */
 
class JPath
{
	/**
	 * Checks if a path's permissions can be changed
	 *
	 * @param	string	$path	Path to check
	 * @return	boolean	True if path can have mode changed
	 * @since	1.5
	 */
	function canChmod($path)
	{
		$perms = fileperms($path);
		if ($perms !== false)
		{
			if (@ chmod($path, $perms ^ 0001))
			{
				@chmod($path, $perms);
				return true;
			}
		}
		return false;
	}

	/**
	 * Chmods files and directories recursivly to given permissions
	 *
	 * @param	string	$path		Root path to begin changing mode [without trailing slash]
	 * @param	string	$filemode	Octal representation of the value to change file mode to [null = no change]
	 * @param	string	$foldermode	Octal representation of the value to change folder mode to [null = no change]
	 * @return	boolean	True if successful [one fail means the whole operation failed]
	 * @since	1.5
	 */
	function setPermissions($path, $filemode = '0644', $foldermode = '0755') {

		// Initialize return value
		$ret = true;

		if (is_dir($path))
		{
			$dh = opendir($path);
			while ($file = readdir($dh))
			{
				if ($file != '.' && $file != '..') {
					$fullpath = $path.'/'.$file;
					if (is_dir($fullpath)) {
						if (!JPath::setPermissions($fullpath, $filemode, $foldermode)) {
							$ret = false;
						}
					} else {
						if (isset ($filemode)) {
							if (!@ chmod($fullpath, octdec($filemode))) {
								$ret = false;
							}
						}
					} // if
				} // if
			} // while
			closedir($dh);
			if (isset ($foldermode)) {
				if (!@ chmod($path, octdec($foldermode))) {
					$ret = false;
				}
			}
		}
		else
		{
			if (isset ($filemode)) {
				$ret = @ chmod($path, octdec($filemode));
			}
		} // if
		return $ret;
	}

	/**
	 * Get the permissions of the file/folder at a give path
	 *
	 * @param	string	$path	The path of a file/folder
	 * @return	string	Filesystem permissions
	 * @since	1.5
	 */
	function getPermissions($path)
	{
		$path = JPath::clean($path);
		$mode = @ decoct(@ fileperms($path) & 0777);

		if (strlen($mode) < 3) {
			return '---------';
		}
		$parsed_mode = '';
		for ($i = 0; $i < 3; $i ++)
		{
			// read
			$parsed_mode .= ($mode { $i } & 04) ? "r" : "-";
			// write
			$parsed_mode .= ($mode { $i } & 02) ? "w" : "-";
			// execute
			$parsed_mode .= ($mode { $i } & 01) ? "x" : "-";
		}
		return $parsed_mode;
	}

	/**
	 * Checks for snooping outside of the file system root
	 *
	 * @param	string	$path	A file system path to check
	 * @return	string	A cleaned version of the path
	 * @since	1.5
	 */
	function check($path)
	{
		if (strpos($path, '..') !== false) {
			JError::raiseError( 20, 'JPath::check Use of relative paths not permitted'); // don't translate
			jexit();
		}
		$path = JPath::clean($path);
		if (strpos($path, JPath::clean(JPATH_ROOT)) !== 0) {
			JError::raiseError( 20, 'JPath::check Snooping out of bounds @ '.$path); // don't translate
			jexit();
		}
	}

	/**
	 * Function to strip additional / or \ in a path name
	 *
	 * @static
	 * @param	string	$path	The path to clean
	 * @param	string	$ds		Directory separator (optional)
	 * @return	string	The cleaned path
	 * @since	1.5
	 */
	function clean($path, $ds=DS)
	{
		$path = trim($path);

		if (empty($path)) {
			$path = JPATH_ROOT;
		} else {
			// Remove double slashes and backslahses and convert all slashes and backslashes to DS
			$path = preg_replace('#[/\\\\]+#', $ds, $path);
		}

		return $path;
	}

	/**
	 * Method to determine if script owns the path
	 *
	 * @static
	 * @param	string	$path	Path to check ownership
	 * @return	boolean	True if the php script owns the path passed
	 * @since	1.5
	 */
	function isOwner($path)
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.user.helper');

		$tmp = md5(JUserHelper::genRandomPassword(16));
		$ssp = ini_get('session.save_path');
		$jtp = JPATH_SITE.DS.'tmp';

		// Try to find a writable directory
		$dir = is_writable('/tmp') ? '/tmp' : false;
		$dir = (!$dir && is_writable($ssp)) ? $ssp : false;
		$dir = (!$dir && is_writable($jtp)) ? $jtp : false;

		if ($dir)
		{
			$test = $dir.DS.$tmp;

			// Create the test file
			JFile::write($test, '');

			// Test ownership
			$return = (fileowner($test) == fileowner($path));

			// Delete the test file
			JFile::delete($test);

			return $return;
		}

		return false;
	}

	/**
	 * Searches the directory paths for a given file.
	 *
	 * @access	protected
	  * @param	array|string	$path	An path or array of path to search in
	 * @param	string	$file	The file name to look for.
	 * @return	mixed	The full path and file name for the target file, or boolean false if the file is not found in any of the paths.
	 * @since	1.5
	 */
	function find($paths, $file)
	{
		settype($paths, 'array'); //force to array

		// start looping through the path set
		foreach ($paths as $path)
		{
			// get the path to the file
			$fullname = $path.DS.$file;

			// is the path based on a stream?
			if (strpos($path, '://') === false)
			{
				// not a stream, so do a realpath() to avoid directory
				// traversal attempts on the local file system.
				$path = realpath($path); // needed for substr() later
				$fullname = realpath($fullname);
			}

			// the substr() check added to make sure that the realpath()
			// results in a directory registered so that
			// non-registered directores are not accessible via directory
			// traversal attempts.
			if (file_exists($fullname) && substr($fullname, 0, strlen($path)) == $path) {
				return $fullname;
			}
		}

		// could not find the file in the set of paths
		return false;
	}
}

/***********************************************************************************************
 * 000 LOAD
 ***********************************************************************************************/
/**
 * @package		Joomla.Framework
 */
class JLoader
{
	 /**
	 * Loads a class from specified directories.
	 *
	 * @param string $name	The class name to look for ( dot notation ).
	 * @param string $base	Search this directory for the class.
	 * @param string $key	String used as a prefix to denote the full path of the file ( dot notation ).
	 * @return void
	 * @since 1.5
	 */
	function import( $filePath, $base = null, $key = 'libraries.' )
	{
		static $paths;

		if (!isset($paths)) {
			$paths = array();
		}

		$keyPath = $key ? $key . $filePath : $filePath;

		if (!isset($paths[$keyPath]))
		{
			if ( ! $base ) {
				$base =  dirname( __FILE__ );
			}

			$parts = explode( '.', $filePath );

			$classname = array_pop( $parts );
			switch($classname)
			{
				case 'helper' :
					$classname = ucfirst(array_pop( $parts )).ucfirst($classname);
					break;

				default :
					$classname = ucfirst($classname);
					break;
			}

			$path  = str_replace( '.', DS, $filePath );

			if (strpos($filePath, 'joomla') === 0)
			{
				/*
				 * If we are loading a joomla class prepend the classname with a
				 * capital J.
				 */
				$classname	= 'J'.$classname;
				$classes	= JLoader::register($classname, $base.DS.$path.'.php');
				$rs			= isset($classes[strtolower($classname)]);
			}
			else
			{
				/*
				 * If it is not in the joomla namespace then we have no idea if
				 * it uses our pattern for class names/files so just include.
				 */
				$rs   = include($base.DS.$path.'.php');
			}

			$paths[$keyPath] = $rs;
		}

		return $paths[$keyPath];
	}

	/**
	 * Add a class to autoload
	 *
	 * @param	string $classname	The class name
	 * @param	string $file		Full path to the file that holds the class
	 * @return	array|boolean  		Array of classes
	 * @since 	1.5
	 */
	function & register ($class = null, $file = null)
	{
		static $classes;

		if(!isset($classes)) {
			$classes    = array();
		}

		if($class && is_file($file))
		{
			// Force to lower case.
			$class = strtolower($class);
			$classes[$class] = $file;

			// In php4 we load the class immediately.
			if((version_compare( phpversion(), '5.0' ) < 0)) {
				JLoader::load($class);
			}

		}

		return $classes;
	}


	/**
	 * Load the file for a class
	 *
	 * @access  public
	 * @param   string  $class  The class that will be loaded
	 * @return  boolean True on success
	 * @since   1.5
	 */
	function load( $class )
	{
		$class = strtolower($class); //force to lower case

		if (class_exists($class)) {
			  return;
		}

		$classes = JLoader::register();
		if(array_key_exists( strtolower($class), $classes)) {
			include($classes[$class]);
			return true;
		}
		return false;
	}
}


/**
 * When calling a class that hasn't been defined, __autoload will attempt to
 * include the correct file for that class.
 *
 * This function get's called by PHP. Never call this function yourself.
 *
 * @param 	string 	$class
 * @access 	public
 * @return  boolean
 * @since   1.5
 */
function __autoload($class)
{
	if(JLoader::load($class)) {
		return true;
	}
	return false;
}

/**
 * Global application exit.
 *
 * This function provides a single exit point for the framework.
 *
 * @param mixed Exit code or string. Defaults to zero.
 */
function jexit($message = 0) {
    exit($message);
}

/**
 * Intelligent file importer
 *
 * @access public
 * @param string $path A dot syntax path
 * @since 1.5
 */
function jimport( $path ) {
	return JLoader::import($path);
}

/***********************************************************************************************
 * 000 Helper
 ***********************************************************************************************/

/**
 * Client helper class
 *
 * @static
 * @author		Louis Landry <louis.landry@joomla.org>
 * @author		Enno Klasing <friesengeist@gmail.com>
 * @package		Joomla.Framework
 * @subpackage	Client
 * @since		1.5
 */
class JClientHelper
{
	/**
	 * Method to return the array of client layer configuration options
	 *
	 * @static
	 * @param	string	Client name, currently only 'ftp' is supported
	 * @param	boolean	Forces re-creation of the login credentials. Set this to
	 *					true if login credentials in the session storage have changed
	 * @return	array	Client layer configuration options, consisting of at least
	 *					these fields: enabled, host, port, user, pass, root
	 * @since	1.5
	 */
	function getCredentials($client, $force=false)
	{
		static $credentials = array();

		$client = strtolower($client);

		if (!isset($credentials[$client]) || $force)
		{
			// Initialize variables
			//$config =& JFactory::getConfig();

			// Fetch the client layer configuration options for the specific client
			switch ($client)
			{
				case 'ftp':
					$options = array(
						'enabled'	=> FTP_ENABLED,
						'host'		=> FTP_HOST,
						'port'		=> FTP_PORT,
						'user'		=> FTP_USER,
						'pass'		=> FTP_PASS,
						'root'		=> FTP_ROOT
					);
					break;

				default:
					$options = array(
						'enabled'	=> false,
						'host'		=> '',
						'port'		=> '',
						'user'		=> '',
						'pass'		=> '',
						'root'		=> ''
					);
					break;
			}
/*
			// If user and pass are not set in global config lets see if its in the session
			if ($options['enabled'] == true && ($options['user'] == '' || $options['pass'] == ''))
			{
				$session =& JFactory::getSession();
				$options['user'] = $session->get($client.'.user', null, 'JClientHelper');
				$options['pass'] = $session->get($client.'.pass', null, 'JClientHelper');
			}

			// If user or pass are missing, disable this client
			if ($options['user'] == '' || $options['pass'] == '') {
				$options['enabled'] = false;
			}
*/
			// Save the credentials for later use
			$credentials[$client] = $options;
		}

		return $credentials[$client];
	}
}

/***********************************************************************************************
 * 000 Buffer
 ***********************************************************************************************/
/**
 * Generic Buffer stream handler
 *
 * This class provides a generic buffer stream.  It can be used to store/retrieve/manipulate
 * string buffers with the standard PHP filesystem I/O methods.
 *
 * @author		Louis Landry <louis.landry@joomla.org>
 * @package 	Joomla.Framework
 * @subpackage	Utilities
 * @since		1.5
 */
class JBuffer
{
	/**
	 * Stream position
	 * @var int
	 */
	var $position = 0;

	/**
	 * Buffer name
	 * @var string
	 */
	var $name = null;

	/**
	 * Buffer hash
	 * @var array
	 */
	var $_buffers = array ();

	function stream_open($path, $mode, $options, & $opened_path)
	{
		$url = parse_url($path);
		$this->name = $url["host"];
		$this->_buffers[$this->name] = null;
		$this->position = 0;

		return true;
	}

	function stream_read($count)
	{
		$ret = substr($this->_buffers[$this->name], $this->position, $count);
		$this->position += strlen($ret);
		return $ret;
	}

	function stream_write($data)
	{
		$left = substr($this->_buffers[$this->name], 0, $this->position);
		$right = substr($this->_buffers[$this->name], $this->position + strlen($data));
		$this->_buffers[$this->name] = $left . $data . $right;
		$this->position += strlen($data);
		return strlen($data);
	}

	function stream_tell() {
		return $this->position;
	}

	function stream_eof() {
		return $this->position >= strlen($this->_buffers[$this->name]);
	}

	function stream_seek($offset, $whence)
	{
		switch ($whence)
		{
			case SEEK_SET :
				if ($offset < strlen($this->_buffers[$this->name]) && $offset >= 0) {
					$this->position = $offset;
					return true;
				} else {
					return false;
				}
				break;

			case SEEK_CUR :
				if ($offset >= 0) {
					$this->position += $offset;
					return true;
				} else {
					return false;
				}
				break;

			case SEEK_END :
				if (strlen($this->_buffers[$this->name]) + $offset >= 0) {
					$this->position = strlen($this->_buffers[$this->name]) + $offset;
					return true;
				} else {
					return false;
				}
				break;

			default :
				return false;
		}
	}
}
// Register the stream
stream_wrapper_register("buffer", "JBuffer");

/***********************************************************************************************
 * 000 FTP
 ***********************************************************************************************/

/** Error Codes:
 *  - 30 : Unable to connect to host
 *  - 31 : Not connected
 *  - 32 : Unable to send command to server
 *  - 33 : Bad username
 *  - 34 : Bad password
 *  - 35 : Bad response
 *  - 36 : Passive mode failed
 *  - 37 : Data transfer error
 *  - 38 : Local filesystem error
 */

if (!defined('CRLF')) {
	define('CRLF', "\r\n");
}
if (!defined("FTP_AUTOASCII")) {
	define("FTP_AUTOASCII", -1);
}
if (!defined("FTP_BINARY")) {
	define("FTP_BINARY", 1);
}
if (!defined("FTP_ASCII")) {
	define("FTP_ASCII", 0);
}

// Is FTP extension loaded?  If not try to load it
if (!extension_loaded('ftp')) {
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		@ dl('php_ftp.dll');
	} else {
		@ dl('ftp.so');
	}
}
if (!defined('FTP_NATIVE')) {
	define('FTP_NATIVE', (function_exists('ftp_connect'))? 1 : 0);
}

/**
 * FTP client class
 *
 * @author		Louis Landry  <louis.landry@joomla.org>
 * @package		Joomla.Framework
 * @subpackage	Client
 * @since		1.5
 */
class JFTP
{

	/**
	 * Server connection resource
	 *
	 * @access private
	 * @var socket resource
	 */
	var $_conn = null;

	/**
	 * Data port connection resource
	 *
	 * @access private
	 * @var socket resource
	 */
	var $_dataconn = null;

	/**
	 * Passive connection information
	 *
	 * @access private
	 * @var array
	 */
	var $_pasv = null;

	/**
	 * Response Message
	 *
	 * @access private
	 * @var string
	 */
	var $_response = null;

	/**
	 * Timeout limit
	 *
	 * @access private
	 * @var int
	 */
	var $_timeout = 15;

	/**
	 * Transfer Type
	 *
	 * @access private
	 * @var int
	 */
	var $_type = null;

	/**
	 * Native OS Type
	 *
	 * @access private
	 * @var string
	 */
	var $_OS = null;

	/**
	 * Array to hold ascii format file extensions
	 *
	 * @final
	 * @access private
	 * @var array
	 */
	var $_autoAscii = array ("asp", "bat", "c", "cpp", "csv", "h", "htm", "html", "shtml", "ini", "inc", "log", "php", "php3", "pl", "perl", "sh", "sql", "txt", "xhtml", "xml");

	/**
	 * Array to hold native line ending characters
	 *
	 * @final
	 * @access private
	 * @var array
	 */
	var $_lineEndings = array ('UNIX' => "\n", 'MAC' => "\r", 'WIN' => "\r\n");

	/**
	 * JFTP object constructor
	 *
	 * @access protected
	 * @param array $options Associative array of options to set
	 * @since 1.5
	 */
	function __construct($options=array()) {

		// If default transfer type is no set, set it to autoascii detect
		if (!isset ($options['type'])) {
			$options['type'] = FTP_BINARY;
		}
		$this->setOptions($options);

		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$this->_OS = 'WIN';
		} elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'MAC') {
			$this->_OS = 'MAC';
		} else {
			$this->_OS = 'UNIX';
		}

		if (FTP_NATIVE) {
			// Import the generic buffer stream handler
			//jimport('joomla.utilities.buffer');
			// Autoloading fails for JBuffer as the class is used as a stream handler
			JLoader::load('JBuffer');
		}

		// Register faked "destructor" in PHP4 to close all connections we might have made
		if (version_compare(PHP_VERSION, '5') == -1) {
			register_shutdown_function(array(&$this, '__destruct'));
		}
	}

	/**
	 * JFTP object destructor
	 *
	 * Closes an existing connection, if we have one
	 *
	 * @access protected
	 * @since 1.5
	 */
	function __destruct() {
		if (is_resource($this->_conn)) {
			$this->quit();
		}
	}

	/**
	 * Returns a reference to the global FTP connector object, only creating it
	 * if it doesn't already exist.
	 *
	 * This method must be invoked as:
	 * 		<pre>  $ftp = &JFTP::getInstance($host);</pre>
	 *
	 * You may optionally specify a username and password in the parameters. If you do so,
	 * you may not login() again with different credentials using the same object.
	 * If you do not use this option, you must quit() the current connection when you
	 * are done, to free it for use by others.
	 *
	 * @param	string	$host		Host to connect to
	 * @param	string	$port		Port to connect to
	 * @param	array	$options	Array with any of these options: type=>[FTP_AUTOASCII|FTP_ASCII|FTP_BINARY], timeout=>(int)
	 * @param	string	$user		Username to use for a connection
	 * @param	string	$pass		Password to use for a connection
	 * @return	JFTP	The FTP Client object.
	 * @since 1.5
	 */
	function &getInstance($host = '127.0.0.1', $port = '21', $options = null, $user = null, $pass = null)
	{
		static $instances = array();

		$signature = $user.':'.$pass.'@'.$host.":".$port;

		// Create a new instance, or set the options of an existing one
		if (!isset ($instances[$signature]) || !is_object($instances[$signature])) {
			$instances[$signature] = new JFTP($options);
		} else {
			$instances[$signature]->setOptions($options);
		}

		// Connect to the server, and login, if requested
		if (!$instances[$signature]->isConnected()) {
			$return = $instances[$signature]->connect($host, $port);
			if ($return && $user !== null && $pass !== null) {
				$instances[$signature]->login($user, $pass);
			}
		}

		return $instances[$signature];
	}

	/**
	 * Set client options
	 *
	 * @access public
	 * @param array $options Associative array of options to set
	 * @return boolean True if successful
	 */
	function setOptions($options) {

		if (isset ($options['type'])) {
			$this->_type = $options['type'];
		}
		if (isset ($options['timeout'])) {
			$this->_timeout = $options['timeout'];
		}
		return true;
	}

	/**
	 * Method to connect to a FTP server
	 *
	 * @access public
	 * @param string $host Host to connect to [Default: 127.0.0.1]
	 * @param string $port Port to connect on [Default: port 21]
	 * @return boolean True if successful
	 */
	function connect($host = '127.0.0.1', $port = 21) {

		// Initialize variables
		$errno = null;
		$err = null;

		// If already connected, return
		if (is_resource($this->_conn)) {
			return true;
		}

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			$this->_conn = @ftp_connect($host, $port, $this->_timeout);
			if ($this->_conn === false) {
				JError::raiseWarning('30', 'JFTP::connect: Could not connect to host "'.$host.'" on port '.$port);
				return false;
			}
			// Set the timeout for this connection
			ftp_set_option($this->_conn, FTP_TIMEOUT_SEC, $this->_timeout);
			return true;
		}

		// Connect to the FTP server.
		$this->_conn = @ fsockopen($host, $port, $errno, $err, $this->_timeout);
		if (!$this->_conn) {
			JError::raiseWarning('30', 'JFTP::connect: Could not connect to host "'.$host.'" on port '.$port, 'Socket error number '.$errno.' and error message: '.$err);
			return false;
		}

		// Set the timeout for this connection
		socket_set_timeout($this->_conn, $this->_timeout);

		// Check for welcome response code
		if (!$this->_verifyResponse(220)) {
			JError::raiseWarning('35', 'JFTP::connect: Bad response', 'Server response: '.$this->_response.' [Expected: 220]');
			return false;
		}

		return true;
	}

	/**
	 * Method to determine if the object is connected to an FTP server
	 *
	 * @access	public
	 * @return	boolean	True if connected
	 * @since	1.5
	 */
	function isConnected()
	{
		return is_resource($this->_conn);
	}

	/**
	 * Method to login to a server once connected
	 *
	 * @access public
	 * @param string $user Username to login to the server
	 * @param string $pass Password to login to the server
	 * @return boolean True if successful
	 */
	function login($user = 'anonymous', $pass = 'jftp@joomla.org') {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (@ftp_login($this->_conn, $user, $pass) === false) {
				JError::raiseWarning('30', 'JFTP::login: Unable to login' );
				return false;
			}
			return true;
		}

		// Send the username
		if (!$this->_putCmd('USER '.$user, array(331, 503))) {
			JError::raiseWarning('33', 'JFTP::login: Bad Username', 'Server response: '.$this->_response.' [Expected: 331] Username sent: '.$user );
			return false;
		}

		// If we are already logged in, continue :)
		if ($this->_responseCode == 503) {
			return true;
		}

		// Send the password
		if (!$this->_putCmd('PASS '.$pass, 230)) {
			JError::raiseWarning('34', 'JFTP::login: Bad Password', 'Server response: '.$this->_response.' [Expected: 230] Password sent: '.str_repeat('*', strlen($pass)));
			return false;
		}

		return true;
	}

	/**
	 * Method to quit and close the connection
	 *
	 * @access public
	 * @return boolean True if successful
	 */
	function quit() {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			@ftp_close($this->_conn);
			return true;
		}

		// Logout and close connection
		@fwrite($this->_conn, "QUIT\r\n");
		@fclose($this->_conn);

		return true;
	}

	/**
	 * Method to retrieve the current working directory on the FTP server
	 *
	 * @access public
	 * @return string Current working directory
	 */
	function pwd() {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (($ret = @ftp_pwd($this->_conn)) === false) {
				JError::raiseWarning('35', 'JFTP::pwd: Bad response' );
				return false;
			}
			return $ret;
		}

		// Initialize variables
		$match = array (null);

		// Send print working directory command and verify success
		if (!$this->_putCmd('PWD', 257)) {
			JError::raiseWarning('35', 'JFTP::pwd: Bad response', 'Server response: '.$this->_response.' [Expected: 257]' );
			return false;
		}

		// Match just the path
		preg_match('/"[^"\r\n]*"/', $this->_response, $match);

		// Return the cleaned path
		return preg_replace("/\"/", "", $match[0]);
	}

	/**
	 * Method to system string from the FTP server
	 *
	 * @access public
	 * @return string System identifier string
	 */
	function syst() {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (($ret = @ftp_systype($this->_conn)) === false) {
				JError::raiseWarning('35', 'JFTP::syst: Bad response' );
				return false;
			}
		} else {
			// Send print working directory command and verify success
			if (!$this->_putCmd('SYST', 215)) {
				JError::raiseWarning('35', 'JFTP::syst: Bad response', 'Server response: '.$this->_response.' [Expected: 215]' );
				return false;
			}
			$ret = $this->_response;
		}

		// Match the system string to an OS
		if (strpos(strtoupper($ret), 'MAC') !== false) {
			$ret = 'MAC';
		} elseif (strpos(strtoupper($ret), 'WIN') !== false) {
			$ret = 'WIN';
		} else {
			$ret = 'UNIX';
		}

		// Return the os type
		return $ret;
	}

	/**
	 * Method to change the current working directory on the FTP server
	 *
	 * @access public
	 * @param string $path Path to change into on the server
	 * @return boolean True if successful
	 */
	function chdir($path) {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (@ftp_chdir($this->_conn, $path) === false) {
				JError::raiseWarning('35', 'JFTP::chdir: Bad response' );
				return false;
			}
			return true;
		}

		// Send change directory command and verify success
		if (!$this->_putCmd('CWD '.$path, 250)) {
			JError::raiseWarning('35', 'JFTP::chdir: Bad response', 'Server response: '.$this->_response.' [Expected: 250] Path sent: '.$path );
			return false;
		}

		return true;
	}

	/**
	 * Method to reinitialize the server, ie. need to login again
	 *
	 * NOTE: This command not available on all servers
	 *
	 * @access public
	 * @return boolean True if successful
	 */
	function reinit() {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (@ftp_site($this->_conn, 'REIN') === false) {
				JError::raiseWarning('35', 'JFTP::reinit: Bad response' );
				return false;
			}
			return true;
		}

		// Send reinitialize command to the server
		if (!$this->_putCmd('REIN', 220)) {
			JError::raiseWarning('35', 'JFTP::reinit: Bad response', 'Server response: '.$this->_response.' [Expected: 220]' );
			return false;
		}

		return true;
	}

	/**
	 * Method to rename a file/folder on the FTP server
	 *
	 * @access public
	 * @param string $from Path to change file/folder from
	 * @param string $to Path to change file/folder to
	 * @return boolean True if successful
	 */
	function rename($from, $to) {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (@ftp_rename($this->_conn, $from, $to) === false) {
				JError::raiseWarning('35', 'JFTP::rename: Bad response' );
				return false;
			}
			return true;
		}

		// Send rename from command to the server
		if (!$this->_putCmd('RNFR '.$from, 350)) {
			JError::raiseWarning('35', 'JFTP::rename: Bad response', 'Server response: '.$this->_response.' [Expected: 320] From path sent: '.$from );
			return false;
		}

		// Send rename to command to the server
		if (!$this->_putCmd('RNTO '.$to, 250)) {
			JError::raiseWarning('35', 'JFTP::rename: Bad response', 'Server response: '.$this->_response.' [Expected: 250] To path sent: '.$to );
			return false;
		}

		return true;
	}

	/**
	 * Method to change mode for a path on the FTP server
	 *
	 * @access public
	 * @param string		$path	Path to change mode on
	 * @param string/int	$mode	Octal value to change mode to, e.g. '0777', 0777 or 511
	 * @return boolean		True if successful
	 */
	function chmod($path, $mode) {

		// If no filename is given, we assume the current directory is the target
		if ($path == '') {
			$path = '.';
		}

		// Convert the mode to a string
		if (is_int($mode)) {
			$mode = decoct($mode);
		}

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (@ftp_site($this->_conn, 'CHMOD '.$mode.' '.$path) === false) {
				if($this->_OS != 'WIN') {
					JError::raiseWarning('35', 'JFTP::chmod: Bad response' );
				}
				return false;
			}
			return true;
		}

		// Send change mode command and verify success [must convert mode from octal]
		if (!$this->_putCmd('SITE CHMOD '.$mode.' '.$path, array(200, 250))) {
			if($this->_OS != 'WIN') {
				JError::raiseWarning('35', 'JFTP::chmod: Bad response', 'Server response: '.$this->_response.' [Expected: 200 or 250] Path sent: '.$path.' Mode sent: '.$mode);
			}
			return false;
		}
		return true;
	}

	/**
	 * Method to delete a path [file/folder] on the FTP server
	 *
	 * @access public
	 * @param string $path Path to delete
	 * @return boolean True if successful
	 */
	function delete($path) {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (@ftp_delete($this->_conn, $path) === false) {
				if (@ftp_rmdir($this->_conn, $path) === false) {
					JError::raiseWarning('35', 'JFTP::delete: Bad response' );
					return false;
				}
			}
			return true;
		}

		// Send delete file command and if that doesn't work, try to remove a directory
		if (!$this->_putCmd('DELE '.$path, 250)) {
			if (!$this->_putCmd('RMD '.$path, 250)) {
				JError::raiseWarning('35', 'JFTP::delete: Bad response', 'Server response: '.$this->_response.' [Expected: 250] Path sent: '.$path );
				return false;
			}
		}
		return true;
	}

	/**
	 * Method to create a directory on the FTP server
	 *
	 * @access public
	 * @param string $path Directory to create
	 * @return boolean True if successful
	 */
	function mkdir($path) {
		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (@ftp_mkdir($this->_conn, $path) === false) {
				JError::raiseWarning('35', 'JFTP::mkdir: Bad response' );
				return false;
			}
			return true;
		}

		// Send change directory command and verify success
		if (!$this->_putCmd('MKD '.$path, 257)) {
		
			JError::raiseWarning('35', 'JFTP::mkdir: Bad response - Server response: '.$this->_response.' [Expected: 257] Path sent: '.$path );
			return false;
		}
		return true;
	}

	/**
	 * Method to restart data transfer at a given byte
	 *
	 * @access public
	 * @param int $point Byte to restart transfer at
	 * @return boolean True if successful
	 */
	function restart($point) {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			if (@ftp_site($this->_conn, 'REST '.$point) === false) {
				JError::raiseWarning('35', 'JFTP::restart: Bad response' );
				return false;
			}
			return true;
		}

		// Send restart command and verify success
		if (!$this->_putCmd('REST '.$point, 350)) {
			JError::raiseWarning('35', 'JFTP::restart: Bad response', 'Server response: '.$this->_response.' [Expected: 350] Restart point sent: '.$point );
			return false;
		}

		return true;
	}

	/**
	 * Method to create an empty file on the FTP server
	 *
	 * @access public
	 * @param string $path Path local file to store on the FTP server
	 * @return boolean True if successful
	 */
	function create($path) {

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			// turn passive mode on
			if (@ftp_pasv($this->_conn, true) === false) {
				JError::raiseWarning('36', 'JFTP::create: Unable to use passive mode' );
				return false;
			}

			$buffer = fopen('buffer://tmp', 'r');
			if (@ftp_fput($this->_conn, $path, $buffer, FTP_ASCII) === false) {
				JError::raiseWarning('35', 'JFTP::create: Bad response' );
				fclose($buffer);
				return false;
			}
			fclose($buffer);
			return true;
		}

		// Start passive mode
		if (!$this->_passive()) {
			JError::raiseWarning('36', 'JFTP::create: Unable to use passive mode' );
			return false;
		}

		if (!$this->_putCmd('STOR '.$path, array (150, 125))) {
			@ fclose($this->_dataconn);
			JError::raiseWarning('35', 'JFTP::create: Bad response', 'Server response: '.$this->_response.' [Expected: 150 or 125] Path sent: '.$path );
			return false;
		}

		// To create a zero byte upload close the data port connection
		fclose($this->_dataconn);

		if (!$this->_verifyResponse(226)) {
			JError::raiseWarning('37', 'JFTP::create: Transfer Failed', 'Server response: '.$this->_response.' [Expected: 226] Path sent: '.$path );
			return false;
		}

		return true;
	}

	/**
	 * Method to read a file from the FTP server's contents into a buffer
	 *
	 * @access public
	 * @param string $remote Path to remote file to read on the FTP server
	 * @param string $buffer Buffer variable to read file contents into
	 * @return boolean True if successful
	 */
	function read($remote, &$buffer) {

		// Determine file type
		$mode = $this->_findMode($remote);

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			// turn passive mode on
			if (@ftp_pasv($this->_conn, true) === false) {
				JError::raiseWarning('36', 'JFTP::read: Unable to use passive mode' );
				return false;
			}

			$tmp = fopen('buffer://tmp', 'br+');
			if (@ftp_fget($this->_conn, $tmp, $remote, $mode) === false) {
				fclose($tmp);
				JError::raiseWarning('35', 'JFTP::read: Bad response' );
				return false;
			}
			// Read tmp buffer contents
			rewind($tmp);
			$buffer = '';
			while (!feof($tmp)) {
				$buffer .= fread($tmp, 8192);
			}
			fclose($tmp);
			return true;
		}

		$this->_mode($mode);

		// Start passive mode
		if (!$this->_passive()) {
			JError::raiseWarning('36', 'JFTP::read: Unable to use passive mode' );
			return false;
		}

		if (!$this->_putCmd('RETR '.$remote, array (150, 125))) {
			@ fclose($this->_dataconn);
			JError::raiseWarning('35', 'JFTP::read: Bad response', 'Server response: '.$this->_response.' [Expected: 150 or 125] Path sent: '.$remote );
			return false;
		}

		// Read data from data port connection and add to the buffer
		$buffer = '';
		while (!feof($this->_dataconn)) {
			$buffer .= fread($this->_dataconn, 4096);
		}

		// Close the data port connection
		fclose($this->_dataconn);

		// Let's try to cleanup some line endings if it is ascii
		if ($mode == FTP_ASCII) {
			$buffer = preg_replace("/".CRLF."/", $this->_lineEndings[$this->_OS], $buffer);
		}

		if (!$this->_verifyResponse(226)) {
			JError::raiseWarning('37', 'JFTP::read: Transfer Failed', 'Server response: '.$this->_response.' [Expected: 226] Path sent: '.$remote );
			return false;
		}

		return true;
	}

	/**
	 * Method to get a file from the FTP server and save it to a local file
	 *
	 * @access public
	 * @param string $local Path to local file to save remote file as
	 * @param string $remote Path to remote file to get on the FTP server
	 * @return boolean True if successful
	 */
	function get($local, $remote) {

		// Determine file type
		$mode = $this->_findMode($remote);

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			// turn passive mode on
			if (@ftp_pasv($this->_conn, true) === false) {
				JError::raiseWarning('36', 'JFTP::get: Unable to use passive mode' );
				return false;
			}

			if (@ftp_get($this->_conn, $local, $remote, $mode) === false) {
				JError::raiseWarning('35', 'JFTP::get: Bad response' );
				return false;
			}
			return true;
		}

		$this->_mode($mode);

		// Check to see if the local file can be opened for writing
		$fp = fopen($local, "wb");
		if (!$fp) {
			JError::raiseWarning('38', 'JFTP::get: Unable to open local file for writing', 'Local path: '.$local );
			return false;
		}

		// Start passive mode
		if (!$this->_passive()) {
			JError::raiseWarning('36', 'JFTP::get: Unable to use passive mode' );
			return false;
		}

		if (!$this->_putCmd('RETR '.$remote, array (150, 125))) {
			@ fclose($this->_dataconn);
			JError::raiseWarning('35', 'JFTP::get: Bad response', 'Server response: '.$this->_response.' [Expected: 150 or 125] Path sent: '.$remote );
			return false;
		}

		// Read data from data port connection and add to the buffer
		while (!feof($this->_dataconn)) {
			$buffer = fread($this->_dataconn, 4096);
			$ret = fwrite($fp, $buffer, 4096);
		}

		// Close the data port connection and file pointer
		fclose($this->_dataconn);
		fclose($fp);

		if (!$this->_verifyResponse(226)) {
			JError::raiseWarning('37', 'JFTP::get: Transfer Failed', 'Server response: '.$this->_response.' [Expected: 226] Path sent: '.$remote );
			return false;
		}

		return true;
	}

	/**
	 * Method to store a file to the FTP server
	 *
	 * @access public
	 * @param string $local Path to local file to store on the FTP server
	 * @param string $remote FTP path to file to create
	 * @return boolean True if successful
	 */
	function store($local, $remote = null) {

		// If remote file not given, use the filename of the local file in the current
		// working directory
		if ($remote == null) {
			$remote = basename($local);
		}

		// Determine file type
		$mode = $this->_findMode($remote);

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			// turn passive mode on
			if (@ftp_pasv($this->_conn, true) === false) {
				JError::raiseWarning('36', 'JFTP::store: Unable to use passive mode' );
				return false;
			}

			if (@ftp_put($this->_conn, $remote, $local, $mode) === false) {
				JError::raiseWarning('35', 'JFTP::store: Bad response' );
				return false;
			}
			return true;
		}

		$this->_mode($mode);

		// Check to see if the local file exists and open for reading if so
		if (@ file_exists($local)) {
			$fp = fopen($local, "rb");
			if (!$fp) {
				JError::raiseWarning('38', 'JFTP::store: Unable to open local file for reading', 'Local path: '.$local );
				return false;
			}
		} else {
			JError::raiseWarning('38', 'JFTP::store: Unable to find local path', 'Local path: '.$local );
			return false;
		}

		// Start passive mode
		if (!$this->_passive()) {
			@ fclose($fp);
			JError::raiseWarning('36', 'JFTP::store: Unable to use passive mode' );
			return false;
		}

		// Send store command to the FTP server
		if (!$this->_putCmd('STOR '.$remote, array (150, 125))) {
			@ fclose($fp);
			@ fclose($this->_dataconn);
			JError::raiseWarning('35', 'JFTP::store: Bad response', 'Server response: '.$this->_response.' [Expected: 150 or 125] Path sent: '.$remote );
			return false;
		}

		// Do actual file transfer, read local file and write to data port connection
		while (!feof($fp)) {
			$line = fread($fp, 4096);
			do {
				if (($result = @ fwrite($this->_dataconn, $line)) === false) {
					JError::raiseWarning('37', 'JFTP::store: Unable to write to data port socket' );
					return false;
				}
				$line = substr($line, $result);
			} while ($line != "");
		}

		fclose($fp);
		fclose($this->_dataconn);

		if (!$this->_verifyResponse(226)) {
			JError::raiseWarning('37', 'JFTP::store: Transfer Failed', 'Server response: '.$this->_response.' [Expected: 226] Path sent: '.$remote );
			return false;
		}

		return true;
	}

	/**
	 * Method to write a string to the FTP server
	 *
	 * @access public
	 * @param string $remote FTP path to file to write to
	 * @param string $buffer Contents to write to the FTP server
	 * @return boolean True if successful
	 */
	function write($remote, $buffer) {

		// Determine file type
		$mode = $this->_findMode($remote);

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			// turn passive mode on
			if (@ftp_pasv($this->_conn, true) === false) {
				JError::raiseWarning('36', 'JFTP::write: Unable to use passive mode' );
				return false;
			}

			$tmp = fopen('buffer://tmp', 'br+');
			fwrite($tmp, $buffer);
			rewind($tmp);
			if (@ftp_fput($this->_conn, $remote, $tmp, $mode) === false) {
				fclose($tmp);
				JError::raiseWarning('35', 'JFTP::write: Bad response' );
				return false;
			}
			fclose($tmp);
			return true;
		}

		// First we need to set the transfer mode
		$this->_mode($mode);

		// Start passive mode
		if (!$this->_passive()) {
			JError::raiseWarning('36', 'JFTP::write: Unable to use passive mode' );
			return false;
		}

		// Send store command to the FTP server
		if (!$this->_putCmd('STOR '.$remote, array (150, 125))) {
			JError::raiseWarning('35', 'JFTP::write: Bad response', 'Server response: '.$this->_response.' [Expected: 150 or 125] Path sent: '.$remote );
			@ fclose($this->_dataconn);
			return false;
		}

		// Write buffer to the data connection port
		do {
			if (($result = @ fwrite($this->_dataconn, $buffer)) === false) {
				JError::raiseWarning('37', 'JFTP::write: Unable to write to data port socket' );
				return false;
			}
			$buffer = substr($buffer, $result);
		} while ($buffer != "");

		// Close the data connection port [Data transfer complete]
		fclose($this->_dataconn);

		// Verify that the server recieved the transfer
		if (!$this->_verifyResponse(226)) {
			JError::raiseWarning('37', 'JFTP::write: Transfer Failed', 'Server response: '.$this->_response.' [Expected: 226] Path sent: '.$remote );
			return false;
		}

		return true;
	}

	/**
	 * Method to list the filenames of the contents of a directory on the FTP server
	 *
	 * Note: Some servers also return folder names. However, to be sure to list folders on all
	 * servers, you should use listDetails() instead, if you also need to deal with folders
	 *
	 * @access public
	 * @param string $path Path local file to store on the FTP server
	 * @return string Directory listing
	 */
	function listNames($path = null) {

		// Initialize variables
		$data = null;

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			// turn passive mode on
			if (@ftp_pasv($this->_conn, true) === false) {
				JError::raiseWarning('36', 'JFTP::listNames: Unable to use passive mode' );
				return false;
			}

			if (($list = @ftp_nlist($this->_conn,$path)) === false) {
				// Workaround for empty directories on some servers
				if ($this->listDetails($path, 'files') === array()) {
					return array();
				}
				JError::raiseWarning('35', 'JFTP::listNames: Bad response' );
				return false;
			}
			$list = preg_replace('#^'.preg_quote($path, '#').'[/\\\\]?#', '', $list);
			if ($keys = array_merge(array_keys($list, '.'), array_keys($list, '..'))) {
				foreach ($keys as $key) {
					unset($list[$key]);
				}
			}
			return $list;
		}

		/*
		 * If a path exists, prepend a space
		 */
		if ($path != null) {
			$path = ' ' . $path;
		}

		// Start passive mode
		if (!$this->_passive()) {
			JError::raiseWarning('36', 'JFTP::listNames: Unable to use passive mode' );
			return false;
		}

		if (!$this->_putCmd('NLST'.$path, array (150, 125))) {
			@ fclose($this->_dataconn);
			// Workaround for empty directories on some servers
			if ($this->listDetails($path, 'files') === array()) {
				return array();
			}
			JError::raiseWarning('35', 'JFTP::listNames: Bad response', 'Server response: '.$this->_response.' [Expected: 150 or 125] Path sent: '.$path );
			return false;
		}

		// Read in the file listing.
		while (!feof($this->_dataconn)) {
			$data .= fread($this->_dataconn, 4096);
		}
		fclose($this->_dataconn);

		// Everything go okay?
		if (!$this->_verifyResponse(226)) {
			JError::raiseWarning('37', 'JFTP::listNames: Transfer Failed', 'Server response: '.$this->_response.' [Expected: 226] Path sent: '.$path );
			return false;
		}

		$data = preg_split("/[".CRLF."]+/", $data, -1, PREG_SPLIT_NO_EMPTY);
		$data = preg_replace('#^'.preg_quote(substr($path, 1), '#').'[/\\\\]?#', '', $data);
		if ($keys = array_merge(array_keys($data, '.'), array_keys($data, '..'))) {
			foreach ($keys as $key) {
				unset($data[$key]);
			}
		}
		return $data;
	}

	/**
	 * Method to list the contents of a directory on the FTP server
	 *
	 * @access public
	 * @param string $path Path local file to store on the FTP server
	 * @param string $type Return type [raw|all|folders|files]
	 * @param boolean $search Recursively search subdirectories
	 * @return mixed : if $type is raw: string Directory listing, otherwise array of string with file-names
	 */
	function listDetails($path = null, $type = 'all') {

		// Initialize variables
		$dir_list = array();
		$data = null;
		$regs = null;
		// TODO: Deal with recurse -- nightmare
		// For now we will just set it to false
		$recurse = false;

		// If native FTP support is enabled lets use it...
		if (FTP_NATIVE) {
			// turn passive mode on
			if (@ftp_pasv($this->_conn, true) === false) {
				JError::raiseWarning('36', 'JFTP::listDetails: Unable to use passive mode' );
				return false;
			}

			if (($contents = @ftp_rawlist($this->_conn, $path)) === false) {
				JError::raiseWarning('35', 'JFTP::listDetails: Bad response' );
				return false;
			}
		} else {
			// Non Native mode

			// Start passive mode
			if (!$this->_passive()) {
				JError::raiseWarning('36', 'JFTP::listDetails: Unable to use passive mode' );
				return false;
			}

			// If a path exists, prepend a space
			if ($path != null) {
				$path = ' ' . $path;
			}

			// Request the file listing
			if (!$this->_putCmd(($recurse == true) ? 'LIST -R' : 'LIST'.$path, array (150, 125))) {
				JError::raiseWarning('35', 'JFTP::listDetails: Bad response', 'Server response: '.$this->_response.' [Expected: 150 or 125] Path sent: '.$path );
				@ fclose($this->_dataconn);
				return false;
			}

			// Read in the file listing.
			while (!feof($this->_dataconn)) {
				$data .= fread($this->_dataconn, 4096);
			}
			fclose($this->_dataconn);

			// Everything go okay?
			if (!$this->_verifyResponse(226)) {
				JError::raiseWarning('37', 'JFTP::listDetails: Transfer Failed', 'Server response: '.$this->_response.' [Expected: 226] Path sent: '.$path );
				return false;
			}

			$contents = explode(CRLF, $data);
		}

		// If only raw output is requested we are done
		if ($type == 'raw') {
			return $data;
		}

		// If we received the listing of an emtpy directory, we are done as well
		if (empty($contents[0])) {
			return $dir_list;
		}

		// If the server returned the number of results in the first response, let's dump it
		if (strtolower(substr($contents[0], 0, 6)) == 'total ') {
			array_shift($contents);
			if (!isset($contents[0]) || empty($contents[0])) {
				return $dir_list;
			}
		}

		// Regular expressions for the directory listing parsing
		$regexps['UNIX'] = '([-dl][rwxstST-]+).* ([0-9]*) ([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{1,2}:[0-9]{2})|[0-9]{4}) (.+)';
		$regexps['MAC'] = '([-dl][rwxstST-]+).* ?([0-9 ]* )?([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{2}:[0-9]{2})|[0-9]{4}) (.+)';
		$regexps['WIN'] = '([0-9]{2})-([0-9]{2})-([0-9]{2}) +([0-9]{2}):([0-9]{2})(AM|PM) +([0-9]+|<DIR>) +(.+)';

		// Find out the format of the directory listing by matching one of the regexps
		$osType = null;
		foreach ($regexps as $k=>$v) {
			if (ereg($v, $contents[0])) {
				$osType = $k;
				$regexp = $v;
				break;
			}
		}
		if (!$osType) {
			JError::raiseWarning('SOME_ERROR_CODE', 'JFTP::listDetails: Unrecognized directory listing format' );
			return false;
		}

		/*
		 * Here is where it is going to get dirty....
		 */
		if ($osType == 'UNIX') {
			foreach ($contents as $file) {
				$tmp_array = null;
				if (ereg($regexp, $file, $regs)) {
					$fType = (int) strpos("-dl", $regs[1] { 0 });
					//$tmp_array['line'] = $regs[0];
					$tmp_array['type'] = $fType;
					$tmp_array['rights'] = $regs[1];
					//$tmp_array['number'] = $regs[2];
					$tmp_array['user'] = $regs[3];
					$tmp_array['group'] = $regs[4];
					$tmp_array['size'] = $regs[5];
					$tmp_array['date'] = date("m-d", strtotime($regs[6]));
					$tmp_array['time'] = $regs[7];
					$tmp_array['name'] = $regs[9];
				}
				// If we just want files, do not add a folder
				if ($type == 'files' && $tmp_array['type'] == 1) {
					continue;
				}
				// If we just want folders, do not add a file
				if ($type == 'folders' && $tmp_array['type'] == 0) {
					continue;
				}
				if (is_array($tmp_array) && $tmp_array['name'] != '.' && $tmp_array['name'] != '..') {
					$dir_list[] = $tmp_array;
				}
			}
		}
		elseif ($osType == 'MAC') {
			foreach ($contents as $file) {
				$tmp_array = null;
				if (ereg($regexp, $file, $regs)) {
					$fType = (int) strpos("-dl", $regs[1] { 0 });
					//$tmp_array['line'] = $regs[0];
					$tmp_array['type'] = $fType;
					$tmp_array['rights'] = $regs[1];
					//$tmp_array['number'] = $regs[2];
					$tmp_array['user'] = $regs[3];
					$tmp_array['group'] = $regs[4];
					$tmp_array['size'] = $regs[5];
					$tmp_array['date'] = date("m-d", strtotime($regs[6]));
					$tmp_array['time'] = $regs[7];
					$tmp_array['name'] = $regs[9];
				}
				// If we just want files, do not add a folder
				if ($type == 'files' && $tmp_array['type'] == 1) {
					continue;
				}
				// If we just want folders, do not add a file
				if ($type == 'folders' && $tmp_array['type'] == 0) {
					continue;
				}
				if (is_array($tmp_array) && $tmp_array['name'] != '.' && $tmp_array['name'] != '..') {
					$dir_list[] = $tmp_array;
				}
			}
		} else {
			foreach ($contents as $file) {
				$tmp_array = null;
				if (ereg($regexp, $file, $regs)) {
					$fType = (int) ($regs[7] == '<DIR>');
					$timestamp = strtotime("$regs[3]-$regs[1]-$regs[2] $regs[4]:$regs[5]$regs[6]");
					//$tmp_array['line'] = $regs[0];
					$tmp_array['type'] = $fType;
					$tmp_array['rights'] = '';
					//$tmp_array['number'] = 0;
					$tmp_array['user'] = '';
					$tmp_array['group'] = '';
					$tmp_array['size'] = (int) $regs[7];
					$tmp_array['date'] = date('m-d', $timestamp);
					$tmp_array['time'] = date('H:i', $timestamp);
					$tmp_array['name'] = $regs[8];
				}
				// If we just want files, do not add a folder
				if ($type == 'files' && $tmp_array['type'] == 1) {
					continue;
				}
				// If we just want folders, do not add a file
				if ($type == 'folders' && $tmp_array['type'] == 0) {
					continue;
				}
				if (is_array($tmp_array) && $tmp_array['name'] != '.' && $tmp_array['name'] != '..') {
					$dir_list[] = $tmp_array;
				}
			}
		}

		return $dir_list;
	}

	/**
	 * Send command to the FTP server and validate an expected response code
	 *
	 * @access private
	 * @param string $cmd Command to send to the FTP server
	 * @param mixed $expected Integer response code or array of integer response codes
	 * @return boolean True if command executed successfully
	 */
	function _putCmd($cmd, $expectedResponse) {

		// Make sure we have a connection to the server
		if (!is_resource($this->_conn)) {
			JError::raiseWarning('31', 'JFTP::_putCmd: Not connected to the control port' );
			return false;
		}

		// Send the command to the server
		if (!fwrite($this->_conn, $cmd."\r\n")) {
			JError::raiseWarning('32', 'JFTP::_putCmd: Unable to send command: '.$cmd );
		}

		return $this->_verifyResponse($expectedResponse);
	}

	/**
	 * Verify the response code from the server and log response if flag is set
	 *
	 * @access private
	 * @param mixed $expected Integer response code or array of integer response codes
	 * @return boolean True if response code from the server is expected
	 */
	function _verifyResponse($expected) {

		// Initialize variables
		$parts = null;

		// Wait for a response from the server, but timeout after the set time limit
		$endTime = time() + $this->_timeout;
		$this->_response = '';
		do {
			$this->_response .= fgets($this->_conn, 4096);
		} while (!preg_match("/^([0-9]{3})(-(.*".CRLF.")+\\1)? [^".CRLF."]+".CRLF."$/", $this->_response, $parts) && time() < $endTime);

		// Catch a timeout or bad response
		if (!isset($parts[1])) {
			JError::raiseWarning('SOME_ERROR_CODE', 'JFTP::_verifyResponse: Timeout or unrecognized response while waiting for a response from the server', 'Server response: '.$this->_response);
			return false;
		}

		// Separate the code from the message
		$this->_responseCode = $parts[1];
		$this->_responseMsg = $parts[0];

		// Did the server respond with the code we wanted?
		if (is_array($expected)) {
			if (in_array($this->_responseCode, $expected)) {
				$retval = true;
			} else {
				$retval = false;
			}
		} else {
			if ($this->_responseCode == $expected) {
				$retval = true;
			} else {
				$retval = false;
			}
		}
		return $retval;
	}

	/**
	 * Set server to passive mode and open a data port connection
	 *
	 * @access private
	 * @return boolean True if successful
	 */
	function _passive() {

		//Initialize variables
		$match = array();
		$parts = array();
		$errno = null;
		$err = null;

		// Make sure we have a connection to the server
		if (!is_resource($this->_conn)) {
			JError::raiseWarning('31', 'JFTP::_passive: Not connected to the control port' );
			return false;
		}

		// Request a passive connection - this means, we'll talk to you, you don't talk to us.
		@ fwrite($this->_conn, "PASV\r\n");

		// Wait for a response from the server, but timeout after the set time limit
		$endTime = time() + $this->_timeout;
		$this->_response = '';
		do {
			$this->_response .= fgets($this->_conn, 4096);
		} while (!preg_match("/^([0-9]{3})(-(.*".CRLF.")+\\1)? [^".CRLF."]+".CRLF."$/", $this->_response, $parts) && time() < $endTime);

		// Catch a timeout or bad response
		if (!isset($parts[1])) {
			JError::raiseWarning('SOME_ERROR_CODE', 'JFTP::_passive: Timeout or unrecognized response while waiting for a response from the server', 'Server response: '.$this->_response);
			return false;
		}

		// Separate the code from the message
		$this->_responseCode = $parts[1];
		$this->_responseMsg = $parts[0];

		// If it's not 227, we weren't given an IP and port, which means it failed.
		if ($this->_responseCode != '227') {
			JError::raiseWarning('36', 'JFTP::_passive: Unable to obtain IP and port for data transfer', 'Server response: '.$this->_responseMsg);
			return false;
		}

		// Snatch the IP and port information, or die horribly trying...
		if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $this->_responseMsg, $match) == 0) {
			JError::raiseWarning('36', 'JFTP::_passive: IP and port for data transfer not valid', 'Server response: '.$this->_responseMsg);
			return false;
		}

		// This is pretty simple - store it for later use ;).
		$this->_pasv = array ('ip' => $match[1].'.'.$match[2].'.'.$match[3].'.'.$match[4], 'port' => $match[5] * 256 + $match[6]);

		// Connect, assuming we've got a connection.
		$this->_dataconn =  @fsockopen($this->_pasv['ip'], $this->_pasv['port'], $errno, $err, $this->_timeout);
		if (!$this->_dataconn) {
			JError::raiseWarning('30', 'JFTP::_passive: Could not connect to host '.$this->_pasv['ip'].' on port '.$this->_pasv['port'].'.  Socket error number '.$errno.' and error message: '.$err );
			return false;
		}

		// Set the timeout for this connection
		socket_set_timeout($this->_conn, $this->_timeout);

		return true;
	}

	/**
	 * Method to find out the correct transfer mode for a specific file
	 *
	 * @access	private
	 * @param	string	$fileName	Name of the file
	 * @return	integer	Transfer-mode for this filetype [FTP_ASCII|FTP_BINARY]
	 */
	function _findMode($fileName) {
		if ($this->_type == FTP_AUTOASCII) {
			$dot = strrpos($fileName, '.') + 1;
			$ext = substr($fileName, $dot);

			if (in_array($ext, $this->_autoAscii)) {
				$mode = FTP_ASCII;
			} else {
				$mode = FTP_BINARY;
			}
		} elseif ($this->_type == FTP_ASCII) {
			$mode = FTP_ASCII;
		} else {
			$mode = FTP_BINARY;
		}
		return $mode;
	}

	/**
	 * Set transfer mode
	 *
	 * @access private
	 * @param int $mode Integer representation of data transfer mode [1:Binary|0:Ascii]
	 *  Defined constants can also be used [FTP_BINARY|FTP_ASCII]
	 * @return boolean True if successful
	 */
	function _mode($mode) {
		if ($mode == FTP_BINARY) {
			if (!$this->_putCmd("TYPE I", 200)) {
				JError::raiseWarning('35', 'JFTP::_mode: Bad response', 'Server response: '.$this->_response.' [Expected: 200] Mode sent: Binary' );
				return false;
			}
		} else {
			if (!$this->_putCmd("TYPE A", 200)) {
				JError::raiseWarning('35', 'JFTP::_mode: Bad response', 'Server response: '.$this->_response.' [Expected: 200] Mode sent: Ascii' );
				return false;
			}
		}
		return true;
	}
}

/***********************************************************************************************
 * 000 File
 ***********************************************************************************************/

/**
 * A File handling class
 *
 * @static
 * @author		Louis Landry <louis.landry@joomla.org>
 * @package 	Joomla.Framework
 * @subpackage	FileSystem
 * @since		1.5
 */
class JFile
{
	/**
	 * Gets the extension of a file name
	 *
	 * @param string $file The file name
	 * @return string The file extension
	 * @since 1.5
	 */
	function getExt($file) {
		$dot = strrpos($file, '.') + 1;
		return substr($file, $dot);
	}

	/**
	 * Strips the last extension off a file name
	 *
	 * @param string $file The file name
	 * @return string The file name without the extension
	 * @since 1.5
	 */
	function stripExt($file) {
		return preg_replace('#\.[^.]*$#', '', $file);
	}

	/**
	 * Makes file name safe to use
	 *
	 * @param string $file The name of the file [not full path]
	 * @return string The sanitised string
	 * @since 1.5
	 */
	function makeSafe($file) {
		$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\.\_\- ]#', '#^\.#');
		return preg_replace($regex, '', $file);
	}

	/**
	 * Delete a file or array of files
	 *
	 * @param mixed $file The file name or an array of file names
	 * @return boolean  True on success
	 * @since 1.5
	 */
	function delete($file)
	{
		// Initialize variables
		jimport('joomla.client.helper');
		$FTPOptions = JClientHelper::getCredentials('ftp');

		if (is_array($file)) {
			$files = $file;
		} else {
			$files[] = $file;
		}

		// Do NOT use ftp if it is not enabled
		if ($FTPOptions['enabled'] == 1)
		{
			// Connect the FTP client
			jimport('joomla.client.ftp');
			$ftp = & JFTP::getInstance($FTPOptions['host'], $FTPOptions['port'], null, $FTPOptions['user'], $FTPOptions['pass']);
		}

		foreach ($files as $file)
		{
			$file = JPath::clean($file);

			// Try making the file writeable first. If it's read-only, it can't be deleted
			// on Windows, even if the parent folder is writeable
			@chmod($file, 0777);

			// In case of restricted permissions we zap it one way or the other
			// as long as the owner is either the webserver or the ftp
			if (@unlink($file)) {
				// Do nothing
			} elseif ($FTPOptions['enabled'] == 1) {
				$file = JPath::clean(str_replace(JPATH_ROOT, $FTPOptions['root'], $file), '/');
				if (!$ftp->delete($file)) {
					// FTP connector throws an error
					return false;
				}
			} else {
				$filename	= basename($file);
				JError::raiseWarning('SOME_ERROR_CODE', JText::_('Delete failed') . ": '$filename'");
				return false;
			}
		}

		return true;
	}


	/**
	 * Read the contents of a file
	 *
	 * @param string $filename The full file path
	 * @param boolean $incpath Use include path
	 * @param int $amount Amount of file to read
	 * @param int $chunksize Size of chunks to read
	 * @param int $offset Offset of the file
	 * @return mixed Returns file contents or boolean False if failed
	 * @since 1.5
	 */
	function read($filename, $incpath = false, $amount = 0, $chunksize = 8192, $offset = 0)
	{
		// Initialize variables
		$data = null;
		if($amount && $chunksize > $amount) { $chunksize = $amount; }
		if (false === $fh = fopen($filename, 'rb', $incpath)) {
			JError::raiseWarning(21, 'JFile::read: '.JText::_('Unable to open file') . ": '$filename'");
			return false;
		}
		clearstatcache();
		if($offset) fseek($fh, $offset);
		if ($fsize = @ filesize($filename)) {
			if($amount && $fsize > $amount) {
				$data = fread($fh, $amount);
			} else {
				$data = fread($fh, $fsize);
			}
		} else {
			$data = '';
			$x = 0;
			// While its:
			// 1: Not the end of the file AND
			// 2a: No Max Amount set OR
			// 2b: The length of the data is less than the max amount we want
			while (!feof($fh) && (!$amount || strlen($data) < $amount)) {
				$data .= fread($fh, $chunksize);
			}
		}
		fclose($fh);

		return $data;
	}

	/**
	 * Write contents to a file
	 *
	 * @param string $file The full file path
	 * @param string $buffer The buffer to write
	 * @return boolean True on success
	 * @since 1.5
	 */
	function write($file, $buffer)
	{
		echo '<span>Creating file '.$file.'</span>';
		// Initialize variables
		//jimport('joomla.client.helper');
		$FTPOptions = JClientHelper::getCredentials('ftp');

		// If the destination directory doesn't exist we need to create it
		if (!file_exists(dirname($file))) {
			//jimport('joomla.filesystem.folder');
			JFolder::create(dirname($file));
		}

		if ($FTPOptions['enabled'] == 1) {
			// Connect the FTP client
			//jimport('joomla.client.ftp');
			$ftp = & JFTP::getInstance($FTPOptions['host'], $FTPOptions['port'], null, $FTPOptions['user'], $FTPOptions['pass']);

			// Translate path for the FTP account and use FTP write buffer to file
			$file = JPath::clean(str_replace(JPATH_ROOT, $FTPOptions['root'], $file), '/');
			$ret = $ftp->write($file, $buffer);
		} else {
			$file = JPath::clean($file);
			$ret = file_put_contents($file, $buffer);
		}
		echo '... <span style="color:#008000;font-weight:bold">OK</span><br />';
		return $ret;
	}

	/**
	 * Moves an uploaded file to a destination folder
	 *
	 * @param string $src The name of the php (temporary) uploaded file
	 * @param string $dest The path (including filename) to move the uploaded file to
	 * @return boolean True on success
	 * @since 1.5
	 */
	function upload($src, $dest)
	{
		// Initialize variables
		jimport('joomla.client.helper');
		$FTPOptions = JClientHelper::getCredentials('ftp');
		$ret		= false;

		// Ensure that the path is valid and clean
		$dest = JPath::clean($dest);

		// Create the destination directory if it does not exist
		$baseDir = dirname($dest);
		if (!file_exists($baseDir)) {
			jimport('joomla.filesystem.folder');
			JFolder::create($baseDir);
		}

		if ($FTPOptions['enabled'] == 1) {
			// Connect the FTP client
			jimport('joomla.client.ftp');
			$ftp = & JFTP::getInstance($FTPOptions['host'], $FTPOptions['port'], null, $FTPOptions['user'], $FTPOptions['pass']);

			//Translate path for the FTP account
			$dest = JPath::clean(str_replace(JPATH_ROOT, $FTPOptions['root'], $dest), '/');

			// Copy the file to the destination directory
			if ($ftp->store($src, $dest)) {
				$ftp->chmod($dest, 0777);
				$ret = true;
			} else {
				JError::raiseWarning(21, JText::_('WARNFS_ERR02'));
			}
		} else {
			if (is_writeable($baseDir) && move_uploaded_file($src, $dest)) { // Short circuit to prevent file permission errors
				if (JPath::setPermissions($dest)) {
					$ret = true;
				} else {
					JError::raiseWarning(21, JText::_('WARNFS_ERR01'));
				}
			} else {
				JError::raiseWarning(21, JText::_('WARNFS_ERR02'));
			}
		}
		return $ret;
	}

	/**
	 * Wrapper for the standard file_exists function
	 *
	 * @param string $file File path
	 * @return boolean True if path is a file
	 * @since 1.5
	 */
	function exists($file)
	{
		return is_file(JPath::clean($file));
	}

	/**
	 * Returns the name, sans any path
	 *
	 * param string $file File path
	 * @return string filename
	 * @since 1.5
	 */
	function getName($file) {
		$slash = strrpos($file, DS) + 1;
		return substr($file, $slash);
	}
}


 
/***********************************************************************************************
* 000 Folder
***********************************************************************************************/

/**
 * A Folder handling class
 *
 * @static
 * @author		Louis Landry <louis.landry@joomla.org>
 * @package 	Joomla.Framework
 * @subpackage	FileSystem
 * @since		1.5
 */
class JFolder
{
	

	/**
	 * Create a folder -- and all necessary parent folders
	 *
	 * @param string $path A path to create from the base path
	 * @param int $mode Directory permissions to set for folders created
	 * @return boolean True if successful
	 * @since 1.5
	 */
	function create($path = '', $mode = 0755)
	{
		echo '<span>Creating folder '.$path.'</span>';
		// Initialize variables
		//jimport('joomla.client.helper');
		//$FTPOptions = JClientHelper::getCredentials('ftp');
		static $nested = 0;
		// Check to make sure the path valid and clean
		$path = JPath::clean($path);
		// Check if parent dir exists
		$parent = dirname($path);
		
		if (!JFolder::exists($parent)) {
			// Prevent infinite loops!
			
			$nested++;
			if (($nested > 20) || ($parent == $path)) {
				echo '... <span style="color:#fc0000;font-weight:bold">Error</span><br />';
				JError::raiseWarning('SOME_ERROR_CODE', 'JFolder::create: Infinite loop detected');
				$nested--;
				
				return false;
			}

			// Create the parent directory
			if (JFolder::create($parent, $mode) !== true) {
				// JFolder::create throws an error
				$nested--;
				echo '... <span style="color:#fc0000;font-weight:bold">Error</span><br />';
				return false;
			}

			// OK, parent directory has been created
			$nested--;
			echo '... <span style="color:#008000;font-weight:bold">OK</span><br />';
		}

		// Check if dir already exists
		if (JFolder::exists($path)) {
			echo '... <span style="color:#008000;font-weight:bold">Folder exists</span><br />';
			return true;
		}

		// Check for safe mode
		if (FTP_ENABLED == 1) {
			// Connect the FTP client
			//jimport('joomla.client.ftp');
			$ftp = & JFTP::getInstance(FTP_HOST, FTP_PORT, null, FTP_USER, FTP_PASS);

			// Translate path to FTP path
			$path = JPath::clean(str_replace(JPATH_ROOT, FTP_ROOT, $path), '/');
			
			$ret = $ftp->mkdir($path);
			$ftp->chmod($path, $mode);
			
		}
		else
		{
			// We need to get and explode the open_basedir paths
			$obd = ini_get('open_basedir');

			// If open_basedir is set we need to get the open_basedir that the path is in
			if ($obd != null)
			{
				
				if (JPATH_ISWIN) {
					$obdSeparator = ";";
				} else {
					$obdSeparator = ":";
				}
				// Create the array of open_basedir paths
				$obdArray = explode($obdSeparator, $obd);
				$inOBD = false;
				
				// Iterate through open_basedir paths looking for a match
				foreach ($obdArray as $test) {
					$test = JPath::clean($test);
					if (strpos($path, $test) === 0) {
						$obdpath = $test;
						$inOBD = true;
						break;
					}
				}
				
				if ($inOBD == false) {
					// Return false for JFolder::create because the path to be created is not in open_basedir
					echo '... <span style="color:#fc0000;font-weight:bold">Error</span><br />';
					JError::raiseWarning('SOME_ERROR_CODE', 'JFolder::create: Path not in open_basedir paths');
					return false;
				}
			}

			// First set umask
			$origmask = @ umask(0);

			// Create the path
			if (!$ret = @mkdir($path, $mode)) {
				@ umask($origmask);
				echo '... <span style="color:#fc0000;font-weight:bold">Error</span><br />';
				JError::raiseWarning('SOME_ERROR_CODE', 'JFolder::create: Could not create directory', 'Path: '.$path);
				
				return false;
			}

			// Reset umask
			@ umask($origmask);
		}
		echo '... <span style="color:#008000;font-weight:bold">OK</span><br />';
		return $ret;
	}

	/**
	 * Delete a folder
	 *
	 * @param string $path The path to the folder to delete
	 * @return boolean True on success
	 * @since 1.5
	 */
	function delete($path)
	{
		// Sanity check
		if ( ! $path ) {
			// Bad programmer! Bad Bad programmer!
			JError::raiseWarning(500, 'JFolder::delete: '.JText::_('Attempt to delete base directory') );
			return false;
		}

		// Initialize variables
		jimport('joomla.client.helper');
		$FTPOptions = JClientHelper::getCredentials('ftp');

		// Check to make sure the path valid and clean
		$path = JPath::clean($path);

		// Is this really a folder?
		if (!is_dir($path)) {
			JError::raiseWarning(21, 'JFolder::delete: '.JText::_('Path is not a folder').' '.$path);
			return false;
		}

		// Remove all the files in folder if they exist
		$files = JFolder::files($path, '.', false, true, array());
		if (count($files)) {
			jimport('joomla.filesystem.file');
			if (JFile::delete($files) !== true) {
				// JFile::delete throws an error
				return false;
			}
		}

		// Remove sub-folders of folder
		$folders = JFolder::folders($path, '.', false, true, array());
		foreach ($folders as $folder) {
			if (JFolder::delete($folder) !== true) {
				// JFolder::delete throws an error
				return false;
			}
		}

		if ($FTPOptions['enabled'] == 1) {
			// Connect the FTP client
			jimport('joomla.client.ftp');
			$ftp = & JFTP::getInstance($FTPOptions['host'], $FTPOptions['port'], null, $FTPOptions['user'], $FTPOptions['pass']);
		}

		// In case of restricted permissions we zap it one way or the other
		// as long as the owner is either the webserver or the ftp
		if (@rmdir($path)) {
			$ret = true;
		} elseif ($FTPOptions['enabled'] == 1) {
			// Translate path and delete
			$path = JPath::clean(str_replace(JPATH_ROOT, $FTPOptions['root'], $path), '/');
			// FTP connector throws an error
			$ret = $ftp->delete($path);
		} else {
			JError::raiseWarning('SOME_ERROR_CODE', 'JFolder::delete: '.JText::_('Could not delete folder').' '.$path);
			$ret = false;
		}

		return $ret;
	}


	/**
	 * Wrapper for the standard file_exists function
	 *
	 * @param string $path Folder name relative to installation dir
	 * @return boolean True if path is a folder
	 * @since 1.5
	 */
	function exists($path)
	{
		return is_dir(JPath::clean($path));
	}

	/**
	 * Utility function to read the files in a folder
	 *
	 * @param	string	$path		The path of the folder to read
	 * @param	string	$filter		A filter for file names
	 * @param	mixed	$recurse	True to recursively search into sub-folders, or an integer to specify the maximum depth
	 * @param	boolean	$fullpath	True to return the full path to the file
	 * @param	array	$exclude	Array with names of files which should not be shown in the result
	 * @return	array	Files in the given folder
	 * @since 1.5
	 */
	function files($path, $filter = '.', $recurse = false, $fullpath = false, $exclude = array('.svn', 'CVS'))
	{
		// Initialize variables
		$arr = array ();

		// Check to make sure the path valid and clean
		$path = JPath::clean($path);

		// Is the path a folder?
		if (!is_dir($path)) {
			JError::raiseWarning(21, 'JFolder::files: '.JText::_('Path is not a folder').' '.$path);
			return false;
		}

		// read the source directory
		$handle = opendir($path);
		while (($file = readdir($handle)) !== false)
		{
			$dir = $path.DS.$file;
			$isDir = is_dir($dir);
			if (($file != '.') && ($file != '..') && (!in_array($file, $exclude))) {
				if ($isDir) {
					if ($recurse) {
						if (is_integer($recurse)) {
							$recurse--;
						}
						$arr2 = JFolder::files($dir, $filter, $recurse, $fullpath);
						$arr = array_merge($arr, $arr2);
					}
				} else {
					if (preg_match("/$filter/", $file)) {
						if ($fullpath) {
							$arr[] = $path.DS.$file;
						} else {
							$arr[] = $file;
						}
					}
				}
			}
		}
		closedir($handle);

		asort($arr);
		return $arr;
	}

	/**
	 * Utility function to read the folders in a folder
	 *
	 * @param	string	$path		The path of the folder to read
	 * @param	string	$filter		A filter for folder names
	 * @param	mixed	$recurse	True to recursively search into sub-folders, or an integer to specify the maximum depth
	 * @param	boolean	$fullpath	True to return the full path to the folders
	 * @param	array	$exclude	Array with names of folders which should not be shown in the result
	 * @return	array	Folders in the given folder
	 * @since 1.5
	 */
	function folders($path, $filter = '.', $recurse = false, $fullpath = false, $exclude = array('.svn', 'CVS'))
	{
		// Initialize variables
		$arr = array ();

		// Check to make sure the path valid and clean
		$path = JPath::clean($path);

		// Is the path a folder?
		if (!is_dir($path)) {
			JError::raiseWarning(21, 'JFolder::folder: '.JText::_('Path is not a folder').' '.$path);
			return false;
		}

		// read the source directory
		$handle = opendir($path);
		while (($file = readdir($handle)) !== false)
		{
			$dir = $path.DS.$file;
			$isDir = is_dir($dir);
			if (($file != '.') && ($file != '..') && (!in_array($file, $exclude)) && $isDir) {
				// removes SVN directores from list
				if (preg_match("/$filter/", $file)) {
					if ($fullpath) {
						$arr[] = $dir;
					} else {
						$arr[] = $file;
					}
				}
				if ($recurse) {
					if (is_integer($recurse)) {
						$recurse--;
					}
					$arr2 = JFolder::folders($dir, $filter, $recurse, $fullpath);
					$arr = array_merge($arr, $arr2);
				}
			}
		}
		closedir($handle);

		asort($arr);
		return $arr;
	}

	/**
	 * Lists folder in format suitable for tree display
	 *
	 * @access	public
	 * @param	string	$path		The path of the folder to read
	 * @param	string	$filter		A filter for folder names
	 * @param	integer	$maxLevel	The maximum number of levels to recursively read, default 3
	 * @param	integer	$level		The current level, optional
	 * @param	integer	$parent
	 * @return	array	Folders in the given folder
	 * @since	1.5
	 */
	function listFolderTree($path, $filter, $maxLevel = 3, $level = 0, $parent = 0)
	{
		$dirs = array ();
		if ($level == 0) {
			$GLOBALS['_JFolder_folder_tree_index'] = 0;
		}
		if ($level < $maxLevel) {
			$folders = JFolder::folders($path, $filter);
			// first path, index foldernames
			for ($i = 0, $n = count($folders); $i < $n; $i ++) {
				$id = ++ $GLOBALS['_JFolder_folder_tree_index'];
				$name = $folders[$i];
				$fullName = JPath::clean($path.DS.$name);
				$dirs[] = array ('id' => $id, 'parent' => $parent, 'name' => $name, 'fullname' => $fullName, 'relname' => str_replace(JPATH_ROOT, '', $fullName));
				$dirs2 = JFolder::listFolderTree($fullName, $filter, $maxLevel, $level +1, $id);
				$dirs = array_merge($dirs, $dirs2);
			}
		}
		return $dirs;
	}

	/**
	 * Makes path name safe to use
	 *
	 * @access	public
	 * @param	string $path The full path to sanitise
	 * @return	string The sanitised string
	 * @since	1.5
	 */
	function makeSafe($path)
	{
		$ds		= ( DS == '\\' ) ? '\\'.DS : DS;
		$regex = array('#[^A-Za-z0-9:\_\-'.$ds.' ]#');
		return preg_replace($regex, '', $path);
	}
} 

/***********************************************************************************************
* ZIP
***********************************************************************************************/


/**
 * @version		$Id:zip.php 6961 2007-03-15 16:06:53Z tcp $
 * @package		Joomla.Framework
 * @subpackage	FileSystem
 * @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

// Check to ensure this file is within the rest of the framework
//defined('JPATH_BASE') or die();

/**
 * ZIP format adapter for the JArchive class
 *
 * The ZIP compression code is partially based on code from:
 *   Eric Mueller <eric@themepark.com>
 *   http://www.zend.com/codex.php?id=535&single=1
 *
 *   Deins125 <webmaster@atlant.ru>
 *   http://www.zend.com/codex.php?id=470&single=1
 *
 * The ZIP compression date code is partially based on code from
 *   Peter Listiak <mlady@users.sourceforge.net>
 *
 * This class is inspired from and draws heavily in code and concept from the Compress package of
 * The Horde Project <http://www.horde.org>
 *
 * @contributor  Chuck Hagenbuch <chuck@horde.org>
 * @contributor  Michael Slusarz <slusarz@horde.org>
 * @contributor  Michael Cochrane <mike@graftonhall.co.nz>
 *
 * @author		Louis Landry <louis.landry@joomla.org>
 * @package 	Joomla.Framework
 * @subpackage	FileSystem
 * @since		1.5
 */
class JArchiveZip
{
	/**
	 * ZIP compression methods.
	 * @var array
	 */
	var $_methods = array (
		0x0 => 'None',
		0x1 => 'Shrunk',
		0x2 => 'Super Fast',
		0x3 => 'Fast',
		0x4 => 'Normal',
		0x5 => 'Maximum',
		0x6 => 'Imploded',
		0x8 => 'Deflated'
	);

	/**
	 * Beginning of central directory record.
	 * @var string
	 */
	var $_ctrlDirHeader = "\x50\x4b\x01\x02";

	/**
	 * End of central directory record.
	 * @var string
	 */
	var $_ctrlDirEnd = "\x50\x4b\x05\x06\x00\x00\x00\x00";

	/**
	 * Beginning of file contents.
	 * @var string
	 */
	var $_fileHeader = "\x50\x4b\x03\x04";

	/**
	 * ZIP file data buffer
	 * @var string
	 */
	var $_data = null;

	/**
	 * ZIP file metadata array
	 * @var array
	 */
	var $_metadata = null;

	/**
	 * Create a ZIP compressed file from an array of file data.
	 *
	 * @todo	Finish Implementation
	 *
	 * @access	public
	 * @param	string	$archive	Path to save archive
	 * @param	array	$files		Array of files to add to archive
	 * @param	array	$options	Compression options [unused]
	 * @return	boolean	True if successful
	 * @since	1.5
	 */
	 
	function create($archive, $files, $options = array ())
	{
		// Initialize variables
		$contents = array();
		$ctrldir  = array();

		foreach ($files as $file)
		{
			$this->_addToZIPFile($file, $contents, $ctrldir);
		}
		return $this->_createZIPFile($contents, $ctrldir, $archive);
	}

	/**
	 * Extract a ZIP compressed file to a given path
	 *
	 * @access	public
	 * @param	string	$archive		Path to ZIP archive to extract
	 * @param	string	$destination	Path to extract archive into
	 * @param	array	$options		Extraction options [unused]
	 * @return	boolean	True if successful
	 * @since	1.5
	 */
	function extract($archive, $destination, $options = array ())
	{		
	
		if ( ! is_file($archive) )
		{
			$this->set('error.message', 'Archive does not exist');
			return false;
		}

		if ($this->hasNativeSupport()) {
			return ($this->_extractNative($archive, $destination, $options))? true : JError::raiseWarning(100, $this->get('error.message'));
		} else {
			return ($this->_extract($archive, $destination, $options))? true : JError::raiseWarning(100, $this->get('error.message'));
		}
	}

	/**
	 * Method to determine if the server has native zip support for faster handling
	 *
	 * @access	public
	 * @return	boolean	True if php has native ZIP support
	 * @since	1.5
	 */
	function hasNativeSupport()
	{
		return (function_exists('zip_open') && function_exists('zip_read'));
	}

	/**
	 * Checks to see if the data is a valid ZIP file.
	 *
	 * @access	public
	 * @param	string	$data	ZIP archive data buffer
	 * @return	boolean	True if valid, false if invalid.
	 * @since	1.5
	 */
	function checkZipData(& $data) {
		if (strpos($data, $this->_fileHeader) === false) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Extract a ZIP compressed file to a given path using a php based algorithm that only requires zlib support
	 *
	 * @access	private
	 * @param	string	$archive		Path to ZIP archive to extract
	 * @param	string	$destination	Path to extract archive into
	 * @param	array	$options		Extraction options [unused]
	 * @return	boolean	True if successful
	 * @since	1.5
	 */
	function _extract($archive, $destination, $options)
	{
		// Initialize variables
		$this->_data = null;
		$this->_metadata = null;

		if (!extension_loaded('zlib')) {
			$this->set('error.message', 'Zlib Not Supported');
			return false;
		}

		if (!$this->_data = JFile::read($archive)) {
			$this->set('error.message', 'Unable to read archive');
			return false;
		}
		if (!$this->_getZipInfo($this->_data)) {
			return false;
		}

		for ($i=0,$n=count($this->_metadata);$i<$n;$i++) {
			if (substr($this->_metadata[$i]['name'], -1, 1) != '/' && substr($this->_metadata[$i]['name'], -1, 1) != '\\') {
				$buffer = $this->_getFileData($i);
				$path = JPath::clean($destination.DS.$this->_metadata[$i]['name']);
				// Make sure the destination folder exists
				if (!JFolder::create(dirname($path))) {
					$this->set('error.message', 'Unable to create destination');
					return false;
				}
				if (JFile::write($path, $buffer) === false) {
					$this->set('error.message', 'Unable to write entry');
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Extract a ZIP compressed file to a given path using native php api calls for speed
	 *
	 * @access	private
	 * @param	string	$archive		Path to ZIP archive to extract
	 * @param	string	$destination	Path to extract archive into
	 * @param	array	$options		Extraction options [unused]
	 * @return	boolean	True if successful
	 * @since	1.5
	 */
	function _extractNative($archive, $destination, $options)
	{
		if ($zip = zip_open($archive)) {
			if ($zip) {
				// Make sure the destination folder exists
				if (!JFolder::create($destination)) {
					$this->set('error.message', 'Unable to create destination');
					return false;
				}
				// Read files in the archive
				while ($file = zip_read($zip))
				{
					if (zip_entry_open($zip, $file, "r")) {
						if (substr(zip_entry_name($file), strlen(zip_entry_name($file)) - 1) != "/") {
							$buffer = zip_entry_read($file, zip_entry_filesize($file));
							if (JFile::write($destination.DS.zip_entry_name($file), $buffer) === false) {
								$this->set('error.message', 'Unable to write entry');
								return false;
							}
							zip_entry_close($file);
						}
					} else {
						$this->set('error.message', 'Unable to read entry');
						return false;
					}
				}
				zip_close($zip);
			}
		} else {
			$this->set('error.message', 'Unable to open archive');
			return false;
		}
		return true;
	}

	/**
	 * Get the list of files/data from a ZIP archive buffer.
	 *
	 * @access	private
	 * @param 	string	$data	The ZIP archive buffer.
	 * @return	array	Archive metadata array
	 * <pre>
	 * KEY: Position in zipfile
	 * VALUES: 'attr'    --  File attributes
	 *         'crc'     --  CRC checksum
	 *         'csize'   --  Compressed file size
	 *         'date'    --  File modification time
	 *         'name'    --  Filename
	 *         'method'  --  Compression method
	 *         'size'    --  Original file size
	 *         'type'    --  File type
	 * </pre>
	 * @since	1.5
	 */
	function _getZipInfo(& $data)
	{
		// Initialize variables
		$entries = array ();

		// Get details from Central directory structure.
		$fhStart = strpos($data, $this->_ctrlDirHeader);
		do {
			if (strlen($data) < $fhStart +31) {
				$this->set('error.message', 'Invalid ZIP data');
				return false;
			}
			$info = unpack('vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength', substr($data, $fhStart +10, 20));
			$name = substr($data, $fhStart +46, $info['Length']);

			$entries[$name] = array('attr' => null, 'crc' => sprintf("%08s", dechex($info['CRC32'] )), 'csize' => $info['Compressed'], 'date' => null, '_dataStart' => null, 'name' => $name, 'method' => $this->_methods[$info['Method']], '_method' => $info['Method'], 'size' => $info['Uncompressed'], 'type' => null);
			$entries[$name]['date'] = mktime((($info['Time'] >> 11) & 0x1f), (($info['Time'] >> 5) & 0x3f), (($info['Time'] << 1) & 0x3e), (($info['Time'] >> 21) & 0x07), (($info['Time'] >> 16) & 0x1f), ((($info['Time'] >> 25) & 0x7f) + 1980));

			if (strlen($data) < $fhStart +43) {
				$this->set('error.message', 'Invalid ZIP data');
				return false;
			}
			$info = unpack('vInternal/VExternal', substr($data, $fhStart +36, 6));

			$entries[$name]['type'] = ($info['Internal'] & 0x01) ? 'text' : 'binary';
			$entries[$name]['attr'] = (($info['External'] & 0x10) ? 'D' : '-') .
									  (($info['External'] & 0x20) ? 'A' : '-') .
									  (($info['External'] & 0x03) ? 'S' : '-') .
									  (($info['External'] & 0x02) ? 'H' : '-') .
									  (($info['External'] & 0x01) ? 'R' : '-');
		} while (($fhStart = strpos($data, $this->_ctrlDirHeader, $fhStart +46)) !== false);

		// Get details from local file header.
		$fhStart = strpos($data, $this->_fileHeader);
		do {
			if (strlen($data) < $fhStart +34) {
				$this->set('error.message', 'Invalid ZIP data');
				return false;
			}
			$info = unpack('vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength/vExtraLength', substr($data, $fhStart +8, 25));
			$name = substr($data, $fhStart +30, $info['Length']);
			$entries[$name]['_dataStart'] = $fhStart +30 + $info['Length'] + $info['ExtraLength'];
		} while (strlen($data) > $fhStart +30 + $info['Length'] && ($fhStart = strpos($data, $this->_fileHeader, $fhStart +30 + $info['Length'])) !== false);

		$this->_metadata = array_values($entries);
		return true;
	}

	/**
	 * Returns the file data for a file by offsest in the ZIP archive
	 *
	 * @access	private
	 * @param	int		$key	The position of the file in the archive.
	 * @return	string	Uncompresed file data buffer
	 * @since	1.5
	 */
	function _getFileData($key) {
		if ($this->_metadata[$key]['_method'] == 0x8) {
			// If zlib extention is loaded use it
			if (extension_loaded('zlib')) {
				return @ gzinflate(substr($this->_data, $this->_metadata[$key]['_dataStart'], $this->_metadata[$key]['csize']));
			}
		}
		elseif ($this->_metadata[$key]['_method'] == 0x0) {
			/* Files that aren't compressed. */
			return substr($this->_data, $this->_metadata[$key]['_dataStart'], $this->_metadata[$key]['csize']);
		} elseif ($this->_metadata[$key]['_method'] == 0x12) {
			// Is bz2 extension loaded?  If not try to load it
			if (!extension_loaded('bz2')) {
				if (JPATH_ISWIN) {
					@ dl('php_bz2.dll');
				} else {
					@ dl('bz2.so');
				}
			}
			// If bz2 extention is sucessfully loaded use it
			if (extension_loaded('bz2')) {
				return bzdecompress(substr($this->_data, $this->_metadata[$key]['_dataStart'], $this->_metadata[$key]['csize']));
			}
		}
		return '';
	}

	/**
	 * Converts a UNIX timestamp to a 4-byte DOS date and time format
	 * (date in high 2-bytes, time in low 2-bytes allowing magnitude
	 * comparison).
	 *
	 * @access	private
	 * @param	int	$unixtime	The current UNIX timestamp.
	 * @return	int	The current date in a 4-byte DOS format.
	 * @since	1.5
	 */
	function _unix2DOSTime($unixtime = null) {
		$timearray = (is_null($unixtime)) ? getdate() : getdate($unixtime);

		if ($timearray['year'] < 1980) {
			$timearray['year'] = 1980;
			$timearray['mon'] = 1;
			$timearray['mday'] = 1;
			$timearray['hours'] = 0;
			$timearray['minutes'] = 0;
			$timearray['seconds'] = 0;
		}
		return (($timearray['year'] - 1980) << 25) | ($timearray['mon'] << 21) | ($timearray['mday'] << 16) | ($timearray['hours'] << 11) | ($timearray['minutes'] << 5) | ($timearray['seconds'] >> 1);
	}

}