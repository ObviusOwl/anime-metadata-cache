<?php 
chdir("..");
require_once("config-app.php");
require_once("inc/redis-cache.php");
require_once("inc/database.php");
require_once("inc/anidb-api-client.php");
require_once("inc/tvdb-api-client.php");
require_once("inc/common-functions.php");

$aid = NULL;
if( isset($_GET["aid"]) ){
    if( !is_numeric($_GET["aid"]) ){
        dieError("aid must be numeric");
    }
    $aid = intval( $_GET["aid"] );
}else{
    dieError("aid must be set");
}

try{
    $db = new Database( $config );
    if( isset($_POST["tvdb_id"]) && isset($_POST["tvdb_season"]) ){
        $m = new AnimeTvdbMapping( $aid, $_POST["tvdb_id"], $_POST["tvdb_season"] );
        $db->updateTvdbMapping( $m );
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    if( isset($_POST["search_overrides"]) ){
        $overrides = explode("\n", $_POST["search_overrides"] );
        $db->updateTitleSearchOverrides( $aid, $overrides );
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

}catch( Exception $e ){
    dieError($e->getMessage());
}


try{
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
    $anime = $anidb->getAnime( intval($_GET["aid"]) );

    $overrides = $db->getTitleSearchOverrides( $anime->id );
    $searchOverrideValue = htmlspecialchars( implode("\n",$overrides) );

}catch( Exception $e ){
    dieError($e->getMessage());
}
try{
    $db->animeLoadTvdbMapping( $anime );
    $tvdb->animeLoadImages( $anime );
}catch( Exception $e ){
    error_log($e->getMessage());
}


$tvdbIdValue="";
$tvdbSeasonValue="";
if( isset($anime->tvdbMapping) ){
    if( isset($anime->tvdbMapping->tvdbId) ){
        $tvdbIdValue = hspc($anime->tvdbMapping->tvdbId);
    }
    if( isset($anime->tvdbMapping->tvdbSeason) ){
        $tvdbSeasonValue = hspc($anime->tvdbMapping->tvdbSeason);
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Anidb Cache Edit Anime</title>
    <link rel="stylesheet" href="common.css">
</head>

<body class="page_content">
    
    <h1><?php echo hspc($anime->getMainTitleName()); ?></h1>

    <table class="nice_table">
        <tr>
            <th>Anidb ID</th>
            <td><?php echo hspc($anime->id); ?></td>
        </tr>
        <tr>
            <th>Aired</th>
            <td><?php echo hspc("{$anime->startdate} - {$anime->enddate}"); ?></td>
        </tr>
        <tr>
            <th>Episode count</th>
            <td><?php echo count($anime->episodes); ?></td>
        </tr>
        <tr>
            <th>Anidb Page</th>
            <td><a href="http://anidb.net/perl-bin/animedb.pl?show=anime&aid=<?php echo $anime->id; ?>" rel="noreferrer">anidb.net</a></td>
        </tr>
        <tr>
            <th>Kodi pages</th>
            <td>
                <a href="../kodi.php?aid=<?php echo $anime->id; ?>" >details</a>
                <a href="../kodi.php?guide&aid=<?php echo $anime->id; ?>" >episodes</a>
            </td>
        </tr>
    </table>

    <h3>tvdb Mapping</h3>
    <form method="post">
        ID: <input name="tvdb_id" value="<?php echo $tvdbIdValue; ?>" />
        Season: <input name="tvdb_season" value="<?php echo $tvdbSeasonValue; ?>" />
        <button type="submit">Update</button>
    </form>
    
    <h3>Search Override</h3>
    <p>One title per line.</p>
    <form method="post">
        <textarea name="search_overrides" rows="5" cols="100"><?php echo $searchOverrideValue; ?></textarea><br/>
        <button type="submit">Update</button>
    </form>
    
    <h3>Titles</h3>
    <table class="nice_table">
        <?php foreach($anime->titles as $title ): ?>
            <tr>
                <td><?php echo hspc($title->type); ?></td>
                <td><?php echo hspc($title->lang); ?></td>
                <td><?php echo hspc($title->value); ?></td>
                <td>Search <a href="https://www.thetvdb.com/search?l=en&q=<?php echo urlencode($title->value); ?>" rel="noreferrer">tvdb</a></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h3>Images</h3>
    <table class="nice_table">
        <?php foreach($anime->images as $img ): ?>
            <tr>
                <td><?php echo hspc($img->type); ?></td>
                <td><?php echo hspc($img->resolution); ?></td>
                <td><a href="<?php echo htmlentities($img->url); ?>" rel="noreferrer"><?php echo hspc($img->url); ?></a></td>
            </tr>
        <?php endforeach; ?>
    </table>
    


</body>
</html> 