<?php

require_once(__DIR__ . '/evoproject.lib.php');

exit(evoproject_execute(array_slice($argv, 1)));
