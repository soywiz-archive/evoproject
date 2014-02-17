<?php

class EvoProjectUtils {
	public $evoFolder;
    public $projectFolder;

	public function __construct() {
        $this->projectFolder = getcwd();
		$this->evoFolder = getenv('USERPROFILE') . '/.evo';
		@mkdir($this->evoFolder, 0777, true);
	}

    public function downloadFile($sourceUrl, $destinationPath) {
        if (!file_exists($destinationPath)) {
            echo "Downloading ${sourceUrl}...";
            file_put_contents($destinationPath, fopen($sourceUrl, 'rb'));
            echo "Ok\n";
        }
    }

    public function extractZip($sourceZip, $destinationPath) {
        if (!is_dir($destinationPath)) {
            echo "Extracting {$sourceZip}...";
            $zip = new ZipArchive();
            $zip->open($sourceZip);
            $zip->extractTo($destinationPath);
            echo "Ok\n";
        }
    }

    public function showClassTargets($className) {
        echo "Targets:\n";
        $reflectionClass = new ReflectionClass($className);
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (substr($method->name, 0, 2) == '__') continue;
            echo ' - ' . $method->name . "\n";
        }
    }
}

function isset_default(&$var, $default) {
    return isset($var) ? $var : $default;
}

$exoProjectJsonPath = 'evoproject.json';

if (!file_exists($exoProjectJsonPath)) {
	die("Can't find '{$exoProjectJsonPath}'");
}

$projectInfo = json_decode(file_get_contents($exoProjectJsonPath));

$language = basename($projectInfo->language);
require_once(__DIR__ . "/{$language}/index.{$language}.php");
$className = 'EvoProject_' . $projectInfo->language;

$utils = new EvoProjectUtils();
$evoProject = new $className($utils, $projectInfo);
if (!isset($argv[1])) {
    $utils->showClassTargets($className);
    exit;
} else {
    exit($evoProject->{$argv[1]}());
}
