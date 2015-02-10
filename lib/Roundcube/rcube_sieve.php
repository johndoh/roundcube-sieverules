<?php

/*
 * rcube_sieve class for managesieve operations (using PEAR::Net_Sieve)
 *
 * @author Aleksander Machniak <alec@alec.pl>
 * @modified by Philip Weir
 *   * Make ruleset name configurable
 *   * Added import functions
 *
 * Copyright (C) 2009-2014 Philip Weir
 *
 * This program is a Roundcube (http://www.roundcube.net) plugin.
 * For configuration see config.inc.php.dist.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Roundcube. If not, see http://www.gnu.org/licenses/.
 */

define('SIEVE_ERROR_CONNECTION', 1);
define('SIEVE_ERROR_LOGIN', 2);
define('SIEVE_ERROR_NOT_EXISTS', 3);	// script not exists
define('SIEVE_ERROR_INSTALL', 4);		// script installation
define('SIEVE_ERROR_ACTIVATE', 5);		// script activation
define('SIEVE_ERROR_OTHER', 255);		// other/unknown error

class rcube_sieve
{
	public $sieve;
	private $ruleset;
	private $importers = array();
	private $elsif;
	private $cache = false;
	public $error = false;
	public $list = array();
	public $script;

	public function __construct($username, $password, $host, $port, $auth_type = NULL, $usetls, $ruleset, $dir, $elsif = true, $auth_cid = NULL, $auth_pw = NULL, $socket_options = array())
	{
		$this->sieve = new Net_Sieve();

		$data = rcube::get_instance()->plugins->exec_hook('sieverules_connect', array(
			'username' => $username, 'password' => $password, 'host' => $host, 'port' => $port,
			'auth_type' => $auth_type, 'usetls' => $usetls, 'ruleset' => $ruleset, 'dir' => $dir,
			'elsif' => $elsif, 'auth_cid' => $auth_cid, 'auth_pw' => $auth_pw, 'socket_options' => $options));

		$username = $data['username'];
		$password = $data['password'];
		$host = $data['host'];
		$port = $data['port'];
		$auth_type = $data['auth_type'];
		$usetls = $data['usetls'];
		$ruleset = $data['ruleset'];
		$dir = $data['dir'];
		$elsif = $data['elsif'];
		$auth_cid = $data['auth_cid'];
		$auth_pw = $data['auth_pw'];
		$socket_options = $data['socket_options'];

		if (PEAR::isError($this->sieve->connect($host, $port, $socket_options, $usetls)))
			return $this->_set_error(SIEVE_ERROR_CONNECTION);

		if (!empty($auth_cid)) {
			$authz = $username;
			$username = $auth_cid;
			$password = $auth_pw;
		}

		if (PEAR::isError($this->sieve->login($username, $password, $auth_type ? strtoupper($auth_type) : NULL, $authz)))
			return $this->_set_error(SIEVE_ERROR_LOGIN);

		$this->ruleset = $ruleset;
		$this->elsif = $elsif;

		if ($this->ruleset !== false) {
			$this->get_script();
		}
		else {
			$this->ruleset = $this->get_active();
			$this->get_script();
		}

		// init importers
		if ($dir = realpath(slashify($dir) . 'importFilters/')) {
			$dir = slashify($dir);
			$handle = opendir($dir);
			while ($importer = readdir($handle)) {
				if (preg_match('/\.php$/', $importer) && is_file($dir . $importer) && is_readable($dir . $importer)) {
					include($dir . $importer);

					$importer = substr($importer, 0, -4);
					$importer = 'srimport_' . $importer;

					if (class_exists($importer, false)) {
						$importerClass = new $importer();
						$this->importers[$importer] = $importerClass;
					}
				}
			}
			closedir($handle);
		}
	}

	public function __destruct()
	{
		$this->sieve->disconnect();
	}

	public function error()
	{
		return $this->error ? $this->error : false;
	}

	public function save($script = '')
	{
		if (!$script)
			$script = $this->script->as_text();

		if (!$script)
			$script = '/* empty script */';

		// allow additional actions before ruleset is saved
		$data = rcube::get_instance()->plugins->exec_hook('sieverules_save', array(
			'ruleset' => $this->ruleset, 'script' => $script, 'obj' => $this));

		if ($data['abort'])
			return $data['message'] ? $data['message'] : false;

		if (PEAR::isError($this->sieve->installScript($this->ruleset, $data['script'])))
			return $this->_set_error(SIEVE_ERROR_INSTALL);

		if ($this->cache) $_SESSION['sieverules_script_cache_' . $this->ruleset] = serialize($this->script);

		return true;
	}

	public function get_extensions()
	{
		if ($this->sieve) {
			$ext = $this->sieve->getExtensions();
			$ext = array_map('strtolower', (array) $ext);
			return $ext;
		}
	}

	public function check_import()
	{
		$result = false;

		foreach ($this->list as $ruleset) {
			if ($ruleset == $this->ruleset)
					continue;

			$script = $this->sieve->getScript($ruleset);

			foreach ($this->importers as $id => $importer) {
				if ($importer->detector($script, $ruleset)) {
					$result = array($id, $importer->name, $ruleset);
					break;
				}
			}
		}

		return $result;
	}

	public function do_import($type, $ruleset)
	{
		$script = $this->sieve->getScript($ruleset);
		$content = $this->importers[$type]->importer($script);
		$this->script->import_filters($content);

		if (is_array($content))
			return $this->save();
		else
			return $this->save($content);
	}

	public function get_script()
	{
		if (!$this->sieve)
			return false;

		if ($this->cache && $_SESSION['sieverules_script_cache']) {
			$this->list = unserialize($_SESSION['sieverules_scripts_list']);
			$this->script = unserialize($_SESSION['sieverules_script_cache_' . $this->ruleset]);
			return;
		}

		$this->list = $this->sieve->listScripts();

		if (PEAR::isError($this->list))
			return $this->_set_error(SIEVE_ERROR_OTHER);

		if (in_array($this->ruleset, $this->list)) {
			$script = $this->sieve->getScript($this->ruleset);

			if (PEAR::isError($script))
				return $this->_set_error(SIEVE_ERROR_OTHER);
		}
		else {
			$this->_set_error(SIEVE_ERROR_NOT_EXISTS);
			$script = '';
		}

		$data = rcube::get_instance()->plugins->exec_hook('sieverules_load', array(
			'ruleset' => $this->ruleset, 'script' => $script, 'obj' => $this));

		$this->script = new rcube_sieve_script($data['script'], $this->get_extensions(), $this->elsif);

		if ($this->cache) {
			$_SESSION['sieverules_scripts_list'] = serialize($this->list);
			$_SESSION['sieverules_script_cache_' . $this->ruleset] = serialize($this->script);
		}
	}

	public function get_active()
	{
		return $this->sieve->getActive();
	}

	public function set_active($ruleset)
	{
		if (PEAR::isError($this->sieve->setActive($ruleset)))
			return $this->_set_error(SIEVE_ERROR_ACTIVATE);

		return true;
	}

	public function del_script($script)
	{
		return $this->sieve->removeScript($script);
	}

	public function set_ruleset($ruleset)
	{
		$this->ruleset = $ruleset;
		$this->get_script();
	}

	public function set_debug($debug)
	{
		$this->sieve->setDebug($debug, array($this, 'debug_handler'));
	}

	public function debug_handler(&$sieve, $message)
	{
		rcube::write_log('sieverules', preg_replace('/\r\n$/', '', $message));
	}

	private function _set_error($error)
	{
		$this->error = $error;
		return false;
	}
}

?>