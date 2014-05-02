<?php

namespace Session\Adapter;

use \Helper\Cookie;
use \Session\Session;

class Native extends Session {

	/**
	 * @return  string
	 */
	public function id()
	{
		return session_id();
	}

	/**
	 * @param   string  $id  session id
	 * @return  null
	 */
	protected function _read($id = null)
	{
		// Sync up the session cookie with Cookie parameters
		session_set_cookie_params(
			$this->_lifetime,
			$this->_cookie->path,
			$this->_cookie->domain,
			$this->_cookie->secure,
			$this->_cookie->httponly);

		// Do not allow PHP to send Cache-Control headers
		session_cache_limiter(false);

		if (!session_id()) {
			// Set the session cookie name
			session_name($this->_name);

			if ($id) {
				// Set the session id
				session_id($id);
			}

			// Start the session
			session_start();
		}

		// Use the $_SESSION global for storing data
		$this->_data =& $_SESSION;

		return null;
	}

	/**
	 * @return  string
	 */
	protected function _regenerate()
	{
		// Regenerate the session id
		session_regenerate_id();

		return session_id();
	}

	/**
	 * @return  bool
	 */
	protected function _write()
	{
		// Write and close the session
		session_write_close();

		return true;
	}

	/**
	 * @return  bool
	 */
	protected function _restart()
	{
		// Fire up a new session
		$status = session_start();

		// Use the $_SESSION global for storing data
		$this->_data =& $_SESSION;

		return $status;
	}

	/**
	 * @return  bool
	 */
	protected function _destroy()
	{
		// Destroy the current session
		session_destroy();

		// Did destruction work?
		$status = ! session_id();

		if ($status) {
			// Make sure the session cannot be restarted
			$this->_cookie->delete($this->_name);
		}

		return $status;
	}
}
