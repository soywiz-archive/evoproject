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
        $compcPath = "{$this->airSdkLocalPath}/bin/compc.bat";

        $arguments = [];
        foreach ($sourceList as $source) {
            $arguments[] = "-include-sources={$source}";
            if (file_exists("{$source}/metadata.xml")) {
                $arguments[] = '-include-file';
                $arguments[] = 'metadata.xml';
                $arguments[] = "{$source}/metadata.xml";
            }
        }

        foreach ($metadataList as $metadata) {
            $arguments[] = "-compiler.keep-as3-metadata={$metadata}";
        }

        foreach ($externalLibraries as $library)
        {
            $arguments[] = '-compiler.external-library-path=' . $library;
        }

        $arguments[] = '-compiler.optimize';
        $arguments[] = '-output=' . $output;
        $arguments[] = '+configname=air';

        $result = 0;
        passthru($compcPath . ' ' . implode(' ', array_map('escapeshellarg', $arguments)), $result);

        return $result;
	}

    private function mxmlc($output, $sourceList, $metadataList, $externalLibraries) {
        $compcPath = "{$this->airSdkLocalPath}/bin/compc.bat";

        $arguments = [];
        foreach ($sourceList as $source) {
            $arguments[] = "-include-sources+={$source}";
        }

        foreach ($metadataList as $metadata) {
            $arguments[] = "-compiler.keep-as3-metadata+={$metadata}";
        }

        foreach ($externalLibraries as $library)
        {
            $arguments[] = '-compiler.external-library-path+=' . $library;
        }
        $arguments[] = '-compiler.optimize';
        $arguments[] = '-output=' . $output;
        $arguments[] = '+configname=air';

        $result = 0;
        passthru($compcPath . ' ' . implode(' ', array_map('escapeshellarg', $arguments)), $result);

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
        $testRunnerSource = file_get_contents(__DIR__ . '/TestRunner.as.template');

        file_put_contents('build/TestRunner.as', $testRunnerSource);
        //$this->serverFlexUnit();
    }

    private function serverFlexUnit() {
        echo "Waiting flash player...\n";
        $socket = stream_socket_server("tcp://127.0.0.1:1024", $errno, $errstr);
        $conn = stream_socket_accept($socket);
        $data = fread($conn, 1024);
        echo $data;
        //fwrite($conn, 'The local time is ' . date('n/j/Y g:i a') . "\n");
        fclose($conn);
        fclose($socket);
    }
}
