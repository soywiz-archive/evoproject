<?php

require_once(__DIR__ . '/utils.php');

class SvnSync {
    function process($path) {
        //echo "SvnSync...\n";
        if (!$this->processOne($path)) {
            //echo "No .svnref files in tree!\n";
        }
    }

    private function processOne($path) {
        $count = 0;
        foreach (scandir($path) as $file) {
            if ($file[0] == '.') continue;
            $rfile = "{$path}/{$file}";
            $info = pathinfo($rfile);
            if (is_dir($rfile)) {
                if (is_dir("{$rfile}/.svn")) {
                    $svnDir = $rfile;
                    $count++;
                    $svnrefFile = "{$rfile}.svnref";
                    if (!is_file($svnrefFile) || (filemtime("{$svnDir}/.svn") > filemtime($svnrefFile))) {
                        echo "Updated {$svnrefFile}!\n";
                        svnref_write($svnrefFile, $svnDir);
                    } else {
                        //echo "Not updated {$svnrefFile}!\n";
                    }
                } else {
                    $count += $this->processOne($rfile);
                }
            } else {
                if (isset($info['extension']) && $info['extension'] == 'svnref') {
                    $count++;
                    $svnrefFile = $rfile;
                    $svnDir = pathinfo($rfile)['filename'];
                    if (!is_dir("{$svnDir}/.svn") || (filemtime("{$svnDir}/.svn") < filemtime($svnrefFile))) {
                        svnref_read($svnrefFile, $svnDir);
                    }
                }
            }
        }
        return $count;
    }
}
