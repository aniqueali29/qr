/**
 * Dynamic Circular Chart Component
 * Creates a dynamic donut chart with 4 segments, central percentage, and tooltip
 * Maintains the same layout and size as the static version
 */

class DynamicCircularChart {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        this.chart = null;
        this.options = {
            width: 300,
            height: 300,
            colors: ['#32cd32', '#00bcd4', '#9e9e9e', '#9c27b0'], // lime green, cyan, gray, purple
            animationDuration: 1000,
            updateInterval: 5000, // 5 seconds
            ...options
        };
        
        this.defaultData = {
            segments: [
                { label: 'Sports', value: 85, color: '#9c27b0' },
                { label: 'Education', value: 45, color: '#32cd32' },
                { label: 'Health', value: 30, color: '#00bcd4' },
                { label: 'Other', value: 15, color: '#9e9e9e' }
            ],
            totalPercentage: 38,
            totalLabel: 'World'
        };
        
        this.currentData = { ...this.defaultData };
        this.init();
    }
    
    init() {
        if (!this.container) {
            console.error(`Container with id '${this.containerId}' not found`);
            return;
        }
        
        this.createChartHTML();
        this.initializeChart();
        this.setupEventListeners();
        this.startAutoUpdate();
    }
    
    createChartHTML() {
        this.container.innerHTML = `
            <div class="dynamic-chart-container" style="position: relative; width: ${this.options.width}px; height: ${this.options.height}px;">
                <canvas id="${this.containerId}-canvas" width="${this.options.width}" height="${this.options.height}"></canvas>
                <div class="chart-center-content" style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    text-align: center;
                    pointer-events: none;
                ">
                    <div class="chart-percentage" style="
                        font-size: 2.5rem;
                        font-weight: bold;
                        color: #374151;
                        line-height: 1;
                    ">${this.currentData.totalPercentage}%</div>
                    <div class="chart-label" style="
                        font-size: 1rem;
                        color: #6b7280;
                        margin-top: 0.25rem;
                    ">${this.currentData.totalLabel}</div>
                </div>
                <div class="chart-tooltip" style="
                    position: absolute;
                    background: #9c27b0;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 500;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    pointer-events: none;
                    z-index: 10;
                "></div>
            </div>
        `;
    }
    
    initializeChart() {
        const canvas = document.getElementById(`${this.containerId}-canvas`);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const centerX = this.options.width / 2;
        const centerY = this.options.height / 2;
        const radius = Math.min(centerX, centerY) - 20;
        const innerRadius = radius * 0.6;
        
        this.chart = {
            canvas,
            ctx,
            centerX,
            centerY,
            radius,
            innerRadius,
            segments: []
        };
        
        this.drawChart();
    }
    
    drawChart() {
        const { ctx, centerX, centerY, radius, innerRadius } = this.chart;
        
        // Clear canvas
        ctx.clearRect(0, 0, this.options.width, this.options.height);
        
        // Calculate segment angles
        const totalValue = this.currentData.segments.reduce((sum, segment) => sum + segment.value, 0);
        let currentAngle = -Math.PI / 2; // Start from top
        
        this.chart.segments = [];
        
        this.currentData.segments.forEach((segment, index) => {
            const segmentAngle = (segment.value / totalValue) * 2 * Math.PI;
            const endAngle = currentAngle + segmentAngle;
            
            // Draw segment
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, currentAngle, endAngle);
            ctx.arc(centerX, centerY, innerRadius, endAngle, currentAngle, true);
            ctx.closePath();
            ctx.fillStyle = segment.color;
            ctx.fill();
            
            // Store segment info for hover detection
            this.chart.segments.push({
                ...segment,
                startAngle: currentAngle,
                endAngle: endAngle,
                centerAngle: currentAngle + segmentAngle / 2
            });
            
            currentAngle = endAngle;
        });
        
        // Add hover effects
        this.addHoverEffects();
    }
    
    addHoverEffects() {
        const { canvas } = this.chart;
        const tooltip = this.container.querySelector('.chart-tooltip');
        
        canvas.addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const { centerX, centerY, radius, innerRadius } = this.chart;
            const distance = Math.sqrt((x - centerX) ** 2 + (y - centerY) ** 2);
            
            if (distance >= innerRadius && distance <= radius) {
                const angle = Math.atan2(y - centerY, x - centerX);
                const normalizedAngle = (angle + Math.PI / 2 + 2 * Math.PI) % (2 * Math.PI);
                
                const hoveredSegment = this.chart.segments.find(segment => {
                    const startAngle = (segment.startAngle + Math.PI / 2 + 2 * Math.PI) % (2 * Math.PI);
                    const endAngle = (segment.endAngle + Math.PI / 2 + 2 * Math.PI) % (2 * Math.PI);
                    return normalizedAngle >= startAngle && normalizedAngle <= endAngle;
                });
                
                if (hoveredSegment) {
                    tooltip.textContent = `${hoveredSegment.label}: ${hoveredSegment.value}`;
                    tooltip.style.background = hoveredSegment.color;
                    tooltip.style.opacity = '1';
                    tooltip.style.left = `${e.clientX - rect.left + 10}px`;
                    tooltip.style.top = `${e.clientY - rect.top - 10}px`;
                    canvas.style.cursor = 'pointer';
                } else {
                    tooltip.style.opacity = '0';
                    canvas.style.cursor = 'default';
                }
            } else {
                tooltip.style.opacity = '0';
                canvas.style.cursor = 'default';
            }
        });
        
        canvas.addEventListener('mouseleave', () => {
            tooltip.style.opacity = '0';
            canvas.style.cursor = 'default';
        });
    }
    
    updateData(newData) {
        this.currentData = { ...this.defaultData, ...newData };
        this.updateCenterContent();
        this.drawChart();
    }
    
    updateCenterContent() {
        const percentageElement = this.container.querySelector('.chart-percentage');
        const labelElement = this.container.querySelector('.chart-label');
        
        if (percentageElement) {
            percentageElement.textContent = `${this.currentData.totalPercentage}%`;
        }
        if (labelElement) {
            labelElement.textContent = this.currentData.totalLabel;
        }
    }
    
    setupEventListeners() {
        // Add any custom event listeners here
        this.container.addEventListener('chartUpdate', (e) => {
            this.updateData(e.detail);
        });
    }
    
    startAutoUpdate() {
        if (this.options.updateInterval > 0) {
            setInterval(() => {
                this.fetchDataAndUpdate();
            }, this.options.updateInterval);
        }
    }
    
    async fetchDataAndUpdate() {
        try {
            // Replace with your actual API endpoint
            const response = await fetch('api/dashboard.php?action=circular-chart-data');
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateData(data.data);
                }
            }
        } catch (error) {
            console.error('Error fetching chart data:', error);
        }
    }
    
    // Public methods for external updates
    updateSegment(index, newValue) {
        if (index >= 0 && index < this.currentData.segments.length) {
            this.currentData.segments[index].value = newValue;
            this.drawChart();
        }
    }
    
    updateTotalPercentage(percentage, label = null) {
        this.currentData.totalPercentage = percentage;
        if (label) {
            this.currentData.totalLabel = label;
        }
        this.updateCenterContent();
    }
    
    setColors(colors) {
        if (colors.length >= this.currentData.segments.length) {
            this.currentData.segments.forEach((segment, index) => {
                segment.color = colors[index] || segment.color;
            });
            this.drawChart();
        }
    }
    
    destroy() {
        if (this.chart && this.chart.canvas) {
            this.chart.canvas.removeEventListener('mousemove', this.addHoverEffects);
            this.chart.canvas.removeEventListener('mouseleave', this.addHoverEffects);
        }
        if (this.container) {
            this.container.innerHTML = '';
        }
    }
}

// Auto-initialize if data attributes are present
document.addEventListener('DOMContentLoaded', function() {
    const chartElements = document.querySelectorAll('[data-dynamic-chart]');
    chartElements.forEach(element => {
        const options = {
            width: parseInt(element.dataset.width) || 300,
            height: parseInt(element.dataset.height) || 300,
            updateInterval: parseInt(element.dataset.updateInterval) || 5000,
            colors: element.dataset.colors ? element.dataset.colors.split(',') : null
        };
        
        new DynamicCircularChart(element.id, options);
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DynamicCircularChart;
}
