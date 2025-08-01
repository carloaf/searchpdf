<?php

namespace Controller;

session_start();

use Slim\Views\Twig;
use Model\FilesModel;

/**
 * Description of FilesController
 *
 * @author Augusto <carloafernandes@gmail.com>
 */
class AjaxController
{

  /**
   * Faz a busca por um texto dentro de um arquivo
   */
  // public static function searchFile($request, $response)
  // {
  //   // Obtém parâmetros do método post
  //   $post               = $request->getParsedBody();
  //   $path_file          = $post['path_file'];
  //   $search             = $post['search'];
  //   $filename           = $post['filename'];
  //   $index              = $post['index'];
  //   $ignore_case        = $post['ignore_case'];
  //   $exact_match        = $post['exact_match'];

  //   // Obtém parâmetros de configuração settings
  //   $settings           = $request->getAttribute('settings');
  //   $directory_files    = $settings['directory_files'];
  //   $total_words        = $settings['total_words'];
  //   $button_color       = 'btn-' . $settings['tpl']['template_color'];
  //   $ext                = pathinfo($filename, PATHINFO_EXTENSION);        

  //   // Se o arquivo for PDF
  //   if ($ext === 'pdf') {
  //     $ignore_case = $post['ignore_case'];
  //     $json = FilesModel::searchPDF(
  //       $directory_files, 
  //       $path_file, 
  //       $search, 
  //       $total_words, 
  //       $index, 
  //       $ignore_case, 
  //       $exact_match,
  //       $button_color
  //     );     
  //   }

  //   $response->getBody()->write($json);
  //   return $response->withStatus(200);               
  // }

  public static function searchFile($request, $response)
  {
      $post = $request->getParsedBody();
      $search = $post['search'];
      $ignore_case = $post['ignore_case'];
      $exact_match = $post['exact_match'];
      $selected_files = $post['path_files'] ?? [];

      $settings = $request->getAttribute('settings'); // A variável já está aqui
      $total_words = $settings['total_words'];

      $json = \Model\FilesModel::searchPDFFromIndex(
          $search,
          $selected_files,
          $total_words,
          $ignore_case,
          $exact_match,
          $settings // <<-- PARÂMETRO FALTANTE ADICIONADO
      );

      $response->getBody()->write($json);
      return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
  }

  /**
   * Cria uma lista só com os "arquivos" (não diretórios) existentes em uma coleção de paths
   */
  public static function selectedFiles($request, $response)
  {        
    $settings   = $request->getAttribute('settings');
    $post       = $request->getParsedBody();
    $path_files = $post['path_files'];
    $filenames  = FilesModel::getJustFiles($path_files, $settings['allowed_extensions']);
    $json       = json_encode($filenames, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($json);
    return $response->withStatus(200);
  }

}