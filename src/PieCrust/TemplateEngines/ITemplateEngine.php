<?php

namespace PieCrust\TemplateEngines;

use PieCrust\IPieCrust;


/**
 * The base interface for PieCrust template engines.
 *
 */
interface ITemplateEngine
{
    /**
     * Initializes the template engine.
     *
     * This function should do minimal processing (ideally just store a reference
     * to the given PieCrust app) because all engines are initialized regardless
     * of their being actually used. Instead, including library files and creating
     * the actual engine implementation should be done the first time renderFile()
     * or renderString() ar called.
     */
    public function initialize(IPieCrust $pieCrust);
    
    /**
     * Gets the file extension this engine supports.
     */
    public function getExtension();
    
    /**
     * Renders the given string, with the given data context, to the standard
     * output.
     */
    public function renderString($content, $data);
    
    /**
     * Renders the given template with the given data context to the standard
     * output. The template name is a file name that is expected to be found in
     * one of the template paths.
     *
     * Template engines should look in the default PieCrust application template
     * directory first, and then in any additional template paths specified by
     * calls to addTemplatePaths().
     */
    public function renderFile($templateName, $data);
    
    /**
     * Clears any in-memory cache the template engine may have.
     *
     * This is mostly used for the ChefServer hosted application, where PieCrust
     * is kept alive between requests.
     */
    public function clearInternalCache();
}
