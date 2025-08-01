// Função para depuração do DOM dos gráficos
function debugChartContainers() {
    console.log('==== DEBUG: Estrutura dos Containers ====');
    console.log('chart-placeholder existe:', $('#chart-placeholder').length > 0);
    console.log('document-distribution-chart-container existe:', $('#document-distribution-chart-container').length > 0);
    
    if ($('#chart-placeholder').length > 0) {
        console.log('chart-placeholder html:', $('#chart-placeholder').html().substring(0, 100) + '...');
    }
    
    // Lista todos os elementos canvas na página
    const canvases = $('canvas');
    console.log('Total de elementos canvas na página:', canvases.length);
    canvases.each(function(index) {
        console.log(`Canvas #${index} - id: ${this.id}, width: ${$(this).width()}, height: ${$(this).height()}`);
    });
    console.log('=======================================');
}

// Função para carregar o gráfico de distribuição de documentos
function loadDocumentDistributionChart() {
    console.log('Iniciando carregamento do gráfico de distribuição de documentos');
    $.ajax({
        url: window.location.pathname.endsWith('/panel') ? 'stats/document-distribution' : '/stats/document-distribution',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('Dados recebidos para o gráfico de distribuição:', data);
            // Atualiza o indicador visual
            $('#document-dist-status span').removeClass('bg-warning').addClass('bg-success').text('Dados recebidos');
            
            // Verifica se temos um container para o gráfico
            if (!$('#document-distribution-chart-container').length) {
                console.log('Criando container para o gráfico de distribuição');
                // Se não existir, cria o elemento no DOM
                const chartHtml = `
                <div class="card mt-4 shadow-sm" id="document-distribution-chart-container">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Distribuição de Documentos por Mês/Ano</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <select id="year-selector" class="form-select">
                                <option value="all">Todos os anos</option>
                            </select>
                        </div>
                        <canvas id="document-distribution-chart" height="250"></canvas>
                    </div>
                </div>`;
                
                // Adiciona após o chart-placeholder diretamente
                $('#chart-placeholder').after(chartHtml);
                console.log('Container criado após o chart-placeholder');
            } else {
                console.log('Container para o gráfico de distribuição já existe');
            }
            
            const canvasElement = document.getElementById('document-distribution-chart');
            if (!canvasElement || !data || !data.years || !data.data) {
                console.error('Elementos necessários para o gráfico de distribuição não encontrados:');
                console.error('- canvasElement:', !!canvasElement);
                console.error('- data:', !!data);
                console.error('- data.years:', data && !!data.years);
                console.error('- data.data:', data && !!data.data);
                console.error('Dados completos:', data);
                return;
            }
            
            console.log('Todos os elementos necessários para o gráfico de distribuição encontrados, configurando...');
            
            // Atualiza o indicador visual
            $('#document-dist-status span').removeClass('bg-success').addClass('bg-primary').text('Renderizando gráfico');
            
            // Preenche o seletor de ano
            const yearSelector = $('#year-selector');
            yearSelector.empty();
            yearSelector.append('<option value="all">Todos os anos</option>');
            
            data.years.forEach(year => {
                yearSelector.append(`<option value="${year}">${year}</option>`);
                console.log(`Adicionado ano ${year} ao seletor`);
            });
            
            // Seleciona o ano atual por padrão, se disponível
            const currentYear = new Date().getFullYear().toString();
            if (data.years.includes(currentYear)) {
                yearSelector.val(currentYear);
            }
            
            // Define as cores para cada mês
            const colorPalette = [
                'rgba(46, 125, 50, 0.8)',    // Verde escuro
                'rgba(56, 142, 60, 0.8)',    
                'rgba(67, 160, 71, 0.8)',    
                'rgba(76, 175, 80, 0.8)',    
                'rgba(102, 187, 106, 0.8)',  
                'rgba(129, 199, 132, 0.8)',  
                'rgba(165, 214, 167, 0.8)',  
                'rgba(200, 230, 201, 0.8)',  
                'rgba(232, 245, 233, 0.8)',  
                'rgba(220, 237, 200, 0.8)',
                'rgba(183, 223, 185, 0.8)',
                'rgba(129, 199, 132, 0.8)'
            ];
            
            // Função para atualizar o gráfico com base no ano selecionado
            function updateChart(selectedYear) {
                // Usa o método do Chart.js para destruir um gráfico existente
                const existingChart = Chart.getChart(canvasElement);
                if (existingChart) {
                    existingChart.destroy();
                }
                
                // Prepara os dados com base no ano selecionado
                let datasets = [];
                
                if (selectedYear === 'all') {
                    // Para todos os anos, cada ano será uma série de dados
                    data.years.forEach((year, index) => {
                        const yearData = [];
                        
                        // Preenche os dados para cada mês
                        data.monthOrder.forEach(month => {
                            yearData.push(data.data[year] && data.data[year][month] ? data.data[year][month] : 0);
                        });
                        
                        datasets.push({
                            label: `${year}`,
                            data: yearData,
                            backgroundColor: `rgba(${index * 30 + 46}, ${index * 20 + 125}, ${index * 10 + 50}, 0.7)`,
                            borderColor: `rgba(${index * 30 + 46}, ${index * 20 + 125}, ${index * 10 + 50}, 1)`,
                            borderWidth: 1
                        });
                    });
                } else {
                    // Para um ano específico, mostra apenas os dados desse ano
                    const yearData = [];
                    
                    // Preenche os dados para cada mês
                    data.monthOrder.forEach((month, index) => {
                        yearData.push(data.data[selectedYear] && data.data[selectedYear][month] ? 
                            data.data[selectedYear][month] : 0);
                    });
                    
                    datasets.push({
                        label: `Documentos em ${selectedYear}`,
                        data: yearData,
                        backgroundColor: colorPalette,
                        borderColor: colorPalette.map(color => color.replace('0.8', '1')),
                        borderWidth: 1
                    });
                }
                
                // Cria o novo gráfico
                new Chart(canvasElement, {
                    type: 'bar',  // Bar chart tipo coluna
                    data: {
                        labels: data.monthOrder,
                        datasets: datasets
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                },
                                title: {
                                    display: true,
                                    text: 'Quantidade de Documentos',
                                    font: {
                                        weight: 'bold'
                                    }
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Mês',
                                    font: {
                                        weight: 'bold'
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    title: function(tooltipItems) {
                                        return data.monthOrder[tooltipItems[0].dataIndex];
                                    }
                                }
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
            
            // Inicializa o gráfico com o valor selecionado
            updateChart(yearSelector.val());
            
            // Adiciona evento de mudança ao seletor
            yearSelector.on('change', function() {
                updateChart($(this).val());
            });
            
            // Atualiza o indicador visual
            $('#document-dist-status span').removeClass('bg-primary').addClass('bg-success').text('Concluído');
            
            // Debug para verificar se o gráfico foi criado corretamente
            setTimeout(function() {
                debugChartContainers();
                
                // Verifica se o gráfico foi registrado no Chart.js
                const chartInstance = Chart.getChart(canvasElement);
                console.log('Gráfico registrado no Chart.js:', !!chartInstance);
                if (chartInstance) {
                    console.log('Tipo do gráfico:', chartInstance.config.type);
                    console.log('Número de datasets:', chartInstance.data.datasets.length);
                    console.log('Primeiro mês no gráfico:', chartInstance.data.labels[0]);
                }
            }, 500);
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar dados para o gráfico de distribuição:', error);
            // Atualiza o indicador visual
            $('#document-dist-status span').removeClass('bg-warning').addClass('bg-danger').text('Erro: ' + error);
        }
    });
}

// Carrega todos os gráficos quando a página estiver pronta
$(document).ready(function() {
    console.log('Iniciando carregamento dos gráficos...');
    
    // Remove qualquer container do gráfico de termos mais pesquisados se existir
    if ($('#top-searches-chart-container').length) {
        $('#top-searches-chart-container').remove();
        console.log('Container do gráfico de termos mais pesquisados removido');
    }
    
    // Adiciona um indicador visual para acompanhar o carregamento
    $('body').append('<div id="chart-loading-indicator" style="position: fixed; bottom: 10px; left: 10px; background-color: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); z-index: 9999;"><div><strong>Status do carregamento:</strong></div><div id="document-dist-status">Distribuição de Documentos: <span class="badge bg-warning">Pendente</span></div></div>');
    
    // Verifica se o chart-placeholder existe
    if ($('#chart-placeholder').length) {
        console.log('Elemento chart-placeholder encontrado!');
    } else {
        console.error('Elemento chart-placeholder NÃO encontrado!');
    }
    
    // Verifica se o Chart.js está disponível
    if (typeof Chart !== 'undefined') {
        console.log('Chart.js está disponível!');
    } else {
        console.error('Chart.js NÃO está disponível!');
    }
    
    // Executar debug inicial
    debugChartContainers();
    
    // Carrega apenas o gráfico de distribuição de documentos
    loadDocumentDistributionChart();
});
