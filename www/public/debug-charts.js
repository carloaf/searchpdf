// Debugging script to help diagnose chart issues

// Function to log the entire DOM structure for debugging
function logDomStructure() {
    console.log('=== DOCUMENT READY FIRED ===');
    console.log('=== DOM STRUCTURE LOG ===');
    console.log('Body children count:', document.body.children.length);
    console.log('Chart placeholders found:');
    console.log('- #chart-placeholder exists:', !!document.getElementById('chart-placeholder'));
    console.log('- #top-searches-chart exists:', !!document.getElementById('top-searches-chart'));
    console.log('- #document-distribution-chart exists:', !!document.getElementById('document-distribution-chart'));
    console.log('- #top-searches-chart-container exists:', !!document.getElementById('top-searches-chart-container'));
    console.log('- #document-distribution-chart-container exists:', !!document.getElementById('document-distribution-chart-container'));
}

// Function to test Chart.js is working properly
function testChartJsBasic() {
    console.log('=== TESTING CHART.JS FUNCTIONALITY ===');

    // Create a test canvas element
    if (!document.getElementById('test-chart-container')) {
        const container = document.createElement('div');
        container.id = 'test-chart-container';
        container.style.width = '400px';
        container.style.height = '200px';
        container.style.position = 'fixed';
        container.style.bottom = '10px';
        container.style.right = '10px';
        container.style.backgroundColor = 'white';
        container.style.border = '1px solid #ddd';
        container.style.padding = '10px';
        container.style.zIndex = '9999';
        
        const canvas = document.createElement('canvas');
        canvas.id = 'test-chart';
        container.appendChild(canvas);
        
        // Add a close button
        const closeBtn = document.createElement('button');
        closeBtn.textContent = 'X';
        closeBtn.style.position = 'absolute';
        closeBtn.style.top = '5px';
        closeBtn.style.right = '5px';
        closeBtn.style.border = 'none';
        closeBtn.style.backgroundColor = '#f44336';
        closeBtn.style.color = 'white';
        closeBtn.style.borderRadius = '50%';
        closeBtn.style.width = '20px';
        closeBtn.style.height = '20px';
        closeBtn.style.cursor = 'pointer';
        closeBtn.onclick = function() {
            document.body.removeChild(container);
        };
        container.appendChild(closeBtn);
        
        document.body.appendChild(container);
    }
    
    const ctx = document.getElementById('test-chart');
    
    try {
        console.log('Chart.js version:', Chart.version);
        console.log('Creating test chart...');
        
        const testChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                datasets: [{
                    label: 'Test Dataset',
                    data: [12, 19, 3, 5, 2, 3],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        console.log('Test chart created successfully!');
    } catch (error) {
        console.error('Error creating test chart:', error);
    }
}

// Function to test API calls
function testApiCalls() {
    console.log('=== TESTING API CALLS ===');
    
    // Test document distribution API
    $.ajax({
        url: '/stats/document-distribution',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('Document distribution API success!');
            console.log('Data received:', data);
            console.log('Has years array:', !!data.years);
            console.log('Has months array:', !!data.monthOrder);
            console.log('Has data object:', !!data.data);
        },
        error: function(xhr, status, error) {
            console.error('Document distribution API error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
        }
    });
    
    // Test top searches API
    $.ajax({
        url: '/stats/top-searches',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('Top searches API success!');
            console.log('Data received:', data);
            console.log('Has labels array:', !!data.labels);
            console.log('Has counts array:', !!data.counts);
            console.log('Has avgResults array:', !!data.avgResults);
        },
        error: function(xhr, status, error) {
            console.error('Top searches API error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
        }
    });
}

// Execute when document is ready
$(document).ready(function() {
    logDomStructure();
    testChartJsBasic();
    testApiCalls();
    
    // Add event listener for charts
    console.log('Adding debug event listeners to charts...');
    
    // For top searches chart
    if (document.getElementById('top-searches-chart')) {
        const topSearchesCanvas = document.getElementById('top-searches-chart');
        const observer = new MutationObserver(function(mutations) {
            console.log('Top searches chart DOM changed!');
        });
        observer.observe(topSearchesCanvas, { attributes: true, childList: true, subtree: true });
    }
    
    // For document distribution chart
    if (document.getElementById('document-distribution-chart')) {
        const docDistCanvas = document.getElementById('document-distribution-chart');
        const observer = new MutationObserver(function(mutations) {
            console.log('Document distribution chart DOM changed!');
        });
        observer.observe(docDistCanvas, { attributes: true, childList: true, subtree: true });
    }
});
