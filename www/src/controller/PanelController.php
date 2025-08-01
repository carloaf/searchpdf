<?php

namespace Controller;

use Slim\Views\Twig;
use Model\FilesModel;

/**
 * PanelController class
 * Controlador do painel principal da aplicação (versão modernizada)
 */
class PanelController
{
    public function __invoke($request, $response)
    {
        // Obtém as configurações
        $settings = $request->getAttribute('settings'); 

        // Chama o novo método para gerar o HTML da árvore de arquivos e a contagem
        $treeResult = FilesModel::buildFileTreeHtml(
            $settings['directory_files'], 
            $settings['allowed_extensions']
        );
        
        // Extrai o HTML do resultado
        $treeHtml = $treeResult['html'];

        // Renderiza o template, passando o HTML bruto e outras variáveis necessárias
        $view = Twig::fromRequest($request);
        return $view->render($response, 'panel.twig', [
            'treeHtml' => $treeHtml,
            'url_base' => $settings['url_base'],
            'lang' => $settings['lang'],
            'favicon' => $settings['favicon'] ?? 'images/favicon.ico',
            'jumbotron_color' => $settings['tpl']['jumbotron_color'] ?? '#f7f7f7',
            'background_color' => $settings['tpl']['background_color'] ?? '#449D44',
            'help_color' => $settings['tpl']['help_color'] ?? '#def9de',
        ]);
    }
}