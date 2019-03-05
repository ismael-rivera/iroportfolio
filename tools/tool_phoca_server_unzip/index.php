<?php
/**
 * @tool		Phoca Server Unzip
 * @copyright	Copyright (C) Jan Pavelka www.phoca.cz (http://www.phoca.cz)
 * @license		http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
define('DS', DIRECTORY_SEPARATOR);
define('JPATH_SITE', str_replace('tool_phoca_server_unzip', '', dirname(__FILE__)));
define('JPATH_BASE', str_replace('tool_phoca_server_unzip', '', dirname(__FILE__))); 
// JPATH_ROOT is defined in assets folder in zip.php file (with JPATH clean cleaned)
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-gb" lang="en-gb">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="robots" content="index, follow" />
  <meta name="keywords" content="phoca, server unzip" />
  <meta name="description" content="Phoca Server Unzip tool" />
  <meta name="generator" content="www.phoca.cz" />
  <title>Phoca Server Unzip tool</title>
  <style type="text/css">
body {font-family: Arial, sans-serif; font-size: 10px; color: #000000 ;}
h1 a {color:#006699;text-decoration:none;}
#info {position: relative;float:right; top:10px; right:10px; text-align:right;margin-bottom:10px;}
.error {font-weight:bold;color:#c10000}
.warning {font-weight:bold;color:#ff8102}
.success {font-weight:bold;color:#008040}
.window {position:relative;top:10px;left:10px;width:95%;padding:5px;height:300px;overflow:auto;border:1px solid #000;background:#fbfbfb;clear:both;}
</style>
</head>
<body>

<div id="info">
	<img src="assets/phoca-logo.png" alt="Phoca" /><br />
	<a href="http://www.phoca.cz/">www.phoca.cz</a><br />
	<a href="http://www.phoca.cz/forum">www.phoca.cz/forum</a>
</div>

<h1><a href="index.php">Phoca Server Unzip tool</a></h1>

<?php

if (isset($_POST['max-exec-time'])) {
	// Change the time -------------------------------------
	$changedMaxExecTime		= 0;
	$standardMaxExecTime 	= ini_get('max_execution_time');
	if ($standardMaxExecTime < (int)$_POST['max-exec-time']) {
		if ((int)$_POST['max-exec-time'] > 600) {
			set_time_limit(600);
		} else {
			set_time_limit((int)$_POST['max-exec-time']);
		}
		$changedMaxExecTime	= 1;
	}
	$postMaxExecTime 	= ini_get('max_execution_time');
	// -----------------------------------------------------
} else {
	// Change the time -------------------------------------
	$changedMaxExecTime		= 0;
	$standardMaxExecTime 	= ini_get('max_execution_time');
	if ($standardMaxExecTime < 250) {
		set_time_limit(250);
		$changedMaxExecTime	= 1;
	}
	$postMaxExecTime 	= ini_get('max_execution_time');
	// -----------------------------------------------------
}

// Forms
if (isset($_POST['ftp-enabled'])) {
	define('FTP_ENABLED', $_POST['ftp-enabled']);
} else {
	define('FTP_ENABLED', 1);
}

if (isset($_POST['ftp-host'])) {
	define('FTP_HOST', $_POST['ftp-host']);
} else {
	define('FTP_HOST', 'localhost');
}

if (isset($_POST['ftp-port'])) {
	define('FTP_PORT', $_POST['ftp-port']);
} else {
	define('FTP_PORT', 21);
}

if (isset($_POST['ftp-user'])) {
	define('FTP_USER', $_POST['ftp-user']);
} else {
	define('FTP_USER', '');
}

if (isset($_POST['ftp-pass'])) {
	define('FTP_PASS', $_POST['ftp-pass']);
} else {
	define('FTP_PASS', '');
}

if (isset($_POST['ftp-root'])) {
	$post_ftp_root = $_POST['ftp-root'];
} else {
	$post_ftp_root = '';
}

// Include
require_once("assets/zip.php");

// Forms (find ftp root)
if (isset($_POST['find-ftp-root']) && $_POST['find-ftp-root'] != '') {
	$post_ftp_root = FTPRoot::findFtpRoot(FTP_USER, FTP_PASS, FTP_HOST, FTP_PORT);	
}
define('FTP_ROOT', $post_ftp_root);
?>

<form method="post" action="index.php">
<table>
	<tr><td>FTP Enabled: </td><td><input type="text" name="ftp-enabled" value="<?php echo FTP_ENABLED ?>" /></td><td></td></tr>
	<tr><td>FTP Host: </td><td><input type="text" name="ftp-host" value="<?php echo FTP_HOST ?>" /></td><td></td></tr>
	<tr><td>FTP Port: </td><td><input type="text" name="ftp-port" value="<?php echo FTP_PORT ?>" /></td><td></td></tr>
	<tr><td>FTP User: </td><td><input type="text" name="ftp-user" value="<?php echo FTP_USER ?>" /></td><td></td></tr>
	<tr><td>FTP Password: </td><td><input type="text" name="ftp-pass" value="<?php echo FTP_PASS ?>" /></td><td></td></tr>
	<tr><td>FTP Root: </td><td><input type="text" name="ftp-root" value="<?php echo FTP_ROOT ?>" /></td><td><input type="submit" name="find-ftp-root" value="Get FTP Root" /></td></tr>
	<tr><td>Mas Script Exec Time: </td><td><input type="text" name="max-exec-time" value="<?php echo $postMaxExecTime ?>" /></td></tr>
</table>

<?php
$errorMsg 	= '';
$process	= 0;


// Files
$handle		= opendir('../');
$i 			= 0;
$fileS 		= '';
while (false!==($file = readdir($handle))) 
{ 
    if ($file != "." && $file != ".." && !is_dir($file))
    {
		if (preg_match("/\.zip/", $file)) {
			$fileS[$i]['file']="$file";
		} else if (preg_match("/\.ZIP/", $file)) {
			$fileS[$i]['file']="$file";
		}
		$i++;
    }
}
closedir($handle);

if (!empty($fileS)) {
	echo '<p>Click on file which you want to unzip:</p>';
	echo '<ul>';
	foreach ($fileS as $key => $value) {
		echo '<li style="list-style-type:none"><input type="radio" name="file" value="'.$value['file'].'" /> '.$value['file'].'</li>';
	}
	echo '</ul>';
} else {
	echo '<p>There is no ZIP file.</p>';
}

echo '<input type="submit" name="submit" value="Unzip" />';
echo '</form>';
echo '<div class="window">';


// POST THE FILE
if (isset($_POST['submit']) && isset($_POST['file']) && $_POST['file'] != '' && (preg_match("/\.zip/", $_POST['file']) || preg_match("/\.ZIP/", $_POST['file']))) {
	
	$process = 1;

	// Path
	$pathAbs 		= str_replace('tool_phoca_server_unzip', '', dirname(__FILE__));
	$fileArchiveRel = "../" . $_POST['file'];
	$fileArchiveAbs = $pathAbs . $_POST['file'];
	
	if (is_file($fileArchiveAbs)) {
			$fileZip = new JArchiveZip;
			$fileZip = $fileZip->extract($fileArchiveAbs, $pathAbs);

	} else {
		$errorMsg = '<p>The file dosn\'t exist on the server</p>';
	}
	
	// Set back the time --------------------
	if ($changedMaxExecTime == 1) {
		set_time_limit($standardMaxExecTime);
	}
	// --------------------------------------

}
echo '</div>';

if ($errorMsg != '') {
	echo $errorMsg;
} else if ($errorMsg == '' && $process == 1) {
	echo '<p class="success">Success: ZIP file was successfully extracted<p>';
	echo '<p class="warning">Warning: Please delete the \'tool_phoca_server_unzip\' folder and the source ZIP file.</p>';
}
?>
</body>
</html>