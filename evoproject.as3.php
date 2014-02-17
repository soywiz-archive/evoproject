<?php

// http://download.macromedia.com/pub/flashplayer/updaters/12/flashplayer_12_sa_debug.exe

class EvoProject_as3 extends EvoProject
{
	private $projectInfo;
    private $airSdkVersion = '4.0';
    private $flashVersion = '12';

	public function __construct($projectInfo) {
		parent::__construct();
		$this->projectInfo = $projectInfo;
        $this->airSdkVersion = isset_default($this->projectInfo->engines->airsdk, '4.0');
        $this->flashVersion = isset_default($this->projectInfo->engines->flashplayer, '12');
        $this->flashPlayerLocalPath = "{$this->evoFolder}/flashplayer_{$this->flashVersion}_sa.exe";
        $this->flashPlayerRemoteUrl = "http://download.macromedia.com/pub/flashplayer/updaters/{$this->flashVersion}/flashplayer_{$this->flashVersion}_sa.exe";
        $this->airSdkLocalPath = "{$this->evoFolder}/airsdk-{$this->airSdkVersion}";
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

        $this->downloadFile($remoteZip, $localZip);
        $this->extractZip($localZip, $localFolder);
	}

    private function downloadFlashPlayer() {
        $localFile = $this->flashPlayerLocalPath;
        $remoteFile = $this->flashPlayerRemoteUrl;
        $this->downloadFile($remoteFile, $localFile);
    }

	public function update() {
		//print_r($this->projectInfo);
		foreach ($this->projectInfo->dependencies as $name => $version) {
			echo "$name -> $version\n";
		}
	}

	public function build() {
	}
}