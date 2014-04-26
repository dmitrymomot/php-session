<?php

namespace Session\Adapter;

class Cookie extends Session {

	/**
	 * @param   string  $id  session id
	 * @return  string
	 */
	protected function _read($id = null)
	{
		return $this->_cookie->get($this->_name, null);
	}

	/**
	 * @return  null
	 */
	protected function _regenerate()
	{
		// Cookie sessions have no id
		return null;
	}

	/**
	 * @return  bool
	 */
	protected function _write()
	{
		return $this->_cookie->set($this->_name, $this->__toString(), $this->_lifetime);
	}

	/**
	 * @return  bool
	 */
	protected function _restart()
	{
		return true;
	}

	/**
	 * @return  bool
	 */
	protected function _destroy()
	{
		return $this->_cookie->delete($this->_name);
	}

}
