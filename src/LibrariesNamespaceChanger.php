<?php

namespace srag\LibrariesNamespaceChanger;

use Closure;
use Composer\Config;
use Composer\Script\Event;

/**
 * Class LibrariesNamespaceChanger
 *
 * @package srag\LibrariesNamespaceChanger
 *
 * @author  studer + raimann ag - Team Custom 1 <support-custom1@studer-raimann.ch>
 *
 * @internal
 */
final class LibrariesNamespaceChanger
{

    /**
     * @var string
     *
     * @internal
     */
    const PLUGIN_NAME_REG_EXP = "/\/([A-Za-z0-9_]+)\/vendor\//";
    /**
     * @var array
     */
    private static $exts
        = [
            "json",
            "md",
            "php",
            "xml"
        ];
    /**
     * @var self|null
     */
    private static $instance = null;
    /**
     * @var string
     */
    private static $plugin_root = "";
    /**
     * @var Event
     */
    private $event;


    /**
     * LibrariesNamespaceChanger constructor
     *
     * @param Event $event
     */
    private function __construct(Event $event)
    {
        $this->event = $event;
    }


    /**
     * @param Event $event
     *
     * @internal
     */
    public static function rewriteLibrariesNamespaces(Event $event)/*: void*/
    {
        self::$plugin_root = rtrim(Closure::bind(function () : string {
            return $this->baseDir;
        }, $event->getComposer()->getConfig(), Config::class)(), "/");

        self::getInstance($event)->doRewriteLibrariesNamespaces();
    }


    /**
     * @param Event $event
     *
     * @return self
     */
    private static function getInstance(Event $event) : self
    {
        if (self::$instance === null) {
            self::$instance = new self($event);
        }

        return self::$instance;
    }


    /**
     *
     */
    private function doRewriteLibrariesNamespaces()/*: void*/
    {
        $plugin_name = $this->getPluginName();

        if (empty($plugin_name)) {
            return;
        }

        $libraries = [];
        foreach (
            array_filter(scandir(self::$plugin_root . "/vendor/srag"), function (string $folder) : bool {
                return (!in_array($folder, [".", "..", "librariesnamespacechanger"]));
            }) as $folder
        ) {
            $folder = self::$plugin_root . "/vendor/srag/" . $folder;

            $composer_json = json_decode(file_get_contents($folder . "/composer.json"), true);

            $namespaces = array_keys($composer_json["autoload"]["psr-4"]);

            if (empty($namespaces)) {
                continue;
            }

            $namespaces = array_map(function (string $namespace) use ($plugin_name) : string {
                if (substr($namespace, -1) === "\\") {
                    $namespace = substr($namespace, 0, -1);
                }

                if (substr($namespace, -strlen("\\" . $plugin_name)) === ("\\" . $plugin_name)) {
                    $namespace = substr($namespace, 0, -strlen("\\" . $plugin_name));
                }

                return $namespace;
            }, $namespaces);

            $libraries[$folder] = $namespaces;
        }

        $files = [];
        foreach (array_keys($libraries) as $folder) {
            $this->getFiles($folder, $files);
        }
        $this->getFiles(self::$plugin_root . "/vendor/composer", $files);

        foreach ($libraries as $folder => $namespaces) {

            foreach ($namespaces as $namespace) {

                foreach ($files as $file) {
                    $code = file_get_contents($file);

                    $replaces = [
                        $namespace                            => $namespace . "\\" . $plugin_name,
                        str_replace("\\", "\\\\", $namespace) => str_replace("\\", "\\\\", $namespace) . "\\\\" . $plugin_name
                    ];

                    foreach ($replaces as $search => $replace) {
                        if (strpos($code, $replace) === false) {
                            $code = str_replace($search, $replace, $code);
                        }
                    }

                    file_put_contents($file, $code);
                }
            }
        }
    }


    /**
     * @param string $folder
     * @param array  $files
     */
    private function getFiles(string $folder, array &$files = [])/*: void*/
    {
        $paths = scandir($folder);

        foreach ($paths as $file) {
            if ($file !== "." && $file !== "..") {
                $path = $folder . "/" . $file;

                if (is_dir($path)) {
                    $this->getFiles($path, $files);
                } else {
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if (in_array($ext, self::$exts)) {
                        array_push($files, $path);
                    }
                }
            }
        }
    }


    /**
     * @return string
     */
    private function getPluginName() : string
    {
        $matches = [];
        preg_match(self::PLUGIN_NAME_REG_EXP, __DIR__, $matches);

        if (is_array($matches) && count($matches) >= 2) {
            $plugin_name = $matches[1];

            return $plugin_name;
        } else {
            return "";
        }
    }
}
