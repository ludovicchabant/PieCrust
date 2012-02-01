<?php

require_once 'sfYaml/lib/sfYaml.php';

require_once 'vfsStream/vfsStream.php';
require_once 'vfsStream/visitor/vfsStreamStructureVisitor.php';

class MockFileSystem
{
    public static function create()
    {
        return new MockFileSystem();
    }

    protected $root;

    public function __construct()
    {
        vfsStream::create(array(
            'kitchen' => array(
                '_content' => array(
                    'config.yml' => 'site:\n  title: Mock Website'
                )
            ),
            'counter' => array()
        ));
    }

    public function withConfig(array $config)
    {
        $configPath = vfsStream::url('root/kitchen/_content/config.yml');
        file_put_contents($configPath, sfYaml::dump($config));
        return $this;
    }

    public function withPage($url, $config, $contents)
    {
        $text  = '---' . PHP_EOL;
        $text .= sfYaml::dump($config) . PHP_EOL;
        $text .= '---' . PHP_EOL;
        $text .= $contents;

        return $this->withAsset("_content/pages/{$url}.html", $text);
    }

    public function withPost($slug, $day, $month, $year, $config, $contents)
    {
        $text  = '---' . PHP_EOL;
        $text .= sfYaml::dump($config) . PHP_EOL;
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

