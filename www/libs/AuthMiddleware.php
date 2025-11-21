<?php

namespace Libs;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

/**
 * Middleware de autenticação
 * Verifica se o usuário está autenticado antes de acessar rotas protegidas
 * 
 * @author Augusto <carloafernandes@gmail.com>
 */
class AuthMiddleware
{
    /**
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Inicia a sessão se ainda não foi iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verifica se o usuário está autenticado
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            $response = new Response();
            
            // Se for uma requisição AJAX, retorna JSON
            if ($this->isAjaxRequest($request)) {
                $response->getBody()->write(json_encode([
                    'error' => 'Não autenticado',
                    'message' => 'Você precisa fazer login para acessar este recurso.'
                ]));
                return $response
                    ->withStatus(401)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            // Caso contrário, redireciona para a página de login
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/login');
        }
        
        // Adiciona informações do usuário na request
        $request = $request->withAttribute('user_id', $_SESSION['user_id']);
        $request = $request->withAttribute('user_role', $_SESSION['user_role']);
        $request = $request->withAttribute('username', $_SESSION['username'] ?? '');
        
        // Continua o processamento
        return $handler->handle($request);
    }
    
    /**
     * Verifica se é uma requisição AJAX
     * 
     * @param Request $request
     * @return bool
     */
    private function isAjaxRequest(Request $request): bool
    {
        return $request->hasHeader('X-Requested-With') &&
               $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }
}
