<?php

namespace projectorangebox\orange\library\log;

use \Monolog\Logger;
use \CI_Log;

/**
 *
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

/**
 * Extension to CodeIgniter Log Class
 *
 * Handle general logging with optional Monolog library support
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @config config.log_threshold `0`
 * @config config.log_path `ROOTPATH.'/var/logs/'`
 * @config config.log_file_extension `log`
 * @config config.log_file_permissions `0644`
 * @config config.log_date_format `Y-m-d H:i:s.u`
 *
 * @method __call
 *
 */

class Monolog extends CI_Log
{
	/**
	 * Local reference to monolog object
	 *
	 * @var \Monolog\Logger
	 */
	protected $monolog = null; /* singleton reference to monolog */

	/**
	 * Local reference to logging configurations
	 *
	 * @var Array
	 */
	protected $config = [];

	/**
	 * String to PSR error levels
	 *
	 * @var Array
	 */
	protected $psr_levels = [
		'EMERGENCY' => 1,
		'ALERT'     => 2,
		'CRITICAL'  => 4,
		'ERROR'     => 8,
		'WARNING'   => 16,
		'NOTICE'    => 32,
		'INFO'      => 64,
		'DEBUG'     => 128,
	];

	/**
	 * String to RFC error levels
	 *
	 * @var Array
	 */
	protected $rfc_log_levels = [
		'DEBUG'     => 100,
		'INFO'      => 200,
		'NOTICE'    => 250,
		'WARNING'   => 300,
		'ERROR'     => 400,
		'CRITICAL'  => 500,
		'ALERT'     => 550,
		'EMERGENCY' => 600,
	];

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 */
	public function __construct(array &$config = [])
	{
		/* we need to go low level here because other services might try to log therefore create a loop */
		$this->config = array_replace(\loadConfigFile('config'),$config);

		$this->configure()->attachMonolog();

		$this->write_log('info','Log Monolog Class Initialized');
	}

	/**
	 *
	 * Allow the assigning of any configuration that starts with log_
	 *
	 * #### Example
	 * ```php
	 * ci('log')->log_threshold(255)
	 * ci('log')->log_path(APPPATH.'/logs')
	 * ```
	 * @access public
	 *
	 * @param string $name
	 * @param array $arguments
	 *
	 * @return Log
	 *
	 */
	public function __call(string $name, array $arguments) : self
	{
		if (substr($name, 0, 4) == 'log_') {
			$this->config[$name] = $arguments[0];

			/* resetup */
			$this->configure();
		}

		return $this;
	}

	protected function attachMonolog(): self
	{
		if (!$this->monolog) {
			/* expose $config to monolog config file */
			$config = &$this->config;

			/**
			 * Create a instance of monolog for the bootstrapper
			 * Make the monolog "channel" "CodeIgniter"
			 * This is a local variable so the bootstrapper can attach stuff to it
			 */
			$monolog = new Logger('CodeIgniter');

			/**
			 * Find the monolog_bootstrap files
			 * This is NOT a standard Codeigniter config
			 * It includes PHP code which can use the $monolog object we just made
			 */
			if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/monolog.php')) {
				include APPPATH.'config/'.ENVIRONMENT.'/monolog.php';
			} elseif (file_exists(APPPATH.'config/monolog.php')) {
				include APPPATH.'config/monolog.php';
			}

			/**
			 * Attach the monolog instance to our class for later use
			 */
			$this->monolog = &$monolog;
		}

		return $this;
	}

	/**
	 *
	 * Write to log file
	 * Generally this function will be called using the global log_message() function
	 *
	 * @access public
	 *
	 * @param $level error|debug|info
	 * @param $msg the error message
	 *
	 * @return bool
	 *
	 */
	public function write_log($level, $msg) : bool
	{
		/**
		 * This function has multiple exit points
		 * because we try to bail as soon as possible
		 * if no logging is needed to keep it a little faster
		 */
		if (!$this->_enabled) {
			return false;
		}

		/* normalize */
		$level = strtoupper($level);

		/* bitwise PSR 3 Mode */
		if ((!array_key_exists($level, $this->psr_levels)) || (!($this->_threshold & $this->psr_levels[$level]))) {
			return false;
		}

		/* logging level check passed - log something! */

		switch ($level) {
		case 'EMERGENCY': // 1
			$this->monolog->emergency($msg);
			break;
		case 'ALERT': // 2
			$this->monolog->alert($msg);
			break;
		case 'CRITICAL': // 4
			$this->monolog->critical($msg);
			break;
		case 'ERROR': // 8
			$this->monolog->error($msg);
			break;
		case 'WARNING': // 16
			$this->monolog->warning($msg);
			break;
		case 'NOTICE': // 32
			$this->monolog->notice($msg);
			break;
		case 'INFO': // 64
			$this->monolog->info($msg);
			break;
		case 'DEBUG': // 128
			$this->monolog->debug($msg);
			break;
		}

		return true;
	}

	/**
	 *
	 * Test whether logging is enabled
	 *
	 * #### Example
	 * ```php
	 * ci('log')->is_enabled();
	 * ```
	 * @access public
	 *
	 * @return Bool
	 *
	 */
	public function is_enabled() : Bool
	{
		return $this->_enabled;
	}

	/**
	 *
	 * configure / reconfigure configure after a configuration value change
	 *
	 * @access protected
	 *
	 */
	protected function configure() : self
	{
		if (isset($this->config['log_threshold'])) {
			$log_threshold = $this->config['log_threshold'];

			/* if they sent in a string split it into a array */
			if (is_string($log_threshold)) {
				$log_threshold = explode(',', $log_threshold);
			}

			/* is the array empty? */
			if (is_array($log_threshold)) {
				if (count($log_threshold) == 0) {
					$log_threshold = 0;
				}
			}

			/* Is all in the array (uppercase or lowercase?) */
			if (is_array($log_threshold)) {
				if (array_search('all', $log_threshold) !== false) {
					$log_threshold = 255;
				}
			}

			/* build the bitwise integer */
			if (is_array($log_threshold)) {
				$int = 0;

				foreach ($log_threshold as $t) {
					$t = strtoupper($t);

					if (isset($this->psr_levels[$t])) {
						$int += $this->psr_levels[$t];
					}
				}

				$log_threshold = $int;
			}

			$this->_threshold = (int)$log_threshold;

			$this->_enabled = ($this->_threshold > 0);
		}

		isset(self::$func_overload) || self::$func_overload = (extension_loaded('mbstring') && ini_get('mbstring.func_overload'));

		if (isset($this->config['log_file_extension'])) {
			$this->_file_ext = (!empty($this->config['log_file_extension'])) 	? ltrim($this->config['log_file_extension'], '.') : 'php';
		}

		if (isset($this->config['log_path'])) {
			$this->_log_path = ($this->config['log_path'] !== '') ? $this->config['log_path'] : APPPATH.'logs/';

			file_exists($this->_log_path) || mkdir($this->_log_path, 0755, true);

			if (!is_dir($this->_log_path) || !is_really_writable($this->_log_path)) {
				/* can't write */
				$this->_enabled = false;
			}
		}

		if (!empty($this->config['log_date_format'])) {
			$this->_date_fmt = $this->config['log_date_format'];
		}

		if (!empty($this->config['log_file_permissions']) && is_int($this->config['log_file_permissions'])) {
			$this->_file_permissions = $this->config['log_file_permissions'];
		}

		return $this;
	}
} /* End of Class */
