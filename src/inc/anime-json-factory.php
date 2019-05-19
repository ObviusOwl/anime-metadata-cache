<?php
require_once("anime.php");

class AnimeTitleJsonFactory{
    public function __construct(){
    }
    
    public function fromString( string $data ){
        $data = json_decode($data, True);
        if( $data === NULL ){
            throw new TypeError("Failed to parse json");
        }
        return $this->fromArray( $data );
    }
    
    protected function setPropFromKey( AnimeTitle $title, $data, $propName, $key, $require=False ){
        if( $require && !isset($data[$key]) ){
            throw new UnexpectedValueException( "AnimeTitle must have a '$key' field." );
        }
        if( isset($data[$key]) ){
            $title->$propName = $data[$key];
        }
    }
    
    public function fromArray( $data ){
        $title = new AnimeTitle();
        $this->setPropFromKey( $title, $data, "value", "value", True );
        $this->setPropFromKey( $title, $data, "lang", "lang", False );
        $this->setPropFromKey( $title, $data, "type", "type", False );
        return $title;
    }
}

class AnimeJsonFactory{
    public function __construct(){
    }
    
    public function fromStringList( $dataList ){
        $animes = Array();
        if( ! isset($dataList) ){
            return $animes;
        }
        foreach( $dataList as $data ){
            $animes[] = $this->fromString( $data );
        }
        return $animes;
    }

    public function fromString( string $data ){
        $data = json_decode($data, True);
        if( $data === NULL ){
            throw new TypeError("Failed to parse json");
        }
        return $this->fromArray( $data );
    }
    
    protected function setPropFromKey( Anime $anime, $data, $propName, $key, $require=False ){
        if( $require && !isset($data[$key]) ){
            throw new UnexpectedValueException( "Anime must have a '$key' field." );
        }
        if( isset($data[$key]) ){
            $anime->$propName = $data[$key];
        }
    }
    
    public function fromArray( $data ){
        $anime = new Anime();
        $this->setPropFromKey( $anime, $data, "id", "id", True );

        if( isset($data["titles"]) ){
            $titleFac = new AnimeTitleJsonFactory();
            foreach( $data["titles"] as $titleData ){
                $anime->titles->addTitle( $titleFac->fromArray( $titleData ) );
            }
        }
        return $anime;
    }
    
}

?>