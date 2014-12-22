<?php

$required_files = array('config.php', 'inptblock.php');

foreach ($required_files as $required_file)
{
	require_once($required_file);
}

class Inpt
{
	var $config = null;
	var $inpt_blocks_cache = array();

	function Inpt()
	{
		global $inpt_conf;
		$this->config = $inpt_conf;
	}

	function scan()
	{
		$files = $this->enumerate_files($this->config['BASE_DIR']);
		$inpt_files = array();
		
		foreach ($files as $file) {
			if (count($this->get_inpt(file_get_contents($file))) > 0)
			{
				$inpt_files[] = $file;
			}
		}

		return $inpt_files;
	}

	function get_inpt($html)
	{
		//Lazy loading
		if (isset($this->inpt_blocks_cache[hash('sha256', $html)]))
		{
			return $this->inpt_blocks_cache[hash('sha256', $html)];
		}

		$dom = new DOMDocument;
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = true;
		@$dom->loadHTML($html);

		$comment = null;
		$inptNodes = array();


		//TODO: Clean this up, cause There Has To Be A Better Way.
		foreach($dom->documentElement->childNodes as $node)
		{
			if ($node->nodeName == 'body')
			{
				foreach($node->childNodes as $innerNode)
				{
					if ($innerNode->nodeName == '#comment')
					{
						$comment = $dom->saveHTML($innerNode);
					}
					else
					{
						if (isset($innerNode->attributes) && null != ($innerNode->attributes->getNamedItem('class')))
						{
							if ($innerNode->attributes->getNamedItem('class')->nodeValue == 'inpt')
							{
								$inpt_node = new InptBlock();

								if ($comment != null)
								{
									$inpt_node->metadata = $this->parse_metadata($comment);
									$inpt_node->name = isset($inpt_node->metadata['inpt-title']) ? $inpt_node->metadata['inpt-title'] : "";
								}

								$inpt_node->contents = $innerNode->nodeValue;

								$inptNodes[] = $inpt_node;
							}
						}

						if ($innerNode->nodeName != '#text')
						{
							$comment = null;
						}
					}
				}
			}
		}

		//Cache for later
		$this->inpt_blocks_cache[hash('sha256', $html)] = $inptNodes;

		return $inptNodes;
	}

	function enumerate_files($dir)
	{
		$structure = scandir($dir);
		$files = array();

		foreach ($structure as $element)
		{
			$full_name = sprintf('%s%s%s', $dir, $this->config['DIR_SEPARATOR'], $element);

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

	function parse_metadata($str)
	{
		$str = trim($str, '<>!-');
		$metadata = array();
		$str = explode("\n", $str);

		foreach ($str as $line)
		{
			$line = explode(':', $line);

			if (count($line) == 2)
			{
				$metadata[trim($line[0])] = trim($line[1]);
			}
		}

		return $metadata;
	}

	function output_json()
	{
		$json = array();
		$files = $this->scan();

		foreach($files as $file)
		{

			//Figure out page_name
			$filepath = explode($this->config['DIR_SEPARATOR'], $file);
			$filename = $filepath[count($filepath) - 1];
			$filefolder = $filepath[count($filepath) - 2];

			if ($filename == 'index.html')
			{
				if ($filefolder == '..')
				{
					$page_name = 'Home';
				}
				else
				{
					$page_name = ucfirst($filefolder);
				}
			}
			else
			{
				$title_regex = '/<title>(.*)<\/title>/m';
				preg_match($title_regex, file_get_contents($file), $matches);

				if (count($matches) > 1)
				{
					$page_name = ucfirst($matches[1]);
				}
				else
				{
					$filename2 = explode('.', $filename);
					$page_name = ucfirst($filename2[0]);
				}

			}

			//Fetch inpt editable sections and parse metadata
			$sections = $this->get_inpt(file_get_contents($file));

			//Build up json
			$json[$file] = array('page_name' => $page_name, 'page_sections' => $sections);
		}

		header('Content-Type: application/json');
		echo(json_encode($json));
		die();
	}
}


$inpt = new Inpt();

switch (@$_GET['mode']) {

	case 'post':
		//TODO: Handle postback
		break;
	
	default:
		$inpt->output_json();
		break;

}
