<?php if (!FRONT_LOADED) die('You do not have permission to view this file directly.');

class Controller_Core
{

	public function __construct()
	{
	
		if (System::$instance == NULL) {
			System::$instance = $this;
		}
	
	}
	
	public function __call($method, $args)
	{
		die('404');
	}

}

?>
