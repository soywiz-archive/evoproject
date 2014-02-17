<?php

// http://download.macromedia.com/pub/flashplayer/updaters/12/flashplayer_12_sa_debug.exe

class EvoProject_as3 extends EvoProject
{
	private $projectInfo;

	public function __construct($projectInfo) {
		$this->projectInfo = $projectInfo;
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