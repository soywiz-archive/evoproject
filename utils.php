<?php

function get_summary_from_phpdoc(ReflectionMethod $method) {
	return trim(implode("\n", array_map(function($line) { return trim($line, " \n\r\t/*"); }, explode('\n', $method->getDocComment()))), " \n\r\t/*");
}

function safe_getopt($_argv) {
    $argv = $_argv;
}

function glob_recursive($pattern, $flags = 0) {
    $files = glob($pattern, $flags);

    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
    }

    return $files;
}

function isset_default(&$var, $default) {
    return isset($var) ? $var : $default;
}

function empty_default(&$var, $default) {
    return !empty($var) ? $var : $default;
}

function rglob($pattern, $flags = 0) {
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, rglob($dir.'/'.basename($pattern), $flags));
    }
    return $files;
}

function stripAccents($stripAccents){
    return strtr($stripAccents,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
}

function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object == "." || $object == "..") continue;
            $rfile = "{$dir}/{$object}";
            if (filetype($rfile) == "dir") {
                rrmdir($rfile);
            } else {
                //echo "unlink($rfile);\n";
                unlink($rfile);
            }
        }
        reset($objects);
        //echo "rmdir($dir);\n";
        rmdir($dir);
    }
}

function setTemporarily(&$var, $newValue, $callback) {
    $oldValue = $var;
    $var = $newValue;
    //try {
        $callback();
    //} finally {
        $var = $oldValue;
    //}
}

function chdirTemporarily($path, $callback) {
    $oldPath = getcwd();
    chdir($path);
    //try {
        $result = $callback();
    //} finally {
        chdir($oldPath);
    //}
    return $result;
}

function svn_info($path) {
    $info = [];
    $output = shell_exec(sprintf('svn info %s', escapeshellarg($path)));
    foreach (explode("\n", $output) as $line) {
        @list($key, $value) = explode(':', $line, 2);
        $key = trim(strtolower($key));
        $value = trim($value);
        if (!empty($key)) $info[$key] = $value;
    }
    return (object)$info;
}

function svnref_read($svnrefFile, $svnDir) {
    $repoFullUrl = file_get_contents($svnrefFile);
    list($repoUrl, $repoVersion) = explode('@', $repoFullUrl, 2);

    if (!is_dir($svnDir)) {
        passthru(sprintf('svn checkout %s %s', escapeshellarg("{$repoUrl}@{$repoVersion}"), escapeshellarg($svnDir)));
    } else {
        passthru(sprintf('svn relocate %s %s', escapeshellarg($repoUrl), escapeshellarg($svnDir)));
        passthru(sprintf('svn update -r %s %s', escapeshellarg($repoVersion), escapeshellarg($svnDir)));
    }

    touch("{$svnDir}/.svn", filemtime($svnrefFile));
}

function svnref_write($svnrefFile, $svnDir) {
    $info = svn_info($svnDir);
    file_put_contents($svnrefFile, "{$info->url}@{$info->revision}");
    touch($svnrefFile, filemtime("{$svnDir}/.svn"));
}

function git_describe($path) {
    return chdirTemporarily($path, function() {
        return trim(`git describe --tags`);
    });
}

