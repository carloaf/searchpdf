<?php

namespace Libs;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class SettingsMiddleware
{
    /**
     * SettingsMiddleware invokable class
     *
     * @param  ServerRequest  $request PSR-7 request
     * @param  RequestHandler $handler PSR-15 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        require '../config/settings.php';
        
        // Detecta automaticamente a URL base a partir da requisição
        $uri = $request->getUri();
        
        // Detecta scheme (https/http)
        // Prioriza X-Forwarded-Proto, depois verifica se a porta é 443
        $scheme = $request->getHeaderLine('X-Forwarded-Proto');
        if (!$scheme) {
            $port = $uri->getPort();
            $scheme = ($port === 443) ? 'https' : $uri->getScheme();
        }
        if (!$scheme) {
            $scheme = 'http';
        }
        
        // Detecta host
        // Prioriza X-Forwarded-Host, depois Host header, depois URI host
        $host = $request->getHeaderLine('X-Forwarded-Host');
        if (!$host) {
            $hostHeader = $request->getHeaderLine('Host');
            // Remove porta do Host header se existir
            $host = preg_replace('/:\d+$/', '', $hostHeader);
        }
        if (!$host) {
            $host = $uri->getHost() ?: 'localhost';
        }
        
        // Detecta porta (não mostra para 80/443)
        $port = $uri->getPort();
        $portStr = '';
        if ($port && $port !== 80 && $port !== 443) {
            $portStr = ':' . $port;
        }
        
        // Detecta prefixo de caminho (X-Forwarded-Prefix enviado pelo nginx)
        $prefix = $request->getHeaderLine('X-Forwarded-Prefix') ?: '';
        
        // Monta a URL base
        $settings['url_base'] = $scheme . '://' . $host . $portStr . rtrim($prefix, '/');
        
        $request = $request->withAttribute('settings', $settings);
        $response = $handler->handle($request);
        return $response;
    }
}