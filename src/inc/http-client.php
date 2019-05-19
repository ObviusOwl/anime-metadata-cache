<?php
require_once("errors.php");

class HttpOptionList{
    public $options = Array();

    public function setOpt( $opt, $value ){
        $this->options[ $opt ] = $value;
    }
    
    public function getOpt( $opt ){
        if( isset($this->options) && isset($this->options[$opt]) ){
            return $this->options[ $opt ];
        }
        return NULL;
    }
}

class HttpHeaderList{
    public $headers = Array();

    public function getOffset( $key ){
        $key = strtolower( $key );
        for( $i=0; $i<count($this->headers); $i++ ){
            if( $this->headers[$i]["key"] == $key ){
                return $i;
            }
        }
        return -1;
    }

    public function getSingle( $key ){
        $i = $this->getOffset( $key );
        if( $i >= 0 ){
            return $this->headers[ $i ][ "value" ];
        }
        return NULL;
    }
    
    public function add( $key, $value ){
        $this->headers[] = Array( "key" => trim(strtolower( $key )), "value" => trim($value) );
    }

    public function set( $key, $value ){
        $i = $this->getOffset( $key );
        if( $i >= 0 ){
            $this->headers[ $i ][ "value" ] = $value;
        }else{
            $this->add( $key, $value );
        }
    }
    
    public function parseLine( $line ){
        $matches = Array();
        $r = preg_match( "/([^:]+):(.+)/", $line, $matches );
        if( $r === 1 ){
            $this->add( $matches[1], $matches[2] );
        }
    }
    
    public function asLinesArray(){
        $data = Array();
        foreach( $this->headers as $h ){
            $data[] = "{$h['key']}: {$h['value']}";
        }
        return $data;
    }
}

class HttpResponse{
    public $req;
    public $response;
    public $status;
    public $headers;
    
    public function __construct( HttpRequest $req ){
        $this->req = $req;
        $this->headers = new HttpHeaderList();
    }
    
    public function responseAsJson(){
        if( isset($this->response) ){
            $data = json_decode( $this->response, True );
            if( $data === NULL ){
                throw new HttpError("Failed to decode response as json: ".json_last_error() );
            }
            return $data;
        }
    }
    
    public function decode(){
        $h = $this->headers->getSingle('content-type');
        if( isset($h) && stripos($h, 'json') !== False ){
            return $this->responseAsJson();
        }
    }
}

class HttpRequest{
    public $url;
    public $query = Array();
    public $options;
    public $body;
    public $headers;
    
    public function __construct( $url ){
        $this->url = $url;
        $this->options = new HttpOptionList();
        $this->headers = new HttpHeaderList();
    }

    public function setOpt( $opt, $value ){
        $this->options->setOpt( $opt, $value );
    }
    
    public function setBodyJson( $data, $setHeader=True ){
        $this->body = json_encode($data);
        if( $setHeader ){
            $this->headers->set( 'content-type', 'application/json' );
        }
    }
    
    public function getUrl(){
        if( ! isset($this->url) ){
            throw new HttpError("HttpRequest::url must be set.");
        }
        $url = $this->url;
        if( isset($this->query) && count($this->query) > 0 ){
            $url = "{$this->url}?" . http_build_query( $this->query );
        }
        return $url;
    }

}

class HttpClient{
    protected $options;
    
    public function __construct(){
        $this->options = new HttpOptionList();
    }

    public function setOpt( $opt, $value ){
        $this->options->setOpt( $opt, $value );
    }

    public function getOpt( $opt, HttpRequest $req=NULL ){
        if( isset($req) ){
            $value = $req->options->getOpt( $opt );
        }
        if( isset($value) ){
            return $value;
        }else{
            return $this->options->getOpt( $opt );
        }
        return NULL;
    }
    
    protected function curlInit( $url ){
        $ch = curl_init( $url );
        if( $ch === False ){
            throw new HttpError("Failed to init curl.");
        }
        return $ch;
    }
    
    protected function curlSetOpts( $ch, $opts ){
        if( isset($opts) && count($opts) > 0 ){
            if( curl_setopt_array( $ch, $opts ) === False ){
                throw new HttpError("Failed to set curl option.");
            }
        }
    }
    
    protected function curlSetAllOpts( $ch, HttpRequest $req, HttpResponse $resp ){
        // per default parse the response headers
        curl_setopt( $ch, CURLOPT_HEADERFUNCTION, function($ch, $str) use (&$resp) {
            $resp->headers->parseLine( $str );
            return strlen( $str );
        });
        // set client's default options
        $this->curlSetOpts( $ch, $this->options->options );
        // set request's headers
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $req->headers->asLinesArray() );
        // set payload (body)
        if( isset($req->body) ){
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $req->body );
        }
        // allow override all options with request's options
        $this->curlSetOpts( $ch, $req->options->options );
    }

    protected function curlExec( $ch, HttpRequest $req, HttpResponse &$resp ){
        $res = curl_exec( $ch );
        if( $res === False ){
            throw new HttpError("HTTP request failed to execute.");
        }
        if( $this->getOpt(CURLOPT_RETURNTRANSFER, $req) === True ){
            $resp->response = $res;
        }
        $resp->status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE );
    }
    
    protected function runReq( HttpRequest $req ){
        $resp = new HttpResponse( $req );
        $ch = $this->curlInit( $req->getUrl() );
        try{
            $this->curlSetAllOpts( $ch, $req, $resp );
            $this->curlExec( $ch, $req, $resp );
        }finally{
            curl_close( $ch );
        }
        return $resp;
    }
    
    public function request( HttpRequest $req ){
        return $this->runReq( $req );
    }

    public function get( HttpRequest $req ){
        $req->setopt( CURLOPT_CUSTOMREQUEST, "GET" );
        return $this->runReq( $req );
    }

    public function post( HttpRequest $req ){
        $req->setopt( CURLOPT_CUSTOMREQUEST, "POST" );
        return $this->runReq( $req );
    }

    public function put( HttpRequest $req ){
        $req->setopt( CURLOPT_CUSTOMREQUEST, "PUT" );
        return $this->runReq( $req );
    }

    public function patch( HttpRequest $req ){
        $req->setopt( CURLOPT_CUSTOMREQUEST, "PATCH" );
        return $this->runReq( $req );
    }

    public function delete( HttpRequest $req ){
        $req->setopt( CURLOPT_CUSTOMREQUEST, "DELETE" );
        return $this->runReq( $req );
    }

}

?>