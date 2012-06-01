<?php

use org\bovigo\vfs\vfsStream;
use Symfony\Component\Yaml\Yaml;


class MockFileSystem
{
    public static function create()
    {
        return new MockFileSystem();
    }

    protected $root;

    public function __construct()
    {
        vfsStream::setup('root', null, array(
            'kitchen' => array(
                '_content' => array(
                    'config.yml' => 'site:\n  title: Mock Website'
                )
            ),
            'counter' => array()
        ));
    }

    public function url($path)
    {
        return vfsStream::url('root/' . $path);
    }

    public function siteRootUrl()
    {
        return $this->url('kitchen');
    }

    public function withCacheDir()
    {
        mkdir(vfsStream::url('root/kitchen/_cache'));
        mkdir(vfsStream::url('root/kitchen/_cache/pages_r'));
        mkdir(vfsStream::url('root/kitchen/_cache/templates_c'));
        return $this;
    }

    public function withPagesDir()
    {
        mkdir(vfsStream::url('root/kitchen/_content/pages'));
        return $this;
    }

    public function withPostsDir()
    {
        mkdir(vfsStream::url('root/kitchen/_content/posts'));
        return $this;
    }

    public function withConfig(array $config)
    {
        $configPath = vfsStream::url('root/kitchen/_content/config.yml');
        file_put_contents($configPath, Yaml::dump($config));
        return $this;
    }

    public function withPage($url, $config = array(), $contents = 'A test page.')
    {
        $text  = '---' . PHP_EOL;
        $text .= Yaml::dump($config) . PHP_EOL;
        $text .= '---' . PHP_EOL;
        $text .= $contents;

        // Don't add an extension if there's one already.
        $fileName = $url . '.html';
        if (preg_match('/\\.[a-zA-Z0-9]+$/', $url))
            $fileName = $url;
        return $this->withAsset("_content/pages/{$fileName}", $text);
    }

    public function withPost($slug, $day, $month, $year, $config, $contents)
    {
        $text  = '---' . PHP_EOL;
        $text .= Yaml::dump($config) . PHP_EOL;
        $text .= '---' . PHP_EOL;
        $text .= $contents;

        if (is_int($day)) $day = sprintf('%02d', $day);
        if (is_int($month)) $month = sprintf('%02d', $month);
        if (is_int($year)) $year = sprintf('%04d', $year);
        $path = "_content/posts/{$year}-{$month}-{$day}_{$slug}.html";
        return $this->withAsset($path, $text);
    }

    public function withDummyPosts(array $dates, array $commonConfig = array())
    {
        $i = 0;
        foreach ($dates as $d)
        {
            $bits = explode('/', $d);
            $year = $bits[0];
            $month = $bits[1];
            $day = $bits[2];
            $slug = "test-slug-{$i}";
            $pageConfig = array_merge(
                array(
                    'title' => "Test Title {$i}"
                ),
                $commonConfig
            );
            $pageContents = "Contents {$i}";

            $this->withPost($slug, $day, $month, $year, $pageConfig, $pageContents);
            ++$i;
        }
        return $this;
    }

    public function withTemplate($name, $contents)
    {
        return $this->withAsset('_content/templates/' . $name . '.html', $contents);
    }

    public function withAsset($path, $contents)
    {
        // Ensure the path exists.
        $path = vfsStream::url('root/kitchen/' . $path);
        if (!is_dir(dirname($path)))
            mkdir(dirname($path), 0777, true);

        // Create the file.
        file_put_contents($path, $contents);
        return $this;
    }
}

