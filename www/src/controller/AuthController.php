<?php

namespace Controller;

use Model\UserModel;

/**
 * Controller para autenticação de usuários
 * 
 * @author Augusto <carloafernandes@gmail.com>
 */
class AuthController
{
    /**
     * Exibe página de login
     */
    public static function showLogin($request, $response)
    {
        $view = \Slim\Views\Twig::fromRequest($request);
        
        return $view->render($response, 'login.twig', [
            'error' => $_SESSION['login_error'] ?? null,
            'message' => $_SESSION['login_message'] ?? null
        ]);
    }
    
    /**
     * Processa o login
     */
    public static function processLogin($request, $response)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $post = $request->getParsedBody();
        $username = $post['username'] ?? '';
        $password = $post['password'] ?? '';
        
        // Verifica se é uma requisição AJAX
        $isAjax = $request->hasHeader('X-Requested-With') && 
                  $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
        
        // Validação básica
        if (empty($username) || empty($password)) {
            if ($isAjax) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Usuário e senha são obrigatórios.'
                ]));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            $_SESSION['login_error'] = 'Usuário e senha são obrigatórios.';
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/login');
        }
        
        // Tenta autenticar
        $user = UserModel::authenticate($username, $password);
        
        if (!$user) {
            if ($isAjax) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Usuário ou senha incorretos.'
                ]));
                return $response
                    ->withStatus(401)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            $_SESSION['login_error'] = 'Usuário ou senha incorretos.';
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/login');
        }
        
        // Armazena informações na sessão
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['logged_in_at'] = time();
        
        // Limpa erros
        unset($_SESSION['login_error']);
        
        // Se for AJAX, retorna JSON de sucesso
        if ($isAjax) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'redirect' => '/upload'
            ]));
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        }
        
        // Redireciona para a página inicial
        $redirect = $_SESSION['redirect_after_login'] ?? '/upload';
        unset($_SESSION['redirect_after_login']);
        
        return $response
            ->withStatus(302)
            ->withHeader('Location', $redirect);
    }
    
    /**
     * Faz logout
     */
    public static function logout($request, $response)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Destroi a sessão
        session_unset();
        session_destroy();
        
        return $response
            ->withStatus(302)
            ->withHeader('Location', '/login');
    }
    
    /**
     * API endpoint para verificar status de autenticação
     */
    public static function checkAuth($request, $response)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $authenticated = isset($_SESSION['user_id']);
        
        $data = [
            'authenticated' => $authenticated,
            'user' => $authenticated ? [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['user_role'],
                'full_name' => $_SESSION['full_name']
            ] : null
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }
}
