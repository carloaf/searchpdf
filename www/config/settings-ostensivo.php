<?php
/***************************************************************************
  _____                            _              _         _ 
  \_   \_ __ ___  _ __   ___  _ __| |_ __ _ _ __ | |_ ___  / \
   / /\/ '_ ` _ \| '_ \ / _ \| '__| __/ _` | '_ \| __/ _ \/  /
/\/ /_ | | | | | | |_) | (_) | |  | || (_| | | | | ||  __/\_/ 
\____/ |_| |_| |_| .__/ \___/|_|   \__\__,_|_| |_|\__\___\/   
                 |_|                                          

ATENÇÃO: MANIPULE ESTE SCRIPT COM ENCODE UTF-8 (NÃO UTILIZE ISO-8856-1)

***************************************************************************/



/**
 * Arquivo de configuração (exemplo do arquivo settings.php) 
 * 
 * @version 1.5
 * @author Augusto <carloafernandes@gmail.com>
 * @copyright Public domain
 * @license MIT
 */



/* ************************************************************************* */
/*                                                                           */
/* CONFIGURAÇÃO                                                              */ 
/*                                                                           */
/* ************************************************************************* */



/**
 * URL base onde está a aplicação, acessível pelo navegador
 * 
 * @param string
 * @example https://localhost:8080/pesquisadocof/public
 * @uses Não colocar barra / no final do caminho, porém, no navegador obrigatoriamente use "/" ao final
 */ 
$settings['url_base'] = 'https://arquivo2.gabcmt.eb.mil.br/ostensivo/public';



/**
 * Caminho absoluto do diretório onde estão os arquivos a serem pesquisados (treeview)
 * 
 * @param string
 * @example /var/www/pesquisadocof/public/files
 * @uses Obrigatoriamente os arquivos devem ser colocados no diretório public/files
 */ 
$settings['directory_files'] = '/var/www/docof2/ostensivo/public/files';



/**
 * Caminho relativo do diretório de publicação, como /var/www ou /var/www/html 
 * 
 * @param string
 * @example /pesquisadocof/public (de [http://localhost:8888]/pesquisadocof/public)
 * @uses Não colocar barra / no final do caminho
 */
$settings['path_relative'] = '/ostensivo/public';



/**
 * Extensões permitidas que serão exibidas na árvore (treeview)
 * 
 * @param string
 * @uses Não utilizar ponto na extensão (ERRADO: .pdf | CORRETO: pdf)
 * 
 */
$settings['allowed_extensions'] = ['pdf'];



/**
 * Quantidade máxima de palavras a serem exibidas no resultado preliminar da busca (junto com o texto encontrado)
 * 
 * @param int
 */
$settings['total_words'] = 45;



/**
 * Quantidade máxima de palavras digitadas no campo de busca
 * 
 * @param int
 */ 
$settings['total_search_length'] = 100;



/**
 * Define o default do checkbox "Pesquisar pelo conteúdo do arquivo"
 * Se falso, automaticamente habilita "Pesquisar em nomes de arquivos e pastas"
 * 
 * @param boolean
 * @uses true => checkbox marcado | false => checkbox desmarcado
 */
$settings['check_file_is_checked'] = true;



/**
 * Define em que nível (collapsed/expanded) a árvore de diretórios/arquivos será exibida
 * 
 * @param int
 * @uses Deve ser um número, inicia em 1, tipicamente é 1 ou 2 
 */
$settings['collapsed_level'] = 1;



/**
 * Escapar da pesquisas nome de arquivos ou pastas com um texto específico definido na REGEX
 * 
 * @param string
 * @uses Deve ser um pattern de expressão regular, como '/todos|semestre/i' (não esqueça os delimitadores)
 */
$settings['scape_regex'] = '/regex-here/i';



/**
 * Define o tipo de ordenação baseada no nome dos arquivos
 * 
 * @param string
 * @uses Deve ser 'ASC' => crescemte ou 'DESC' => decrescente, '' => nenhum
 */
$settings['sort_level1'] = 'DESC';
$settings['sort_level2'] = 'DESC';
$settings['sort_level3'] = 'DESC';



/**
 * Define o default do checkbox "Agrupar resultados"
 * 
 * @param boolean
 * @uses Deve ser true => marca o checkbox ou false => não marca o checkbox
 */
$settings['group_results'] = true;



/**
 * Define o modo como as opções de busca serão exibidas
 * 
 * @param boolean
 * @uses Deve ser 'dropdown' => exibie a configuração em dropdown ou 'panel' => exibe a configuração em painel
 */
$settings['search_config'] = 'panel';



/**
 * Icone (favicon) exibido na aba do navegador
 */ 
$settings['favicon'] = 'images/favicon.ico';



/**
 * Informações de ajuda
 */ 
$settings['help'] = '    
  <h2>Orientações</h2><br />
  <p>
    <strong>Escolha o tipo de pesquisa</strong> a ser realizada, isto é, 
    se deseja buscar na estrutura de nomes de pastas e arquivos  
    ou no conteúdo do arquivo.</p>
  </p>
  <p>
    <strong>Se a busca for na estrutura</strong>, marque a opção "Pesquisar em nomes de arquivos e pastas",
    digite o texto a ser pesquisado e clique no botão Buscar. 
    Neste caso, note que o texto encontrado ficará em vermelho. 
    É possível também agrupar o resultado da busca e expandir os itens selecionados.
  </p>
  <p>
    <strong>Se a busca for no conteúdo do arquivo</strong>, marque a opção "Pesquisar no conteúdo do arquivo".
    Neste caso, selecione também uma ou mais pastas ou arquivos onde deve ser feita a pesquisa.
    Note que ao marcar uma pasta, automaticamente todas as subpastas e arquivos são selecionados.
    Por fim, digite o texto a ser pesquisado e clique no botão Buscar. 
  </p>
'; 



/* ************************************************************************* */
/*                                                                           */
/* TEXTOS                                                                    */ 
/*                                                                           */
/* ************************************************************************* */



// Título principal que aparece no topo da página entre tags <h2/>
$lang['main_title'] = 'Documentos Ostensivos do Gabinete do Comandante do Exército';

// Título que aparece na aba do navegador (tag HTML title) junto ao favicon
$lang['page_title'] = 'Documentos Ostensivos do Gabinete do Comandante do Exército';

// Placeholder que aparece dentro da caixa de texto da busca
$lang['search_placeholder'] = '';

// Label do radiobox para "pesquisar em nomes de arquivos e pastas"
$lang['check_structure'] = 'Pesquisar em nomes de arquivos e pastas';

// Label do radiobox para "pesquisar no conteúdo do arquivo"
$lang['check_file'] = 'Pesquisar no conteúdo do arquivo';

// Label do checkbox para diferenciar caixa alta e baixa (maiúscula / minúscula)
$lang['check_type'] = 'Diferenciar maiúsculas e minúsculas';

// Label do checkbox para pesquisar palavra inteira 
$lang['check_word'] = 'Pesquisar palavra inteira';

// Label do checkbox para agrupar o resultado da pesquisa
$lang['check_group_search'] = 'Agrupar resultados';

// Label do botão de busca
$lang['button_search'] = 'Buscar';

// Label do botão de cancelar
$lang['button_cancel'] = 'Cancelar';

// Label do botão de limpar busca (clean search)
$lang['button_clean'] = 'Limpar';

// Label do botão de abrir o arquivo pdf
$lang['button_open'] = 'Abrir';

// Label do botão de voltar
$lang['button_back'] = 'Voltar';



/* ************************************************************************* */
/*                                                                           */
/* TEMPLATES                                                                 */ 
/*                                                                           */
/* ************************************************************************* */



/**
 * Cor de botões e tooltips, com base no Bootstrap v3.3.7
 * 
 * @example Opções: 
 * default
 * success  (default)
 * primary
 * danger
 * warning
 * info
 * link
 */
$tpl['template_color']   = 'success';



/**
 * Cor do painel no topo (jumbotron), com base no Bootstrap v3.3.7
 * 
 * @example Opções: 
 * default
 * success  (default)
 * primary
 * danger
 * warning
 * info
 * link
 */
$tpl['jumbotron_color']  = 'link';



/**
 * Cor da caixa de diálogo de ajuda (dropdown), com base no Bootstrap v3.3.7
 * 
 * @example Opções: 
 * default
 * success  (default)
 * primary
 * danger
 * warning
 * info
 * link
 */
$tpl['help_color'] = 'success';



/**
 * Define o tipo de ícone que expande os subdiretórios (expanded)
 * 
 * @example Opções:
 * glyphicon glyphicon-hand-right
 * glyphicon glyphicon-folder-close 
 * glyphicon glyphicon-plus 
 * glyphicon glyphicon-chevron-right (default)
 */
$tpl['expandIcon'] = 'glyphicon glyphicon-chevron-right';



/** 
 * Define o tipo de ícone que recolhe/oculta um subdiretório (collapsed)
 * 
 * @example Opções:
 * glyphicon glyphicon-hand-down
 * glyphicon glyphicon-folder-open 
 * glyphicon glyphicon-minus
 * glyphicon glyphicon-chevron-down (default)
 */ 
$tpl['collapseIcon'] = 'glyphicon glyphicon-chevron-down';

  

/**
 * Retorna $lang e $tpl nas configurações como parte de $settings  
 */
$settings['lang'] = $lang;
$settings['tpl']  = $tpl;