<?php if (!FRONT_LOADED) die('You do not have permission to view this file directly.');

abstract class System
{

	const VERSION = '0.1';
	const CODENAME = 'Gilead';
	
	// The singleton instance of the controller
	public static $instance;

	// Include paths	
	protected static $include_paths;
	
	// Internal caches and write status
	protected static $internal_cache = array();
	protected static $write_cache;
	protected static $internal_cache_path;

	
	/**
	 * Set up the initial environment
	 */
	public static function init()
	{
	
		// Set autoloader
		spl_autoload_register(array('System', 'auto_load'));
		
		// Process system include paths
		System::include_paths(TRUE);
		
		// Define system error constant
		define('E_SYSTEM', 42);

		// Define 404 error constant
		define('E_PAGE_NOT_FOUND', 43);

		// Define database error constant
		define('E_DATABASE_ERROR', 44);
		
		System_Exception::enable();
		
	}
	
	/**
	 * Loads the controller and initialises it
	 */
	public static function & instance()
	{
		if (System::$instance === NULL) {
			
			require_once Router::$controller_path;
			
			try
			{
				$class = new ReflectionClass(ucfirst(Router::$controller).'_Controller');
			}
			catch (ReflectionException $e)
			{
				die('Controller does not exist');
			}
			
			$controller = $class->newInstance();
			
			try
			{
				// Load the controller method
				$method = $class->getMethod(Router::$method);

				// Method exists
				if (Router::$method[0] === '_')
				{
					// Do not allow access to hidden methods
					die('404');
				}

				if ($method->isProtected() or $method->isPrivate())
				{
					// Do not attempt to invoke protected methods
					throw new ReflectionException('protected controller method');
				}

				// Default arguments
				$arguments = Router::$arguments;
			}
			catch (ReflectionException $e)
			{
				// Use __call instead
				$method = $class->getMethod('__call');

				// Use arguments in __call format
				$arguments = array(Router::$method, Router::$arguments);
			}
			
			// Execute the controller method
			$method->invokeArgs($controller, $arguments);
			
		}
		
		return System::$instance;
	}
	
	/**
	 * Provides class auto-loading.
	 *
	 * @param   string  name of class
	 * @return  bool
	 */
	public static function auto_load($class)
	{
	
		if (class_exists($class, FALSE) || interface_exists($class, FALSE)) {
			return TRUE;
		}
		
		if (($suffix = strrpos($class, '_')) > 0)
		{
			// Find the class suffix
			$suffix = substr($class, $suffix + 1);
		}
		else
		{
			// No suffix
			$suffix = FALSE;
		}

		if ($suffix === 'Core')
		{
			$type = 'libraries';
			$file = substr($class, 0, -5);
		}
		elseif ($suffix === 'Controller')
		{
			$type = 'controllers';
			// Lowercase filename
			$file = strtolower(substr($class, 0, -11));
		}
		elseif ($suffix === 'Model')
		{
			$type = 'models';
			// Lowercase filename
			$file = strtolower(substr($class, 0, -6));
		}
		elseif ($suffix === 'Driver')
		{
			$type = 'libraries/drivers';
			$file = str_replace('_', '/', substr($class, 0, -7));
		}
		else
		{
			// This could be either a library or a helper, but libraries must
			// always be capitalized, so we check if the first character is
			// uppercase. If it is, we are loading a library, not a helper.
			$type = ($class[0] < 'a') ? 'libraries' : 'helpers';
			$file = $class;
		}
		
		if ($filename = System::find_file($type, $file))
		{
			// Load the class
			require $filename;
		}
		else
		{
			// The class could not be found
			return FALSE;
		}
		
		if ($suffix !== 'Core' AND class_exists($class.'_Core', FALSE))
		{
			// Class extension to be evaluated
			$extension = 'class '.$class.' extends '.$class.'_Core { }';

			// Start class analysis
			$core = new ReflectionClass($class.'_Core');

			if ($core->isAbstract())
			{
				// Make the extension abstract
				$extension = 'abstract '.$extension;
			}

			// Transparent class extensions are handled using eval. This is
			// a disgusting hack, but it gets the job done.
			eval($extension);
		}
		
		return TRUE;
	
	}
	
	/**
	 * Find a resource file in a given directory. Files will be located according
	 * to the order of the include paths. Configuration and i18n files will be
	 * returned in reverse order.
	 *
	 * @throws  System_Exception  if file is required and not found
	 * @param   string   directory to search in
	 * @param   string   filename to look for (without extension)
	 * @param   boolean  file required
	 * @param   string   file extension
	 * @return  array    if the type is config, i18n or l10n
	 * @return  string   if the file is found
	 * @return  FALSE    if the file is not found
	 */
	public static function find_file($directory, $filename, $required = FALSE)
	{
		$search = $directory.'/'.$filename.'.'.EXT;
		
		if (isset(System::$internal_cache['find_file_paths'][$search]))
			return System::$internal_cache['find_file_paths'][$search];

		// Load include paths
		$paths = System::$include_paths;

		// Nothing found, yet
		$found = NULL;
		
		if ($directory === 'config')
		{
			// Search in reverse, for merging
			$paths = array_reverse($paths);

			foreach ($paths as $path)
			{
				if (is_file($path.$search))
				{
					// A matching file has been found
					$found[] = $path.$search;
				}
			}
		}
		else
		{
			foreach ($paths as $path)
			{
				if (is_file($path.$search))
				{
					// A matching file has been found
					$found = $path.$search;

					// Stop searching
					break;
				}
			}
		}
		
		if ($found === NULL)
		{
			if ($required === TRUE) {
				// perform some show-stopping error code here
			} else {
				$found = FALSE;
			}
		}
		
		return System::$internal_cache['find_file_paths'][$search] = $found;
		
	}
	
	
	/**
	 * Get all include paths. APPPATH is the first path, followed by module
	 * paths in the order they are configured, follow by the SYSPATH.
	 *
	 * @param   boolean  re-process the include paths
	 * @return  array
	 */
	public static function include_paths($process = FALSE)
	{
		if ($process === TRUE)
		{
			// Add APPPATH as the first path
			System::$include_paths = array(APPPATH);

			/*foreach (System::config('core.modules') as $path)
			{
				if ($path = str_replace('\\', '/', realpath($path)))
				{
					// Add a valid path
					System::$include_paths[] = $path.'/';
				}
			}*/

			// Add SYSPATH as the last path
			System::$include_paths[] = SYSPATH;

			// Clear cached include paths
			self::$internal_cache['find_file_paths'] = array();
			if ( ! isset(self::$write_cache['find_file_paths']))
			{
				// Write cache at shutdown
				self::$write_cache['find_file_paths'] = TRUE;
			}

		}
		
		return System::$include_paths;

	}
	
	/**
	 * Get a config item or group proxies System_Config.
	 *
	 * @param   string   item name
	 * @param   boolean  force a forward slash (/) at the end of the item
	 * @param   boolean  is the item required?
	 * @return  mixed
	 */
	public static function config($key, $slash = FALSE, $required = FALSE)
	{
		return System_Config::instance()->get($key,$slash,$required);
	}
	
	/**
	 * Returns the value of a key, defined by a 'dot-noted' string, from an array.
	 *
	 * @param   array   array to search
	 * @param   string  dot-noted string: foo.bar.baz
	 * @return  string  if the key is found
	 * @return  void    if the key is not found
	 */
	public static function key_string($array, $keys)
	{
		if (empty($array))
			return NULL;

		// Prepare for loop
		$keys = explode('.', $keys);

		do
		{
			// Get the next key
			$key = array_shift($keys);

			if (isset($array[$key]))
			{
				if (is_array($array[$key]) AND ! empty($keys))
				{
					// Dig down to prepare the next loop
					$array = $array[$key];
				}
				else
				{
					// Requested key was found
					return $array[$key];
				}
			}
			else
			{
				// Requested key is not set
				break;
			}
		}
		while ( ! empty($keys));

		return NULL;
	}

	/**
	 * Sets values in an array by using a 'dot-noted' string.
	 *
	 * @param   array   array to set keys in (reference)
	 * @param   string  dot-noted string: foo.bar.baz
	 * @return  mixed   fill value for the key
	 * @return  void
	 */
	public static function key_string_set( & $array, $keys, $fill = NULL)
	{
		if (is_object($array) AND ($array instanceof ArrayObject))
		{
			// Copy the array
			$array_copy = $array->getArrayCopy();

			// Is an object
			$array_object = TRUE;
		}
		else
		{
			if ( ! is_array($array))
			{
				// Must always be an array
				$array = (array) $array;
			}

			// Copy is a reference to the array
			$array_copy =& $array;
		}

		if (empty($keys))
			return $array;

		// Create keys
		$keys = explode('.', $keys);

		// Create reference to the array
		$row =& $array_copy;

		for ($i = 0, $end = count($keys) - 1; $i <= $end; $i++)
		{
			// Get the current key
			$key = $keys[$i];

			if ( ! isset($row[$key]))
			{
				if (isset($keys[$i + 1]))
				{
					// Make the value an array
					$row[$key] = array();
				}
				else
				{
					// Add the fill key
					$row[$key] = $fill;
				}
			}
			elseif (isset($keys[$i + 1]))
			{
				// Make the value an array
				$row[$key] = (array) $row[$key];
			}

			// Go down a level, creating a new row reference
			$row =& $row[$key];
		}

		if (isset($array_object))
		{
			// Swap the array back in
			$array->exchangeArray($array_copy);
		}
	}

}

?>
