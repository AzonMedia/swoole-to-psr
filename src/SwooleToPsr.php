<?php
declare(strict_types=1);


namespace Azonmedia\SwooleToPsr;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class SwooleToPsr
 * @package SwooleToPsr
 */
class SwooleToPsr
{

    /**
     * @param \Swoole\Http\Request $swoole_request
     * @param RequestInterface|null $psr_request
     * @return RequestInterface
     */
    public static function ConvertRequest(\Swoole\Http\Request $swoole_request, ?RequestInterface $psr_request = NULL) : RequestInterface
    {

    }

    public static function ConvertResponse(\Swoole\Http\Request $swoole_response, ?ResponseInterface $psr_response = NULL) : ResponseInterface
    {

    }
}