<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Logging class.
 *
 * $Id: System_Log.php 4679 2009-11-10 01:45:52Z isaiah $
 *
 * @package    Core
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class System_Log {

	// Configuration
	protected static $config;

	// Drivers
	protected static $drivers;

	// Logged messages
	protected static $messages;

	/**
	 * Add a new message to the log.
	 *
	 * @param   string  type of message
	 * @param   string  message text
	 * @return  void
	 */
	public static function add($type, $message)
	{
		// Make sure the drivers and config are loaded
		if ( ! is_array(System_Log::$config))
		{
			System_Log::$config = System::config('log');
		}

		if ( ! is_array(System_Log::$drivers))
		{
			foreach ( (array) System::config('log.drivers') as $driver_name)
			{
				// Set driver name
				$driver = 'Log_'.ucfirst($driver_name).'_Driver';

				// Load the driver
				if ( ! System::auto_load($driver))
					throw new System_Exception('Log Driver Not Found: %driver%', array('%driver%' => $driver));

				// Initialize the driver
				$driver = new $driver(array_merge(System::config('log'), System::config('log_'.$driver_name)));

				// Validate the driver
				if ( ! ($driver instanceof Log_Driver))
					throw new System_Exception('%driver% does not implement the Log_Driver interface', array('%driver%' => $driver));

				System_Log::$drivers[] = $driver;
			}
		}

		System_Log::$messages[] = array('date' => time(), 'type' => $type, 'message' => $message);
	}

	/**
	 * Save all currently logged messages.
	 *
	 * @return  void
	 */
	public static function save()
	{
		if (empty(System_Log::$messages))
			return;

		foreach (System_Log::$drivers as $driver)
		{
			// We can't throw exceptions here or else we will get a
			// Exception thrown without a stack frame error
			try
			{
				$driver->save(System_Log::$messages);
			}
			catch(Exception $e){}
		}

		// Reset the messages
		System_Log::$messages = array();
	}
}
