<?php

namespace Modules\LinkedConversations\Tests;

/**
 * Every __('…') literal in the module needs a German entry, either in the
 * module's de.json or in core's.
 */
class TranslationsTest extends TestCase
{
    public function test_every_module_string_has_a_german_translation()
    {
        $module = dirname(__DIR__);
        $module_de = json_decode(file_get_contents($module.'/Resources/lang/de.json'), true);
        $core_de = json_decode(file_get_contents(base_path('resources/lang/de.json')), true);
        $this->assertIsArray($module_de, 'Resources/lang/de.json is not valid JSON');

        $missing = [];
        foreach ($this->sourceFiles($module) as $file) {
            preg_match_all('/__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', file_get_contents($file), $matches);
            foreach ($matches[1] as $key) {
                $key = stripslashes($key);
                if (empty($module_de[$key]) && empty($core_de[$key])) {
                    $missing[] = $key.'  ('.basename($file).')';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    private function sourceFiles($module)
    {
        $files = [];
        foreach (['Entities', 'Http', 'Providers', 'Resources/views', 'Services'] as $dir) {
            if (!is_dir($module.'/'.$dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($module.'/'.$dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (substr($file->getFilename(), -4) === '.php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
