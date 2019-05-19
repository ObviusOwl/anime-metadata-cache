<?php 
chdir("..");
require_once("config-app.php");
require_once("inc/anime.php");
require_once("inc/redis-cache.php");
require_once("inc/database.php");
require_once("inc/anidb-api-client.php");
require_once("inc/tvdb-api-client.php");
require_once("inc/common-functions.php");

$ssEnc = "";
$searchString = "";
if( isset($_GET["q"]) ){
    $searchString = $_GET["q"];
    $ssEnc = htmlspecialchars( $searchString );
}

$animes = Array();
if( $searchString !== "" ){
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
        $animes = $anidb->searchTitle( $searchString );
    }catch( Exception $e ){
        dieError($e->getMessage());
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Anidb Cache Search</title>
    <link rel="stylesheet" href="common.css">
</head>

<body class="page_content">
    <form method="get" action="../kodi.php">
        <label>Kodi search</label>
        <input type="text" name="q"/>
        <button type="submit">Search</button>
    </form>
    
    <p style="margin-top:4em"></p>

    <form method="get" >
        <label>Anidb local search</label>
        <input type="text" name="q" value="<?php echo $ssEnc;?>" />
        <button type="submit">Search</button>
    </form>
    
    <h3>Search Results</h3>
    <table class="nice_table" >
        <?php foreach($animes as $anime ): ?>
        <tr>
            <td><?php echo hspc($anime->getMainTitleName()); ?></td>
            <td><a href="anime.php?aid=<?php echo $anime->id; ?>" >edit</a></td>
        </tr>
        <?php endforeach; ?>
    </table>

</body>
</html> 