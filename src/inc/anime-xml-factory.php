<?php
require_once("anime.php");

abstract class AnimeXmlFactoryBase {
    
    abstract public function fromElement( SimpleXMLElement $data );

    public function fromString( string $data ){
        $xml = new SimpleXMLElement( $data );
        return $this->fromElement( $xml );
    }

    public function fromStringList( $dataList ){
        $items = Array();
        foreach( $dataList as $data ){
            $items[] = $this->fromString( $data );
        }
        return $items;
    }

    protected function setStrPropFromPath( $obj, SimpleXMLElement $root, $propName, $path ){
        $elem = $root->xpath( $path );
        if( $elem !== False && count($elem) != 0 ){
            $obj->$propName = (string) $elem[0];
        }
    }

    protected function setIntPropFromPath( $obj, SimpleXMLElement $root, $propName, $path ){
        $elem = $root->xpath( $path );
        if( $elem !== False && count($elem) != 0 ){
            $obj->$propName = intval( (string) $elem[0] );
        }
    }

}

class AnimeTitleXmlFactory extends AnimeXmlFactoryBase{
    
    public function fromElement( SimpleXMLElement $xml ){
        $title = new AnimeTitle();
        
        $xmlNsAttr = $xml->attributes('xml', true);
        if( isset( $xmlNsAttr['lang'] ) ){
            $title->lang = (string) $xmlNsAttr['lang'];
        }
        
        if( isset($xml['type']) ){
            $title->type = (string) $xml['type'];
        }
        $title->value = (string) $xml;
        return $title;
    }

}

class AnimeEpisodeXmlFactory extends AnimeXmlFactoryBase{
    
    public function fromElement( SimpleXMLElement $xml ){
        $ep = new AnimeEpisode();
        $titlesFact = new AnimeTitleXmlFactory();

        $ep->id = intval( (string) $xml['id'] );
        $this->setStrPropFromPath($ep, $xml, 'epno', 'epno' );
        $this->setIntPropFromPath($ep, $xml, 'length', 'length' );
        $this->setStrPropFromPath($ep, $xml, 'airdate', 'airdate' );
        $this->setStrPropFromPath($ep, $xml, 'summary', 'summary' );
        foreach( $xml->xpath( 'title' ) as $titleElem ){
            $ep->addTitle( $titlesFact->fromElement( $titleElem  ) );
        }

        $epNoElem = $xml->xpath( 'epno' );
        if( $epNoElem !== False && count($epNoElem) != 0 ){
            $ep->type = intval( (string) $epNoElem[0]['type'] );
        }

        $ratingElem = $xml->xpath( 'rating' );
        if( $ratingElem !== False && count($ratingElem) != 0 ){
            $ep->rating = new AnimeRating();
            $ep->rating->value = (string) $ratingElem[0]; 
            $ep->rating->votes = (string) $ratingElem[0]['votes']; 
        }
        
        return $ep;
    }

}

class AnimeCharacterXmlFactory extends AnimeXmlFactoryBase{
    protected $imagesUrl;
    
    public function __construct( $imagesUrl ){
        $this->imagesUrl = $imagesUrl;
    }
    
    public function fromElement( SimpleXMLElement $xml ){
        $char = new AnimeCharacter();
        $char->id = intval( (string) $xml['id'] );
        $char->type = (string) $xml['type'];
        $this->setStrPropFromPath($char, $xml, 'name', 'name' );
        $this->setStrPropFromPath($char, $xml, 'gender', 'gender' );
        $this->setStrPropFromPath($char, $xml, 'characterType', 'charactertype' );
        $this->setStrPropFromPath($char, $xml, 'description', 'description' );

        $char->seiyuu = new AnimeSeiyuu();
        $seiyuuElem = $xml->xpath( 'seiyuu' );
        if( $seiyuuElem !== False && count($seiyuuElem) != 0 ){
            $char->seiyuu->id = intval( (string) $seiyuuElem[0]['id'] ); 
            $char->seiyuu->name = (string) $seiyuuElem[0]; 
            $pic = (string) $seiyuuElem[0]['picture'];
            $char->seiyuu->imageUrl = $this->imagesUrl .'/'. $pic;
        }
        return $char;
    }

}

class AnimeCreatorXmlFactory extends AnimeXmlFactoryBase{
    
    public function fromElement( SimpleXMLElement $xml ){
        $cre = new AnimeCreator();
        $cre->id = intval( (string) $xml['id'] );
        $cre->type = (string) $xml['type'];
        $cre->name = (string) $xml;
        return $cre;
    }

}


class AnimeXmlFactory extends AnimeXmlFactoryBase{
    protected $imagesUrl;
    
    public function __construct( $imagesUrl ){
        $this->imagesUrl = $imagesUrl;
    }
    
    public function fromElement( SimpleXMLElement $xml ){
        $anime = new Anime();
        $titlesFact = new AnimeTitleXmlFactory();
        $epFact = new AnimeEpisodeXmlFactory();
        $charFact = new AnimeCharacterXmlFactory( $this->imagesUrl );
        $creFact = new AnimeCreatorXmlFactory();

        $anime->id = intval( (string) $xml['id'] );
        $this->setStrPropFromPath($anime, $xml, 'description', 'description' );
        $this->setStrPropFromPath($anime, $xml, 'startdate', 'startdate' );
        $this->setStrPropFromPath($anime, $xml, 'enddate', 'enddate' );
        
        $picElem = $xml->xpath( 'picture' );
        if( $picElem !== False && count($picElem) > 0 ){
            $img = new AnimeImage( "poster", $this->imagesUrl ."/". (string) $picElem[0] );
            $anime->addImage( $img );
        }

        foreach( $xml->xpath( 'titles/*' ) as $titleElem ){
            $anime->addTitle( $titlesFact->fromElement( $titleElem ) );
        }

        foreach( $xml->xpath( 'episodes/*' ) as $epElem ){
            $anime->addEpisode( $epFact->fromElement( $epElem ) );
        }

        foreach( $xml->xpath( 'characters/*' ) as $charElem ){
            $anime->addCharacter( $charFact->fromElement( $charElem ) );
        }

        foreach( $xml->xpath( 'creators/*' ) as $creElem ){
            $anime->addCreator( $creFact->fromElement( $creElem ) );
        }
        
        return $anime;
    }

}

?>