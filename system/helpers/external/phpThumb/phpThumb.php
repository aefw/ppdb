<?php
//////////////////////////////////////////////////////////////
//   phpThumb() by James Heinrich <info@silisoftware.com>   //
//        available at http://phpthumb.sourceforge.net      //
//         and/or https://github.com/JamesHeinrich/phpThumb //
//////////////////////////////////////////////////////////////
///                                                         //
// See: phpthumb.changelog.txt for recent changes           //
// See: phpthumb.readme.txt for usage instructions          //
//                                                         ///
//////////////////////////////////////////////////////////////

error_reporting(E_ALL);
ini_set('display_errors', '1');

// check for magic quotes in PHP < 7.4.0 (when these functions became deprecated)
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
	ini_set('magic_quotes_runtime', '0');
	if (ini_get('magic_quotes_runtime')) {
		die('"magic_quotes_runtime" is set in php.ini, cannot run phpThumb with this enabled');
	}
}
// Set a default timezone if web server has not done already in php.ini
if (!ini_get('date.timezone') && function_exists('date_default_timezone_set')) { // PHP >= 5.1.0
    date_default_timezone_set('UTC');
}
$starttime = array_sum(explode(' ', microtime())); // could be called as microtime(true) for PHP 5.0.0+

// this script relies on the superglobal arrays, fake it here for old PHP versions
if (PHP_VERSION < '4.1.0') {
	$_SERVER = $HTTP_SERVER_VARS;
	$_GET    = $HTTP_GET_VARS;
}

function SendSaveAsFileHeaderIfNeeded($getimagesize=false) {
	if (headers_sent()) {
		return false;
	}
	global $phpThumb;
	$downloadfilename = phpthumb_functions::SanitizeFilename(!empty($_GET['sia']) ? $_GET['sia'] : (!empty($_GET['down']) ? $_GET['down'] : 'phpThumb_generated_thumbnail.'.(!empty($_GET['f']) ? $_GET['f'] : 'jpg')));
	//if (empty($_GET['sia']) && empty($_GET['down']) && !empty($phpThumb->thumbnail_image_width) && !empty($phpThumb->thumbnail_image_height)) {
	if (empty($_GET['sia']) && empty($_GET['down']) && !empty($getimagesize[0]) && !empty($getimagesize[1])) {
		// if we know the output image dimensions we can generate a better default filename
		$downloadfilename = phpthumb_functions::SanitizeFilename((!empty($phpThumb->src) ? basename($phpThumb->src) : md5((string)$phpThumb->rawImageData)).'-'.intval($getimagesize[0]).'x'.intval($getimagesize[1]).'.'.(!empty($_GET['f']) ? $_GET['f'] : 'jpg'));
	}
	if (!empty($downloadfilename)) {
		$phpThumb->DebugMessage('SendSaveAsFileHeaderIfNeeded() sending header: Content-Disposition: '.(!empty($_GET['down']) ? 'attachment' : 'inline').'; filename="'.$downloadfilename.'"', __FILE__, __LINE__);
		header('Content-Disposition: '.(!empty($_GET['down']) ? 'attachment' : 'inline').'; filename="'.$downloadfilename.'"');
	}
	return true;
}

function RedirectToCachedFile() {
	global $phpThumb;

	$nice_cachefile = str_replace(DIRECTORY_SEPARATOR, '/', $phpThumb->cache_filename);
	$nice_docroot   = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($phpThumb->config_document_root, '/\\'));

	$parsed_url = phpthumb_functions::ParseURLbetter(@$_SERVER['HTTP_REFERER']);

	$nModified  = filemtime($phpThumb->cache_filename);

	if ($phpThumb->config_nooffsitelink_enabled && !empty($_SERVER['HTTP_REFERER']) && !in_array(@$parsed_url['host'], $phpThumb->config_nooffsitelink_valid_domains)) {

		$phpThumb->DebugMessage('Would have used cached (image/'.$phpThumb->thumbnailFormat.') file "'.$phpThumb->cache_filename.'" (Last-Modified: '.gmdate('D, d M Y H:i:s', $nModified).' GMT), but skipping because $_SERVER[HTTP_REFERER] ('.@$_SERVER['HTTP_REFERER'].') is not in $phpThumb->config_nooffsitelink_valid_domains ('.implode(';', $phpThumb->config_nooffsitelink_valid_domains).')', __FILE__, __LINE__);

	} elseif ($phpThumb->phpThumbDebug) {

		$phpThumb->DebugTimingMessage('skipped using cached image', __FILE__, __LINE__);
		$phpThumb->DebugMessage('Would have used cached file, but skipping due to phpThumbDebug', __FILE__, __LINE__);
		$phpThumb->DebugMessage('* Would have sent headers (1): Last-Modified: '.gmdate('D, d M Y H:i:s', $nModified).' GMT', __FILE__, __LINE__);
		if ($getimagesize = @getimagesize($phpThumb->cache_filename)) {
			$phpThumb->DebugMessage('* Would have sent headers (2): Content-Type: '.phpthumb_functions::ImageTypeToMIMEtype($getimagesize[2]), __FILE__, __LINE__);
		}
		if (preg_match('#^'.preg_quote($nice_docroot).'(.*)$#', $nice_cachefile, $matches)) {
			$phpThumb->DebugMessage('* Would have sent headers (3): Location: '.dirname($matches[1]).'/'.urlencode(basename($matches[1])), __FILE__, __LINE__);
		} else {
			$phpThumb->DebugMessage('* Would have sent data: file_get_contents('.$phpThumb->cache_filename.')', __FILE__, __LINE__);
		}

	} else {

		if (headers_sent()) {
			$phpThumb->ErrorImage('Headers already sent ('.basename(__FILE__).' line '.__LINE__.')');
			exit;
		}
		$getimagesize = @getimagesize($phpThumb->cache_filename);
		SendSaveAsFileHeaderIfNeeded($getimagesize);

		header('Pragma: private');
		header('Cache-Control: max-age='.$phpThumb->getParameter('config_cache_maxage'));
		header('Expires: '.date(DATE_RFC1123,  time() + $phpThumb->getParameter('config_cache_maxage')));
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && ($nModified == strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) && !empty($_SERVER['SERVER_PROTOCOL'])) {
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $nModified).' GMT');
			header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
			exit;
		}
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', $nModified).' GMT');
		header('ETag: "'.md5_file($phpThumb->cache_filename).'"');
		if (!empty($getimagesize[2])) {
			header('Content-Type: '.phpthumb_functions::ImageTypeToMIMEtype($getimagesize[2]));
		} elseif (preg_match('#\\.ico$#i', $phpThumb->cache_filename)) {
			header('Content-Type: image/x-icon');
		}
		header('Content-Length: '.filesize($phpThumb->cache_filename));
		if (empty($phpThumb->config_cache_force_passthru) && preg_match('#^'.preg_quote($nice_docroot).'(.*)$#', $nice_cachefile, $matches)) {
			header('Location: '.dirname($matches[1]).'/'.urlencode(basename($matches[1])));
		} else {
			echo file_get_contents($phpThumb->cache_filename);
		}
		exit;

	}
	return true;
}



// instantiate a new phpThumb() object
ob_start();
if (!include_once __DIR__ .'/phpthumb.class.php' ) {
	ob_end_flush();
	die('failed to include_once("'.realpath( __DIR__ .'/phpthumb.class.php').'")');
}
ob_end_clean();
$phpThumb = new phpThumb();
$phpThumb->DebugTimingMessage('phpThumb.php start', __FILE__, __LINE__, $starttime);
$phpThumb->setParameter('config_error_die_on_error', true);

if (!phpthumb_functions::FunctionIsDisabled('set_time_limit')) {
	set_time_limit(60);  // shouldn't take nearly this long in most cases, but with many filters and/or a slow server...
}

// phpThumbDebug[0] used to be here, but may reveal too much
// info when high_security_mode should be enabled (not set yet)

if (file_exists( __DIR__ .'/phpThumb.config.php')) {
	ob_start();
	if (include_once __DIR__ .'/phpThumb.config.php' ) {
		// great
	} else {
		ob_end_flush();
		$phpThumb->config_disable_debug = false; // otherwise error message won't print
		$phpThumb->ErrorImage('failed to include_once('. __DIR__ .'/phpThumb.config.php) - realpath="'.realpath( __DIR__ .'/phpThumb.config.php').'"');
	}
	ob_end_clean();
} elseif (file_exists( __DIR__ .'/phpThumb.config.php.default')) {
	$phpThumb->config_disable_debug = false; // otherwise error message won't print
	$phpThumb->ErrorImage('Please rename "phpThumb.config.php.default" to "phpThumb.config.php"');
} else {
	$phpThumb->config_disable_debug = false; // otherwise error message won't print
	$phpThumb->ErrorImage('failed to include_once('. __DIR__ .'/phpThumb.config.php) - realpath="'.realpath( __DIR__ .'/phpThumb.config.php').'"');
}

if (!empty($PHPTHUMB_CONFIG)) {
	foreach ($PHPTHUMB_CONFIG as $key => $value) {
		$keyname = 'config_'.$key;
		$phpThumb->setParameter($keyname, $value);
		if (!preg_match('#(password|mysql)#i', $key)) {
			$phpThumb->DebugMessage('setParameter('.$keyname.', '.$phpThumb->phpThumbDebugVarDump($value).')', __FILE__, __LINE__);
		}
	}
	if (!$phpThumb->config_disable_debug) {
		// if debug mode is enabled, force phpThumbDebug output, do not allow normal thumbnails to be generated
		$_GET['phpThumbDebug'] = (!empty($_GET['phpThumbDebug']) ? max(1, (int) $_GET[ 'phpThumbDebug']) : 9);
		$phpThumb->setParameter('phpThumbDebug', $_GET['phpThumbDebug']);
	}
} else {
	$phpThumb->DebugMessage('$PHPTHUMB_CONFIG is empty', __FILE__, __LINE__);
}

if (empty($phpThumb->config_disable_pathinfo_parsing) && (empty($_GET) || isset($_GET['phpThumbDebug'])) && !empty($_SERVER['PATH_INFO'])) {
	$_SERVER['PHP_SELF'] = str_replace($_SERVER['PATH_INFO'], '', @$_SERVER['PHP_SELF']);

	$args = explode(';', substr($_SERVER['PATH_INFO'], 1));
	$phpThumb->DebugMessage('PATH_INFO.$args set to ('.implode(')(', $args).')', __FILE__, __LINE__);
	if (!empty($args)) {
		$_GET['src'] = @$args[count($args) - 1];
		$phpThumb->DebugMessage('PATH_INFO."src" = "'.$_GET['src'].'"', __FILE__, __LINE__);
		if (preg_match('#^new\=([a-z0-9]+)#i', $_GET['src'], $matches)) {
			unset($_GET['src']);
			$_GET['new'] = $matches[1];
		}
	}
	if (preg_match('#^([\d]*)x?([\d]*)$#i', @$args[count($args) - 2], $matches)) {
		$_GET['w'] = $matches[1];
		$_GET['h'] = $matches[2];
		$phpThumb->DebugMessage('PATH_INFO."w"x"h" set to "'.$_GET['w'].'"x"'.$_GET['h'].'"', __FILE__, __LINE__);
	}
	for ($i = 0; $i < count($args) - 2; $i++) {
		list($key, $value) = array_pad(explode('=', $args[$i]), 2, '');
		if (substr($key, -2) == '[]') {
			$array_key_name = substr($key, 0, -2);
			$_GET[$array_key_name][] = $value;
			$phpThumb->DebugMessage('PATH_INFO."'.$array_key_name.'[]" = "'.$value.'"', __FILE__, __LINE__);
		} else {
			$_GET[$key] = $value;
			$phpThumb->DebugMessage('PATH_INFO."'.$key.'" = "'.$value.'"', __FILE__, __LINE__);
		}
	}
}

if (!empty($phpThumb->config_high_security_enabled)) {
	if (empty($_GET['hash'])) {
		$phpThumb->config_disable_debug = false; // otherwise error message won't print
		$phpThumb->ErrorImage('ERROR: missing hash');
	} elseif (phpthumb_functions::PasswordStrength($phpThumb->config_high_security_password) < 20) {
		$phpThumb->config_disable_debug = false; // otherwise error message won't print
		$phpThumb->ErrorImage('ERROR: $PHPTHUMB_CONFIG[high_security_password] is not complex enough');
	} elseif ($_GET['hash'] != hash_hmac('sha256', str_replace($phpThumb->config_high_security_url_separator.'hash='.$_GET['hash'], '', $_SERVER['QUERY_STRING']), $phpThumb->config_high_security_password)) {
		header('HTTP/1.0 403 Forbidden');
		$phpThumb->ErrorImage('ERROR: invalid hash');
	}
}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[0]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '0')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

// check for magic quotes in PHP < 7.4.0 (when these functions became deprecated)
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
	// returned the fixed string if the evil "magic_quotes_gpc" setting is on
	if (get_magic_quotes_gpc()) {
		// deprecated: 'err', 'file', 'goto',
		$RequestVarsToStripSlashes = array('src', 'wmf', 'down');
		foreach ($RequestVarsToStripSlashes as $key) {
			if (isset($_GET[$key])) {
				if (is_string($_GET[$key])) {
					$_GET[$key] = stripslashes($_GET[$key]);
				} else {
					unset($_GET[$key]);
				}
			}
		}
	}
}

if (empty($_SERVER['PATH_INFO']) && empty($_SERVER['QUERY_STRING'])) {
	$phpThumb->config_disable_debug = false; // otherwise error message won't print
	$phpThumb->ErrorImage('ERROR: no parameters specified');
}

if (!empty($_GET['src']) && isset($_GET['md5s']) && empty($_GET['md5s'])) {
	$md5s = '';
	if (preg_match('#^([a-z0-9]+)://#i', $_GET['src'], $protocol_matches)) {
		if (preg_match('#^(f|ht)tps?://#i', $_GET['src'])) {
			if ($rawImageData = phpthumb_functions::SafeURLread($_GET['src'], $error, $phpThumb->config_http_fopen_timeout, $phpThumb->config_http_follow_redirect)) {
				$md5s = md5($rawImageData);
			}
		} else {
			$phpThumb->ErrorImage('only FTP and HTTP/HTTPS protocols are allowed, "'.$protocol_matches[1].'" is not');
		}
	} else {
		$SourceFilename = $phpThumb->ResolveFilenameToAbsolute($_GET['src']);
		if (is_readable($SourceFilename)) {
			$md5s = phpthumb_functions::md5_file_safe($SourceFilename);
		} else {
			$phpThumb->ErrorImage('ERROR: "'.$SourceFilename.'" cannot be read');
		}
	}
	if (!empty($_SERVER['HTTP_REFERER'])) {
		$phpThumb->ErrorImage('&md5s='.$md5s);
	} else {
		die('&md5s='.$md5s);
	}
}

if (!empty($_GET['src']) && empty($phpThumb->config_allow_local_http_src) && preg_match('#^http://'.@$_SERVER['HTTP_HOST'].'(.+)#i', $_GET['src'], $matches)) {
	$phpThumb->ErrorImage('It is MUCH better to specify the "src" parameter as "'.$matches[1].'" instead of "'.$matches[0].'".'."\n\n".'If you really must do it this way, enable "allow_local_http_src" in phpThumb.config.php');
}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[1]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '1')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

$parsed_url_referer = phpthumb_functions::ParseURLbetter(@$_SERVER['HTTP_REFERER']);
if ($phpThumb->config_nooffsitelink_require_refer && !in_array(@$parsed_url_referer['host'], $phpThumb->config_nohotlink_valid_domains)) {
	$phpThumb->ErrorImage('config_nooffsitelink_require_refer enabled and '.(@$parsed_url_referer['host'] ? '"'.$parsed_url_referer['host'].'" is not an allowed referer' : 'no HTTP_REFERER exists'));
}
$parsed_url_src = phpthumb_functions::ParseURLbetter(@$_GET['src']);
if ($phpThumb->config_nohotlink_enabled && $phpThumb->config_nohotlink_erase_image && preg_match('#^(f|ht)tps?://#i', (string)@$_GET['src']) && !in_array(@$parsed_url_src['host'], $phpThumb->config_nohotlink_valid_domains)) {
	$phpThumb->ErrorImage($phpThumb->config_nohotlink_text_message);
}

if ($phpThumb->config_mysql_query) {
	if ($phpThumb->config_mysql_extension == 'mysqli') {

		$found_missing_function = false;
		foreach (array('mysqli_connect') as $required_mysqli_function) {
			if (!function_exists($required_mysqli_function)) {
				$found_missing_function = $required_mysqli_function;
				break;
			}
		}
		if ($found_missing_function) {
			$phpThumb->ErrorImage('SQL function unavailable: '.$found_missing_function);
		} else {
			$mysqli = new mysqli($phpThumb->config_mysql_hostname, $phpThumb->config_mysql_username, $phpThumb->config_mysql_password, $phpThumb->config_mysql_database);
			if ($mysqli->connect_error) {
				$phpThumb->ErrorImage('MySQLi connect error ('.$mysqli->connect_errno.') '.$mysqli->connect_error);
			} else {
				if ($result = $mysqli->query($phpThumb->config_mysql_query)) {
					if ($row = $result->fetch_array()) {

						$result->free();
						$mysqli->close();
						$phpThumb->setSourceData($row[0]);
						unset($row);

					} else {
						$result->free();
						$mysqli->close();
						$phpThumb->ErrorImage('no matching data in database.');
					}
				} else {
					$mysqli->close();
					$phpThumb->ErrorImage('Error in MySQL query: "'.$mysqli->error.'"');
				}
			}
			unset($_GET['id']);
		}

	} elseif ($phpThumb->config_mysql_extension == 'mysql') {

		$found_missing_function = false;
		//foreach (array('mysql_connect', 'mysql_select_db', 'mysql_query', 'mysql_fetch_array', 'mysql_free_result', 'mysql_close', 'mysql_error') as $required_mysql_function) {
		foreach (array('mysql_connect') as $required_mysql_function) {
			if (!function_exists($required_mysql_function)) {
				$found_missing_function = $required_mysql_function;
				break;
			}
		}
		if ($found_missing_function) {
			$phpThumb->ErrorImage('SQL function unavailable: '.$found_missing_function);
		} else {
			if ($cid = @mysql_connect($phpThumb->config_mysql_hostname, $phpThumb->config_mysql_username, $phpThumb->config_mysql_password)) {
				if (@mysql_select_db($phpThumb->config_mysql_database, $cid)) {
					if ($result = @mysql_query($phpThumb->config_mysql_query, $cid)) {
						if ($row = @mysql_fetch_array($result)) {

							mysql_free_result($result);
							mysql_close($cid);
							$phpThumb->setSourceData($row[0]);
							unset($row);

						} else {
							mysql_free_result($result);
							mysql_close($cid);
							$phpThumb->ErrorImage('no matching data in database.');
						}
					} else {
						mysql_close($cid);
						$phpThumb->ErrorImage('Error in MySQL query: "'.mysql_error($cid).'"');
					}
				} else {
					mysql_close($cid);
					$phpThumb->ErrorImage('cannot select MySQL database: "'.mysql_error($cid).'"');
				}
			} else {
				$phpThumb->ErrorImage('cannot connect to MySQL server');
			}
			unset($_GET['id']);
		}

	} else {
		$phpThumb->ErrorImage('config_mysql_extension not supported');
	}
}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[2]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '2')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

$PHPTHUMB_DEFAULTS_DISABLEGETPARAMS = (bool) ($phpThumb->config_cache_default_only_suffix && (strpos($phpThumb->config_cache_default_only_suffix, '*') !== false));

// deprecated: 'err', 'file', 'goto',
$allowedGETparameters = array('src', 'new', 'w', 'h', 'wp', 'hp', 'wl', 'hl', 'ws', 'hs', 'f', 'q', 'sx', 'sy', 'sw', 'sh', 'zc', 'ica', 'bc', 'bg', 'bgt', 'fltr', 'xto', 'ra', 'ar', 'aoe', 'far', 'iar', 'maxb', 'down', 'phpThumbDebug', 'hash', 'md5s', 'sfn', 'dpi', 'sia', 'nocache');
foreach ($_GET as $key => $value) {
	if (!empty($PHPTHUMB_DEFAULTS_DISABLEGETPARAMS) && ($key != 'src')) {
		// disabled, do not set parameter
		$phpThumb->DebugMessage('ignoring $_GET['.$key.'] because of $PHPTHUMB_DEFAULTS_DISABLEGETPARAMS', __FILE__, __LINE__);
	} elseif ($key == 'hash') {
		// "hash" is for use in phpThumb.phpdoes only, should not be set on object
	} elseif (in_array($key, $allowedGETparameters)) {
		$phpThumb->DebugMessage('setParameter('.$key.', '.$phpThumb->phpThumbDebugVarDump($value).')', __FILE__, __LINE__);
		$phpThumb->setParameter($key, $value);
	} else {
		$phpThumb->ErrorImage('Forbidden parameter: '.$key);
	}
}

if (!empty($PHPTHUMB_DEFAULTS) && is_array($PHPTHUMB_DEFAULTS)) {
	$phpThumb->DebugMessage('setting $PHPTHUMB_DEFAULTS['.implode(';', array_keys($PHPTHUMB_DEFAULTS)).']', __FILE__, __LINE__);
	foreach ($PHPTHUMB_DEFAULTS as $key => $value) {
		if (!$PHPTHUMB_DEFAULTS_GETSTRINGOVERRIDE || !isset($_GET[$key])) { // set parameter to default value if config is set to allow _GET to override default, OR if no value is passed via _GET for this parameter
			//$_GET[$key] = $value;
			//$phpThumb->DebugMessage('PHPTHUMB_DEFAULTS assigning ('.(is_array($value) ? print_r($value, true) : $value).') to $_GET['.$key.']', __FILE__, __LINE__);
			$phpThumb->setParameter($key, $value);
			$phpThumb->DebugMessage('setParameter('.$key.', '.$phpThumb->phpThumbDebugVarDump($value).') from $PHPTHUMB_DEFAULTS', __FILE__, __LINE__);
		}
	}
}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[3]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '3')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

//if (!@$_GET['phpThumbDebug'] && !is_file($phpThumb->sourceFilename) && !phpthumb_functions::gd_version()) {
//	if (!headers_sent()) {
//		// base64-encoded error image in GIF format
//		$ERROR_NOGD = 'R0lGODlhIAAgALMAAAAAABQUFCQkJDY2NkZGRldXV2ZmZnJycoaGhpSUlKWlpbe3t8XFxdXV1eTk5P7+/iwAAAAAIAAgAAAE/vDJSau9WILtTAACUinDNijZtAHfCojS4W5H+qxD8xibIDE9h0OwWaRWDIljJSkUJYsN4bihMB8th3IToAKs1VtYM75cyV8sZ8vygtOE5yMKmGbO4jRdICQCjHdlZzwzNW4qZSQmKDaNjhUMBX4BBAlmMywFSRWEmAI6b5gAlhNxokGhooAIK5o/pi9vEw4Lfj4OLTAUpj6IabMtCwlSFw0DCKBoFqwAB04AjI54PyZ+yY3TD0ss2YcVmN/gvpcu4TOyFivWqYJlbAHPpOntvxNAACcmGHjZzAZqzSzcq5fNjxFmAFw9iFRunD1epU6tsIPmFCAJnWYE0FURk7wJDA0MTKpEzoWAAskiAAA7';
//		header('Content-Type: image/gif');
//		echo base64_decode($ERROR_NOGD);
//	} else {
//		echo '*** ERROR: No PHP-GD support available ***';
//	}
//	exit;
//}

// check to see if file can be output from source with no processing or caching
$CanPassThroughDirectly = true;
if ($phpThumb->rawImageData) {
	// data from SQL, should be fine
} elseif (preg_match('#^https?\\://[^\\?&]+\\.(jpe?g|gif|png|webp|avif)$#i', (string)$phpThumb->src)) {
	// assume is ok to passthru if no other parameters specified
} elseif (preg_match('#^(f|ht)tps?\\://#i', (string)$phpThumb->src)) {
	$phpThumb->DebugMessage('$CanPassThroughDirectly=false because preg_match("#^(f|ht)tps?://#i", '.$phpThumb->src.')', __FILE__, __LINE__);
	$CanPassThroughDirectly = false;
} elseif (!@is_readable($phpThumb->sourceFilename)) {
	$phpThumb->DebugMessage('$CanPassThroughDirectly=false because !@is_readable('.$phpThumb->sourceFilename.')', __FILE__, __LINE__);
	$CanPassThroughDirectly = false;
} elseif (!@is_file($phpThumb->sourceFilename)) {
	$phpThumb->DebugMessage('$CanPassThroughDirectly=false because !@is_file('.$phpThumb->sourceFilename.')', __FILE__, __LINE__);
	$CanPassThroughDirectly = false;
}
foreach ($_GET as $key => $value) {
	switch ($key) {
		case 'src':
			// allowed
			break;

		case 'w':
		case 'h':
			// might be OK if exactly matches original
			if (preg_match('#^https?\\://[^\\?&]+\\.(jpe?g|gif|png|webp|avif)$#i', (string)$phpThumb->src)) {
				// assume it is not ok for direct-passthru of remote image
				$CanPassThroughDirectly = false;
			}
			break;

		case 'phpThumbDebug':
			// handled in direct-passthru code
			break;

		default:
			// all other parameters will cause some processing,
			// therefore cannot pass through original image unmodified
			$CanPassThroughDirectly = false;
			$UnAllowedGET[] = $key;
			break;
	}
}
if (!empty($UnAllowedGET)) {
	$phpThumb->DebugMessage('$CanPassThroughDirectly=false because $_GET['.implode(';', array_unique($UnAllowedGET)).'] are set', __FILE__, __LINE__);
}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[4]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '4')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

$phpThumb->DebugMessage('$CanPassThroughDirectly="'. (int) $CanPassThroughDirectly .'" && $phpThumb->src="'.$phpThumb->src.'"', __FILE__, __LINE__);
while ($CanPassThroughDirectly && $phpThumb->src) {
	// no parameters set, passthru

	if (preg_match('#^https?\\://[^\\?&]+\.(jpe?g|gif|png|webp|avif)$#i', $phpThumb->src)) {
		$phpThumb->DebugMessage('Passing HTTP source through directly as Location: redirect ('.$phpThumb->src.')', __FILE__, __LINE__);
		header('Location: '.$phpThumb->src);
		exit;
	}

	$SourceFilename = $phpThumb->ResolveFilenameToAbsolute($phpThumb->src);

	// security and size checks
	if ($phpThumb->getimagesizeinfo = @getimagesize($SourceFilename)) {
		$phpThumb->DebugMessage('Direct passthru getimagesize() returned [w='.$phpThumb->getimagesizeinfo[0].';h='.$phpThumb->getimagesizeinfo[1].';t='.$phpThumb->getimagesizeinfo[2].']', __FILE__, __LINE__);

		if (!@$_GET['w'] && !@$_GET['wp'] && !@$_GET['wl'] && !@$_GET['ws'] && !@$_GET['h'] && !@$_GET['hp'] && !@$_GET['hl'] && !@$_GET['hs']) {
			// no resizing needed
			$phpThumb->DebugMessage('Passing "'.$SourceFilename.'" through directly, no resizing required ("'.$phpThumb->getimagesizeinfo[0].'"x"'.$phpThumb->getimagesizeinfo[1].'")', __FILE__, __LINE__);
		} elseif (($phpThumb->getimagesizeinfo[0] <= @$_GET['w']) && ($phpThumb->getimagesizeinfo[1] <= @$_GET['h']) && ((@$_GET['w'] == $phpThumb->getimagesizeinfo[0]) || (@$_GET['h'] == $phpThumb->getimagesizeinfo[1]))) {
			// image fits into 'w'x'h' box, and at least one dimension matches exactly, therefore no resizing needed
			$phpThumb->DebugMessage('Passing "'.$SourceFilename.'" through directly, no resizing required ("'.$phpThumb->getimagesizeinfo[0].'"x"'.$phpThumb->getimagesizeinfo[1].'" fits inside "'.@$_GET['w'].'"x"'.@$_GET['h'].'")', __FILE__, __LINE__);
		} else {
			$phpThumb->DebugMessage('Not passing "'.$SourceFilename.'" through directly because resizing required (from "'.$phpThumb->getimagesizeinfo[0].'"x"'.$phpThumb->getimagesizeinfo[1].'" to "'.@$_GET['w'].'"x"'.@$_GET['h'].'")', __FILE__, __LINE__);
			break;
		}
		switch ($phpThumb->getimagesizeinfo[2]) {
			case IMAGETYPE_GIF:
			case IMAGETYPE_JPEG:
			case IMAGETYPE_PNG:
			case IMAGETYPE_WEBP:
			case IMAGETYPE_AVIF:
				// great, let it through
				break;
			default:
				// browser probably can't handle format, remangle it to JPEG/PNG/GIF
				$phpThumb->DebugMessage('Not passing "'.$SourceFilename.'" through directly because $phpThumb->getimagesizeinfo[2] = "'.$phpThumb->getimagesizeinfo[2].'"', __FILE__, __LINE__);
				break 2;
		}

		$ImageCreateFunctions = array(
			IMAGETYPE_GIF  => 'imagecreatefromgif',
			IMAGETYPE_JPEG => 'imagecreatefromjpeg',
			IMAGETYPE_PNG  => 'imagecreatefrompng',
			IMAGETYPE_WEBP => 'imagecreatefromwebp',
			IMAGETYPE_AVIF => 'imagecreatefromavif',
		);
		$theImageCreateFunction = @$ImageCreateFunctions[$phpThumb->getimagesizeinfo[2]];
		$dummyImage = false;
		if ($phpThumb->config_disable_onlycreateable_passthru || (function_exists($theImageCreateFunction) && ($dummyImage = @$theImageCreateFunction($SourceFilename)))) {

			// great
			if (@is_resource($dummyImage) || (@is_object($dummyImage) && $dummyImage instanceOf \GdImage)) {
				unset($dummyImage);
			}

			if (headers_sent()) {
				$phpThumb->ErrorImage('Headers already sent ('.basename(__FILE__).' line '.__LINE__.')');
				exit;
			}
			if (!empty($_GET['phpThumbDebug'])) {
				$phpThumb->DebugTimingMessage('skipped direct $SourceFilename passthru', __FILE__, __LINE__);
				$phpThumb->DebugMessage('Would have passed "'.$SourceFilename.'" through directly, but skipping due to phpThumbDebug', __FILE__, __LINE__);
				break;
			}

			SendSaveAsFileHeaderIfNeeded($phpThumb->getimagesizeinfo);
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', @filemtime($SourceFilename)).' GMT');
			if ($contentType = phpthumb_functions::ImageTypeToMIMEtype(@$phpThumb->getimagesizeinfo[2])) {
				header('Content-Type: '.$contentType);
			}
			echo file_get_contents($SourceFilename);
			exit;

		} else {
			$phpThumb->DebugMessage('Not passing "'.$SourceFilename.'" through directly because ($phpThumb->config_disable_onlycreateable_passthru = "'.$phpThumb->config_disable_onlycreateable_passthru.'") and '.$theImageCreateFunction.'() failed', __FILE__, __LINE__);
			break;
		}

	} else {
		$phpThumb->DebugMessage('Not passing "'.$SourceFilename.'" through directly because getimagesize() failed', __FILE__, __LINE__);
		break;
	}
	break;
}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[5]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '5')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

// check to see if file already exists in cache, and output it with no processing if it does
$phpThumb->SetCacheFilename();
if (@is_readable($phpThumb->cache_filename)) {
	RedirectToCachedFile();
} else {
	$phpThumb->DebugMessage('Cached file "'.$phpThumb->cache_filename.'" does not exist, processing as normal', __FILE__, __LINE__);
}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[6]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '6')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

if ($phpThumb->rawImageData) {

	// great

} elseif (!empty($_GET['new'])) {

	// generate a blank image resource of the specified size/background color/opacity
	if (($phpThumb->w <= 0) || ($phpThumb->h <= 0)) {
		$phpThumb->ErrorImage('"w" and "h" parameters required for "new"');
	}
	list($bghexcolor, $opacity) = array_pad(explode('|', $_GET['new']), 2, '');
	if (!phpthumb_functions::IsHexColor($bghexcolor)) {
		$phpThumb->ErrorImage('BGcolor parameter for "new" is not valid');
	}
	$opacity = ('' !== $opacity ? $opacity : 100);
	if ($phpThumb->gdimg_source = phpthumb_functions::ImageCreateFunction($phpThumb->w, $phpThumb->h)) {
		$alpha = (100 - min(100, max(0, $opacity))) * 1.27;
		if ($alpha) {
			$phpThumb->setParameter('is_alpha', true);
			imagealphablending($phpThumb->gdimg_source, false);
			imagesavealpha($phpThumb->gdimg_source, true);
		}
		$new_background_color = phpthumb_functions::ImageHexColorAllocate($phpThumb->gdimg_source, $bghexcolor, false, $alpha);
		imagefilledrectangle($phpThumb->gdimg_source, 0, 0, $phpThumb->w, $phpThumb->h, $new_background_color);
	} else {
		$phpThumb->ErrorImage('failed to create "new" image ('.$phpThumb->w.'x'.$phpThumb->h.')');
	}

} elseif (!$phpThumb->src) {

	$phpThumb->ErrorImage('Usage: '.$_SERVER['PHP_SELF'].'?src=/path/and/filename.jpg'."\n".'read Usage comments for details');

} elseif (preg_match('#^([a-z0-9]+)://#i', $_GET['src'], $protocol_matches)) {

	if (preg_match('#^(f|ht)tps?://#i', $_GET['src'])) {
		$phpThumb->DebugMessage('$phpThumb->src ('.$phpThumb->src.') is remote image, attempting to download', __FILE__, __LINE__);
		if ($phpThumb->config_http_user_agent) {
			$phpThumb->DebugMessage('Setting "user_agent" to "'.$phpThumb->config_http_user_agent.'"', __FILE__, __LINE__);
			ini_set('user_agent', $phpThumb->config_http_user_agent);
		}
		$cleanedupurl = phpthumb_functions::CleanUpURLencoding($phpThumb->src);
		$phpThumb->DebugMessage('CleanUpURLencoding('.$phpThumb->src.') returned "'.$cleanedupurl.'"', __FILE__, __LINE__);
		$phpThumb->src = $cleanedupurl;
		unset($cleanedupurl);
		if ($rawImageData = phpthumb_functions::SafeURLread($phpThumb->src, $error, $phpThumb->config_http_fopen_timeout, $phpThumb->config_http_follow_redirect)) {
			$phpThumb->DebugMessage('SafeURLread('.$phpThumb->src.') succeeded'.($error ? ' with messages: "'.$error.'"' : ''), __FILE__, __LINE__);
			$phpThumb->DebugMessage('Setting source data from URL "'.$phpThumb->src.'"', __FILE__, __LINE__);
			$phpThumb->setSourceData($rawImageData, urlencode($phpThumb->src));
		} else {
			$phpThumb->ErrorImage($error);
		}
	} else {
		$phpThumb->ErrorImage('only FTP and HTTP/HTTPS protocols are allowed, "'.$protocol_matches[1].'" is not');
	}

}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[7]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '7')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

$phpThumb->GenerateThumbnail();

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[8]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '8')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

if (!empty($phpThumb->config_high_security_enabled) && !empty($_GET['nocache'])) {

	// cache disabled, don't write cachefile

} else {

	phpthumb_functions::EnsureDirectoryExists(dirname($phpThumb->cache_filename));
	if (is_writable(dirname($phpThumb->cache_filename)) || (file_exists($phpThumb->cache_filename) && is_writable($phpThumb->cache_filename))) {

		$phpThumb->CleanUpCacheDirectory();
		if ($phpThumb->RenderToFile($phpThumb->cache_filename) && is_readable($phpThumb->cache_filename)) {
			chmod($phpThumb->cache_filename, 0644);
			RedirectToCachedFile();
		} else {
			$phpThumb->DebugMessage('Failed: RenderToFile('.$phpThumb->cache_filename.')', __FILE__, __LINE__);
		}

	} else {

		$phpThumb->DebugMessage('Cannot write to $phpThumb->cache_filename ('.$phpThumb->cache_filename.') because that directory ('.dirname($phpThumb->cache_filename).') is not writable', __FILE__, __LINE__);

	}

}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[9]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '9')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////

if (!$phpThumb->OutputThumbnail()) {
	$phpThumb->ErrorImage('Error in OutputThumbnail():'."\n". $phpThumb->debugmessages[ count($phpThumb->debugmessages) - 1 ]);
}

////////////////////////////////////////////////////////////////
// Debug output, to try and help me diagnose problems
$phpThumb->DebugTimingMessage('phpThumbDebug[10]', __FILE__, __LINE__);
if (isset($_GET['phpThumbDebug']) && ($_GET['phpThumbDebug'] == '10')) {
	$phpThumb->phpThumbDebug();
}
////////////////////////////////////////////////////////////////
