<?php

interface ITemplateEngine
{
    public function initialize(PieCrust $pieCrust);
    public function addTemplatesPaths($paths);
	public function renderString($content, $data);
    public function renderFile($templateName, $data);
}
