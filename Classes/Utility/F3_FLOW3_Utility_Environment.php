<?php
declare(ENCODING = 'utf-8');
namespace F3::FLOW3::Utility;

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 2 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

/**
 * @package FLOW3
 * @subpackage Utility
 * @version $Id:F3::FLOW3::AOP::FLOW3Test.php 201 2007-03-30 11:18:30Z robert $
 */

/**
 * Abstraction methods which return system environment variables regardless
 * of server OS, CGI/MODULE version etc. Basically they are the _SERVER
 * variables in most cases.
 *
 * This class should be used instead of the $_SERVER/ENV_VARS to get reliable
 * values for all situations.
 *
 * @package FLOW3
 * @subpackage Utility
 * @version $Id:F3::FLOW3::Utility::Environment.php 467 2008-02-06 19:34:56Z robert $
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class Environment {

	const REQUEST_METHOD_UNKNOWN = NULL;
	const REQUEST_METHOD_GET = 'GET';
	const REQUEST_METHOD_POST = 'POST';
	const REQUEST_METHOD_HEAD = 'HEAD';
	const REQUEST_METHOD_OPTIONS = 'OPTIONS';
	const REQUEST_METHOD_PUT = 'PUT';
	const REQUEST_METHOD_DELETE = 'DELETE';

	/**
	 * @var array A local copy of the _SERVER super global.
	 */
	protected $SERVER;

	/**
	 * @var array A local copy of the _GET super global.
	 */
	protected $GET;

	/**
	 * @var array A local copy of the _POST super global.
	 */
	protected $POST;

	/**
	 * @var string A lower case string specifying the currently used Server API. See php_sapi_name()/PHP_SAPI for possible values.
	 */
	protected $SAPIName;

	/**
	 * @var string
	 */
	protected $temporaryDirectory;

	/**
	 * This constructor copies the superglobals $_SERVER, $_GET, $_POST to local
	 * variables and unsets the orginals.
	 *
	 * @param array The configuration for the utility environment
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function __construct(F3::FLOW3::Configuration::Container $configuration) {

		if (!($_SERVER instanceof F3::FLOW3::Utility::SuperGlobalReplacement)) {
			$this->SERVER = $_SERVER;
			$this->GET = $_GET;
			$this->POST = $_POST;
			$this->SAPIName = PHP_SAPI;

			$_SERVER = new F3::FLOW3::Utility::SuperGlobalReplacement('_SERVER', 'Please use the ' . __CLASS__ . ' component instead of accessing the superglobal directly.');
			$_GET = new F3::FLOW3::Utility::SuperGlobalReplacement('_GET', 'Please use the Request object which is built by the Request Handler instead of accessing the _GET superglobal directly.');
			$_POST = new F3::FLOW3::Utility::SuperGlobalReplacement('_POST', 'Please use the Request object which is built by the Request Handler instead of accessing the _POST superglobal directly.');
		}

		try {
			$this->temporaryDirectory = $this->createTemporaryDirectory((string)$configuration['temporaryDirectoryBase']);
		} catch (F3::FLOW3::Utility::Exception $exception) {
			$fallBackTemporaryDirectoryBase = (DIRECTORY_SEPARATOR == '/') ? '/tmp' : '\\WINDOWS\\TEMP';
			$this->temporaryDirectory = $this->createTemporaryDirectory($fallBackTemporaryDirectoryBase);
		}
	}

	/**
	 * Returns the HTTP Host
	 *
	 * @return string The HTTP Host as found in _SERVER[HTTP_HOST]
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getHTTPHost() {
		return isset($this->SERVER['HTTP_HOST']) ? $this->SERVER['HTTP_HOST'] : NULL;
	}

	/**
	 * Returns the HTTP referer
	 *
	 * @return string The HTTP referer as found in _SERVER[HTTP_REFERER]
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getHTTPReferer() {
		return isset($this->SERVER['HTTP_REFERER']) ? $this->SERVER['HTTP_REFERER'] : NULL;
	}

	/**
	 * Returns the HTTP user agent
	 *
	 * @return string The HTTP user agent as found in _SERVER[HTTP_USER_AGENT]
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getHTTPUserAgent() {
		return isset($this->SERVER['HTTP_USER_AGENT']) ? $this->SERVER['HTTP_USER_AGENT'] : NULL;
	}

	/**
	 * Returns the HTTP accept language
	 *
	 * @return string The HTTP accept language as found in _SERVER[HTTP_ACCEPT_LANGUAGE]
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getHTTPAcceptLanguage() {
		return isset($this->SERVER['HTTP_ACCEPT_LANGUAGE']) ? $this->SERVER['HTTP_ACCEPT_LANGUAGE'] : NULL;
	}

	/**
	 * Returns the remote address
	 *
	 * @return string The remote address as found in _SERVER[REMOTE_ADDR]
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getRemoteAddress() {
		return isset($this->SERVER['REMOTE_ADDR']) ? $this->SERVER['REMOTE_ADDR'] : NULL;
	}

	/**
	 * Returns the remote host
	 *
	 * @return string The remote host as found in _SERVER[REMOTE_HOST]
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getRemoteHost() {
		return isset($this->SERVER['REMOTE_HOST']) ? $this->SERVER['REMOTE_HOST'] : NULL;
	}

	/**
	 * Returns the protocol (http or https) used in the request
	 *
	 * @return string The used protol, either http or https
	 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getRequestProtocol() {
		$protocol = 'http';
		if (isset($this->SERVER['SSL_SESSION_ID'])) $protocol = 'https';
		if (isset($this->SERVER['HTTPS'])) {
			if ($this->SERVER['HTTPS'] === 'on' || strcmp($this->SERVER['HTTPS'], '1') === 0) {
				$protocol = 'https';
			}
		}
		return $protocol;
	}

	/**
	 * Returns the request URI
	 *
	 * @return F3::FLOW3::Property::DataType::URI The request URI consisting of protocol, path and query, eg. http://typo3.org/xyz/index.php/arg1/arg2/arg3/?arg1,arg2,arg3&p1=parameter1&p2[key]=value
	 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getRequestURI() {
		if (isset($this->SERVER['REQUEST_URI'])) {
			$requestURIString = $this->getRequestProtocol() . '://' . $this->getHTTPHost() . $this->SERVER['REQUEST_URI'];
		} else {
			$requestURIString = $this->getRequestProtocol() . '://' . $this->getHTTPHost() . '/' . ltrim($this->getScriptPathAndFileName(), '/') . (isset($this->SERVER['QUERY_STRING']) ? '?' . $this->SERVER['QUERY_STRING']:'');
		}

		$requestURI = new F3::FLOW3::Property::DataType::URI($requestURIString);
		return $requestURI;
	}

	/**
	 * Returns the script file name (usually index.php)
	 *
	 * @return string The script file name
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getScriptFileName() {
		return basename($this->SERVER['SCRIPT_FILENAME']);
	}

	/**
	 * Returns the full, absolute path and the file name of the executed PHP file
	 *
	 * @return string The full path and file name of the PHP script
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getScriptPathAndFilename() {
		return F3::FLOW3::Utility::Files::getUnixStylePath($this->SERVER['SCRIPT_FILENAME']);
	}

	/**
	 * Returns the relative path (ie. relative to the web root) and name of the
	 * script as it was accessed through the webserver.
	 *
	 * @return string Relative path and name of the PHP script as accessed through the web
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getScriptRequestPathAndName() {
		if (isset($this->SERVER['SCRIPT_NAME'])) return $this->SERVER['SCRIPT_NAME'];
		if (isset($this->SERVER['ORIG_SCRIPT_NAME'])) return $this->SERVER['ORIG_SCRIPT_NAME'];
	}

	/**
	 * Returns the request method as found in the SERVER environment.
	 *
	 * @return string The request method
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getRequestMethod() {
		if (!isset($this->SERVER['REQUEST_METHOD'])) return self::REQUEST_METHOD_UNKNOWN;

		switch ($this->SERVER['REQUEST_METHOD']) {
			case 'GET' : return self::REQUEST_METHOD_GET;
			case 'POST' : return self::REQUEST_METHOD_POST;
			case 'PUT' : return self::REQUEST_METHOD_PUT;
			case 'DELETE' : return self::REQUEST_METHOD_DELETE;
			case 'HEAD' : return self::REQUEST_METHOD_HEAD;
			case 'OPTIONS' : return self::REQUEST_METHOD_OPTIONS;
		}
		return self::REQUEST_METHOD_UNKNOWN;
	}

	/**
	 * Returns the number of command line arguments, including the program name!
	 *
	 * @return integer The number of command line arguments passed to the main script.
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getCommandLineArgumentCount() {
		return isset($this->SERVER['argc']) ? $this->SERVER['argc'] : 0;
	}

	/**
	 * Returns an array of arguments passed through the command line.
	 * Only makes sense in CLI mode of course.
	 *
	 * @return array The command line arguments (including program name), if any
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getCommandLineArguments() {
		return isset($this->SERVER['argv']) ? $this->SERVER['argv'] : array();
	}

	/**
	 * Returns a lowercase string which identifies the currently used
	 * Server API (SAPI).
	 *
	 * Common SAPIS are "apache", "isapi", "cli", "cgi" etc.
	 *
	 * @return string A lower case string identifying the SAPI used
	 * @see php_sapi_name()/PHP_SAPI
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getSAPIName() {
		return $this->SAPIName;
	}

	/**
	 * Returns the GET arguments array from the _GET superglobal
	 *
	 * @return array Unfiltered, raw, insecure, tainted GET arguments
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function getGETArguments() {
		return $this->GET;
	}

	/**
	 * Returns the POST arguments array from the _POST superglobal
	 *
	 * @return array Unfiltered, raw, insecure, tainted POST arguments
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getPOSTArguments() {
		return $this->POST;
	}

	/**
	 * Returns the full path to FLOW3's temporary directory.
	 *
	 * @return string Path to PHP's temporary directory
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getPathToTemporaryDirectory() {
		return $this->temporaryDirectory;
	}

	/**
	 * Creates FLOW3's temporary directory - or at least asserts that it exists and is
	 * writeable.
	 *
	 * @param string Full path to the base for the temporary directory
	 * @return string The full path to the temporary directory
	 * @throws F3::FLOW3::Utility::Exception if the temporary directory could not be created or is not writeable
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function createTemporaryDirectory($temporaryDirectoryBase) {
		$temporaryDirectoryBase = F3::FLOW3::Utility::Files::getUnixStylePath($temporaryDirectoryBase);
		if (substr($temporaryDirectoryBase, -1, 1) != '/') $temporaryDirectoryBase .= '/';

		$pathHash = md5(FLOW3_PATH_PUBLIC . $this->getSAPIName());
		$processUser = extension_loaded('posix') ? posix_getpwuid(posix_geteuid()) : array('name' => 'default');
		$temporaryDirectory = $temporaryDirectoryBase . $pathHash . '/' . $processUser['name'] . '/';

		if (!is_dir($temporaryDirectory)) {
			try {
				F3::FLOW3::Utility::Files::createDirectoryRecursively($temporaryDirectory);
			} catch (F3::FLOW3::Error::Exception $exception) {
			}
		}

		if (!is_writable($temporaryDirectory)) {
			throw new F3::FLOW3::Utility::Exception('The temporary directory "' . $temporaryDirectory . '" could not be created or is not writeable for the current user "' . $processUser['name'] . '". Please make this directory writeable or define another temporary directory by setting the respective system environment variable (eg. TMPDIR) or defining it in the FLOW3 configuration.', 1216287176);
		}

		return $temporaryDirectory;
	}
}
?>