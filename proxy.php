<?php

define('CONFIG_FILE', 'simple-php-proxy_config.php');

/*
    Installation note:

    Copy example.simple-php-proxy_config.php to simple-php-proxy_config.php
    in the same folder as this script or to one or two levels above it.

    Optionally you can change the variables right here and not use the config file.

    For an explanation of the config variables see example.simple-php-proxy_config.php
*/

require_once 'logger.php';

$log = new KLogger ( "proxy.log" , KLogger::DEBUG );
$log->LogInfo("Started proxy up");
 
$dest_host = "127.0.0.1:9200";

$proxy_base_url = '/';

$proxied_headers = array('Set-Cookie', 'Content-Type', 'Cookie', 'Location');

$rest_json = file_get_contents("php://input");
$log->LogDebug("JSON In: $rest_json");

$json_rest_decode = json_decode($rest_json, true);
$_POST = $json_rest_decode;


// Variables you specify in the config file overwrite variables set above.
foreach( array('./', '../', '../../') as $path_rel )
{
    if( file_exists(dirname(__file__)."/$path_rel" . CONFIG_FILE) )
    { 
        include(dirname(__file__)."/$path_rel" . CONFIG_FILE);
        break;
    }
}

//canonical trailing slash
$proxy_base_url_canonical = rtrim($proxy_base_url, '/ ') . '/';

//check if valid
if( strpos($_SERVER['REQUEST_URI'], $proxy_base_url) !== 0 )
{
    die("The config paramter \$prox_base_url \"$proxy_base_url\" that you specified 
        does not match the beginning of the request URI: ".
        $_SERVER['REQUEST_URI']);
}

//remove base_url and optional proxy.php from request_uri
$proxy_request_url = substr($_SERVER['REQUEST_URI'], strlen($proxy_base_url_canonical));

if( strpos($proxy_request_url, 'proxy.php') === 0 )
{
    $proxy_request_url = ltrim(substr($proxy_request_url, strlen('proxy.php')), '/');
}

//final proxied request url
$proxy_request_url = "http://" . rtrim($dest_host, '/ ') . '/' . $proxy_request_url;
$log->LogDebug("Proxy Request URL: $proxy_request_url");


/* Init CURL */
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $proxy_request_url);
curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);

/*
$ci = curl_init();
curl_setopt($ch, CURLOPT_URL, $proxy_request_url);
curl_setopt($ch, CURLOPT_TIMEOUT, 200);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 0);
*/

/* Collect and pass client request headers */
if(isset($_SERVER['HTTP_COOKIE']))     
{ 
    $hdrs[]="Cookie: " . $_SERVER['HTTP_COOKIE'];        
}

if(isset($_SERVER['HTTP_USER_AGENT'])) 
{ 
    $hdrs[]="User-Agent: " . $_SERVER['HTTP_USER_AGENT']; 
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);

/* pass POST params */
if( sizeof($_POST) > 0 )
{ 

/*	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST'); 
	$json_post = json_encode($_POST);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_post); 
	$log->LogDebug("JSON_POST: $json_post");*/

	curl_setopt($ch, CURLOPT_POSTFIELDS, $rest_json); 
	$log->LogDebug("JSON_POST: $rest_json");

}

$res = curl_exec($ch);
$log->LogDebug("Response: $res");

curl_close($ch);

/* parse response */
list($headers, $body) = explode("\r\n\r\n", $res, 2);

$headers = explode("\r\n", $headers);
$hs = array();

foreach($headers as $header)
{
    if( false !== strpos($header, ':') )
    {
        list($h, $v) = explode(':', $header);
        $hs[$h][] = $v;
    }
    else 
    {
        $header1  = $header;
    }
}

/* set headers */
list($proto, $code, $text) = explode(' ', $header1);
header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code . ' ' . $text);

foreach($proxied_headers as $hname)
{
    if( isset($hs[$hname]) )
    {
        foreach( $hs[$hname] as $v )
        {
            if( $hname === 'Set-Cookie' ) 
            {
                header($hname.": " . $v, false);
            }
            else
            {
                header($hname.": " . $v);
            }
        }
    }
}

die($body);
