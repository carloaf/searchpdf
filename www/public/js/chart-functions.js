/**
 * chart-functions.js
 * Responsável por carregar e renderizar os gráficos na aplicação SearchPDF
 */

const chartThemeUtils = (() => {
    const external = window.searchThemeUtils || {};

    const clamp01 = (value) => {
        const number = Number(value);
        if (!Number.isFinite(number)) return 0;
        if (number < 0) return 0;
        if (number > 1) return 1;
        return number;
    };

    const cssVarFallback = (name, fallback = '') => {
        try {
            const value = getComputedStyle(document.documentElement).getPropertyValue(name);
            return value ? value.trim() || fallback : fallback;
        } catch (error) {
            return fallback;
        }
    };

    const toRgbaString = ({ r, g, b, a }) => {
        const red = Math.round(Math.min(Math.max(r, 0), 255));
        const green = Math.round(Math.min(Math.max(g, 0), 255));
        const blue = Math.round(Math.min(Math.max(b, 0), 255));
        const alpha = Math.round(clamp01(a) * 1000) / 1000;
        return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
    };

    const parseColorString = (color) => {
        if (!color) return null;
        const normalized = color.trim();
        if (!normalized) return null;

        if (normalized.startsWith('#')) {
            let hex = normalized.slice(1);
            if (hex.length === 3) {
                hex = hex.split('').map((char) => char + char).join('');
            }
            if (hex.length === 6) {
                const value = parseInt(hex, 16);
                if (Number.isNaN(value)) return null;
                return {
                    r: (value >> 16) & 255,
                    g: (value >> 8) & 255,
                    b: value & 255,
                    a: 1
                };
            }
            if (hex.length === 8) {
                const value = parseInt(hex, 16);
                if (Number.isNaN(value)) return null;
                return {
                    r: (value >> 24) & 255,
                    g: (value >> 16) & 255,
                    b: (value >> 8) & 255,
                    a: ((value & 255) / 255)
                };
            }
        }

        const rgbaMatch = normalized.match(/rgba?\(([^)]+)\)/i);
        if (rgbaMatch) {
            const parts = rgbaMatch[1].split(',').map((part) => part.trim());
            if (parts.length >= 3) {
                const r = parseFloat(parts[0]);
                const g = parseFloat(parts[1]);
                const b = parseFloat(parts[2]);
                const a = parts.length >= 4 ? parseFloat(parts[3]) : 1;
                if ([r, g, b, a].every((component) => !Number.isNaN(component))) {
                    return { r, g, b, a: clamp01(a) };
                }
            }
        }
        return null;
    };

    const colorWithAlpha = (color, alpha) => {
        const parsed = parseColorString(color);
        if (!parsed) {
            return color || toRgbaString({ r: 0, g: 0, b: 0, a: clamp01(alpha) });
        }
        return toRgbaString({ ...parsed, a: clamp01(alpha) });
    };

    const mixColors = (colorA, colorB, weight, alphaOverride = null) => {
        const parsedA = parseColorString(colorA);
        const parsedB = parseColorString(colorB);
        if (!parsedA && !parsedB) {
            return colorA || colorB || '';
        }
        if (!parsedA) {
            return alphaOverride !== null ? colorWithAlpha(colorB, alphaOverride) : colorB;
        }
        if (!parsedB) {
            return alphaOverride !== null ? colorWithAlpha(colorA, alphaOverride) : colorA;
        }
        const w = clamp01(weight);
        const mixed = {
            r: parsedA.r * (1 - w) + parsedB.r * w,
            g: parsedA.g * (1 - w) + parsedB.g * w,
            b: parsedA.b * (1 - w) + parsedB.b * w,
            a: alphaOverride !== null ? clamp01(alphaOverride) : (parsedA.a * (1 - w) + parsedB.a * w)
        };
        return toRgbaString(mixed);
    };

    const generateGradientPalette = (startColor, endColor, count, options = {}) => {
        const total = Math.max(count, 0);
        if (total === 0) return [];
        const { startAlpha = 0.85, endAlpha = 0.55 } = options;
        if (total === 1) {
            return [mixColors(startColor, endColor, 0, startAlpha)];
        }
        const palette = [];
        for (let index = 0; index < total; index++) {
            const weight = index / (total - 1);
            const alpha = startAlpha + (endAlpha - startAlpha) * weight;
            palette.push(mixColors(startColor, endColor, weight, alpha));
        }
        return palette;
    };

    return {
        cssVar: external.cssVar || cssVarFallback,
        colorWithAlpha: external.colorWithAlpha || colorWithAlpha,
        generateGradientPalette: external.generateGradientPalette || generateGradientPalette,
        mixColors: external.mixColors || mixColors,
        parseColorString: external.parseColorString || parseColorString
    };
})();

let documentDistributionChart = null;
let lastDistributionData = null;
let lastDistributionYear = 'all';

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

const { cssVar, colorWithAlpha, generateGradientPalette, mixColors } = chartThemeUtils;

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
            
            if (!data || !Array.isArray(data.years) || !data.data) {
                return;
            }

            lastDistributionData = data;

            const canvasElement = document.getElementById('document-distribution-chart');
            if (!canvasElement) {
                return;
            }

            const yearSelector = $('#year-selector');
            const previousSelection = lastDistributionYear;

            yearSelector.empty();
            yearSelector.append('<option value="all">Todos os anos</option>');

            data.years.forEach(year => {
                yearSelector.append(`<option value="${year}">${year}</option>`);
            });

            if (previousSelection !== 'all' && !data.years.includes(previousSelection)) {
                lastDistributionYear = 'all';
            }

            yearSelector.val(lastDistributionYear);

            renderDocumentDistributionChart(canvasElement, data, lastDistributionYear);

            yearSelector.off('change.documentDistribution')
                .on('change.documentDistribution', function() {
                    lastDistributionYear = $(this).val() || 'all';
                    renderDocumentDistributionChart(canvasElement, lastDistributionData, lastDistributionYear);
                });
        },
        error: function(xhr, status, error) {
            // Erro silencioso na requisição AJAX
        }
    });
}

function renderDocumentDistributionChart(canvasElement, data, selectedYear = 'all') {
    if (!canvasElement || !data || !Array.isArray(data.monthOrder)) {
        return;
    }

    if (documentDistributionChart) {
        documentDistributionChart.destroy();
        documentDistributionChart = null;
    }

    const baseColor = cssVar('--chart-bar-color', 'rgba(46, 125, 50, 0.75)');
    const secondaryColor = cssVar('--chart-secondary-color', '#ff8f00');
    const borderColor = cssVar('--chart-border-color', 'rgba(46, 125, 50, 1)');
    const tooltipBg = cssVar('--chart-tooltip-bg', 'rgba(46, 125, 50, 0.9)');
    const tooltipText = cssVar('--chart-tooltip-text', '#ffffff');
    const axisTickColor = cssVar('--muted-text-color', '#757575');
    const titleColor = cssVar('--text-primary', '#212121');
    const gridColorY = colorWithAlpha(borderColor, 0.2);
    const gridColorX = colorWithAlpha(borderColor, 0.1);

    const datasets = [];

    if (selectedYear === 'all') {
        const totalYears = Array.isArray(data.years) ? data.years.length : 0;
        const yearPalette = generateGradientPalette(baseColor, secondaryColor, totalYears || 1, {
            startAlpha: 0.7,
            endAlpha: 0.45
        });

        if (Array.isArray(data.years)) {
            data.years.forEach((year, index) => {
                const entries = data.monthOrder.map((month) => {
                    return data.data[year] && data.data[year][month] ? data.data[year][month] : 0;
                });

                const yearColor = yearPalette[index] || colorWithAlpha(baseColor, 0.65);

                datasets.push({
                    label: `${year}`,
                    data: entries,
                    backgroundColor: yearColor,
                    borderColor: colorWithAlpha(yearColor, 0.95),
                    borderWidth: 1
                });
            });
        }
    } else {
        const selectedData = data.data[selectedYear] || {};
        const monthCount = Array.isArray(data.monthOrder) ? data.monthOrder.length : 0;
        const backgroundPalette = generateGradientPalette(baseColor, secondaryColor, monthCount || 1, {
            startAlpha: 0.85,
            endAlpha: 0.55
        });
        const borderPalette = backgroundPalette.map((color) => colorWithAlpha(color, 0.95));

        const entries = data.monthOrder.map((month) => {
            return selectedData[month] ? selectedData[month] : 0;
        });

        datasets.push({
            label: `Documentos em ${selectedYear}`,
            data: entries,
            backgroundColor: backgroundPalette,
            borderColor: borderPalette,
            borderWidth: 1
        });
    }

    documentDistributionChart = new Chart(canvasElement, {
        type: 'bar',
        data: {
            labels: data.monthOrder,
            datasets
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: axisTickColor
                    },
                    grid: {
                        color: gridColorY
                    },
                    title: {
                        display: true,
                        text: 'Quantidade de Documentos',
                        color: titleColor,
                        font: {
                            weight: 'bold'
                        }
                    }
                },
                x: {
                    ticks: {
                        color: axisTickColor
                    },
                    grid: {
                        color: gridColorX
                    },
                    title: {
                        display: true,
                        text: 'Mês',
                        color: titleColor,
                        font: {
                            weight: 'bold'
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: axisTickColor
                    }
                },
                tooltip: {
                    backgroundColor: tooltipBg,
                    bodyColor: tooltipText,
                    titleColor: tooltipText,
                    callbacks: {
                        title: function(tooltipItems) {
                            if (!tooltipItems || !tooltipItems.length) {
                                return '';
                            }
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

document.addEventListener('searchpdf:themechange', function() {
    const canvasElement = document.getElementById('document-distribution-chart');
    if (canvasElement && lastDistributionData) {
        renderDocumentDistributionChart(canvasElement, lastDistributionData, lastDistributionYear);
    }
});

// Carrega todos os gráficos quando a página estiver pronta
$(document).ready(function() {
    // Remove qualquer container do gráfico de termos mais pesquisados se existir
    if ($('#top-searches-chart-container').length) {
        $('#top-searches-chart-container').remove();
    }
    
    // Carrega apenas o gráfico de distribuição de documentos
    loadDocumentDistributionChart();
});
