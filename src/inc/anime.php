<?php

interface AnimeView{
    public function setAnime( Anime $anime );
}

interface AnimeTitleView{
    public function setTitle( AnimeTitle $title );
}

interface AnimeEpisodeView{
    public function setEpisode( AnimeEpisode $ep );
}

class AnimeTvdbMapping{
    public $anidbId;
    public $tvdbId;
    public $tvdbSeason;
    
    public function __construct( $aid, $tid, $season ){
        $this->anidbId = intval( $aid );
        $this->tvdbId = intval( $tid );
        $this->tvdbSeason = intval( $season );
    }
}

class AnimeTitle{
    public $type;
    public $lang;
    public $value;
}

class AnimeTitleCollection implements Iterator{
    protected $titles = Array();
    protected $pos = 0;
    
    /* Iterator Interface */
    public function rewind(){
        $this->pos = 0;
    }
    public function current(){
        return $this->titles[ $this->pos ];
    }
    public function key(){
        return $this->pos;
    }
    public function next(){
        $this->pos++;
    }
    public function valid(){
        return $this->pos >= 0 && $this->pos < count($this->titles);
    }
    
    public function addTitle( AnimeTitle $title ){
        if( isset($title) ){
            $this->titles[] = $title;
        }
    }
    
    public function getTitleForLang($lang, $fallbackLang ){
        $fallbackTitle = NULL;
        foreach( $this->titles as $title ){
            if( $title->lang == $lang ){
                return $title;
            }
            if( $title->lang == $fallbackLang ){
                $fallbackTitle = $title;
            }
        }
        return $fallbackTitle;
    }

    public function getMainTitle(){
        foreach( $this->titles as $title ){
            if( $title->type == "main" ){
                return $title;
            }
        }
    }

    public function getMainTitleName(){
        $title = $this->getMainTitle();
        if( isset($title) ){
            return $title->value;
        }
        return NULL;
    }

}

class AnimeImage{
    public $type;
    public $url;
    public $thumbUrl;
    public $resolution;
    
    public function __construct( $type, $url ){
        $this->type = strtolower($type);
        $this->url = $url;
    }
}

class AnimeSeiyuu{
    public $id;
    public $name;
    public $imageUrl;
}

class AnimeCreator{
    public $id;
    public $name;
    public $type;
}

class AnimeRating{
    public $value;
    public $votes;
}

class AnimeCharacter{
    public $id;
    public $type;
    public $name;
    public $gender;
    public $characterType;
    public $description;
    public $seiyuu;
}

class AnimeEpisode{
    public $id;
    public $type; // int
    public $epno; // string: 1,2,3... C1,C2, S1,S2 ...
    public $length;
    public $airdate;
    public $titles;
    public $summary;
    public $rating;
    
    public function __construct(){
        $this->titles = new AnimeTitleCollection();
    }

    public function addTitle( AnimeTitle $title ){
        $this->titles->addTitle( $title );
    }
    
    public function getTitleForLang( $lang, $fallbackLang ){
        return $this->titles->getTitleForLang( $lang, $fallbackLang );
    }

}

class Anime{
    public $id;
    public $titles;
    public $description;
    public $episodeCount;
    public $startdate;
    public $enddate;
    public $episodes = Array();
    public $tvdbMapping;
    public $images = Array();
    public $characters = Array();
    public $creators = Array();
    
    public function __construct(){
        $this->titles = new AnimeTitleCollection();
    }

    public function addTitle( AnimeTitle $title ){
        $this->titles->addTitle( $title );
    }

    public function addEpisode( AnimeEpisode $ep ){
        if( isset($ep) ){
            $this->episodes[] = $ep;
        }
    }

    public function addImage( AnimeImage $img ){
        if( isset($img) ){
            $this->images[] = $img;
        }
    }

    public function addCharacter( AnimeCharacter $char ){
        if( isset($char) ){
            $this->characters[] = $char;
        }
    }

    public function addCreator( AnimeCreator $cre ){
        if( isset($cre) ){
            $this->creators[] = $cre;
        }
    }

    public function getMainTitle(){
        return $this->titles->getMainTitle();
    }

    public function getMainTitleName(){
        return $this->titles->getMainTitleName();
    }
    
    public function getTitleForLang( $lang, $fallbackLang ){
        return $this->titles->getTitleForLang( $lang, $fallbackLang );
    }
    
    public function getTvdbId(){
        if( isset($this->tvdbMapping) && isset($this->tvdbMapping->tvdbId) ){
            return $this->tvdbMapping->tvdbId;
        }
    }
    public function getTvdbSeason(){
        if( isset($this->tvdbMapping) && isset($this->tvdbMapping->tvdbSeason) ){
            return $this->tvdbMapping->tvdbSeason;
        }
    }
    
    public function getEpisodeByNo( $epno ){
        foreach( $this->episodes as $ep ){
            if( $ep->epno == $epno ){
                return $ep;
            }
        }
    }

}

?>