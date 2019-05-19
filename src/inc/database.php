<?php 
require_once("errors.php");
require_once("anime.php");

class Database {
    private $dbh;
    private $conf;
    private $isInTransaction = False;
    
    public function __construct( Config $conf ){
        $this->conf = $conf;
        $dsnParts = Array(
            "host={$conf["db.host"]}", "port={$conf["db.port"]}",
            "dbname={$conf["db.dbname"]}"
        );
        $dsn = "mysql:" . implode(';', $dsnParts );
        $options = Array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        );
        $this->dbh = new PDO($dsn, $conf["db.user"], $conf["db.password"], $options );
    }

    private function beginTransaction(){
        if( $this->isInTransaction ){
            throw new DatabaseError( "Failed to start transaction: already in transaction" );
        }
        if( $this->dbh->beginTransaction() === False ){
            throw new DatabaseError( "Failed to start transaction" );
        }
        $this->isInTransaction = True;
    }

    private function endTransaction( $rollback=False ){
        if( ! $this->isInTransaction ){
            throw new DatabaseError( "Failed to end transaction: not in transaction" );
        }
        if( $rollback ){
            $r = $this->dbh->rollBack();
        }else{
            $r = $this->dbh->commit();
        }
        if( $r === false ){
            throw new DatabaseError( "Failed to end transaction" );
        }
        $this->isInTransaction = False;
    }
    
    private function requireAnimeId( Anime $anime ){
        if( ! isset($anime->id) ){
            throw new DatabaseError("Anime::id must be set.");
        }
        return $anime->id;
    }
    
    private function prepare( $sql ){
        $stm = $this->dbh->prepare( $sql );
        if( $stm === False ){
            $e = $this->dbh->errorInfo();
            throw new DatabaseError( "Failed to prepare SQL statement: {$e[0]} {$e[2]}" );
        }
        return $stm;
    }

    private function execute( $stm, $params=NULL ){
        if( isset($params) ){
            $r = $stm->execute( $params );
        }else{
            $r = $stm->execute();
        }
        if( $r === False ){
            $e = $stm->errorInfo();
            throw new DatabaseError( "Failed to execute SQL statement: {$e[0]} {$e[2]}" );
        }
    }

    private function fetchAssoc( $stm ){
        $data = $stm->fetch( PDO::FETCH_ASSOC );
        if( $data === False ){
            return NULL;
        }
        return $data;
    }

    public function animeLoadTvdbMapping( Anime $anime ){
        $map = $this->getTvdbMapping( $this->requireAnimeId($anime) );
        $anime->tvdbMapping = $map;
    }
    
    public function getTvdbMapping( int $aid ){
        $res = NULL;
        $this->beginTransaction();
        try{
            $stm = $this->prepare("SELECT anidb_id, tvdb_id, tvdb_season FROM `anime_anidb_tvdb` WHERE `anidb_id` = :D");
            $this->execute( $stm, array(':D' => $aid) );
            $data = $this->fetchAssoc( $stm );
            if( isset($data) ){
                $res = new AnimeTvdbMapping( $data['anidb_id'], $data['tvdb_id'], $data['tvdb_season'] );
            }
        }finally{
            $this->endTransaction();
        }
        return $res;
    }

    public function updateTvdbMapping( AnimeTvdbMapping $map ){
        $delQuer = "DELETE FROM `anime_anidb_tvdb` WHERE `anidb_id` = :A";
        $insQuer = "INSERT INTO `anime_anidb_tvdb` (anidb_id, tvdb_id, tvdb_season) VALUES ( :A, :T, :S)";
        $data = Array( ':A' => $map->anidbId, ':T' => $map->tvdbId, ':S' => $map->tvdbSeason );

        $this->beginTransaction();
        try{
            $stm = $this->prepare( $delQuer );
            $this->execute( $stm, Array( ':A' => $map->anidbId ) );
            $stm = $this->prepare( $insQuer );
            $this->execute( $stm, $data );
        }catch( Exception $e ){
            $this->endTransaction( True );
            throw $e;
        }
        $this->endTransaction();
    }
    
    public function searchTitle( $search ){
        $quer = "SELECT `anidb_id` FROM `anime_title_search` WHERE `search` = :S";
        $data = Array( ':S' => $search );
        $stm = $this->prepare( $quer );
        $this->execute( $stm, $data );
        $data = $stm->fetchAll( PDO::FETCH_COLUMN, 0 );
        if( $data === False ){
            $data = Array();
        }
        for( $i=0; $i<count($data); $i++ ){
            $data[$i] = intval($data[$i]);
        }
        return $data;
    }

    public function updateTitleSearchOverrides( $aid, $searchStrings ){
        $delQuer = "DELETE FROM `anime_title_search` WHERE `anidb_id` = :A";
        $insQuer = "INSERT INTO `anime_title_search` (anidb_id, search) VALUES ( :A, :S )";

        $this->beginTransaction();
        try{
            $stm = $this->prepare( $delQuer );
            $this->execute( $stm, Array(":A"=>$aid) );

            $stm = $this->prepare( $insQuer );
            foreach( $searchStrings as $ss ){
                $this->execute( $stm, Array(":A"=>$aid, ":S"=>$ss) );
            }
        }catch( Exception $e ){
            $this->endTransaction( True );
            throw $e;
        }
        $this->endTransaction();
    }

    public function getTitleSearchOverrides( $aid ){
        $quer = "SELECT `search` FROM `anime_title_search` WHERE `anidb_id` = :A";
        $data = Array( ':A' => $aid );
        $stm = $this->prepare( $quer );
        $this->execute( $stm, $data );
        $data = $stm->fetchAll( PDO::FETCH_COLUMN, 0 );
        if( $data === False ){
            $data = Array();
        }
        return $data;
    }
}

?>