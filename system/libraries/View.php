<?php if (!FRONT_LOADED) die('You do not have permission to view this file directly.');

class View_Core
{

	// The view filename and type
	protected $filename = FALSE;
	protected $filetype = FALSE;

	// View variable storage
	protected $local_data = array();

	public static function factory($name = NULL, $data = NULL, $type = NULL)
	{
		return new View($name, $data, $type);
	}

	public function __construct($name = NULL, $data = NULL, $type = NULL)
	{
	
		if (is_string($name) && $name !== "") {
			$this->set_filename($name, $type);
		}
		
		if (is_array($data) && !emptay($data)) {
			$this->local_data = $data;
		}
		
	}
	
	public function set_filename($name, $type = NULL)
	{
	
		if ($type == NULL) {
			$this->filename = System::find_file('views', $name, TRUE);
			$this->filetype = EXT;
		} else {
			$this->filename = System::find_file('views', $name, TRUE, $type);
			
			if ($this->filetype == NULL) {
				$this->filetype = $type;
			}
		}
	
		return $this;
		
	}
	
	public function render($print = FALSE)
	{
		if (empty($this->filename)) {
			die('You must set the view filename before calling render');
		}
		
		if (is_string($this->filetype)) {
		
			$data = $this->local_data;
			
			$output = $this->load_view($this->filename, $data);
			
			if ($print === TRUE) {
			
				echo $output;
				
				return;
				
			}
			
		} else {
		
			header('Content-type: text/html; charset=utf-8');
			
			if ($print === TRUE) {
				
				if ($file = fopen($this->filename, 'rb')) {
					
					// Display the output
					fpassthru($file);
					fclose($file);
					
				}
				
				return;
				
			}
			
			$output = file_get_contents($this->filename);
			
		}
		
		return $output;
		
	}
	
	public function load_view($view_filename, $data)
	{
	
		if ($view_filename == "") {
			return;
		}
		
		ob_start();
		
		extract($data, EXTR_SKIP);
		
		try
		{
			include $view_filename;
		}
		catch (Exception $e)
		{
			ob_end_clean();
			throw $e;
		}
		
		return ob_get_clean();
	
	}
	
	public function __set($key, $value)
	{
		$this->local_data[$key] = $value;
	}
	
	public function __get($key)
	{
	
		if (isset($this->local_data[$key])) {
			return $this->local_data[$key];
		} elseif (isset($this->$key)) {
			return $this->$key;
		} else {
			die('Undefined view variable: ' . $key);
		}
		
	}
	
	public function __toString()
	{
		return $this->render();
	}

}

?>
