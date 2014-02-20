<?php

function requestCredentials() {
	header('WWW-Authenticate: Basic realm="evorepository"');
	header('HTTP/1.0 401 Unauthorized');
	echo 'Text to send if user hits Cancel button';
	exit;
}

if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) requestCredentials();

$authUser = $_SERVER['PHP_AUTH_USER'];
$authPassword = $_SERVER['PHP_AUTH_PW'];

$credentialsServerJsonFile = __DIR__ . '/credentials_server.json';
if (!is_file($credentialsServerJsonFile)) die("Must create a 'credentials_server.json' file based on 'credentials_server.json.sample'");
$credentials = json_decode(file_get_contents($credentialsServerJsonFile));
$credentialsUsers = $credentials->users;

if (!isset($credentialsUsers->{$authUser}) || $credentialsUsers->{$authUser}->password != $authPassword) requestCredentials();

$credentialsCurrentUser = $credentialsUsers->{$authUser};

$method = @$_SERVER['REQUEST_METHOD'];
$file = basename($_SERVER['REQUEST_URI']);

if (in_array($file, ['.gitignore']) || substr($file, -4, 4) == '.php') die('Invalid file');

$localPath = __DIR__ . '/repo/'. $file;

if (empty($file)) {
	die("Must specify a file");
}

switch ($method) {
	case 'GET':
		if (!is_file($localPath)) {
			header("HTTP/1.0 404 Not Found");
			echo "File not found '" . htmlspecialchars($file) . "'!";
			exit;
		} else {
			header('Content-Type: application/octet-stream');
			readfile($localPath);
			exit;
		}
		break;
	case 'PUT':
		if (!$credentialsCurrentUser->canwrite) die("Current user can't write");
		file_put_contents($localPath, fopen("php://input", "r"));
		break;
	default:
		die("Unhandled REQUEST_METHOD: " . $method);
}