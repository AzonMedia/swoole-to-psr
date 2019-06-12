<?php
declare(strict_types=1);


namespace Azonmedia\SwooleToPsr;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Body\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

/**
 * Class SwooleToPsr
 * @package SwooleToPsr
 *
 * Contains code from Slim Framework
 */
abstract class SwooleToPsr
{

    /**
     * @param SwooleRequest $SwooleRequest
     * @param ServerRequestInterface|null $PsrRequest
     * @return ServerRequestInterface
     * @throws RunTimeException
     */
    public static function ConvertRequest(SwooleRequest $SwooleRequest, ServerRequestInterface $PsrRequest): ServerRequestInterface
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
        // TODO check if url generation is proper
        $host = $SwooleRequest->header['host'] ?? 'localhost';//temporary fix
        $uri_string = 'http://' . $host . $SwooleRequest->server['request_uri'];
        $uri_class = get_class($PsrRequest->getUri());
        $uri = self::CreateUri($uri_class, $uri_string);
        $Body = new Stream();

        $Body->write((string)$SwooleRequest->rawContent());

        $PsrRequest = $PsrRequest
            ->withUri($uri)
            ->withMethod($method)
            ->withCookieParams($SwooleRequest->cookie ?? [])
            ->withQueryParams($SwooleRequest->get ?? [])
            ->withParsedBody($SwooleRequest->post ?? [])
            ->withUploadedFiles($SwooleRequest->files ?? [])
            ->withBody($Body);

        foreach ($headers as $key => $value) {
            $PsrRequest = $PsrRequest->withHeader($key, $value);
        }

        return $PsrRequest;
    }

    /**
     * @param SwooleResponse $SwooleResponse
     * @param ResponseInterface $PsrResponse
     * @return ResponseInterface
     */
    public static function ConvertResponse(SwooleResponse $SwooleResponse, ResponseInterface $PsrResponse): ResponseInterface
    {
        // SwooleResponse doesn't have any documented getters
        return $PsrResponse;
    }

    /**
     * Contains code from Slim Framework
     * @param string $uri_class
     * @param string $uri
     * @return UriInterface
     */
    public static function CreateUri(string $uri_class, string $uri): UriInterface
    {
        $parts = parse_url($uri);
        $scheme = $parts['scheme'] ?? '';
        $user = $parts['user'] ?? '';
        $pass = $parts['pass'] ?? '';
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? null;
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';
        $fragment = $parts['fragment'] ?? '';
        return new $uri_class($scheme, $host, $port, $path, $query, $fragment, $user, $pass);
    }
}