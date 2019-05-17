<?php
declare(strict_types=1);


namespace Azonmedia\SwooleToPsr;

use Guzaba2\Http\Body\Stream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class SwooleToPsr
 * @package SwooleToPsr
 *
 * Contains code from Slim Framework
 */
class SwooleToPsr
{

    /**
     * @param \Swoole\Http\Request $SwooleRequest
     * @param RequestInterface|null $PsrRequest
     * @return RequestInterface
     */
    public static function ConvertRequest(\Swoole\Http\Request $SwooleRequest, ?RequestInterface $PsrRequest = NULL) : RequestInterface
    {
        //print_r($SwooleRequest);
//        Swoole\Http\Request Object
//    (
//        [fd] => 1
//    [header] => Array
//    (
//        [host] => localhost:8081
//            [user-agent] => Mozilla/5.0 (X11; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0
//            [accept] => text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
//            [accept-language] => en-US,en;q=0.5
//            [accept-encoding] => gzip, deflate
//            [connection] => keep-alive
//            [upgrade-insecure-requests] => 1
//            [cache-control] => max-age=0
//        )
//
//    [server] => Array
//        (
//            [request_method] => GET
//            [request_uri] => /
//            [path_info] => /
//            [request_time] => 1556632933
//            [request_time_float] => 1556632933.936
//            [server_port] => 8081
//            [remote_port] => 52250
//            [remote_addr] => 127.0.0.1
//            [master_time] => 1556632933
//            [server_protocol] => HTTP/1.1
//        )
//
//    [request] =>
//    [cookie] =>
//    [get] =>
//    [files] =>
//    [post] =>
//    [tmpfiles] =>
//)О
        //print_r(get_class_methods($SwooleRequest));
//        Array
//        (
//            [0] => rawcontent
//            [1] => getData
//    [2] => __destruct
//)

        $headers = $SwooleRequest->header;
//headers may not contain host
//        Array
//        (
//            [content-type] => text/xml
//                [content-length] => 259
//    [content-transfer-encoding] => text
//    [connection] => close
//)

        $method = $SwooleRequest->server['request_method'];
        $host = $SwooleRequest->header['host'] ?? 'localhost';//temporary fix
        $uri_string = 'http://'.$host.$SwooleRequest->server['request_uri'];
        $uri_class = get_class($PsrRequest->getUri());
        $Body = new Stream();
        $Body->write($SwooleRequest->rawContent());
        $PsrRequest = $PsrRequest
            ->withUri(self::CreateUri($uri_class, $uri_string))
            ->withMethod($method)
            //->withHeaders($headers)
            ->withQueryParams($SwooleRequest->get ? : [])->withBody($Body);//todo ... complete this... take into account post

        return $PsrRequest;

    }

    public static function ConvertResponse(\Swoole\Http\Request $SwooleResponse, ?ResponseInterface $PsrResponse = NULL) : ResponseInterface
    {

    }

    /**
     * Contains code from Slim Framework
     * @param string $uri_class
     * @param string $uri
     * @return UriInterface
     */
    public static function CreateUri(string $uri_class, string $uri) : UriInterface
    {
//        if (!is_string($uri) && !method_exists($uri, '__toString')) {
//            throw new \InvalidArgumentException('Uri must be a string');
//        }

        $parts = parse_url($uri);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : '';
        $user = isset($parts['user']) ? $parts['user'] : '';
        $pass = isset($parts['pass']) ? $parts['pass'] : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? $parts['port'] : null;
        $path = isset($parts['path']) ? $parts['path'] : '';
        $query = isset($parts['query']) ? $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? $parts['fragment'] : '';
        return new $uri_class($scheme, $host, $port, $path, $query, $fragment, $user, $pass);
    }
}