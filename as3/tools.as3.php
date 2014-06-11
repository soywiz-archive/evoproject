<?php

class As3Tools
{
	private $airSdkLocalPath;

	function __construct($airSdkLocalPath)
	{
		$this->airSdkLocalPath = $airSdkLocalPath;
	}

	public function compc($output, $sourceList, $metadataList, $externalLibraries, $defines, $mergedLibraries, $includeLibraries) {
		$cmdPath = "{$this->airSdkLocalPath}/bin/compc";

		$arguments = [];
		foreach ($sourceList as $source) {
			$arguments[] = "-source-path+={$source}";
			$arguments[] = "-include-sources+={$source}";
			if (file_exists("{$source}/metadata.xml")) {
				$arguments[] = '-include-file';
				$arguments[] = 'metadata.xml';
				$arguments[] = "{$source}/metadata.xml";
			}
		}

		foreach ($metadataList as $metadata) $arguments[] = "-compiler.keep-as3-metadata+={$metadata}";
		//$arguments[] = "-compiler.keep-as3-metadata+=Inline";
		foreach ($externalLibraries as $library) if (file_exists($library)) $arguments[] = '-compiler.external-library-path+=' . $library;
		foreach ($mergedLibraries as $library) if (file_exists($library)) $arguments[] = '-compiler.library-path+=' . $library;
		foreach ($includeLibraries as $library) if (file_exists($library)) $arguments[] = '-compiler.include-libraries+=' . $library;
		foreach ($defines as $defineName => $defineValue) $arguments[] = '-define=' . $defineName . ',' . $defineValue;
		$arguments[] = '-debug=false';
		$arguments[] = '-compiler.optimize';
		//$arguments[] = '-compiler.inline';
		$arguments[] = '-output=' . $output;
		$arguments[] = '+configname=air';

		$result = 0;
		passthru($cmdPath . ' ' . implode(' ', array_map('escapeshellarg', $arguments)), $result);

		if ($result != 0) throw(new Exception("Error executing compc"));
	}

	public function mxmlc($output, $entryFile, $sourceList, $metadataList, $libraries, $defines) {
		$cmdPath = "{$this->airSdkLocalPath}/bin/mxmlc";

		$arguments = [];
		foreach ($sourceList as $source) {
			$arguments[] = "-source-path+={$source}";
		}
		foreach ($metadataList as $metadata) $arguments[] = "-compiler.keep-as3-metadata+={$metadata}";
		//$arguments[] = "-compiler.keep-as3-metadata+=Inline";
		foreach ($libraries as $library) if (file_exists($library)) $arguments[] = '-compiler.library-path+=' . $library;
		foreach ($defines as $defineName => $defineValue) $arguments[] = '-define=' . $defineName . ',' . $defineValue;
		$arguments[] = '-debug=false';
		$arguments[] = '-compiler.optimize';
		//$arguments[] = '-compiler.inline';
		$arguments[] = '-output=' . $output;
		$arguments[] = '+configname=air';
		$arguments[] = $entryFile;

		$result = 0;
		passthru($cmdPath . ' ' . implode(' ', array_map('escapeshellarg', $arguments)), $result);

		if ($result != 0) throw(new Exception("Error executing mxmlc"));
	}

}