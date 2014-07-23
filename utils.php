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

function stripAccents($str){
    return strtr(utf8_decode($str), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
}

function convertIntoValidId($str) {
    return preg_replace("@\\s+@", '', stripAccents($str));
}

function convertIntoValidClassName($str) {
    return convertIntoValidId($str);
}

function convertIntoValidInstanceName($str) {
    return convertIntoValidId($str);
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

function evo_path() {
    //return getenv('USERPROFILE') . '/.evo';
    $evoPath = __DIR__ . '/.evo';
    if (!is_dir($evoPath)) mkdir($evoPath, 0777, true);
    return $evoPath;
}

function svn_path() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return realpath(__DIR__ . '/bin/windows/svn.exe');
    } else {
        return 'svn';
    }
}

function svn_get_user_pass() {
    $svn_access = evo_path() . '/.svn_access';
    if (is_file($svn_access)) {
        list($svnUser, $svnPassword) = explode(':', trim(file_get_contents($svn_access)));
        return (object)[ 'user' => $svnUser, 'password' => $svnPassword, ];
    } else {
        return null;
        //return (object)[ 'user' => 'anonymous', 'password' => '-password-', ];
    }
}

function svn_command($command, array $args) {
    $chunks = [];
    $chunks[] = svn_path();

    $svnAuth = svn_get_user_pass();
    if ($svnAuth) {
        $chunks[] = '--non-interactive';
        $chunks[] = '--username';
        $chunks[] = $svnAuth->user;
        $chunks[] = '--password';
        $chunks[] = $svnAuth->password;
    }
    $chunks[] = $command;
    $chunks[] = implode(" ", array_map('escapeshellarg', $args));
    return implode(' ', $chunks);
}

function svn($command, $args) {
    return shell_exec(svn_command($command, $args));
}

function svn_info($path) {
    $info = [];
    $output = svn('info', [$path]);
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

    //echo "$svnrefFile, $svnDir, $repoUrl, $repoVersion\n";

    if (!is_dir($svnDir)) {
        passthru(svn_command('checkout', ["{$repoUrl}@{$repoVersion}", $svnDir]));
    } else {
        passthru(svn_command('relocate', [$repoUrl, $svnDir]));
        passthru(svn_command('update', ['-r', $repoVersion, $svnDir]));
    }

    touch("{$svnDir}/.svn", filemtime($svnrefFile));
}

function svnref_write($svnrefFile, $svnDir) {
    //echo "$svnrefFile, $svnDir\n";
    $info = svn_info($svnDir);
    file_put_contents($svnrefFile, "{$info->url}@{$info->revision}");
    touch($svnrefFile, filemtime("{$svnDir}/.svn"));
}

function git_describe($path) {
    return chdirTemporarily($path, function() {
        return trim(`git describe --tags`);
    });
}

function str_removefromstart($string, $start) {
    if (substr($string, 0, strlen($start)) == $start) {
        return substr($string, strlen($start));
    } else {
        return $string;
    }
}

function copyCreatePath($from, $to) {
    $toName = dirname($to);
    if (!is_dir($toName)) mkdir($toName, 0777, true);
    copy($from, $to);
}