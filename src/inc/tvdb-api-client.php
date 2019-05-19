<?php
require_once("errors.php");
require_once("anime.php");

interface TvdbHttpClient{
    public function setApiURL( string $url );
    public function setImageURL( string $url );
    public function login();
    public function getImages( int $tid, int $season );
}

class DirectTvdbHttpClient implements TvdbHttpClient{
    protected $clientid;
    protected $clientver;
    protected $apikey;
    protected $apiVersion = "2.2.0";
    protected $apiurl = "https://api.thetvdb.com";
    protected $imgurl = "http://thetvdb.com/banners";
    protected $token;
    protected $http;
    
    public function __construct( $clientid, $clientver, $apikey ){
        $this->clientid = $clientid;
        $this->clientver = $clientver;
        $this->apikey = $apikey;

        $this->http = new HttpClient();
        $this->http->setOpt( CURLOPT_ENCODING, "gzip" );
        $this->http->setOpt( CURLOPT_USERAGENT, "{$this->clientid}/{$this->clientver}" );
        $this->http->setOpt( CURLOPT_HEADER, 0 );
        $this->http->setOpt( CURLOPT_CONNECTTIMEOUT, 30 );
        $this->http->setOpt( CURLOPT_FOLLOWLOCATION, True );
        $this->http->setOpt( CURLOPT_RETURNTRANSFER, True );
        $this->http->setopt( CURLOPT_CUSTOMREQUEST, "GET" );
    }
    
    public function setApiURL( string $url ){
        $this->apiurl = $url;
    }
    public function setImageURL( string $url ){
        $this->imgurl = $url;
    }
    
    protected function decodeResponse( $resp ){
        $data = $resp->decode();
        if( ! isset($data) ){
            throw new TvdbApiError("Failed to parse API response.");
        }
        return $data;
    }
    
    protected function checkResponseStatus( $resp, $message ){
        if( $resp->status != 200 ){
            throw new TvdbApiError( "$message: http status code {$resp->status}" );
        }
    }
    
    public function login(){
        if( ! isset($this->apikey) ){
            throw new TvdbApiError("You must set the API key.");
        }
        try{
            $req = new HttpRequest( $this->apiurl . "/login" );
            $req->headers->add( "Accept", "application/vnd.thetvdb.v{$this->apiVersion}" );
            $req->setBodyJson( Array("apikey" => $this->apikey ) );

            $resp = $this->http->post( $req );
            $this->checkResponseStatus( $resp, "Failed to login to API" );
            $data = $this->decodeResponse( $resp );
            $this->token = $data["token"]; 
        }catch( HttpError $e ){
            throw new TvdbApiError("HTTPError: {$e->getMessage()}");
        }
    }
    
    protected function doRequestLoggedIn( $req ){
        if( ! isset($this->token) ){
            $this->login();
        }
        $req->headers->set( "Authorization", "Bearer $this->token" );
        $req->headers->add( "Accept", "application/vnd.thetvdb.v{$this->apiVersion}" );
        $resp = $this->http->request( $req );
        if( $resp->status === 401 ){
            $this->login();
            $req->headers->set( "Authorization", "Bearer $this->token" );
            $resp = $this->http->request( $req );
        }
        return $resp;
    }
    
    protected function imageFactory($data){
        $img = new AnimeImage( $data["keyType"], $this->imgurl ."/". $data["fileName"] );
        if( isset($data["thumbnail"]) ){
            $img->thumbUrl = $this->imgurl ."/". $data["thumbnail"];
        }
        if( isset($data["resolution"]) ){
            $img->resolution = $data["resolution"];
        }
        return $img;
    }
    
    protected function getImageQueryParams( int $tid ){
        $data = NULL;
        try{
            $req = new HttpRequest( $this->apiurl . "/series/{$tid}/images/query/params" );
            $resp = $this->doRequestLoggedIn( $req );
            $this->checkResponseStatus( $resp, "Failed to get image query parameters" );
            $data = $this->decodeResponse( $resp );
        }catch( HttpError $e ){
            throw new TvdbApiError("HTTPError: {$e->getMessage()}");
        }
        return $data;
    }

    protected function getImageQuery(int $tid, $type, $subtype=NULL ){
        $req = new HttpRequest( $this->apiurl . "/series/{$tid}/images/query" );
        $req->query[ "keyType" ] = $type;
        if( isset($subtype) ){
            $req->query[ "subKey" ] = $subtype;
        }

        $resp = $this->doRequestLoggedIn( $req );
        $this->checkResponseStatus( $resp, "Failed to get image details" );
        return $this->decodeResponse( $resp );
    }
    
    protected function getImagesOfType(int $tid, $type, $subtype=NULL ){
        $images = Array();
        try{
            $data = $this->getImageQuery( $tid, $type, $subtype );
            foreach( $data["data"] as $imgData ){
                $images[] = $this->imageFactory( $imgData );
            }
        }catch( HttpError $e ){
        }
        return $images;
    }
    
    public function getImages( int $tid, int $season ){
        $images = Array();
        $typeList = Array( "fanart", "poster", "season", "series" );
        $queryParamsInfos = $this->getImageQueryParams( $tid );

        foreach( $queryParamsInfos["data"] as $info ){
            if( ! in_array( $info["keyType"], $typeList ) ){
                continue;
            }
            $type = $info["keyType"];
            $subtype = NULL;
            if( $type == "season" ){
                $subtype = "$season";
            }
            $newImages = $this->getImagesOfType( $tid, $type, $subtype );
            $images = array_merge( $images, $newImages );
        }
        return $images;
    }
    
    public function animeLoadImages( Anime $anime ){
        $tid = $anime->getTvdbId();
        $season = $anime->getTvdbSeason();
        if( isset($tid) && isset($season) ){
            $images = $this->getImages( $tid, $season );
            foreach( $images as $img ){
                $anime->addImage( $img );
            }
        }
    }

}


class CachedTvdbHttpClient extends DirectTvdbHttpClient implements TvdbHttpClient{
    protected $cache;

    public function __construct( RedisCache $cache, $clientid, $clientver, $apikey ){
        $this->cache = $cache;
        $this->cache->setTvdbTTL( 3600 );
        parent::__construct( $clientid, $clientver, $apikey );
        
        try{
            $this->token = $this->cache->getTvdbToken();
        }catch( CacheError $e ){
        }
    }
    
    public function login(){
        parent::login();
        $this->cache->putTvdbToken( $this->token, 86400 );
    }
    
    protected function getImageQueryParams( int $tid ){
        try{
            $data = $this->cache->getTvdbImageQueryParams( $tid );
        }catch( CacheError $e ){
            $data = parent::getImageQueryParams( $tid );
            $this->cache->putTvdbImageQueryParams( $tid, $data );
        }
        return $data;
    }

    protected function getImageQuery(int $tid, $type, $subtype=NULL ){
        try{
            $data = $this->cache->getTvdbImageQuery( $tid, $type, $subtype );
        }catch( CacheError $e ){
            $data = parent::getImageQuery( $tid, $type, $subtype );
            $this->cache->putTvdbImageQuery( $tid, $type, $subtype, $data );
        }
        return $data;
    }

    protected function getImagesOfType(int $tid, $type, $subtype=NULL ){
        $images = Array();
        try{
            $data = $this->getImageQuery( $tid, $type, $subtype );
            foreach( $data["data"] as $imgData ){
                $images[] = $this->imageFactory( $imgData );
            }
        }catch( HttpError $e ){
        }catch( CacheError $e ){
        }
        return $images;
    }

}

?>