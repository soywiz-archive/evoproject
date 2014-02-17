<?php

class EvoProject {
}

$projectInfo = json_decode(file_get_contents('evoproject.json'));

require_once(__DIR__ . '/evoproject.' . basename($projectInfo->language) . '.php');
$className = 'EvoProject_' . $projectInfo->language;

$evoProject = new $className($projectInfo);

$evoProject->update();