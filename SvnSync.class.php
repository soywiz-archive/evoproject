<?php

require_once(__DIR__ . '/utils.php');

class SvnSync {
    private $_print;

    function __construct($print = null) {
        $this->_print = $print;
    }

    private function pprint($str) {
        if ($this->_print) call_user_func($this->_print, $str);
    }

    function process($path) {
        $this->pprint("SvnSync...\n");
        if (!$this->processOne($path)) {
            $this->pprint("No .svnref files in tree!\n");
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
                        $this->pprint("Updated {$svnrefFile}!\n");
                        svnref_write($svnrefFile, $svnDir);
                    } else {
                        $this->pprint("Not updated {$svnrefFile}!\n");
                    }
                } else {
                    $count += $this->processOne($rfile);
                }
            } else {
                if (isset($info['extension']) && $info['extension'] == 'svnref') {
                    $count++;
                    $svnrefFile = $rfile;
                    $svnDir = dirname($rfile) . '/' . pathinfo($rfile)['filename'];
                    if (!is_dir("{$svnDir}/.svn") || (filemtime("{$svnDir}/.svn") < filemtime($svnrefFile))) {
                        svnref_read($svnrefFile, $svnDir);
                    }
                }
            }
        }
        return $count;
    }
}
