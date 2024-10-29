<?php
define("MOODLE_INTERNAL", true);
define("MATURITY_ALPHA", true);
$plugin = new stdClass();
include_once("version.php");
echo "release: $plugin->release<br>";
echo "version: $plugin->version<br>";
