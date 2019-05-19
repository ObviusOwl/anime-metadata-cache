<?php 
require_once("config-app.php");
require_once("inc/redis-cache.php");
require_once("inc/database.php");
require_once("inc/anidb-api-client.php");
require_once("inc/tvdb-api-client.php");
require_once("inc/common-functions.php");
require_once("inc/anime-kodi-view.php");

$page = "";
if( isset($_GET["q"]) ){
    $page = "search";
    $query = $_GET["q"];
}else if( isset($_GET["aid"]) ){
    $aid = parseIntGetParam( "aid" );
    $page = "anime";
    
    if( isset($_GET["ep"]) ){
        $epno = $_GET["ep"];
        $page = "episode";
    }
    if( isset($_GET["guide"]) ){
        $page = "guide";
    }
}

if( $page == "" ){
    dieError("Invalid parameter combination.");
}

try{
    $db = new Database( $config );
    $cache = new RedisCache( $config["redis.host"], $config["redis.port"] );

    $anidb = new CachedAnidbHttpClient( $cache, 
        $config["anidb.clientid"], 
        $config["anidb.clientver"] 
    );
    $anidb->setApiURL( $config["anidb.apiurl"] );
    $anidb->setTitlesURL( $config["anidb.titlesurl"] );
    $anidb->setImagesURL( $config["anidb.imagesurl"] );

    $tvdb = new CachedTvdbHttpClient( $cache, 
        $config["tvdb.clientid"], 
        $config["tvdb.clientver"], 
        $config["tvdb.apikey"] 
    );
    $tvdb->setApiURL( $config["tvdb.apiurl"] );
    $tvdb->setImageURL( $config["tvdb.imagesurl"] );

    if( isset($aid) ){
        $anime = $anidb->getAnime( intval($_GET["aid"]) );
        try{
            $db->animeLoadTvdbMapping( $anime );
            $tvdb->animeLoadImages( $anime );
        }catch( Exception $e ){
            error_log($e->getMessage());
        }
    }
    if( $page == "search" ){
        $view = new AnimeKodiSearchView( $config["app.url"] );
        $aidList = $db->searchTitle( $query );
        if( count($aidList) > 0 ){
            $animes = $anidb->getAnimelist( $aidList );
        }else{
            $animes = $anidb->searchTitle( $query );
        }
    }else if( $page == "anime" ){
        $view = new AnimeKodiDetailsView( $config["app.url"] );
        $view->setAnime( $anime );
    }else if( $page == "guide" ){
        $view = new AnimeKodiEpisodeGuideView( $config["app.url"] );
        $view->setAnime( $anime );
    }else if( $page == "episode" ){
        $view = new AnimeEpisodeKodiDetailsView( $config["app.url"] );
        $ep = $anime->getEpisodeByNo( $epno );
        if( ! isset($ep) ){
            throw new Exception("Episode not found");
        }
        $view->setEpisode( $ep );
    }

}catch( Exception $e ){
    dieException( $e );
}

header("Content-type: text/xml");
echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;

if( $page == "search"){
    echo "<results>".PHP_EOL;
    foreach( $animes as $anime ){
        $view->setAnime( $anime );
        echo $view->pprint() . PHP_EOL;
    }
    echo "</results>".PHP_EOL;
}else if( $page == "anime" || $page == "guide" || $page == "episode" ){
    echo $view->pprint() . PHP_EOL;
} 

?>