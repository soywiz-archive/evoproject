<?php

require_once(__DIR__ . '/utils.php');
require_once(__DIR__ . '/SvnSync.class.php');

date_default_timezone_set('Europe/Madrid');

class TestCase {
	/**
	 * @var TestSuite
	 */
	public $suite;

	/**
	 * @var string
	 */
	public $classname;

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var num
	 */
	public $time;

	public $status;

	public $message;

	public $stacktrace;

	function __construct($classname, $name, $time, $status)
	{
		$this->classname = $classname;
		$this->name = $name;
		$this->time = $time;
		$this->status = $status;
	}

	public function hasFailed() {
		return $this->status == 'failure';
	}

	public function toXml() {
		$result = '';
		$result .= '<testcase classname="' . htmlspecialchars($this->classname) . '" name="' . htmlspecialchars($this->name) . '" time="' . htmlspecialchars($this->time) . '"';
		if ($this->hasFailed()) {
			$result .= '>';
			$result .= '<failure message="' . htmlspecialchars($this->message) . '" type="' . htmlspecialchars($this->classname . '.' . $this->name) . '" ><![CDATA[' . $this->stacktrace . ']]></failure>';
			$result .= '</testcase>';
		} else {
			$result .= ' />';
		}
		return $result;
	}
}

class TestSuite {
	public $name;

	/**
	 * @var Array<TestCase>
	 */
	public $tests = [];

	function __construct($name)
	{
		$this->name = $name;
	}


	public function getTotalTime() {
		return array_sum(array_map(function(TestCase $test) { return $test->time; }, $this->tests));
	}

	public function getCount() {
		return count($this->tests);
	}

	public function getFailuresTests() {
		return array_filter($this->tests, function(TestCase $test) { return $test->hasFailed(); });
	}

	public function getFailureCount() {
		return count($this->getFailuresTests());
	}

	public function toXml() {
		$result = '';
		$result .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$result .= '<testsuite name="' . htmlspecialchars($this->name) . '" tests="' . htmlspecialchars($this->getCount()) . '" failures="' . htmlspecialchars($this->getFailureCount()) . '" errors="0" skipped="0" time="' . htmlspecialchars($this->getTotalTime()) . '" hostname="' . htmlspecialchars(getenv('COMPUTERNAME')) . '" timestamp="' . htmlspecialchars(date('c')) . '">' . "\n";
		foreach ($this->tests as $test) {
			$result .= "\t" . $test->toXml() . "\n";
		}
		$result .= '</testsuite>';
		return $result;
	}

	public function addTestCase(TestCase $testCase)
	{
		$testCase->suite = $this;
		$this->tests[] = $testCase;
	}
}

function freadStringz($f) {
	$string = '';
	while (!feof($f)) {
		$char = fgetc($f);
		if ($char === false || $char == "\0") break;
		$string .= $char;
	}
	if ($string == '') return false;
	return $string;
}

function fwriteStringz($f, $str) {
	fwrite($f, "{$str}\0");
}

$EVO_PROJECT_PATH = getenv('USERPROFILE') . '/.evo';

class EvoProjectUtils {
	public $evoFolder;
	public $projectFolder;

	public function __construct($evoProjectJsonPath) {
		global $EVO_PROJECT_PATH;
		$this->evoProjectJsonPath = $evoProjectJsonPath;
		$this->projectFolder = getcwd();
		$this->evoFolder = $EVO_PROJECT_PATH;
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

	public function repackZip($zipPath) {
		$files = [];
		$zip = new ZipArchive();
		$zip->open($zipPath);
		for ($n = 0; $n < $zip->numFiles; $n++) {
			$name = $zip->getNameIndex($n);
			$content = $zip->getFromIndex($n);
			$files[$name] = $content;
		}
		$zip->close();

		$zip = new ZipArchive();
		$zip->open($zipPath, ZipArchive::OVERWRITE);
		foreach ($files as $name => $content) {
			$zip->addFromString($name, $content);
		}
		$zip->close();
	}

	public function showClassTargets($className) {
		echo "Targets ($className):\n";
		$reflectionClass = new ReflectionClass($className);
		foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if (substr($method->name, 0, 2) == '__') continue;
			$method_name = $method->name;
			$method_summary = get_summary_from_phpdoc($method);
			echo " - {$method_name} - {$method_summary}\n";
		}
	}
}

function isset_default(&$var, $default) {
	return isset($var) ? $var : $default;
}

function empty_default(&$var, $default) {
	return !empty($var) ? $var : $default;
}

function rglob($pattern, $flags = 0) {
	$files = glob($pattern, $flags);
	foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
		$files = array_merge($files, rglob($dir.'/'.basename($pattern), $flags));
	}
	return $files;
}

function strstarts($string, $expectedStart) {
	return substr($string, 0, strlen($expectedStart)) == $expectedStart;
}

function get_credentials($url) {
	global $credentials;

	if (isset($credentials->servers)) {
		foreach ($credentials->servers as $serverUrl => $info) {
			if (strstarts($url, $serverUrl)) {
				return (object)['user' => $info->user, 'pass' => $info->password];
			}
		}
	}

	return NULL;
}

function cache_content($cacheId, $generatorCallback) {
	global $EVO_PROJECT_PATH;
	$localPath = $EVO_PROJECT_PATH . '/cache/' . urlencode($cacheId);
	if (!is_file($localPath)) {
		@mkdir(dirname($localPath), 0777, true);
		file_put_contents($localPath, serialize($generatorCallback()));
	}
	return unserialize(file_get_contents($localPath));
}

function evo_file_exists($url) {
	if (strstarts($url, 'http://')) {
		return cache_content("exists_{$url}", function() use ($url) {
			$headers = [];
			$headers[] = "Content-Type: application/octet-stream";
			$auth = get_credentials($url);
			if ($auth !== NULL) $headers[] = "Authorization: Basic ".base64_encode("{$auth->user}:{$auth->pass}");

			stream_context_set_default([
				"http" => [
					"method" => 'HEAD',
					'header'  => implode("\r\n", $headers) . "\r\n",
				]
			]);
			$result = get_headers($url);

			//echo "URL: $url\n";
			//print_r($result);

			return substr($result[0], 9, 3) == '200';
		});
	} else {
		return file_exists($url);
	}
}

function evo_file_put_contents($url, $content, $auth = NULL) {
	if (strstarts($url, 'http://')) {
		if (is_resource($content)) $content = stream_get_contents($content);
		$headers = [];
		$headers[] = "Content-Type: application/octet-stream";
		$auth = get_credentials($url);
		if ($auth !== NULL) $headers[] = "Authorization: Basic ".base64_encode("{$auth->user}:{$auth->pass}");
		file_get_contents($url, false, stream_context_create([
			"http" => [
				"method" => 'PUT',
				'content' => $content,
				'header'  => implode("\r\n", $headers) . "\r\n",
			]
		]));
	} else {
		file_put_contents($url, $content);
	}
}

function evo_file_get_contents($url, $auth = NULL) {
	if (strstarts($url, 'http://')) {
		return cache_content("get_{$url}", function() use ($url, $auth) {
			$headers = [];
			$headers[] = "Content-Type: application/octet-stream";
			$auth = get_credentials($url);
			if ($auth !== NULL) $headers[] = "Authorization: Basic ".base64_encode("{$auth->user}:{$auth->pass}");
			return file_get_contents($url, false, stream_context_create([
				"http" => [
					"method" => 'GET',
					'header'  => implode("\r\n", $headers) . "\r\n",
				]
			]));
		});
	} else {
		return file_get_contents($url);
	}
}

$options = getopt('f:');

$evoProjectJsonPath = empty_default($options['f'], 'evoproject.json');

$target = array_pop($argv);

if ($target == 'server') {
	echo "Listening at 0.0.0.0:9090\n";
	passthru(PHP_BINARY . ' -S 0.0.0.0:9090 -t . ' . __DIR__ . '/evoproject_reposerv.php 2>&1');
	exit;
}

if (!file_exists($evoProjectJsonPath)) {
	die("Can't find '{$evoProjectJsonPath}'");
}

$credentials = [];
$credentialsJsonFile = __DIR__ . '/credentials.json';
if (is_file($credentialsJsonFile)) $credentials = json_decode(file_get_contents($credentialsJsonFile));

$projectInfo = json_decode(file_get_contents($evoProjectJsonPath));

$language = basename($projectInfo->language);
require_once(__DIR__ . "/{$language}/index.{$language}.php");
$className = 'EvoProject_' . $projectInfo->language;

$utils = new EvoProjectUtils($evoProjectJsonPath);
$evoProject = new $className($utils, $projectInfo);

if (count($argv) < 1 || !method_exists($evoProject, $target)) {
	$utils->showClassTargets($className);
	exit;
} else {
	try {
		$evoProject->{$target}();
		exit(0);
	} catch (Exception $e) {
		echo $e->getMessage() . "\n";
		exit(-1);
	}
}
