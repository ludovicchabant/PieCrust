<?php

class Twig_Loader_ExtendedFilesystem extends Twig_Loader_Filesystem implements Twig_LoaderInterface
{
	protected $templateStrings;
	
	public function __construct($paths)
	{
		parent::__construct($paths);
		$this->templateStrings = array();
	}

	public function setTemplateSource($name, $source)
	{
		$this->templateStrings[$name] = $source;
	}
	
	public function getSource($name)
	{
		if (isset($this->templateStrings[$name]))
			return $this->templateStrings[$name];
		return parent::getSource($name);
	}
	
	public function getCacheKey($name)
	{
		if (isset($this->templateStrings[$name]))
			return $this->templateStrings[$name];
		return parent::getCacheKey($name);
	}
	
	public function isFresh($name, $time)
	{
		if (isset($this->templateStrings[$name]))
			return false;
		return parent::isFresh($name, $time);
	}
}
