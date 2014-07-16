<?php

require_once(__DIR__ . '/tools.as3.php');
require_once(__DIR__ . '/../utils.php');
require_once(__DIR__ . '/../SvnSync.class.php');

// http://download.macromedia.com/pub/flashplayer/updaters/12/flashplayer_12_sa_debug.exe

class EvoProject_as3
{
	private $projectInfo;
	private $airSdkVersion;
	private $flashVersion;
	private $tools;
	private $utils;

	public function __construct(EvoProjectUtils $utils, $projectInfo) {
		$this->utils = $utils;
		$this->projectInfo = $projectInfo;
		$this->airSdkVersion = isset_default($this->projectInfo->engines->airsdk, '14.0');
		$this->flashVersion = isset_default($this->projectInfo->engines->flashplayer, '14');
		$this->flashPlayerLocalPath = "{$this->utils->evoFolder}/flashplayer_{$this->flashVersion}_sa.exe";
		$this->flashPlayerRemoteUrl = "http://download.macromedia.com/pub/flashplayer/updaters/{$this->flashVersion}/flashplayer_{$this->flashVersion}_sa.exe";
		$this->airSdkLocalPath = "{$this->utils->evoFolder}/airsdk-{$this->airSdkVersion}";
		$this->airSdkRemoteUrl = "http://airdownload.adobe.com/air/win/download/{$this->airSdkVersion}/AIRSDK_Compiler.zip";
		$this->tools = new As3Tools($this->airSdkLocalPath);
		$this->prepare();
	}

	private function svnSync() {
		(new SvnSync())->process($this->utils->projectFolder);
	}

	private function prepare() {
		$this->downloadAirSdk();
		$this->downloadFlashPlayer();
		$this->svnSync();
	}

	private function downloadAirSdk() {
		//file_put_contents
		$remoteZip = $this->airSdkRemoteUrl;
		$localFolder = $this->airSdkLocalPath;
		$localZip = "{$localFolder}.zip";

		$this->utils->downloadFile($remoteZip, $localZip);
		$this->utils->extractZip($localZip, $localFolder);
	}

	private function downloadFlashPlayer() {
		$localFile = $this->flashPlayerLocalPath;
		$remoteFile = $this->flashPlayerRemoteUrl;
		$this->utils->downloadFile($remoteFile, $localFile);
	}

	private function getEvoProjectJsonPath() {
		return $this->utils->evoProjectJsonPath;
	}

	private function getArtifactExtension() {
		return (!empty($this->projectInfo->main)) ? 'swf' : 'swc';
	}

    private function getProjectVersion() {
        $version = $this->projectInfo->version;
        if ($version == '*git*') {
            $version = git_describe($this->utils->projectFolder);
            $version = preg_replace('@^v@', '', $version);
        }
        return $version;
    }

	private function getArtifactFileName() {
		return $this->projectInfo->name . '-' . $this->getProjectVersion() . '.' . $this->getArtifactExtension();
	}

	private function getArtifactPath() {
		return $this->utils->projectFolder . "/bin/" . $this->getArtifactFileName();
	}

	private function dependencyDictionaryToArray($dependencyList) {
		$dependencies = [];
		foreach ($dependencyList as $name => $version) $dependencies[] = [$name, $version];
		return $dependencies;
	}

	private function resolveDependencies($dependencyList, $outFolder, $recursive) {
		$repository = $this->projectInfo->repository;

		$dependencies = $this->dependencyDictionaryToArray($dependencyList);
		$processedDependencies = [];

		while (count($dependencies) > 0) {
			list($name, $version) = array_shift($dependencies);

			if (isset($processedDependencies[$name])) continue;
			$processedDependencies[$name] = true;

			$artifactFileName = "{$name}-{$version}.swc";
			$remotePath = $repository . '/' . $artifactFileName;
			$localPath = $outFolder . '/' . $artifactFileName;
			if (!is_file($localPath)) {
				echo "Downloading {$remotePath}...";
				if (evo_file_exists($remotePath)) {
					@mkdir(dirname($localPath), 0777, true);
					file_put_contents($localPath, evo_file_get_contents($remotePath));

					$remoteProjectJson = $remotePath . '.project.json';
					if (evo_file_exists($remoteProjectJson)) {
						foreach ($this->dependencyDictionaryToArray(json_decode(evo_file_get_contents($remoteProjectJson))->dependencies) as $item) {
							$dependencies[] = $item;
						}
					}

					//$dependencies[]

					echo "Ok\n";
				} else {
					echo "Not exists\n";
				}
			}
		}
	}

	public function update() {
		if (isset($this->projectInfo->dependencies)) {
			$this->resolveDependencies($this->projectInfo->dependencies, $this->utils->projectFolder . '/lib', $recursive = true);
		}
		if (isset($this->projectInfo->mergedDependencies)) {
			$this->resolveDependencies($this->projectInfo->mergedDependencies, $this->utils->projectFolder . '/libmerged', $recursive = false);
		}
		if (isset($this->projectInfo->includeDependencies)) {
			$this->resolveDependencies($this->projectInfo->includeDependencies, $this->utils->projectFolder . '/libinclude', $recursive = false);
		}
		if (isset($this->projectInfo->testDependencies)) {
			$this->resolveDependencies($this->projectInfo->testDependencies, $this->utils->projectFolder . '/libtest', $recursive = false);
		}
	}

	public function build() {
	    $this->update();

		$output = $this->getArtifactPath();
		$sourceList = isset_default($this->projectInfo->sources, ['src']);
		$metadataList = isset_default($this->projectInfo->metadata, []);
		$externalLibraries = [
			$this->utils->projectFolder . '/lib',
			$this->utils->projectFolder . '/libane',
			$this->airSdkLocalPath . '/frameworks/libs/air/airglobal.swc',
		];
		$mergedLibraries = [
			$this->utils->projectFolder . '/libmerged',
		];
		$includeLibraries = [
			$this->utils->projectFolder . '/libinclude',
		];
		$defines = isset_default($this->projectInfo->defines, []);

		if (!empty($this->projectInfo->main)) {
			$this->tools->mxmlc($output, $this->projectInfo->main, $sourceList, $metadataList, $externalLibraries, $defines);
		} else {
			$this->tools->compc($output, $sourceList, $metadataList, $externalLibraries, $defines, $mergedLibraries, $includeLibraries);
			$this->utils->repackZip($this->getArtifactPath());
		}
	}

	public function deploy() {
		$this->update();
		//$this->test();
		$this->build();

		$repository = $this->projectInfo->repository;
		evo_file_put_contents($repository . '/' . $this->getArtifactFileName(), fopen($this->getArtifactPath(), 'rb'));
		evo_file_put_contents($repository . '/' . $this->getArtifactFileName() . '.project.json', fopen($this->getEvoProjectJsonPath(), 'rb'));
	}

	public function run()
	{
		$this->update();
		$this->build();
		$this->executeSwfAndUpdateFlashTrust($this->getArtifactPath());
	}

	public function test()
	{
	    $this->update();
		$this->buildTest();
		$this->serverFlexUnit();
	}

	private function executeSwfAndUpdateFlashTrust($swf) {
		$this->updateFlashTrust(dirname($swf));
		return $this->executeSwf($swf);
	}

	private function executeSwf($swf) {
		$pipes = [];
		return proc_open(
			"{$this->flashPlayerLocalPath} " . escapeshellarg($swf),
			[
				0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
				1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
				2 => array("pipe", "w") // stderr is a file to write to
			],
			$pipes
		);
	}

	private function buildTest() {
		$testRunnerSource = file_get_contents(__DIR__ . '/TestRunner.as.template');

		@mkdir('out', 0777, true);
		@mkdir('out/report', 0777, true);

	    $testNames = [];
	    $imports = [];
		foreach ($this->projectInfo->tests as $baseTest) {
		    foreach (rglob($baseTest . '/*.as') as $file) {
			    $fileContent = file_get_contents($file);

			    if (strpos($fileContent, '[Test') < 0) continue;

			    if (!preg_match("/package\\s+([\\w\\.]*)/msi", $fileContent, $matches)) continue;

			    $packageName = trim($matches[1]);
			    //echo "$packageName\n";
			    if (!preg_match("/public\\s+class\\s+(\\w+)/msi", $fileContent, $matches)) continue;

			    $className = trim($matches[1]);

			    $qualifiedName = ltrim("{$packageName}.{$className}", '.');
			    //echo "$qualifiedName\n";

			    $testNames[] = $qualifiedName;
			    $imports[] = "import {$qualifiedName};";
		    }
		}

	    if (count($testNames) == 0) throw(new Exception("No tests to run"));

	    $testRunnerSource = str_replace('/*@IMPORTS@*/', implode("\n", $imports), $testRunnerSource);
	    $testRunnerSource = str_replace('/*@CLASSES@*/', implode(",\n", $testNames), $testRunnerSource);

		file_put_contents('out/TestRunner.as', $testRunnerSource);

	    $sourceList = isset_default($this->projectInfo->sources, ['src']);
	    $testList = isset_default($this->projectInfo->tests, ['test']);

		$this->tools->mxmlc(
			$output = 'out/tests.swf',
			$entryFile = 'out/TestRunner.as',
			$sourceList = array_merge($sourceList, $testList),
			$metadataList = isset_default($this->projectInfo->metadata, []),
			$externalLibraries = [
				$this->utils->projectFolder . '/lib',
	            $this->utils->projectFolder . '/libtest',
				$this->utils->projectFolder . '/libane',
				$this->airSdkLocalPath . '/frameworks/libs/air/airglobal.swc'
			],
			isset_default($this->projectInfo->defines, [])
		);
	}

	private function updateFlashTrust($folder) {
		// "/etc/adobe/FlashPlayerTrust/"
		// "/Library/Application Support/Macromedia/FlashPlayerTrust/"
		// System.getenv("SYSTEMROOT") + "\\system32\\Macromed\\Flash\\FlashPlayerTrust\\"

		// home + "/.macromedia/Flash_Player/#Security/FlashPlayerTrust/"
		// home + "/Library/Preferences/Macromedia/Flash Player/#Security/FlashPlayerTrust/"

		$trustFolder = getenv("APPDATA") . "/Macromedia/Flash Player/#Security/FlashPlayerTrust";
		$trustFile = $trustFolder . "/evoproject.cfg";
		$items = [];
		if (is_file($trustFile)) $items = array_map('trim', file($trustFile));
		$items[] = realpath($folder);
		$items = array_unique($items);
		file_put_contents($trustFile, implode("\n", $items));
	}

	private function serverFlexUnit() {
		$swfHandle = $this->executeSwfAndUpdateFlashTrust('out/tests.swf');

		echo "Waiting flash player...\n";
	    $testSuites = [];

	    $socket = stream_socket_server("tcp://127.0.0.1:1024", $errno, $errstr);
		while (true) {
			$conn = stream_socket_accept($socket, 2);
	        if (!$conn) throw(new Exception("Socket not connected"));

			stream_set_timeout($conn, 0, 10000);
			//echo "Connection! {$conn}";

			while (!feof($conn)) {
				$data = freadStringz($conn);

				//echo "$data\n";

				if ($data === false) {
		            fwriteStringz($conn, "<startOfTestRunAck/>");
		            continue;
	            }

				//if (strpos())
				if ($data == "<policy-file-request/>") {
	                fwrite($conn,
		                '<?xml version="1.0"?>'
		                . '<cross-domain-policy xmlns="http://localhost" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.adobe.com/xml/schemas PolicyFileSocket.xsd">'
		                . '<allow-access-from domain="*" to-ports="*" />'
		                . '</cross-domain-policy>'
	                );
	                fclose($conn);
	                break;
				} else if ($data == "<endOfTestRun/>") {
					fwriteStringz($conn, "<endOfTestRunAck/>");
					break 2;
				} else {
					//echo "{$data}\n\n";
					$xml = new SimpleXMLElement($data);
					$classname = (string)$xml->attributes()->classname;
					$time = ((float)(string)$xml->attributes()->time) / 1000;
					$name = (string)$xml->attributes()->name;
					$status = (string)$xml->attributes()->status;

					$testSuiteName = str_replace(':', '.', str_replace('::', '.', $classname));

					if (!isset($testSuites[$classname])) $testSuites[$classname] = new TestSuite($testSuiteName);

	                /*
	                 * @var TestSuite
	                 */
	                $testSuite = $testSuites[$classname];
	                $testCase = new TestCase($classname, $name, $time, $status);
	                if ($testCase->hasFailed()) {
		                $testCase->message = (string)$xml->failure[0]->attributes()->message;
		                $testCase->stacktrace = (string)(string)$xml->failure[0];
	                }
					$testSuite->addTestCase($testCase);

					file_put_contents("out/report/TEST-{$testSuiteName}.xml", $testSuite->toXml());

	                echo $testCase->toXml() . "\n";
				}
			}
		}
		fclose($socket);

	    $totalFailCount = 0;
	    foreach ($testSuites as $testSuite) {
		    /* @var TestSuite */
		    $totalFailCount += $testSuite->getFailureCount();
	    }
	    if ($totalFailCount != 0) throw(new Exception("Some tests failed failedCount:{$totalFailCount}"));
	}
}
