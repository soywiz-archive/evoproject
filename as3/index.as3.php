<?php

// http://download.macromedia.com/pub/flashplayer/updaters/12/flashplayer_12_sa_debug.exe

class EvoProject_as3
{
	private $projectInfo;
    private $airSdkVersion = '4.0';
    private $flashVersion = '12';
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

    private function getArtifactPath() {
        return $this->utils->projectFolder . "/bin/" . $this->projectInfo->name . '-' . $this->projectInfo->version . '.swc';
    }

	public function update() {
		//print_r($this->projectInfo);
		foreach ($this->projectInfo->dependencies as $name => $version) {
			echo "$name -> $version\n";
		}
	}

	private function compc($output, $sourceList, $metadataList, $externalLibraries) {
        $cmdPath = "{$this->airSdkLocalPath}/bin/compc";

        $arguments = [];
        foreach ($sourceList as $source) {
            $arguments[] = "-include-sources+={$source}";
            if (file_exists("{$source}/metadata.xml")) {
                $arguments[] = '-include-file';
                $arguments[] = 'metadata.xml';
                $arguments[] = "{$source}/metadata.xml";
            }
        }

        foreach ($metadataList as $metadata) $arguments[] = "-compiler.keep-as3-metadata+={$metadata}";
        foreach ($externalLibraries as $library) $arguments[] = '-compiler.external-library-path+=' . $library;

        $arguments[] = '-compiler.optimize';
        $arguments[] = '-output=' . $output;
        $arguments[] = '+configname=air';

        $result = 0;
        passthru($cmdPath . ' ' . implode(' ', array_map('escapeshellarg', $arguments)), $result);

        return $result;
	}

    private function mxmlc($output, $entryFile, $sourceList, $metadataList, $libraries) {
        $cmdPath = "{$this->airSdkLocalPath}/bin/mxmlc";

        $arguments = [];
        foreach ($sourceList as $source) $arguments[] = "-source-path+={$source}";
        foreach ($metadataList as $metadata) $arguments[] = "-compiler.keep-as3-metadata+={$metadata}";
        foreach ($libraries as $library) $arguments[] = '-compiler.library-path+=' . $library;
        $arguments[] = '-compiler.optimize';
        $arguments[] = '-output=' . $output;
        $arguments[] = '+configname=air';
        $arguments[] = $entryFile;

        $result = 0;
        passthru($cmdPath . ' ' . implode(' ', array_map('escapeshellarg', $arguments)), $result);

        return $result;
    }

    public function build() {
        $this->compc(
            $output = $this->getArtifactPath(),
            $sourceList = isset_default($this->projectInfo->sources, ['src']),
            $metadataList = isset_default($this->projectInfo->metadata, []),
            $externalLibraries = [
                $this->utils->projectFolder . '/lib',
                $this->airSdkLocalPath . '/frameworks/libs/air/airglobal.swc'
            ]
        );
    }

    public function test()
    {
        $this->buildTest();
        $this->serverFlexUnit();
    }

    private function buildTest() {
        $testRunnerSource = file_get_contents(__DIR__ . '/TestRunner.as.template');

        file_put_contents('build/TestRunner.as', $testRunnerSource);

        $this->mxmlc(
            $output = 'build/tests.swf',
            $entryFile = 'build/TestRunner.as',
            $sourceList = isset_default($this->projectInfo->sources, ['src']),
            $metadataList = isset_default($this->projectInfo->metadata, []),
            $externalLibraries = [
                $this->utils->projectFolder . '/lib',
                $this->airSdkLocalPath . '/frameworks/libs/air/airglobal.swc'
            ]
        );
    }

    private function serverFlexUnit() {
        $pipes = [];
        $handle = proc_open(
            "{$this->flashPlayerLocalPath} build/tests.swf",
            [
                0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
                1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
                2 => array("pipe", "w") // stderr is a file to write to
            ],
            $pipes
        );
        /*
        echo '1';
        //sleep(5);
        echo '2';
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        sleep(2);

        $procInfo = proc_get_status($handle);
        print_r($procInfo);
        `taskkill /F /PID {$procInfo['pid']}`;
        proc_terminate($handle);
        */
        //`{$this->flashPlayerLocalPath} build/tests.swf`;

        echo "Waiting flash player...\n";
        $socket = stream_socket_server("tcp://127.0.0.1:1024", $errno, $errstr);
        $waitingPolicyFile = true;
        while (true) {
            $conn = stream_socket_accept($socket);
            echo "Connection! {$conn}";
            if ($waitingPolicyFile) {
                $waitingPolicyFile = false;
                $data = fread($conn, 1024);
                if ($data == "<policy-file-request/>\0") {
                    fwrite(
                        $conn,
                        '<?xml version="1.0"?>'
                        . '<cross-domain-policy xmlns="http://localhost" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.adobe.com/xml/schemas PolicyFileSocket.xsd">'
                        . '<allow-access-from domain="*" to-ports="*" />'
                        . '</cross-domain-policy>'
                    );
                    fclose($conn);
                    continue;
                }
            } else {
                fwrite($conn, "<startOfTestRunAck/>\0");

                //echo 'waiting data!';
                $data = '';
                while (true) {
                    $data = fread($conn, 10240);
                    //if (strpos())
                    if ($data == "<endOfTestRun/>\0") {
                        fwrite($conn, "<endOfTestRunAck/>\0");
                        break 2;
                    } else {
                        echo $data;
                    }
                }
            }

            //fwrite($conn, 'The local time is ' . date('n/j/Y g:i a') . "\n");
        }
        fclose($socket);
    }
}
