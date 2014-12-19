<?php

require_once('config.php');

class Inpt
{
	var $config = null;

	function Inpt()
	{
		global $inpt_conf;
		$this->config = $inpt_conf;
	}

	function scan()
	{
		$inpt_match_regex = '/<[a-zA-Z]+ .* ?class=["|\'].* ?inpt.* ?["|\'] ?.*>/m';
		$files = $this->enumerate_files($this->config['BASE_DIR']);
		$inpt_files = array();
		
		foreach ($files as $file) {
			if (preg_match($inpt_match_regex, file_get_contents($file)))
			{
				$inpt_files[] = $file;
			}
		}

		return $inpt_files;
	}

	function enumerate_files($dir)
	{
		$structure = scandir($dir);
		$files = array();

		foreach ($structure as $element)
		{
			$full_name = sprintf('%s/%s', $dir, $element);

			if (is_file($full_name))
			{
				$files[] = $full_name;
			}
			else if (is_dir($full_name) && $element != $this->config['ADMIN_DIR'] && $element != '.' && $element != '..')
			{
				$files = array_merge($files, $this->enumerate_files($full_name));
			}
		}

		return $files;
	}
}

$inpt = new Inpt();
$files = $inpt->scan();

echo "Locating Inpt Files: <br />\n";

foreach($files as $file)
{
	echo sprintf("%s <br />\n", $file);
}