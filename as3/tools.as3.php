<?php

class As3Tools
{
	private $airSdkLocalPath;

	function __construct($airSdkLocalPath)
	{
		$this->airSdkLocalPath = $airSdkLocalPath;
	}

	public function compc($output, $sourceList, $metadataList, $externalLibraries) {
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

		if ($result != 0) throw(new Error("Error executing compc"));
	}

	public function mxmlc($output, $entryFile, $sourceList, $metadataList, $libraries) {
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

		if ($result != 0) throw(new Error("Error executing mxmlc"));
	}

}