<?php
require_once( "inc/config.php" );
$config = new Config();

/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
* Application configs,
* do not change unless you read and understand the source code
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

$config["anidb.apiurl"] = "http://api.anidb.net:9001/httpapi";
$config["anidb.titlesurl"] = "http://anidb.net/api/anime-titles.xml.gz";
$config["anidb.imagesurl"] = "http://img7.anidb.net/pics/anime";

$config["anidb.clientid"] = "animemetacache";
$config["anidb.clientver"] = "1";

$config["tvdb.apiurl"] = "https://api.thetvdb.com";
$config["tvdb.imagesurl"] = "http://thetvdb.com/banners";

$config["tvdb.clientid"] = $config["anidb.clientid"];
$config["tvdb.clientver"] = $config["anidb.clientver"];
$config["tvdb.apikey"] = "8EOV96XMIO650SQV";

/* default configs */
$conf["db.port"] = 3306;

// include user configs
require_once( "config.php" );
?>