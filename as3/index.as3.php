<?php

require_once(__DIR__ . '/tools.as3.php');

// http://download.macromedia.com/pub/flashplayer/updaters/12/flashplayer_12_sa_debug.exe

class EvoProject_as3
{
	private $projectInfo;
    private $airSdkVersion = '4.0';
    private $flashVersion = '12';
	private $tools;
    private $utils;

	public function __construct(EvoProjectUtils $utils, $projectInfo) {
        $this->utils = $utils;
		$this->projectInfo = $projectInfo;
        $this->airSdkVersion = isset_default($this->projectInfo->engines->airsdk, '4.0');
        $this->flashVersion = isset_default($this->projectInfo->engines->flashplayer, '12');
        $this->flashPlayerLocalPath = "{$this->utils->evoFolder}/flashplayer_{$this->flashVersion}_sa.exe";
        $this->flashPlayerRemoteUrl = "http://download.macromedia.com/pub/flashplayer/updaters/{$this->flashVersion}/flashplayer_{$this->flashVersion}_sa.exe";
        $this->airSdkLocalPath = "{$this->utils->evoFolder}/airsdk-{$this->airSdkVersion}";
        $this->airSdkRemoteUrl = "http://airdownload.adobe.com/air/win/download/{$this->airSdkVersion}/AIRSDK_Compiler.zip";
		$this->tools = new As3Tools($this->airSdkLocalPath);
        $this->prepare();
	}

    private function prepare() {
        $this->downloadAirSdk();
        $this->downloadFlashPlayer();
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

	private function getProjectPath() {
		return $this->utils->projectFolder . '/evoproject.json';
	}

	private function getArtifactFileName() {
		return $this->projectInfo->name . '-' . $this->projectInfo->version . '.swc';
	}

    private function getArtifactPath() {
        return $this->utils->projectFolder . "/bin/" . $this->getArtifactFileName();
    }

	public function update() {
		$repository = $this->projectInfo->repository;

		foreach (['dependencies', 'testDependencies'] as $kind) {
			foreach ($this->projectInfo->{$kind} as $name => $version) {
				$artifactFileName = "{$name}-{$version}.swc";
				switch ($kind) {
					case 'dependencies':
						$localPath = $this->utils->projectFolder . '/lib/' . $artifactFileName;
						break;
					case 'testDependencies':
						$localPath = $this->utils->projectFolder . '/libtest/' . $artifactFileName;
						break;
					default:
						throw(new InvalidArgumentException());
				}
				$remotePath = $repository . '/' . $artifactFileName;
				if (!is_file($localPath)) {
					echo "Downloading {$remotePath}...";
					if (is_file($remotePath)) {
						@mkdir(dirname($localPath), 0777, true);
						file_put_contents($localPath, fopen($remotePath, 'rb'));
						echo "Ok\n";
					} else {
						echo "Not exists\n";
					}

				}
			}
		}
	}

    public function build() {
	    $this->update();
        $this->tools->compc(
            $output = $this->getArtifactPath(),
            $sourceList = isset_default($this->projectInfo->sources, ['src']),
            $metadataList = isset_default($this->projectInfo->metadata, []),
            $externalLibraries = [
                $this->utils->projectFolder . '/lib',
                $this->airSdkLocalPath . '/frameworks/libs/air/airglobal.swc'
            ]
        );
	    $this->utils->repackZip($this->getArtifactPath());
    }

	public function deploy() {
		$this->update();
		$this->test();
		$this->build();

		$repository = $this->projectInfo->repository;
		file_put_contents($repository . '/' . $this->getArtifactFileName(), fopen($this->getArtifactPath(), 'rb'));
		file_put_contents($repository . '/' . $this->getArtifactFileName() . '.project.json', fopen($this->getProjectPath(), 'rb'));
	}

    public function test()
    {
	    $this->update();
        $this->buildTest();
        $this->serverFlexUnit();
    }

    private function buildTest() {
        $testRunnerSource = file_get_contents(__DIR__ . '/TestRunner.as.template');

        @mkdir('out', 0777, true);
        @mkdir('out/report', 0777, true);

	    $baseTest = 'test';

	    $testNames = [];
	    $imports = [];
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
                $this->airSdkLocalPath . '/frameworks/libs/air/airglobal.swc'
            ]
        );
    }

    private function serverFlexUnit() {
        $pipes = [];
        $handle = proc_open(
            "{$this->flashPlayerLocalPath} out/tests.swf",
            [
                0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
                1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
                2 => array("pipe", "w") // stderr is a file to write to
            ],
            $pipes
        );

        echo "Waiting flash player...\n";
	    $testSuites = [];

	    $socket = stream_socket_server("tcp://127.0.0.1:1024", $errno, $errstr);
        while (true) {
            $conn = stream_socket_accept($socket, 2);
	        stream_set_timeout($conn, 5);
	        if (!$conn) throw(new Exception("Socket not connected"));
            echo "Connection! {$conn}";

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
	    if ($totalFailCount != 0) throw(new Exception("Failed {$totalFailCount}"));
    }
}
