<?php

interface ITemplateEngine
{
    public function initialize($config);
    public function renderPage($pieCrustApp, $pageConfig, $pageData);
}
