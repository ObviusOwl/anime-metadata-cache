<?php 
require_once("errors.php");

class RedisCacheRLock{
    protected $cache = NULL;
    protected $id = NULL;
    protected $key = NULL;
    protected $ttl = NULL;

    public function __construct( RedisCache $cache, string $key ){
        $this->cache = $cache;
        $this->key = $key;
    }
    
    public function setDefaultTimeout( int $ttl ){
        $this->ttl = $ttl;
    }
    
    public function aquire( $timeout=NULL ){
        if( isset($timeout) ){
            $ttl = intval($timeout);
        }else if( isset($this->ttl) ){
            $ttl = $this->ttl;
        }else{
            throw new InvalidArgumentException("You must either set a default timeout, or provide a timeout to the aquire method.");
        }

        if( ! isset($this->id) ){
            // reentrant lock
            $this->id = $this->cache->aquireLock($this->key, $ttl);
        }
    }

    public function release(){
        if( isset($this->id) ){
            $r = $this->cache->releaseLock($this->key, $this->id);
            if( $r ){
                unset( $this->id );
            }
        }
    }

}

class RedisCache {
    
    protected $redis;
    protected $animeTTL = 86400;
    protected $titlesTTL = 86400;
    protected $tvdbTTL = 900;
    
    public function __construct( $host, $port ){
        $this->redis = new Redis();
        $this->redis->connect( $host, $port );
    }
    
    public function __destruct(){
        $this->redis->close();
    }

    public function setAnimeTTL( int $ttl ){
        $this->animeTTL = $ttl;
    }

    public function setTitlesTTL( int $ttl ){
        $this->titlesTTL = $ttl;
    }

    public function setTvdbTTL( int $ttl ){
        $this->tvdbTTL = $ttl;
    }

    /**
     * Source: https://redislabs.com/ebook/part-2-core-concepts/chapter-6-application-components-in-redis/6-2-distributed-locking/6-2-5-locks-with-timeouts/
     * @param  [type] $key         [description]
     * @param  [type] $lockTimeout [description]
     * @return [type]              [description]
     */
    public function aquireLock( $key, $lockTimeout ){
        $myId = uniqid( "", True );
        $lockTimeout = intval( $lockTimeout );
        
        // Note: we do not use aquire timeouts
        while( True ){
            // set the key to myId if it does not exist and add a expire timeout
            $r = $this->redis->set($key, $myId, ['nx', 'ex'=>$lockTimeout]); // is atomic
            if( !$r ){
                // lock is held by someone else
                if( $this->redis->ttl($key) < 0 ){
                    // ensure existing keys have a timeout, or else we risk a permanent deadlock
                    $this->redis->expire( $key, $lockTimeout);
                }
            }else{
                // we got the lock
                return $myId;
            }
            // busy wait
            usleep(10000); 
        }
    }
    
    /**
     * Source https://redislabs.com/ebook/part-2-core-concepts/chapter-6-application-components-in-redis/6-2-distributed-locking/6-2-3-building-a-lock-in-redis/
     * @param  [type] $key [description]
     * @param  [type] $id  [description]
     * @return [type]      [description]
     */
    public function releaseLock( $key, $id ){
        while( True ){
            // watch key, in case someone modifies it as we are reading
            $this->redis->watch( $key );
            if( $this->redis->get( $key ) != $id ){
                // we try to release a lock we did not aquire 
                $this->redis->unwatch();
                return False;
            }
            // remove the key, but fail if the key changed
            $r = $this->redis->multi()->delete( $key )->exec();
            $this->redis->unwatch();
            if( $r ){
                return True;
            }
        }
    }
    
    public function requireKey( $key ){
        $r = $this->redis->exists( $key );
        if( $r === False || $r === 0 ){
            throw new CacheError("Redis key '$key' not found");
        }
    }
    
    public function get( $key, $failMsg ){
        $r = $this->redis->get( $key );
        if( $r === False ){
            throw new CacheError( $failMsg );
        }
        return $r;
    }
    
    public function set( $key, $data, $ttl, $failMsg ){
        $r = $this->redis->set( $key, $data, $ttl );
        if( $r !== True ){
            throw new CacheError( $failMsg );
        }
    }

    
    public function putAnimeXml( int $aid, $data, $ttl=NULL ){
        if( !isset($ttl) ){
            $ttl = $this->animeTTL;
        }
        $key = "anidb:anime-$aid-xml";
        $failMsg = "Failed to save anidb metadata to redis cache.";
        $this->set( $key, $data, $ttl, $failMsg );
    }

    public function getAnimeXml( int $aid ){
        $key = "anidb:anime-$aid-xml";
        $failMsg = "Failed to get anidb metadata from redis cache.";
        return $this->get( $key, $failMsg );
    }

    /**
     * Assuming non-concurrent access, or else lost updates may happen
     * @param  string $title [description]
     * @param  int    $aid   [description]
     * @return [type]        [description]
     */
    public function indexTitle( string $title, int $aid ){
        $title = strtolower($title);
        $key = "anidb:titles-index";
        
        $data = $this->redis->hGet( $key, $title );
        if( $data !== False ){
            $data = json_decode($data);
        }
        if( $data !== False && $data !== NULL ){
            if( ! in_array($aid, $data) ){
                $data[] = $aid;
            }
            $data = json_encode($data);
        }else{
            $data = json_encode( Array($aid) );
        }
        
        $r = $this->redis->hSet( $key, $title, $data );
        if( $r === False ){
            throw new CacheError("Failed to set title key");
        }
    }
    
    public function addAnime( int $aid, $data ){
        $key = "anidb:animes";

        $r = $this->redis->hSet( $key, $aid, $data );
        if( $r === False ){
            throw new CacheError("Failed to set title key");
        }
    }

    public function getAnime( int $aid ){
        $key = "anidb:animes";
        $data = $this->redis->hGet( $key, $aid );
        if( $data === False ){
            throw new CacheError("Failed to get anime");
        }
        return $data;
    }
    
    public function updateTitlesTTL(){
        $akey = "anidb:animes";
        $ikey = "anidb:titles-index";
        $this->redis->expire( $akey, $this->titlesTTL );
        $this->redis->expire( $ikey, $this->titlesTTL );
    }
    
    public function getAnimesByTitle( $title ){
        $title = strtolower($title);
        $akey = "anidb:animes";
        $ikey = "anidb:titles-index";
        $this->requireKey( $akey );
        $this->requireKey( $ikey );
        
        $idList = $this->redis->hGet( $ikey, $title );
        if( $idList === False ){
            return NULL;
        }
        $idList = json_decode($idList);
        if( $idList === NULL ){
            return NULL;
        }
        $animes = Array();
        foreach( $idList as $aid ){
            $data = $this->redis->hGet( $akey, $aid );
            if( $data !== False ){
                $animes[] = $data;
            }
        }
        return $animes;
    }


    public function putTvdbToken( $token, $ttl ){
        $key = "tvdb:api-token";
        $failMsg = "Failed to save tvdb api token to redis cache.";
        $this->set( $key, $token, $ttl, $failMsg );
    }

    public function getTvdbToken(){
        $key = "tvdb:api-token";
        $failMsg = "Failed to get tvdb api token from redis cache.";
        return $this->get( $key, $failMsg );
    }
    
    public function putTvdbImageQueryParams( int $tid, $data ){
        $key = "tvdb:image-query-params:$tid";
        $failMsg = "Failed to save tvdb image query params to redis cache.";
        $this->set( $key, json_encode($data), $this->tvdbTTL, $failMsg );
    }

    public function getTvdbImageQueryParams( int $tid ){
        $key = "tvdb:image-query-params:$tid";
        $failMsg = "Failed to get tvdb image query params from redis cache.";
        return json_decode( $this->get( $key, $failMsg ), True );
    }

    public function putTvdbImageQuery( int $tid, string $type, $subtype, $data ){
        $key = "tvdb:image-query:$tid:$type-$subtype";
        $failMsg = "Failed to save tvdb image query to redis cache.";
        $this->set( $key, json_encode($data), $this->tvdbTTL, $failMsg );
    }

    public function getTvdbImageQuery( int $tid, string $type, $subtype ){
        $key = "tvdb:image-query:$tid:$type-$subtype";
        $failMsg = "Failed to get tvdb image query from redis cache.";
        return json_decode( $this->get( $key, $failMsg ), True );
    }

}

?>