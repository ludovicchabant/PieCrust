<?php

interface ITemplateEngine
{
    public function initialize(PieCrust $pieCrust);
    public function renderPage($pageConfig, $pageData);
    public function isCacheValid($templateName, $time);
}
