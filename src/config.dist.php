<?php

/* Connection to the redis server */
$config["redis.host"] = "localhost";
$config["redis.port"] = 6379;

/* app.url is used by the kodi page to refer back to itself. 
   Set this to the URL to this directory. 
 */
$config["app.url"] = "http://{$_SERVER['HTTP_HOST']}";

/* Connection to the Mysql database */
$config["db.host"] = "localhost";
$config["db.port"] = 3306;
$config["db.dbname"] = "anime_metadata_cache";
$config["db.user"] = NULL;
$config["db.password"] = NULL;
?>