<?php

namespace Session;

use \Exception;
use \Helper\Cookie;
use \Session\Adapter;
use \Session\SessionException;

abstract class Session {

	/**
	 * @var  string  default session adapter
	 */
	public static $default = 'native';

	/**
	 * @var  array  session instances
	 */
	public static $instances = array();

	/**
	 * Creates a singleton session of the given type. Some session types
	 * (native, database) also support restarting a session by passing a
	 * session id as the second parameter.
	 *
	 *     $session = Session::instance();
	 *
	 * [!!] [Session::write] will automatically be called when the request ends.
	 *
	 * @param   string  $type   type of session (native, cookie, etc)
	 * @param   string  $id     session identifier
	 * @return  Session
	 */
	public static function instance($type = null, $id = null)
	{
		if ($type === null) {
			// Use the default type
			$type = static::$default;
		}

		if ( ! isset(static::$instances[$type])) {
			// Set the session class name
			$class = 'Adapter\\'.ucfirst($type);

			// Create a new session instance
			static::$instances[$type] = $session = new $class($id);

			// Write the session at shutdown
			register_shutdown_function(array($session, 'write'));
		}

		return static::$instances[$type];
	}

	/**
	 * Instance of class Cookie
	 * @var object
	 */
	protected $_cookie;

	/**
	 * Cookie name
	 * @var string
	 */
	protected $_name = 'session';

	/**
	 * Cookie lifetime
	 * @var integer
	 */
	protected $_lifetime = 3600;

	/**
	 * Session data
	 * @var array
	 */
	protected $_data = array();

	/**
	 * Session destroyed?
	 * @var boolean
	 */
	protected $_destroyed = false;

	/**
	 * Overloads the name, lifetime, and encrypted session settings.
	 *
	 * @param   string  $id     session id
	 * @return  void
	 */
	protected function __construct($id = null)
	{
		$this->_cookie = new Cookie;

		// Load the session
		$this->read($id);
	}

	/**
	 * Session object is rendered to a serialized string. If encryption is
	 * enabled, the session will be encrypted. If not, the output string will
	 * be encoded.
	 *
	 *     echo $session;
	 *
	 * @return  string
	 * @uses    Encrypt::encode
	 */
	public function __toString()
	{
		$data = $this->_serialize($this->_data);
		$data = $this->_encode($data);

		return $data;
	}

	/**
	 * Returns the current session array. The returned array can also be
	 * assigned by reference.
	 *
	 *     // Get a copy of the current session data
	 *     $data = $session->asArray();
	 *
	 *     // Assign by reference for modification
	 *     $data =& $session->asArray();
	 *
	 * @return  array
	 */
	public function & asArray()
	{
		return $this->_data;
	}

	/**
	 * Get the current session id, if the session supports it.
	 *
	 *     $id = $session->id();
	 *
	 * [!!] Not all session types have ids.
	 *
	 * @return  string
	 */
	public function id()
	{
		return null;
	}

	/**
	 * Get the current session cookie name.
	 *
	 *     $name = $session->name();
	 *
	 * @return  string
	 * @since   3.0.8
	 */
	public function name()
	{
		return $this->_name;
	}

	/**
	 * Get a variable from the session array.
	 *
	 *     $foo = $session->get('foo');
	 *
	 * @param   string  $key        variable name
	 * @param   mixed   $default    default value to return
	 * @return  mixed
	 */
	public function get($key, $default = null)
	{
		return array_key_exists($key, $this->_data) ? $this->_data[$key] : $default;
	}

	/**
	 * Get and delete a variable from the session array.
	 *
	 *     $bar = $session->get_once('bar');
	 *
	 * @param   string  $key        variable name
	 * @param   mixed   $default    default value to return
	 * @return  mixed
	 */
	public function get_once($key, $default = null)
	{
		$value = $this->get($key, $default);
		unset($this->_data[$key]);
		return $value;
	}

	/**
	 * Set a variable in the session array.
	 *
	 *     $session->set('foo', 'bar');
	 *
	 * @param   string  $key    variable name
	 * @param   mixed   $value  value
	 * @return  $this
	 */
	public function set($key, $value)
	{
		$this->_data[$key] = $value;
		return $this;
	}

	/**
	 * Set a variable by reference.
	 *
	 *     $session->bind('foo', $foo);
	 *
	 * @param   string  $key    variable name
	 * @param   mixed   $value  referenced value
	 * @return  $this
	 */
	public function bind($key, & $value)
	{
		$this->_data[$key] =& $value;
		return $this;
	}

	/**
	 * Removes a variable in the session array.
	 *
	 *     $session->delete('foo');
	 *
	 * @param   string  $key,...    variable name
	 * @return  $this
	 */
	public function delete($key)
	{
		$args = func_get_args();
		foreach ($args as $key) {
			unset($this->_data[$key]);
		}
		return $this;
	}

	/**
	 * Loads existing session data.
	 *
	 *     $session->read();
	 *
	 * @param   string  $id session id
	 * @return  void
	 */
	public function read($id = null)
	{
		$data = null;

		try {
			if (is_string($data = $this->_read($id))) {
				$data = $this->_decode($data);
				$data = $this->_unserialize($data);
			}
		} catch (Exception $e) {
			throw new SessionException('Error reading session data.', SessionException::SESSION_CORRUPT);
		}

		if (is_array($data)) {
			// Load the data locally
			$this->_data = $data;
		}
	}

	/**
	 * Generates a new session id and returns it.
	 *
	 *     $id = $session->regenerate();
	 *
	 * @return  string
	 */
	public function regenerate()
	{
		return $this->_regenerate();
	}

	/**
	 * Sets the last_active timestamp and saves the session.
	 *
	 *     $session->write();
	 *
	 * [!!] Any errors that occur during session writing will be logged,
	 * but not displayed, because sessions are written after output has
	 * been sent.
	 *
	 * @return  boolean
	 */
	public function write()
	{
		if (headers_sent() OR $this->_destroyed) {
			// Session cannot be written when the headers are sent or when
			// the session has been destroyed
			return false;
		}

		// Set the last active timestamp
		$this->_data['last_active'] = time();

		try {
			return $this->_write();
		} catch (\Exception $e) {
			// Log & ignore all errors when a write fails
			$this->_log($e->getMessage());
			return false;
		}
	}

	/**
	 * Completely destroy the current session.
	 *
	 *     $success = $session->destroy();
	 *
	 * @return  boolean
	 */
	public function destroy()
	{
		if ($this->_destroyed === false) {
			if ($this->_destroyed = $this->_destroy()) {
				// The session has been destroyed, clear all data
				$this->_data = array();
			}
		}
		return $this->_destroyed;
	}

	/**
	 * Restart the session.
	 *
	 *     $success = $session->restart();
	 *
	 * @return  boolean
	 */
	public function restart()
	{
		if ($this->_destroyed === false) {
			// Wipe out the current session.
			$this->destroy();
		}

		// Allow the new session to be saved
		$this->_destroyed = false;

		return $this->_restart();
	}

	/**
	 * Serializes the session data.
	 *
	 * @param   array  $data  data
	 * @return  string
	 */
	protected function _serialize($data)
	{
		return serialize($data);
	}

	/**
	 * Unserializes the session data.
	 *
	 * @param   string  $data  data
	 * @return  array
	 */
	protected function _unserialize($data)
	{
		return unserialize($data);
	}

	/**
	 * Encodes the session data using [base64_encode].
	 *
	 * @param   string  $data  data
	 * @return  string
	 */
	protected function _encode($data)
	{
		return base64_encode($data);
	}

	/**
	 * Decodes the session data using [base64_decode].
	 *
	 * @param   string  $data  data
	 * @return  string
	 */
	protected function _decode($data)
	{
		return base64_decode($data);
	}

	/**
	 * Logging in syslog
	 *
	 * @param string $msg
	 * @return void
	 */
	protected function _log($msg)
	{
		openlog($host, LOG_CONS, LOG_USER);
		syslog(LOG_ERR, $msg);
		closelog();
	}

	/**
	 * Loads the raw session data string and returns it.
	 *
	 * @param   string  $id session id
	 * @return  string
	 */
	abstract protected function _read($id = null);

	/**
	 * Generate a new session id and return it.
	 *
	 * @return  string
	 */
	abstract protected function _regenerate();

	/**
	 * Writes the current session.
	 *
	 * @return  boolean
	 */
	abstract protected function _write();

	/**
	 * Destroys the current session.
	 *
	 * @return  boolean
	 */
	abstract protected function _destroy();

	/**
	 * Restarts the current session.
	 *
	 * @return  boolean
	 */
	abstract protected function _restart();

}
