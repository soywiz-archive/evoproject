<?php

class EvoProject {
	public $evoFolder;

	public function __construct() {
		$this->evoFolder = getenv('USERPROFILE') . '/.evo';
		@mkdir($this->evoFolder, 0777, true);
	}

    protected function downloadFile($sourceUrl, $destinationPath) {
        if (!file_exists($destinationPath)) {
            echo "Downloading ${sourceUrl}...";
            file_put_contents($destinationPath, fopen($sourceUrl, 'rb'));
            echo "Ok\n";
        }
    }

    protected function extractZip($sourceZip, $destinationPath) {
        if (!is_dir($destinationPath)) {
            echo "Extracting {$sourceZip}...";
            $zip = new ZipArchive();
            $zip->open($sourceZip);
            $zip->extractTo($destinationPath);
            echo "Ok\n";
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

require_once(__DIR__ . '/evoproject.' . basename($projectInfo->language) . '.php');
$className = 'EvoProject_' . $projectInfo->language;

$evoProject = new $className($projectInfo);
$evoProject->update();