<?php defined('SYSPATH') or die('No direct script access.');
/**
 * System_Config Array driver to get and set
 * configuration options using PHP arrays.
 * 
 * This driver can cache and encrypt settings
 * if required.
 *
 * $Id: Array.php 4679 2009-11-10 01:45:52Z isaiah $
 *
 * @package    System_Config
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Config_Array_Driver extends Config_Driver {

	/**
	 * Internal caching
	 *
	 * @var     Cache
	 */
	protected $cache;

	/**
	 * The name of the internal cache
	 *
	 * @var     string
	 */
	protected $cache_name = 'System_Config_Array_Cache';

	/**
	 * The Encryption library
	 *
	 * @var     Encrypt
	 */
	protected $encrypt;

	/**
	 * Loads a configuration group based on the setting
	 *
	 * @param   string       group 
	 * @param   bool         required 
	 * @return  array
	 * @access  public
	 */
	public function load($group, $required = FALSE)
	{
		if ($group === 'core')
		{
			// Load the application configuration file
			require APPPATH.'config/config'.'.'.EXT;

			if ( ! isset($config['site_domain']))
			{
				// Invalid config file
				throw new System_Config_Exception('Your application configuration file is not valid.');
			}

			return $config;
		}

		// Load matching configs
		$configuration = array();

		if ($files = System::find_file('config', $group, $required))
		{
			foreach ($files as $file)
			{
				require $file;

				if (isset($config) AND is_array($config))
				{
					// Merge in configuration
					$configuration = array_merge($configuration, $config);
				}
			}
		}

		// Return merged configuration
		return $configuration;
	}
} // End Config_Array_Driver
