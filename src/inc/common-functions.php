<?php 

function hspc( $value ){ return htmlspecialchars($value); }

function dieError( $msg ){
    http_response_code(500);
    echo "Error: $msg";
    die();
}

function dieException( $e ){
    http_response_code(500);
    echo get_class($e) .": ". $e->getMessage();
    die();
}

function parseIntGetParam( $key ){
    if( ! is_numeric($_GET[$key]) ){
        dieError("Parameter '$key' must be integer.");
    }
    return intval($_GET[$key]);
}

?>