<?php

namespace Libs;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use Model\UserModel;

/**
 * Middleware de autorização por role
 * Verifica se o usuário tem permissão baseada em seu papel
 * 
 * @author Augusto <carloafernandes@gmail.com>
 */
class RoleMiddleware
{
    private $allowedRoles;
    
    /**
     * @param array $allowedRoles Roles permitidas (ex: ['admin', 'uploader'])
     */
    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }
    
    /**
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $userRole = $request->getAttribute('user_role');
        
        // Se não há roles específicas, permite qualquer usuário autenticado
        if (empty($this->allowedRoles)) {
            return $handler->handle($request);
        }
        
        // Verifica se o role do usuário está na lista permitida
        if (!in_array($userRole, $this->allowedRoles)) {
            $response = new Response();
            
            // Se for uma requisição AJAX, retorna JSON
            if ($this->isAjaxRequest($request)) {
                $response->getBody()->write(json_encode([
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar este recurso.'
                ]));
                return $response
                    ->withStatus(403)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            // Caso contrário, redireciona para página de acesso negado
            $response->getBody()->write('Acesso negado. Você não tem permissão para acessar esta página.');
            return $response->withStatus(403);
        }
        
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
