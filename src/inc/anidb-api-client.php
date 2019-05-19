<?php
require_once("errors.php");
require_once("anime.php");
require_once("http-client.php");
require_once("anime-json-view.php");
require_once("anime-json-factory.php");
require_once("anime-xml-factory.php");

interface AnidbHttpClient{
    public function setApiURL( string $url );
    public function setTitlesURL( string $url );
    public function setImagesURL( string $url );

    public function getAnime( int $aid );
    public function getAnimeXml( int $aid );
    public function getTitlesXml( $fd );
}

class AnimeTitleStreamer implements Iterator{
    protected $isGz = True;
    protected $fp;
    protected $parser;

    protected $animeCb;
    protected $animeCount = 0;
    
    protected $titleCurr;
    protected $animeCurr;
    protected $animeStack = Array();
    protected $iterIndex = -1;
    
    public function __construct(){
    }
    public function __destruct(){
        $this->close();
    }
    
    /* Iterator Interface */
    public function rewind(){
        $this->next();
    }
    public function current(){
        return $this->animeStack[0];
    }
    public function key(){
        return $this->iterIndex;
    }
    public function next(){
        array_shift($this->animeStack);
        if( count($this->animeStack) == 0 ){
            if( $this->read(5) == 0 ){
                $this->animeCurr = NULL;
            }
        }
        $this->iterIndex++;
    }
    public function valid(){
        return count( $this->animeStack ) > 0;
    }
    
    /**
     * Set the callback function to be called when a anime and it's titles was parsed.
     * @param callable $cb callable accepting a Anime object as single parameter
     */
    public function setAnimeCallback( callable $cb ){
        $this->animeCb = $cb;
    }
    
    public function open( $fileName, bool $gzip=True ){
        $this->isGz = $gzip;
        $this->parser = xml_parser_create();
        if( $this->parser === False ){
            throw new AnidbApiError("Failed to create a xml parser.");
        }
        xml_set_default_handler( $this->parser, array($this, 'xmlDefaultHandler') );
        xml_set_element_handler( $this->parser, array($this, 'xmlStartElementHandler'), array($this, 'xmlEndElementHandler') );
        
        if( $this->isGz ){
            $fp = gzopen( $fileName, 'rb' );
        }else{
            $fp = fopen( $fileName, 'rb' );
        }
        if( $fp === False ){
            throw new AnidbApiError("Failed to open file '{$fileName}'.");
        }
        $this->fp = $fp;
    }
    
    public function close(){
        if( isset($this->parser) ){
            xml_parser_free( $this->parser );
        }
        if( isset($this->fp) ){
            fclose( $this->fp );
        }
        $this->animeCount = 0;
        $this->isGz = True;
        $this->animeStack = Array();
        $this->iterIndex = -1;
        $this->titleCurr = $this->animeCurr = $this->parser = $this->fp = NULL;
    }
    
    public function read( $maxAnime=NULL ){
        $oldCount = $this->animeCount;
        $isEnd = False;
        while( ! $isEnd ){
            if( isset($maxAnime) && abs($this->animeCount - $oldCount) >= $maxAnime ){
                break;
            }
            if( $this->isGz ){
                if( gzeof($this->fp) ){
                    break;
                }
                $buff = gzread( $this->fp, 200 );
                $isEnd = gzeof( $this->fp );
            }else{
                if(feof($this->fp) ){
                    break;
                }
                $buff = fread( $this->fp, 200 );
                $isEnd = feof( $this->fp );
            }
            xml_parse( $this->parser, $buff, $isEnd );
        }
        return abs($this->animeCount - $oldCount);
    }
    
    protected function xmlDefaultHandler( $parser, $data ){
        if( isset($this->titleCurr) ){
            $this->titleCurr->value = $data;
        }
    }
    
    protected function xmlStartElementHandler( $parser, $name, $attribs ){
        if( $name == "ANIME" ){
            $anime = new Anime();
            if( isset($attribs["AID"]) && is_numeric($attribs["AID"]) ){
                $anime->id = intval( $attribs["AID"] );
            }
            $this->animeCurr = $anime;
        }else if( $name == "TITLE" ){
            $title = new AnimeTitle();
            if( isset($attribs["TYPE"]) ){
                $title->type = $attribs["TYPE"];
            }
            if( isset($attribs["XML:LANG"]) ){
                $title->lang = $attribs["XML:LANG"];
            }
            $this->titleCurr = $title;
        }

    }
    
    protected function xmlEndElementHandler( $parser, $name ){
        if( $name == "ANIME" ){
            if( isset($this->animeCb) && isset($this->animeCurr) ){
                call_user_func($this->animeCb, $this->animeCurr );
            }
            $this->animeCount++;
            $this->animeStack[] = $this->animeCurr;
            $this->animeCurr = NULL;
        }else if( $name == "TITLE" ){
            if( isset($this->animeCurr) && isset($this->titleCurr) ){
                $this->animeCurr->titles->addTitle( $this->titleCurr );
            }
            $this->titleCurr = NULL;
        }

    }
    
}

/**
 * [Note that this class does not enforce rate limiting. This is done in the cached version.
 */
class DirectAnidbHttpClient implements AnidbHttpClient{
    protected $clientid;
    protected $clientver;
    protected $apiurl = "http://api.anidb.net:9001/httpapi";
    protected $titlesurl = "http://anidb.net/api/anime-titles.xml.gz";
    protected $imagesUrl = "http://img7.anidb.net/pics/anime";
    protected $http;
    
    public function __construct( $clientid, $clientver ){
        $this->clientid = $clientid;
        $this->clientver = $clientver;
        $this->http = new HttpClient();
        $this->http->setOpt( CURLOPT_ENCODING, "gzip" );
        $this->http->setOpt( CURLOPT_USERAGENT, "{$this->clientid}/{$this->clientver}" );
        $this->http->setOpt( CURLOPT_HEADER, 0 );
        $this->http->setOpt( CURLOPT_CONNECTTIMEOUT, 30 );
        $this->http->setOpt( CURLOPT_FOLLOWLOCATION, True );
    }
    
    public function setApiURL( string $url ){
        $this->apiurl = $url;
    }

    public function setTitlesURL( string $url ){
        $this->titlesurl = $url;
    }

    public function setImagesURL( string $url ){
        $this->imagesurl = $url;
    }
    
    public function getAnime( int $aid ){
        $xml = $this->getAnimeXml( $aid );
        $fact = new AnimeXmlFactory( $this->imagesUrl );
        return $fact->fromString( $xml );
    }

    public function getAnimeXml( int $aid ){
        try{
            $req = new HttpRequest( $this->apiurl );
            $req->setOpt( CURLOPT_RETURNTRANSFER, True );
            $req->query = Array(
                "client" => $this->clientid, "clientver" => $this->clientver, "protover" => "1",
                "request" => "anime", "aid" => "{$aid}"
            );

            error_log( "Downloading '{$req->getUrl()}'" );
            $resp = $this->http->get( $req );
        }catch( HttpError $e ){
            throw new AnidbApiError("HTTPError: {$e->getMessage()}");
        }
        if( $resp->status != 200 ){
            throw new AnidbApiError("Anidb API Error: http status code {$resp->status}");
        }
        return $resp->response;
    }
    
    public function getTitlesXml( $fd ){
        try{
            $req = new HttpRequest( $this->titlesurl );
            $req->setOpt( CURLOPT_WRITEFUNCTION, function($ch, $str) use (&$fd) {
                return fwrite($fd, $str);
            });

            error_log( "Downloading '{$req->getUrl()}'" );
            $resp = $this->http->get( $req );
        }catch( HttpError $e ){
            throw new AnidbApiError("HTTPError: {$e->getMessage()}");
        }
        if( $resp->status != 200 ){
            throw new AnidbApiError("Anidb API Error: http status code {$resp->status}");
        }
    }
}

/**
 * @see https://wiki.anidb.net/w/HTTP_API_Definition
 */
class CachedAnidbHttpClient extends DirectAnidbHttpClient implements AnidbHttpClient{
    protected $cache;
    protected $apiLock;
    protected $animeJsonFactory;

    public function __construct( RedisCache $cache, $clientid, $clientver ){
        // Caching the API is considered mandatory!
        // wiki quote: "Requesting the same dataset multiple times on a single day can get you banned"
        $this->cache = $cache;
        $this->cache->setAnimeTTL( 86400 );
        
        // We serialize calls to the anidb.net API in order to controll the connection rate
        // wiki quote: "You should not request more than one page every two seconds."
        $this->apiLock = new RedisCacheRLock($this->cache, "anidb:http-api-lock" );
        $this->apiLock->setDefaultTimeout(60);
        $this->animeJsonFactory = new AnimeJsonFactory();

        parent::__construct($clientid, $clientver);
    }
    
    public function getAnimeXml( int $aid ){
        try{
            $data = $this->cache->getAnimeXml( $aid );
        }catch( CacheError $e ){
            $data = $this->downloadAnimeXml( $aid );
        }
        return $data;
    }
    
    protected function downloadAnimeXml( int $aid ){
        $data = "";
        try{
            $this->apiLock->aquire();
            try{
                // check if other instance for this anime already got the lock
                $data = $this->cache->getAnimeXml( $aid );
            }catch( CacheError $e ){
                // first instance to have aquired the lock -> download
                $data = parent::getAnimeXml( $aid );
                try{
                    $this->cache->putAnimeXml( $aid, $data );
                }catch( CacheError $e ){
                    error_log("Ignored exception {$e->getMessage()}");
                }
                // throttle the connection rate: holding the lock a litle bit longer
                sleep(1);
            }
        }finally{
            $this->apiLock->release();
        }
        return $data;
    }

    public function searchTitle( $title ){
        $animes = Array();
        try{
            $data = $this->cache->getAnimesByTitle( $title );
            $animes = $this->animeJsonFactory->fromStringList( $data );
        }catch( CacheError $e ){
            $animes = $this->downloadTitlesXml( $title );
        }
        return $animes;
    }
    
    public function getAnimelist( $aidList ){
        $animes = Array();
        $data = Array();
        foreach( $aidList as $aid ){
            $data[] = $this->cache->getAnime( $aid );
        }
        return $this->animeJsonFactory->fromStringList( $data );
    }
    
    protected function downloadTitlesXml( $searchTitle ){
        $foundAnimes = [];
        try{
            $this->apiLock->aquire();
            try{
                // check if other instance for this anime already got the lock
                $data = $this->cache->getAnimesByTitle( $searchTitle );
                $foundAnimes = $this->animeJsonFactory->fromStringList( $data );
            }catch( CacheError $e ){
                // first instance to have aquired the lock -> download
                $tmpFd = tmpfile();
                if( $tmpFd === False){
                    throw new AnidbApiError("Failed to create a temp file for anime-titles.xml.gz");
                }
                $tmpFile = stream_get_meta_data($tmpFd)['uri'];
                $stream = new AnimeTitleStreamer();
                $view = new AnimeJsonView();

                parent::getTitlesXml( $tmpFd );
                $stream->open( $tmpFile );
                
                foreach( $stream as $k => $anime ){
                    $view->setAnime($anime);
                    $data = json_encode( $view );
                    if( $data === False ){
                        continue;
                    }
                    $this->cache->addAnime( $anime->id, $data );

                    $animeAdded = False;
                    foreach( $anime->titles as $title ){
                        if( $searchTitle == $title->value && ! $animeAdded){
                            $foundAnimes[] = $anime;
                            $animeAdded = True;
                        }
                        try{
                            $this->cache->indexTitle( $title->value, $anime->id );
                        }catch( CacheError $e ){
                            error_log("Ignored exception {$e->getMessage()}");
                        }
                    }
                }

                $stream->close();
                fclose( $tmpFd );
                $this->cache->updateTitlesTTL();
                // throttle the connection rate: holding the lock a litle bit longer
                sleep(1);
            }
        }finally{
            $this->apiLock->release();
        }
        return $foundAnimes;
    }

}

?>