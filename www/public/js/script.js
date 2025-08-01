// Guarda de inicialização para prevenir execução múltipla do script
if (!window.searchAppInitialized) {
    window.searchAppInitialized = true;

    $(document).ready(function() {

        // ===================================================================
        // 1. FUNÇÕES DE INICIALIZAÇÃO (Estatísticas e Gráficos)
        // ===================================================================

        // Constante com o maior número atual - definido manualmente
        const MAIOR_NUMERO_ATUAL = 135;
        
        // Função para obter o maior número O_XXX_ dos arquivos do ano atual
        function getMaiorNumeroDocumentoAnoAtual() {
            // Inicializa uma variável para armazenar o maior número encontrado
            let maiorNumero = 0;
            
            // Obtém o ano atual
            const anoAtual = new Date().getFullYear();
            
            // Realiza uma requisição AJAX para obter a lista de arquivos
            return new Promise((resolve) => {
                $.ajax({
                    url: 'files/list/' + anoAtual, // Endpoint para listar arquivos do ano atual
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (Array.isArray(data)) {
                            // Percorre todos os arquivos retornados
                            data.forEach(file => {
                                // Extrai só o nome do arquivo, sem o caminho
                                const fileName = file.split('/').pop();
                                console.log("Analisando arquivo: " + fileName);
                                
                                // Procura o padrão O_XXX com expressão regular
                                const match = fileName.match(/O_(\d{3})/);
                                if (match && match[1]) {
                                    // Converte para número
                                    const numero = parseInt(match[1], 10);
                                    console.log("  - Encontrado número: " + numero);
                                    
                                    // Atualiza o maior número se necessário
                                    if (numero > maiorNumero) {
                                        maiorNumero = numero;
                                        console.log("  - Novo maior número: " + numero);
                                    }
                                }
                            });
                        }
                        console.log("Maior número encontrado: " + maiorNumero);
                        resolve(maiorNumero);
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro ao buscar lista de arquivos do ano atual: ', error);
                        console.error('Status: ', status);
                        console.error('Response: ', xhr.responseText);
                        resolve(0); // Em caso de erro, retorna 0
                    }
                });
            });
        }

        // Função para carregar as estatísticas de contagem via AJAX
        function loadStats() {
            // Define o maior número diretamente como fallback
            window.maiorNumeroDocumento = MAIOR_NUMERO_ATUAL;
            
            // Carrega as estatísticas normais
            $.ajax({
                url: 'stats',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    // Atualiza as estatísticas gerais
                    $('#stats-total').text(data.total || 0);
                    $('#stats-week').text(data.week || 0);
                    
                    // Obtém o maior número da resposta da API, ou usa o valor padrão
                    const maiorNumero = data.max_number > 0 ? data.max_number : MAIOR_NUMERO_ATUAL;
                    window.maiorNumeroDocumento = maiorNumero;
                    
                    // Extrai a data do arquivo com maior número
                    let dataFormatada = '';
                    if (data.max_date) {
                        const partesData = data.max_date.split('-');
                        if (partesData.length === 3) {
                            dataFormatada = `${partesData[2]}/${partesData[1]}/${partesData[0]}`;
                        }
                    }
                    
                    // Se não tiver data, usa a data atual
                    if (!dataFormatada) {
                        const hoje = new Date();
                        const dia = String(hoje.getDate()).padStart(2, '0');
                        const mes = String(hoje.getMonth() + 1).padStart(2, '0');
                        const ano = hoje.getFullYear();
                        dataFormatada = `${dia}/${mes}/${ano}`;
                    }
                    
                    // Define o último arquivo indexado com o maior número do ano atual
                    $('#stats-last-file')
                        .text(`BI ${maiorNumero} - ${dataFormatada}`)
                        .addClass('has-file')
                        .attr('title', data.max_file || `Boletim Interno ${maiorNumero}`);
                    
                    console.log(`Maior número de documento do ano atual: ${maiorNumero}`);
                    console.log(`Data do arquivo: ${dataFormatada}`);
                    
                    // Carrega os gráficos adicionais após as estatísticas básicas
                    loadTopSearchesChart();
                    loadDocumentDistributionChart();
                },
                error: function() {
                    $('#stats-total').text('-');
                    $('#stats-week').text('-');
                    
                    // Mesmo em caso de erro, exibimos um valor padrão
                    $('#stats-last-file')
                        .text(`BI ${MAIOR_NUMERO_ATUAL} - 01/08/2025`)
                        .addClass('has-file')
                        .attr('title', 'Boletim Interno');
                    console.error('Falha ao carregar estatísticas.');
                }
            });
        }
        
        // Função para formatar o nome do arquivo, extraindo e formatando a data
        function formatFileName(fileName) {
            try {
                // Verifica se o nome do arquivo possui o padrão de data esperado (YYYY-MM-DD)
                if (fileName.length >= 10 && /^\d{4}-\d{2}-\d{2}/.test(fileName)) {
                    // Extrai a data (10 primeiros caracteres) e o restante do nome
                    const dateStr = fileName.substring(0, 10);
                    let remainingName = fileName.substring(11); // Pega o texto após a data
                    
                    // Extrai o número de identificação no formato O_XXX (com ou sem underscore final)
                    let numeroDocumento = '';
                    // Busca o padrão O_ seguido por exatamente 3 dígitos, com ou sem underscore depois
                    const matchO = remainingName.match(/O_(\d{3})(_|$)/);
                    
                    if (matchO && matchO[1]) {
                        // Extrai apenas os 3 dígitos
                        numeroDocumento = matchO[1];
                        
                        // Remove tudo até depois do número se houver um underscore após
                        if (matchO[2] === '_') {
                            const fullMatchPos = remainingName.indexOf(matchO[0]);
                            const afterMatchPos = fullMatchPos + matchO[0].length;
                            remainingName = remainingName.substring(afterMatchPos);
                        }
                    }
                    
                    // Remove a extensão .pdf se presente
                    if (remainingName.toLowerCase().endsWith('.pdf')) {
                        remainingName = remainingName.substring(0, remainingName.length - 4);
                    }
                    
                    // Formata a data para o formato brasileiro (DD/MM/YYYY)
                    const parts = dateStr.split('-');
                    if (parts.length === 3) {
                        const formattedDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
                        
                        // Substitui underscores por espaços e capitaliza para melhor legibilidade
                        remainingName = remainingName.replace(/_/g, ' ').trim();
                        
                        // Define BI como tipo de documento padrão, mas verifica se é BA no caminho
                        const tipoDocumento = fileName.includes('/BA/') ? 'BA' : 'BI';
                        
                        // Usa o número encontrado no arquivo ou o maior número como fallback
                        let numeroFinal = numeroDocumento || window.maiorNumeroDocumento.toString();
                        
                        // Para debug
                        console.log(`Formatando arquivo: ${fileName}`);
                        console.log(`Número extraído: ${numeroDocumento}`);
                        console.log(`Número final: ${numeroFinal}`);
                        console.log(`Data formatada: ${formattedDate}`);
                        
                        // Capitaliza primeira letra de cada palavra para melhor legibilidade
                        remainingName = remainingName.split(' ')
                            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                            .join(' ');
                        
                        // Limita o tamanho do nome para visualização
                        if (remainingName.length > 15) {
                            remainingName = remainingName.substring(0, 15) + '...';
                        }
                        
                        // Monta o resultado final no formato "BI 127 - 11/07/2025"
                        // Usa o maior número encontrado ou o número do arquivo atual
                        let resultado = `${tipoDocumento} ${numeroFinal || ''}`;
                        resultado += ` - ${formattedDate}`;
                        
                        return resultado;
                    }
                }
                
                // Fallback para arquivo sem padrão de data
                return fileName.length > 25 ? fileName.substring(0, 22) + '...' : fileName;
            } catch (e) {
                console.error('Erro ao formatar nome do arquivo:', e);
                return fileName; // Em caso de erro, retorna o nome original
            }
        }

        // Função para carregar e renderizar o gráfico de barras de buscas diárias
        function loadDailyChart() {
            $.ajax({
                url: 'stats/daily',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    const canvasElement = document.getElementById('daily-searches-chart');
                    if (!canvasElement || !data || !data.labels || !data.data) return;

                    // Usa o método oficial do Chart.js para verificar e destruir um gráfico existente
                    const existingChart = Chart.getChart(canvasElement);
                    if (existingChart) {
                        existingChart.destroy();
                    }

                    // Cria o novo gráfico
                    new Chart(canvasElement, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Nº de Buscas',
                                data: data.data,
                                backgroundColor: 'rgba(46, 125, 50, 0.7)',
                                borderColor: 'rgba(46, 125, 50, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1,
                                        precision: 0
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(46, 125, 50, 0.9)',
                                    titleFont: {
                                        size: 14
                                    },
                                    bodyFont: {
                                        size: 13
                                    },
                                    callbacks: {
                                        label: function(context) {
                                            return context.parsed.y + ' busca(s)';
                                        }
                                    }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                },
                error: function() {
                    $('#chart-placeholder .card-body').html('<p class="text-muted text-center py-5">Não foi possível carregar os dados do gráfico.</p>');
                }
            });
        }
        
        // Função para carregar e renderizar o gráfico de termos mais pesquisados
        function loadTermsChart() {
            $.ajax({
                url: 'stats/terms',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    const canvasElement = document.getElementById('top-terms-chart');
                    if (!canvasElement || !data || !data.labels || !data.data) return;

                    // Filtra nomes no lado do cliente (segundo nível de proteção)
                    const commonNames = ['CARLOS', 'AUGUSTO', 'JOSE', 'JOAO', 'ANTONIO', 'MARIA', 'ANA'];
                    
                    // Cria arrays filtrados
                    let filteredLabels = [];
                    let filteredData = [];
                    let filteredColors = [];
                    
                    // Filtra termos que são nomes ou contêm nomes específicos
                    for (let i = 0; i < data.labels.length; i++) {
                        const term = data.labels[i].toUpperCase();
                        let isName = false;
                        
                        // Verifica se o termo contém algum nome comum
                        for (const name of commonNames) {
                            if (term.includes(name)) {
                                isName = true;
                                break;
                            }
                        }
                        
                        if (!isName) {
                            filteredLabels.push(data.labels[i]);
                            filteredData.push(data.data[i]);
                            filteredColors.push(data.colors[i]);
                        }
                    }
                    
                    // Se todos os termos foram filtrados, mostra uma mensagem
                    if (filteredLabels.length === 0) {
                        filteredLabels = ['Sem termos técnicos'];
                        filteredData = [1];
                        filteredColors = ['rgba(200, 200, 200, 0.7)'];
                    }

                    // Usa o método oficial do Chart.js para verificar e destruir um gráfico existente
                    const existingChart = Chart.getChart(canvasElement);
                    if (existingChart) {
                        existingChart.destroy();
                    }

                    // Cria o novo gráfico com os dados filtrados
                    new Chart(canvasElement, {
                        type: 'doughnut',
                        data: {
                            labels: filteredLabels,
                            datasets: [{
                                data: filteredData,
                                backgroundColor: filteredColors,
                                borderColor: 'white',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        boxWidth: 12,
                                        padding: 15
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleFont: {
                                        size: 14
                                    },
                                    bodyFont: {
                                        size: 13
                                    },
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + ' busca(s)';
                                        }
                                    }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '65%'
                        }
                    });
                },
                error: function() {
                    $('#chart-terms-placeholder .card-body').html('<p class="text-muted text-center py-5">Não foi possível carregar os dados dos termos.</p>');
                }
            });
        }

        // Chama as funções de inicialização
        loadStats();
        loadDailyChart();
        loadTermsChart();

        // ===================================================================
        // 2. LÓGICA DA ÁRVORE DE ARQUIVOS (VERSÃO FINAL)
        // ===================================================================

        // Listener para EXPANDIR/RECOLHER - Atrelado apenas ao nome e à seta
        $('.file-tree').on('click', '.folder-toggle, .folder-name', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            var folderDiv = $(this).closest('.folder-item');
            folderDiv.toggleClass('collapsed');
        });

        // Listener para MARCAR/DESMARCAR - Atrelado apenas ao checkbox
        $('.file-tree').on('change', '.folder-check', function(event) {
            event.stopPropagation();
            var isChecked = this.checked;
            $(this).closest('li').find('input[type="checkbox"]').prop('checked', isChecked);
        });

        // ===================================================================
        // 3. FUNÇÃO DE BUSCA PRINCIPAL (AJAX)
        // ===================================================================
        function searchFiles() {
            function collectPdfFiles(node, collectedPaths) {
                if (node.path && node.path.toLowerCase().endsWith('.pdf')) {
                    collectedPaths.push(node.path);
                }
                if (node.nodes) {
                    $.each(node.nodes, function(index, childNode) {
                        collectPdfFiles(childNode, collectedPaths);
                    });
                }
            }

            var selectedCheckboxes = $('.file-check:checked');
            var pathFilesSelected = [];
            selectedCheckboxes.each(function() {
                pathFilesSelected.push($(this).data('path'));
            });
            
            pathFilesSelected = [...new Set(pathFilesSelected)];

            if (pathFilesSelected.length === 0) {
                alert('Por favor, selecione pelo menos um arquivo para pesquisar.');
                return;
            }

            $.ajax({
                type: "POST",
                url: "searchFile",
                data: {
                    'search': $('#input-search').val(),
                    'ignore_case': $('#chk-ignore-case').is(':checked'),
                    'exact_match': $('#chk-exact-match').is(':checked'),
                    'path_files': pathFilesSelected
                },
                beforeSend: function() {
                    $('#search-results-container').html('<p class="text-center py-5">Buscando...</p>');
                },
                success: function(data) {
                    var results = typeof data === "string" ? JSON.parse(data) : data;
                    var htmlResult = '';
                    var resultCount = results ? results.length : 0;

                    $('#chart-placeholder').hide();

                    htmlResult += '<h4 class="search-result-title">Foram encontrados ' + resultCount + ' registro(s) para a sua busca.</h4>';

                    if (resultCount > 0) {
                        htmlResult += '<div class="list-group">';
                        $.each(results, function(i, item) {
                            htmlResult += '<div class="list-group-item result-item">';
                            htmlResult += '  <div class="d-flex w-100 justify-content-between align-items-center mb-2">';
                            htmlResult += '    <h5 class="mb-0 result-title">' + item.filename + '</h5>';
                            htmlResult += '    <a href="' + item.path_file + '" target="_blank" class="btn btn-sm btn-outline-primary ms-3">Abrir PDF</a>';
                            htmlResult += '  </div>';
                            htmlResult += '  <p class="mb-1 result-snippet">' + item.text + '</p>';
                            htmlResult += '</div>';
                        });
                        htmlResult += '</div>';
                    } else {
                        htmlResult += '<p class="text-muted text-center">Tente refinar seus termos de busca ou selecionar mais arquivos.</p>';
                    }
                    
                    $('#search-results-container').html(htmlResult);
                },
                error: function() {
                    $('#search-results-container').html('<p class="text-center text-danger">Ocorreu um erro na busca.</p>');
                }
            });
        }

        // ===================================================================
        // 4. EVENTOS DE CLIQUE E INTERAÇÃO DO USUÁRIO
        // ===================================================================

        $('#btn-search').on('click', function() {
            if ($('#input-search').val().length < 2) {
                alert('Por favor, digite pelo menos 2 caracteres.');
                return;
            }
            searchFiles();
        });

        $('#input-search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#btn-search').click();
            }
        });

        // Inicializa os novos gráficos após a página carregar
        loadTopSearchesChart();
        loadDocumentDistributionChart();
    });

} // Fim da guarda de inicialização