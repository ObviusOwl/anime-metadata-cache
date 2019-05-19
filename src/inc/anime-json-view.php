<?php
require_once("anime.php");

abstract class AnimeJsonViewBase {
    protected function setDataFromProp(&$data, $obj, $keyName, $propName){
        if( isset($obj->$propName) ){
            $data[ $keyName ] = $obj->$propName;
        }
    }
    
    protected function setDataTitles( &$data, $obj, AnimeTitleJsonView $view, $titlesKey, $titlesPropName ){
        if( !isset($obj->$titlesPropName) ){
            return;
        }
        foreach( $obj->$titlesPropName as $title ){
            $view->setTitle( $title );
            $data[ $titlesKey ][] = $view->jsonSerialize();
        }
    }

}


class AnimeTitleJsonView extends AnimeJsonViewBase implements JsonSerializable, AnimeTitleView{
    protected $title;
    
    public function setTitle( AnimeTitle $title ){
        $this->title = $title;
    }
    
    public function jsonSerialize(){
        $data = Array(
            "type" => $this->title->type,
            "lang" => $this->title->lang,
            "value" => $this->title->value,
        );
        return $data;
    }
}

class AnimeEpisodeJsonView extends AnimeJsonViewBase implements JsonSerializable, AnimeEpisodeView{
    protected $episode;
    
    public function setEpisode( AnimeEpisode $ep ){
        $this->episode = $ep;
    }
    
    public function jsonSerialize(){
        $titleView = new AnimeTitleJsonView();
        $data = Array(
            "id" => $this->episode->id,
            "titles" => Array()
        );

        $this->setDataTitles( $data, $this->episode, $titleView, 'titles', 'titles' );
        $this->setDataFromProp( $data, $this->episode, 'type', 'type' );
        $this->setDataFromProp( $data, $this->episode, 'epno', 'epno' );
        $this->setDataFromProp( $data, $this->episode, 'length', 'length' );
        $this->setDataFromProp( $data, $this->episode, 'airdate', 'airdate' );
        $this->setDataFromProp( $data, $this->episode, 'summary', 'summary' );
        
        return $data;
    }
    
}

class AnimeJsonView extends AnimeJsonViewBase implements JsonSerializable, AnimeView{
    protected $anime;
    
    public function setAnime( Anime $anime ){
        $this->anime = $anime;
    }
    
    public function jsonSerialize(){
        $titleView = new AnimeTitleJsonView();
        $epView = new AnimeEpisodeJsonView();
        $data = Array(
            "id" => $this->anime->id,
            "titles" => Array()
        );
        $this->setDataTitles( $data, $this->anime, $titleView, 'titles', 'titles' );
        foreach( $this->anime->episodes as $ep ){
            $epView->setEpisode( $ep );
            $data[ "episodes" ][] = $epView->jsonSerialize();
        }

        return $data;
    }
    
}

?>