<?php

/**
 * Interface to classes that import content from other CMS/engines.
 *
 */
interface IImporter
{
	public function importPages($pagesDir);
	public function importPosts($postsDir, $mode = 'flat');
	public function close();
}
