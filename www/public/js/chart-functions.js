/**
 * chart-functions.js
 * Responsável por carregar e renderizar os gráficos na aplicação SearchPDF
 */

// Função para depuração do DOM dos gráficos (modo silencioso)
function debugChartContainers() {
    // Função mantida mas com logs desativados para reduzir ruído no console
    // Descomente os logs abaixo apenas durante depuração quando necessário
    /*
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
    */
}

// Função auxiliar para manipulação silenciosa de erros
function silentError(fn) {
    return function() {
        try {
            return fn.apply(this, arguments);
        } catch (e) {
            // Silenciosamente ignora erros
            return null;
        }
    };
}

// Função para carregar o gráfico de distribuição de documentos
function loadDocumentDistributionChart() {
    // Verifica se chart-placeholder existe antes de prosseguir
    if (!$('#chart-placeholder').length) {
        return; // Sai silenciosamente se não houver onde anexar o gráfico
    }
    
    $.ajax({
        url: window.location.pathname.endsWith('/panel') ? 'stats/document-distribution' : '/stats/document-distribution',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            // Verifica se temos um container para o gráfico
            if (!$('#document-distribution-chart-container').length) {
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
            }
            
            const canvasElement = document.getElementById('document-distribution-chart');
            if (!canvasElement || !data || !data.years || !data.data) {
                return;
            }
            
            // Preenche o seletor de ano
            const yearSelector = $('#year-selector');
            yearSelector.empty();
            yearSelector.append('<option value="all" selected>Todos os anos</option>');
            
            data.years.forEach(year => {
                yearSelector.append(`<option value="${year}">${year}</option>`);
            });
            
            // Mantém "Todos os anos" como padrão
            yearSelector.val('all');
            
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
                try {
                    const existingChart = Chart.getChart(canvasElement);
                    if (existingChart) {
                        existingChart.destroy();
                    }
                } catch (e) {
                    // Ignora erros ao tentar destruir gráficos
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
                try {
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
                } catch (e) {
                    // Ignora erros ao criar o gráfico
                }
            }
            
            // Inicializa o gráfico com a opção "Todos os anos"
            updateChart('all');
            
            // Adiciona evento de mudança ao seletor
            yearSelector.on('change', function() {
                updateChart($(this).val());
            });
        },
        error: function(xhr, status, error) {
            // Erro silencioso na requisição AJAX
        }
    });
}

// Carrega todos os gráficos quando a página estiver pronta
$(document).ready(function() {
    // Remove qualquer container do gráfico de termos mais pesquisados se existir
    if ($('#top-searches-chart-container').length) {
        $('#top-searches-chart-container').remove();
    }
    
    // Carrega apenas o gráfico de distribuição de documentos
    loadDocumentDistributionChart();
});
