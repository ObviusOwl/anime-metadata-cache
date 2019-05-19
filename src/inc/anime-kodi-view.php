<?php
require_once("anime.php");
abstract class AnimeKodiViewBase{
    abstract public function toXML();
    
    public function pprint(){
        $xml = $this->toXML();
        if( $xml === NULL ){
            return;
        }
        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        return $dom->saveXML( $dom->firstChild );
    }

    protected function addChildValue(SimpleXMLElement $parent, $elemName, $value ){
        if( isset($value) ){
            // $parent->addChild does not escape "&" when passed a $value
            $parent->$elemName = (string) $value;
        }
    }

    /**
     * Appends SimpleXMLElement $from to the direct children of SimpleXMLElement $to
     * @see https://stackoverflow.com/questions/4778865/php-simplexml-addchild-with-another-simplexmlelement
     * @param  SimpleXMLElement $to   parent SimpleXMLElement
     * @param  SimpleXMLElement $from child SimpleXMLElement
     * @return void
     */
    function sxmlAppend( SimpleXMLElement $to=NULL, SimpleXMLElement $from=NULL ) {
        if( !isset($to) || !isset($from) ){
            return;
        }
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
    }
    
    function mangleAnidbMarkup( $data ){
        // Links to characters in plots, i.e.: http://anidb.net/ch11801 [Natsu]
        return preg_replace("~https?://anidb\.net/[a-z]+[0-9]+\s+\[([^]]+)\]~", '$1', $data );
    }
}

/*

        EPISODE

 */

abstract class AnimeEpisodeKodiViewBase extends AnimeKodiViewBase{
    protected $episode;
    
    public function setEpisode( AnimeEpisode $ep ){
        $this->episode = $ep;
    }
    
    protected function getSeason(){
        if( $this->episode->type == 1 ){
            // regular episodes
            return 1;
        }
        // specials and other
        return 0;
    }
    
    protected function addTitle( $xml ){
        $title = $this->episode->getTitleForLang("en", "en");
        if( isset($title) ){
            $this->addChildValue( $xml, 'title', $title->value );
        }
    }

}

class AnimeEpisodeKodiView extends AnimeEpisodeKodiViewBase implements AnimeEpisodeView{
    protected $selfURL;
    protected $anime;
    
    public function __construct( $anime, $selfURL ){
        $this->selfURL = $selfURL;
        $this->anime = $anime;
    }
    
    public function toXML(){
        $xml = new SimpleXMLElement('<episode/>');
        
        $this->addTitle( $xml );
        $url = "{$this->selfURL}/kodi.php?aid={$this->anime->id}&ep={$this->episode->epno}";
        $this->addChildValue( $xml, 'url', $url );
        $this->addChildValue( $xml, 'epnum', $this->episode->epno );
        $this->addChildValue( $xml, 'season', (string) $this->getSeason() );
        $this->addChildValue( $xml, 'aired', $this->episode->airdate );

        return $xml;
    }
}

class AnimeEpisodeKodiDetailsView extends AnimeEpisodeKodiViewBase implements AnimeEpisodeView{
    
    public function toXML(){
        $xml = new SimpleXMLElement('<details/>');
        
        $this->addTitle( $xml );
        $this->addChildValue( $xml, 'epnum', $this->episode->epno );
        $this->addChildValue( $xml, 'episode', $this->episode->epno );
        $this->addChildValue( $xml, 'season', (string) $this->getSeason() );
        $this->addChildValue( $xml, 'aired', $this->episode->airdate );
        $this->addChildValue( $xml, 'runtime', $this->episode->length );
        $this->addChildValue( $xml, 'plot', $this->mangleAnidbMarkup($this->episode->summary) );
        if( isset($this->episode->rating) ){
            $this->addChildValue( $xml, 'rating', $this->episode->rating->value );
            $this->addChildValue( $xml, 'votes', $this->episode->rating->votes );
        }

        return $xml;
    }
}

/*

        IMAGE

 */

class AnimeImageKodiView extends AnimeKodiViewBase{
    protected $img;
    
    public function setImage( AnimeImage $img ){
        $this->img = $img;
    }
    
    public function toXML(){
        $xml = new SimpleXMLElement('<thumb/>');
        $xml[0] = $this->img->url;

        if( $this->img->type == "poster" ){
            // this is the only onw we know we get right,
            // for the other types kodi can guess from the dimensions
            $xml->addAttribute("aspect", "poster");
        }else if( $this->img->type == "season" ){
            $xml->addAttribute("type", "season");
            $xml->addAttribute("season", "1");
        }

        if( isset($this->img->resolution) && $this->img->resolution != "" ){
            $xml->addAttribute("dim", $this->img->resolution );
        }
        if( isset($this->img->thumbUrl) && $this->img->thumbUrl != "" ){
            $xml->addAttribute("preview", $this->img->thumbUrl );
        }
        return $xml;
    }
}

/*

        CHARACTER

 */

class AnimeCharacterKodiView extends AnimeKodiViewBase{
    protected $char;
    
    public function setcharacter( AnimeCharacter $char ){
        $this->char = $char;
    }
    
    public function toXML(){
        if( ! preg_match("/main character in|secondary cast in/i", $this->char->type ) ){
            return;
        }
        $xml = new SimpleXMLElement('<actor/>');
        $this->addChildValue( $xml, 'role', $this->char->name );
        if( isset($this->char->seiyuu) ){
            $this->addChildValue( $xml, 'name', $this->char->seiyuu->name );
            $this->addChildValue( $xml, 'thumb', $this->char->seiyuu->imageUrl );
        }
        return $xml;
    }
}

/*

        CREATOR

 */

class AnimeCreatorKodiView extends AnimeKodiViewBase{
    protected $creator;
    
    public function setCreator( AnimeCreator $creator ){
        $this->creator = $creator;
    }
    
    public function toXML(){
        if( preg_match("/Direction/i", $this->creator->type ) ){
            $xml = new SimpleXMLElement('<director/>');
        }else if( preg_match("/animation work/i", $this->creator->type ) ){
            $xml = new SimpleXMLElement('<studio/>');
        }else{
            $xml = new SimpleXMLElement('<credits/>');
        }
        $xml[0] = $this->creator->name;
        return $xml;
    }
}

/*

        ANIME

 */

abstract class AnimeKodiViewAnimeBase extends AnimeKodiViewBase{
    protected $selfURL;
    
    public function __construct( $selfURL ){
        $this->selfURL = $selfURL;
    }

    public function setAnime( Anime $anime ){
        $this->anime = $anime;
    }

    protected function getAnimeYear(){
        if( isset($this->anime) && isset($this->anime->startdate) ){
            $matches= Array();
            $r = preg_match("/([0-9]{4})-.*/", $this->anime->startdate, $matches );
            if( $r === 1 ){
                return $matches[1];
            }
        }
    }
}


class AnimeKodiDetailsView extends AnimeKodiViewAnimeBase implements AnimeView{
    
    public function toXML(){
        $imgView = new AnimeImageKodiView();
        $charView = new AnimeCharacterKodiView();
        $creView = new AnimeCreatorKodiView();

        $details = new SimpleXMLElement('<details/>');
        $this->addChildValue( $details, 'id', strval($this->anime->id) );
        $this->addChildValue( $details, 'title', $this->anime->getMainTitleName() );
        $this->addChildValue( $details, 'plot', $this->mangleAnidbMarkup( $this->anime->description ) );
        $this->addChildValue( $details, 'year', $this->getAnimeYear() );
        $this->addChildValue( $details, 'premiered', $this->anime->startdate );

        $epGuide = $details->addChild("episodeguide");
        $url = "{$this->selfURL}/kodi.php?aid={$this->anime->id}&guide";
        $this->addChildValue( $epGuide, 'url', $url );
        
        $fanart = Array();
        $images = Array();
        foreach( $this->anime->images as $img ){
            if( $img->type == "fanart" ){
                $fanart[] = $img;
            }else{
                $images[] = $img;
            }
        }
        foreach( $images as $img ){
            $imgView->setImage( $img );
            $this->sxmlAppend( $details, $imgView->toXML() );
        }
        $fanartElem = $details->addChild("fanart");
        foreach( $fanart as $img ){
            $imgView->setImage( $img );
            $this->sxmlAppend( $fanartElem, $imgView->toXML() );
        }

        foreach( $this->anime->characters as $char ){
            $charView->setcharacter( $char );
            $this->sxmlAppend( $details, $charView->toXML() );
        }

        foreach( $this->anime->creators as $cre ){
            $creView->setCreator( $cre );
            $this->sxmlAppend( $details, $creView->toXML() );
        }
        
        return $details;
    }
    
}


class AnimeKodiSearchView extends AnimeKodiViewAnimeBase implements AnimeView{
    
    public function toXML(){
        $title = $this->anime->getMainTitle();
        if( $title === NULL ){
            return NULL;
        }
        $url = "{$this->selfURL}/kodi.php?aid={$this->anime->id}";
        
        $xml = new SimpleXMLElement('<entity/>');
        $this->addChildValue( $xml, 'title', $title->value );
        $this->addChildValue( $xml, 'url', $url );
        $this->addChildValue( $xml, 'id', $this->anime->id );
        $this->addChildValue( $xml, 'year', $this->getAnimeYear() );
        return $xml;
    }

}

class AnimeKodiEpisodeGuideView extends AnimeKodiViewAnimeBase implements AnimeView{
    
    public function toXML(){
        $epView = new AnimeEpisodeKodiView( $this->anime, $this->selfURL );
        $xml = new SimpleXMLElement('<episodeguide/>');

        foreach( $this->anime->episodes as $episode ){
            $epView->setEpisode( $episode );
            $this->sxmlAppend( $xml, $epView->toXML() );
        }
        return $xml;
    }
    
}

?>