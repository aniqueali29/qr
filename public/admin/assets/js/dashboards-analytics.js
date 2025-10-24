/**
 * Dashboard Analytics - Merged
 * Handles all chart initializations and updates for the admin dashboard
 */

'use strict';

(function () {
  let cardColor, headingColor, axisColor, shadeColor, borderColor;

  cardColor = config.colors.white;
  headingColor = config.colors.headingColor;
  axisColor = config.colors.axisColor;
  borderColor = config.colors.borderColor;

  // Global chart instances
  let orderStatisticsChart = null;
  let totalRevenueChart = null;
  let growthChart = null;
  let profileReportChart = null;
  let incomeChart = null;
  let weeklyExpenses = null;

  // Initialize all charts when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    initializeAllCharts();
  });

  /**
   * Initialize all dashboard charts
   */
  function initializeAllCharts() {
    initializeTotalRevenueChart();
    initializeGrowthChart();
    initializeProfileReportChart();
    initializeOrderStatisticsChart();
    initializeIncomeChart();
    initializeExpensesChart();
  }

  // Total Revenue Report Chart - Bar Chart
  // --------------------------------------------------------------------
  function initializeTotalRevenueChart() {
    const totalRevenueChartEl = document.querySelector('#totalRevenueChart');
    const totalRevenueChartOptions = {
      series: [
        {
          name: '2021',
          data: [18, 7, 15, 29, 18, 12, 9]
        },
        {
          name: '2020',
          data: [-13, -18, -9, -14, -5, -17, -15]
        }
      ],
      chart: {
        height: 300,
        stacked: true,
        type: 'bar',
        toolbar: { show: false }
      },
      plotOptions: {
        bar: {
          horizontal: false,
          columnWidth: '33%',
          borderRadius: 12,
          startingShape: 'rounded',
          endingShape: 'rounded'
        }
      },
      colors: [config.colors.primary, config.colors.info],
      dataLabels: {
        enabled: false
      },
      stroke: {
        curve: 'smooth',
        width: 6,
        lineCap: 'round',
        colors: [cardColor]
      },
      legend: {
        show: true,
        horizontalAlign: 'left',
        position: 'top',
        markers: {
          height: 8,
          width: 8,
          radius: 12,
          offsetX: -3
        },
        labels: {
          colors: axisColor
        },
        itemMargin: {
          horizontal: 10
        }
      },
      grid: {
        borderColor: borderColor,
        padding: {
          top: 0,
          bottom: -8,
          left: 20,
          right: 20
        }
      },
      xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        labels: {
          style: {
            fontSize: '13px',
            colors: axisColor
          }
        },
        axisTicks: {
          show: false
        },
        axisBorder: {
          show: false
        }
      },
      yaxis: {
        labels: {
          style: {
            fontSize: '13px',
            colors: axisColor
          }
        }
      },
      responsive: [
        {
          breakpoint: 1700,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '32%'
              }
            }
          }
        },
        {
          breakpoint: 1580,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '35%'
              }
            }
          }
        },
        {
          breakpoint: 1440,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '42%'
              }
            }
          }
        },
        {
          breakpoint: 1300,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '48%'
              }
            }
          }
        },
        {
          breakpoint: 1200,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '40%'
              }
            }
          }
        },
        {
          breakpoint: 1040,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 11,
                columnWidth: '48%'
              }
            }
          }
        },
        {
          breakpoint: 991,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '30%'
              }
            }
          }
        },
        {
          breakpoint: 840,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '35%'
              }
            }
          }
        },
        {
          breakpoint: 768,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '28%'
              }
            }
          }
        },
        {
          breakpoint: 640,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '32%'
              }
            }
          }
        },
        {
          breakpoint: 576,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '37%'
              }
            }
          }
        },
        {
          breakpoint: 480,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '45%'
              }
            }
          }
        },
        {
          breakpoint: 420,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '52%'
              }
            }
          }
        },
        {
          breakpoint: 380,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '60%'
              }
            }
          }
        }
      ],
      states: {
        hover: {
          filter: {
            type: 'none'
          }
        },
        active: {
          filter: {
            type: 'none'
          }
        }
      }
    };
    
    if (typeof totalRevenueChartEl !== 'undefined' && totalRevenueChartEl !== null) {
      totalRevenueChart = new ApexCharts(totalRevenueChartEl, totalRevenueChartOptions);
      totalRevenueChart.render();
    }
  }

  // Growth Chart - Radial Bar Chart
  // --------------------------------------------------------------------
  function initializeGrowthChart() {
    const growthChartEl = document.querySelector('#growthChart');
    if (!growthChartEl) return;
    
    // Load growth data from API
    loadGrowthData();
  }

  /**
   * Load growth data from API
   */
  function loadGrowthData() {
    fetch('api/dashboard.php?action=growth-metrics')
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data) {
          initializeGrowthChartWithData(data.data);
        } else {
          initializeGrowthChartWithData({
            growth_rate: 0,
            label: 'Growth'
          });
        }
      })
      .catch(error => {
        console.error('Error loading growth data:', error);
        // Fallback to default data
        initializeGrowthChartWithData({
          growth_rate: 0,
          label: 'Growth'
        });
      });
  }

  /**
   * Initialize growth chart with dynamic data
   */
  function initializeGrowthChartWithData(growthData) {
    const growthChartEl = document.querySelector('#growthChart');
    if (!growthChartEl) return;
    
    const growthRate = growthData.growth_rate || 0;
    const label = growthData.label || 'Growth';
    
    // Determine color based on growth rate
    let chartColor = config.colors.primary;
    if (growthRate >= 80) {
      chartColor = '#10b981'; // Green for high growth
    } else if (growthRate >= 50) {
      chartColor = '#f59e0b'; // Yellow for medium growth
    } else if (growthRate >= 20) {
      chartColor = '#ef4444'; // Red for low growth
    } else {
      chartColor = '#6b7280'; // Gray for very low growth
    }
    
    const growthChartOptions = {
      series: [growthRate],
      labels: [label],
      chart: {
        height: 240,
        type: 'radialBar'
      },
      plotOptions: {
        radialBar: {
          size: 150,
          offsetY: 10,
          startAngle: -150,
          endAngle: 150,
          hollow: {
            size: '55%'
          },
          track: {
            background: cardColor,
            strokeWidth: '100%'
          },
          dataLabels: {
            name: {
              offsetY: 15,
              color: headingColor,
              fontSize: '15px',
              fontWeight: '600',
              fontFamily: 'Public Sans'
            },
            value: {
              offsetY: -25,
              color: headingColor,
              fontSize: '22px',
              fontWeight: '500',
              fontFamily: 'Public Sans'
            }
          }
        }
      },
      colors: [chartColor],
      fill: {
        type: 'gradient',
        gradient: {
          shade: 'dark',
          shadeIntensity: 0.5,
          gradientToColors: [chartColor],
          inverseColors: true,
          opacityFrom: 1,
          opacityTo: 0.6,
          stops: [30, 70, 100]
        }
      },
      stroke: {
        dashArray: 5
      },
      grid: {
        padding: {
          top: -35,
          bottom: -10
        }
      },
      states: {
        hover: {
          filter: {
            type: 'none'
          }
        },
        active: {
          filter: {
            type: 'none'
          }
        }
      }
    };
    
    // Destroy existing chart if it exists
    if (growthChart) {
      growthChart.destroy();
    }
    
    growthChart = new ApexCharts(growthChartEl, growthChartOptions);
    growthChart.render();
  }

  // Profit Report Line Chart
  // --------------------------------------------------------------------
  function initializeProfileReportChart() {
    const profileReportChartEl = document.querySelector('#profileReportChart');
    const profileReportChartConfig = {
      chart: {
        height: 80,
        type: 'line',
        toolbar: {
          show: false
        },
        dropShadow: {
          enabled: true,
          top: 10,
          left: 5,
          blur: 3,
          color: config.colors.warning,
          opacity: 0.15
        },
        sparkline: {
          enabled: true
        }
      },
      grid: {
        show: false,
        padding: {
          right: 8
        }
      },
      colors: [config.colors.warning],
      dataLabels: {
        enabled: false
      },
      stroke: {
        width: 5,
        curve: 'smooth'
      },
      series: [
        {
          data: [110, 270, 145, 245, 205, 285]
        }
      ],
      xaxis: {
        show: false,
        lines: {
          show: false
        },
        labels: {
          show: false
        },
        axisBorder: {
          show: false
        }
      },
      yaxis: {
        show: false
      }
    };
    
    if (typeof profileReportChartEl !== 'undefined' && profileReportChartEl !== null) {
      profileReportChart = new ApexCharts(profileReportChartEl, profileReportChartConfig);
      profileReportChart.render();
    }
  }

  // Order Statistics Chart (Program Distribution)
  // --------------------------------------------------------------------
  /**
   * Initialize Order Statistics (Program Distribution) Donut Chart
   */
  function initializeOrderStatisticsChart() {
    const chartElement = document.getElementById('orderStatisticsChart');
    if (!chartElement) return;
    
    // Load program distribution data via AJAX
    loadProgramDistributionData();
  }

  /**
   * Load program distribution data from API
   */
  function loadProgramDistributionData() {
    fetch('api/dashboard.php?action=program-distribution')
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data) {
          initializeOrderStatisticsChartWithData(data.data);
        } else {
          initializeOrderStatisticsChartWithData([]);
        }
      })
      .catch(error => {
        console.error('Error loading program distribution:', error);
        // Fallback to static data if API fails
        initializeOrderStatisticsChartWithStaticData();
      });
  }

  /**
   * Initialize chart with dynamic data from API
   */
  function initializeOrderStatisticsChartWithData(programData) {
    const chartElement = document.getElementById('orderStatisticsChart');
    if (!chartElement) {
      console.warn('initializeOrderStatisticsChartWithData: Chart element not found');
      return;
    }
    
    // Enhanced data validation and sanitization
    let safeData = [];
    if (Array.isArray(programData) && programData.length > 0) {
      safeData = programData.filter(item => {
        return item && 
               typeof item === 'object' && 
               item.program_name && 
               item.student_count !== undefined;
      }).map(item => ({
        program_name: String(item.program_name || 'Unknown').trim(),
        student_count: Math.max(0, parseInt(item.student_count) || 0)
      }));
    }
    
    if (safeData.length > 0) {
      // Prepare data for ApexCharts
      const labels = safeData.map(item => item.program_name);
      const series = safeData.map(item => item.student_count);
      
      // Validate series data
      const hasValidSeries = series.every(val => typeof val === 'number' && !isNaN(val) && val >= 0);
      if (!hasValidSeries) {
        console.error('initializeOrderStatisticsChartWithData: Invalid series data:', series);
        safeData = [];
      }
      
      const colors = [
        (config && config.colors && config.colors.primary) || '#696cff',
        (config && config.colors && config.colors.secondary) || '#8592a3',
        (config && config.colors && config.colors.success) || '#71dd37',
        (config && config.colors && config.colors.info) || '#03c3ec',
        (config && config.colors && config.colors.warning) || '#ffab00',
        '#ff3e1d', 
        '#ff5722', 
        '#9c27b0', 
        '#607d8b', 
        '#795548'
      ];
      
      const options = {
        series: series,
        chart: {
          height: 165,
          width: 130,
          type: 'donut'
        },
        labels: labels,
        colors: colors.slice(0, safeData.length),
        stroke: {
          width: 5,
          colors: cardColor || '#ffffff'
        },
        dataLabels: {
          enabled: false,
          formatter: function (val, opt) {
            return parseInt(val) + '%';
          }
        },
        legend: {
          show: false
        },
        grid: {
          padding: {
            top: 0,
            bottom: 0,
            right: 15
          }
        },
        plotOptions: {
          pie: {
            donut: {
              size: '75%',
              labels: {
                show: true,
                value: {
                  fontSize: '1.5rem',
                  fontFamily: 'Public Sans',
                  color: headingColor,
                  offsetY: -15,
                  formatter: function (val) {
                    return parseInt(val) + '%';
                  }
                },
                name: {
                  offsetY: 20,
                  fontFamily: 'Public Sans'
                },
                total: {
                  show: true,
                  fontSize: '0.8125rem',
                  color: axisColor,
                  label: 'Total',
                  formatter: function (w) {
                    const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                    return total;
                  }
                }
              }
            }
          }
        },
        tooltip: {
          enabled: true,
          y: {
            formatter: function(val) {
              return `${val}`;
            }
          }
        }
      };
      
      if (orderStatisticsChart) {
        orderStatisticsChart.destroy();
      }
      
      try {
      orderStatisticsChart = new ApexCharts(chartElement, options);
      orderStatisticsChart.render();
        console.log('initializeOrderStatisticsChartWithData: Chart created successfully with', safeData.length, 'data points');
      } catch (chartError) {
        console.error('initializeOrderStatisticsChartWithData: Error creating chart:', chartError);
        orderStatisticsChart = null;
      }
    } else {
      // Show empty state
      const options = {
        series: [100],
        chart: {
          height: 165,
          width: 130,
          type: 'donut'
        },
        labels: ['No Data'],
        colors: ['#e5e7eb'],
        stroke: {
          width: 5,
          colors: cardColor
        },
        dataLabels: {
          enabled: false
        },
        legend: {
          show: false
        },
        plotOptions: {
          pie: {
            donut: {
              size: '75%',
              labels: {
                show: true,
                value: {
                  fontSize: '1.5rem',
                  fontFamily: 'Public Sans',
                  color: headingColor,
                  offsetY: -15,
                  formatter: function (val) {
                    return '0';
                  }
                },
                name: {
                  offsetY: 20,
                  fontFamily: 'Public Sans'
                },
                total: {
                  show: true,
                  fontSize: '0.8125rem',
                  color: axisColor,
                  label: 'No Students',
                  formatter: function (w) {
                    return '0';
                  }
                }
              }
            }
          }
        },
        tooltip: {
          enabled: false
        }
      };
      
      if (orderStatisticsChart) {
        orderStatisticsChart.destroy();
      }
      
      try {
      orderStatisticsChart = new ApexCharts(chartElement, options);
      orderStatisticsChart.render();
        console.log('initializeOrderStatisticsChartWithData: Empty state chart created successfully');
      } catch (chartError) {
        console.error('initializeOrderStatisticsChartWithData: Error creating empty state chart:', chartError);
        orderStatisticsChart = null;
      }
    }
  }

  /**
   * Initialize chart with static fallback data
   */
  function initializeOrderStatisticsChartWithStaticData() {
    const chartElement = document.querySelector('#orderStatisticsChart');
    const orderChartConfig = {
      chart: {
        height: 165,
        width: 130,
        type: 'donut'
      },
      labels: ['Electronic', 'Sports', 'Decor', 'Fashion'],
      series: [85, 15, 50, 50],
      colors: [config.colors.primary, config.colors.secondary, config.colors.info, config.colors.success],
      stroke: {
        width: 5,
        colors: cardColor
      },
      dataLabels: {
        enabled: false,
        formatter: function (val, opt) {
          return parseInt(val) + '%';
        }
      },
      legend: {
        show: false
      },
      grid: {
        padding: {
          top: 0,
          bottom: 0,
          right: 15
        }
      },
      plotOptions: {
        pie: {
          donut: {
            size: '75%',
            labels: {
              show: true,
              value: {
                fontSize: '1.5rem',
                fontFamily: 'Public Sans',
                color: headingColor,
                offsetY: -15,
                formatter: function (val) {
                  return parseInt(val) + '%';
                }
              },
              name: {
                offsetY: 20,
                fontFamily: 'Public Sans'
              },
              total: {
                show: true,
                fontSize: '0.8125rem',
                color: axisColor,
                label: 'Weekly',
                formatter: function (w) {
                  return '38%';
                }
              }
            }
          }
        }
      }
    };
    
    if (typeof chartElement !== 'undefined' && chartElement !== null) {
      if (orderStatisticsChart) {
        orderStatisticsChart.destroy();
      }
      orderStatisticsChart = new ApexCharts(chartElement, orderChartConfig);
      orderStatisticsChart.render();
    }
  }

  // Income Chart - Area chart
  // --------------------------------------------------------------------
  function initializeIncomeChart() {
    const incomeChartEl = document.querySelector('#incomeChart');
    const incomeChartConfig = {
      series: [
        {
          data: [24, 21, 30, 22, 42, 26, 35, 29]
        }
      ],
      chart: {
        height: 215,
        parentHeightOffset: 0,
        parentWidthOffset: 0,
        toolbar: {
          show: false
        },
        type: 'area'
      },
      dataLabels: {
        enabled: false
      },
      stroke: {
        width: 2,
        curve: 'smooth'
      },
      legend: {
        show: false
      },
      markers: {
        size: 6,
        colors: 'transparent',
        strokeColors: 'transparent',
        strokeWidth: 4,
        discrete: [
          {
            fillColor: config.colors.white,
            seriesIndex: 0,
            dataPointIndex: 7,
            strokeColor: config.colors.primary,
            strokeWidth: 2,
            size: 6,
            radius: 8
          }
        ],
        hover: {
          size: 7
        }
      },
      colors: [config.colors.primary],
      fill: {
        type: 'gradient',
        gradient: {
          shade: shadeColor,
          shadeIntensity: 0.6,
          opacityFrom: 0.5,
          opacityTo: 0.25,
          stops: [0, 95, 100]
        }
      },
      grid: {
        borderColor: borderColor,
        strokeDashArray: 3,
        padding: {
          top: -20,
          bottom: -8,
          left: -10,
          right: 8
        }
      },
      xaxis: {
        categories: ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        axisBorder: {
          show: false
        },
        axisTicks: {
          show: false
        },
        labels: {
          show: true,
          style: {
            fontSize: '13px',
            colors: axisColor
          }
        }
      },
      yaxis: {
        labels: {
          show: false
        },
        min: 10,
        max: 50,
        tickAmount: 4
      }
    };
    
    if (typeof incomeChartEl !== 'undefined' && incomeChartEl !== null) {
      incomeChart = new ApexCharts(incomeChartEl, incomeChartConfig);
      incomeChart.render();
    }
  }

  // Expenses Mini Chart - Radial Chart
  // --------------------------------------------------------------------
  function initializeExpensesChart() {
    const weeklyExpensesEl = document.querySelector('#expensesOfWeek');
    const weeklyExpensesConfig = {
      series: [65],
      chart: {
        width: 60,
        height: 60,
        type: 'radialBar'
      },
      plotOptions: {
        radialBar: {
          startAngle: 0,
          endAngle: 360,
          strokeWidth: '8',
          hollow: {
            margin: 2,
            size: '45%'
          },
          track: {
            strokeWidth: '50%',
            background: borderColor
          },
          dataLabels: {
            show: true,
            name: {
              show: false
            },
            value: {
              formatter: function (val) {
                return '$' + parseInt(val);
              },
              offsetY: 5,
              color: '#697a8d',
              fontSize: '13px',
              show: true
            }
          }
        }
      },
      fill: {
        type: 'solid',
        colors: config.colors.primary
      },
      stroke: {
        lineCap: 'round'
      },
      grid: {
        padding: {
          top: -10,
          bottom: -15,
          left: -10,
          right: -10
        }
      },
      states: {
        hover: {
          filter: {
            type: 'none'
          }
        },
        active: {
          filter: {
            type: 'none'
          }
        }
      }
    };
    
    if (typeof weeklyExpensesEl !== 'undefined' && weeklyExpensesEl !== null) {
      weeklyExpenses = new ApexCharts(weeklyExpensesEl, weeklyExpensesConfig);
      weeklyExpenses.render();
    }
  }

  /**
   * Update Order Statistics Chart with new data
   */
  function updateOrderStatisticsChart(data) {
    // Check if chart element exists
    const chartElement = document.getElementById('orderStatisticsChart');
    if (!chartElement) {
      console.warn('updateOrderStatisticsChart: Chart element not found in DOM');
      return;
    }

    // Check if chart instance exists, if not try to initialize it
    if (!orderStatisticsChart) {
      console.warn('updateOrderStatisticsChart: Chart instance not found, attempting to initialize...');
      try {
        initializeOrderStatisticsChart();
        // Wait a bit for initialization to complete
        setTimeout(() => {
          if (orderStatisticsChart) {
            updateOrderStatisticsChart(data);
          }
        }, 100);
      } catch (error) {
        console.error('updateOrderStatisticsChart: Failed to initialize chart:', error);
      }
      return;
    }

    // Handle undefined, null, or non-array data
    if (!data || !Array.isArray(data) || data.length === 0) {
      console.warn('updateOrderStatisticsChart: Invalid or empty data provided');
      return;
    }

    // Validate data structure and filter out invalid items
    const validData = data.filter(item => {
      return item && 
             typeof item === 'object' && 
             item.program_name && 
             item.student_count !== undefined;
    });

    if (validData.length === 0) {
      console.warn('updateOrderStatisticsChart: No valid data items found');
      return;
    }

    try {
      // Enhanced data sanitization
      const sanitizedData = validData.map(item => {
        const sanitized = {
          program_name: String(item.program_name || 'Unknown').trim(),
          student_count: Math.max(0, parseInt(item.student_count) || 0)
        };
        
        // Ensure program_name is not empty
        if (!sanitized.program_name || sanitized.program_name === 'Unknown') {
          sanitized.program_name = `Program ${Math.random().toString(36).substr(2, 5)}`;
        }
        
        return sanitized;
      }).filter(item => item.student_count >= 0);

      if (sanitizedData.length === 0) {
        console.warn('updateOrderStatisticsChart: No valid data after sanitization');
        return;
      }

      const labels = sanitizedData.map(item => item.program_name);
      const series = sanitizedData.map(item => item.student_count);
      
      // Debug logging with detailed data inspection
      console.log('updateOrderStatisticsChart: Sanitized data:', {
        labels: labels,
        series: series,
        originalLength: validData.length,
        sanitizedLength: sanitizedData.length
      });
      
      // Deep validation of each data point
      console.log('updateOrderStatisticsChart: Detailed data inspection:', {
        labels: labels.map((label, index) => ({ index, label, type: typeof label, length: label ? label.length : 0 })),
        series: series.map((value, index) => ({ index, value, type: typeof value, isNaN: isNaN(value) }))
      });

      // Validate that all series values are numbers
      const hasValidSeries = series.every(val => typeof val === 'number' && !isNaN(val) && val >= 0);
      if (!hasValidSeries) {
        console.error('updateOrderStatisticsChart: Invalid series data detected:', series);
        return;
      }
      
      // Additional validation for labels
      const hasValidLabels = labels.every(label => typeof label === 'string' && label.length > 0);
      if (!hasValidLabels) {
        console.error('updateOrderStatisticsChart: Invalid labels detected:', labels);
        return;
      }
      
      // Ensure arrays have the same length
      if (labels.length !== series.length) {
        console.error('updateOrderStatisticsChart: Mismatched array lengths:', {
          labelsLength: labels.length,
          seriesLength: series.length
        });
        return;
      }
      
      // Ensure we have at least one data point
      if (labels.length === 0 || series.length === 0) {
        console.warn('updateOrderStatisticsChart: No data to display');
        return;
      }

      // Safe color configuration with fallbacks
      const colors = [
        (config && config.colors && config.colors.primary) || '#696cff',
        (config && config.colors && config.colors.secondary) || '#8592a3',
        (config && config.colors && config.colors.success) || '#71dd37',
        (config && config.colors && config.colors.info) || '#03c3ec',
        (config && config.colors && config.colors.warning) || '#ffab00',
        '#ff3e1d',
        '#ff5722',
        '#9c27b0',
        '#607d8b',
        '#795548'
      ];

      // Instead of updating, always recreate the chart to avoid ApexCharts internal errors
      console.log('updateOrderStatisticsChart: Recreating chart with new data...');
      
      // Destroy existing chart if it exists
      if (orderStatisticsChart) {
        try {
          orderStatisticsChart.destroy();
          console.log('updateOrderStatisticsChart: Existing chart destroyed successfully');
        } catch (destroyError) {
          console.warn('updateOrderStatisticsChart: Error destroying existing chart:', destroyError);
        }
        orderStatisticsChart = null;
      }
      
      // Verify chart element still exists before recreating
      const chartElement = document.getElementById('orderStatisticsChart');
      if (!chartElement) {
        console.error('updateOrderStatisticsChart: Chart element not found, cannot recreate chart');
        return;
      }
      
      // Create a new chart with the sanitized data (with small delay to ensure DOM is ready)
      setTimeout(() => {
        try {
          initializeOrderStatisticsChartWithData(sanitizedData);
          console.log('updateOrderStatisticsChart: Chart recreated successfully');
        } catch (recreateError) {
          console.error('updateOrderStatisticsChart: Error recreating chart:', recreateError);
          // Last resort: try with empty data
          try {
            initializeOrderStatisticsChartWithData([]);
          } catch (finalError) {
            console.error('updateOrderStatisticsChart: Final fallback failed:', finalError);
          }
        }
      }, 50); // Small delay to ensure DOM is ready
    } catch (error) {
      console.error('updateOrderStatisticsChart: Error updating chart:', error);
      console.error('updateOrderStatisticsChart: Error details:', {
        message: error.message,
        stack: error.stack,
        validData: validData
      });
      
      // Try to reinitialize the chart as a fallback
      try {
        console.log('Attempting to reinitialize chart with data...');
        // Destroy existing chart first
        if (orderStatisticsChart) {
          try {
            orderStatisticsChart.destroy();
          } catch (destroyError) {
            console.warn('Error destroying chart during fallback:', destroyError);
          }
          orderStatisticsChart = null;
        }
        initializeOrderStatisticsChartWithData(validData);
      } catch (reinitError) {
        console.error('updateOrderStatisticsChart: Failed to reinitialize chart:', reinitError);
        // Last resort: show empty chart
        try {
          if (orderStatisticsChart) {
            orderStatisticsChart.destroy();
            orderStatisticsChart = null;
          }
          initializeOrderStatisticsChartWithData([]);
        } catch (finalError) {
          console.error('updateOrderStatisticsChart: Final fallback failed:', finalError);
        }
      }
    }
  }

  /**
   * Refresh all charts
   */
  function refreshCharts() {
    // Destroy existing charts
    destroyCharts();
    
    // Reinitialize all charts
    initializeAllCharts();
    
    // Reload dashboard data if function exists
    if (typeof loadDashboardData === 'function') {
      loadDashboardData();
    }
  }

  /**
   * Destroy all charts (cleanup)
   */
  function destroyCharts() {
    if (orderStatisticsChart) {
      orderStatisticsChart.destroy();
      orderStatisticsChart = null;
    }
    
    if (totalRevenueChart) {
      totalRevenueChart.destroy();
      totalRevenueChart = null;
    }
    
    if (growthChart) {
      growthChart.destroy();
      growthChart = null;
    }
    
    if (profileReportChart) {
      profileReportChart.destroy();
      profileReportChart = null;
    }
    
    if (incomeChart) {
      incomeChart.destroy();
      incomeChart = null;
    }
    
    if (weeklyExpenses) {
      weeklyExpenses.destroy();
      weeklyExpenses = null;
    }
  }

  /**
   * Update growth chart with new data
   */
  function updateGrowthChart(data) {
    if (!growthChart) return;
    
    if (data && data.growth_rate !== undefined) {
      const growthRate = data.growth_rate || 0;
      const label = data.label || 'Growth';
      
      // Determine color based on growth rate
      let chartColor = config.colors.primary;
      if (growthRate >= 80) {
        chartColor = '#10b981'; // Green for high growth
      } else if (growthRate >= 50) {
        chartColor = '#f59e0b'; // Yellow for medium growth
      } else if (growthRate >= 20) {
        chartColor = '#ef4444'; // Red for low growth
      } else {
        chartColor = '#6b7280'; // Gray for very low growth
      }
      
      growthChart.updateOptions({
        series: [growthRate],
        labels: [label],
        colors: [chartColor],
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'dark',
            shadeIntensity: 0.5,
            gradientToColors: [chartColor],
            inverseColors: true,
            opacityFrom: 1,
            opacityTo: 0.6,
            stops: [30, 70, 100]
          }
        }
      });
    }
  }

  // Export functions for global access
  window.initializeOrderStatisticsChart = initializeOrderStatisticsChart;
  window.updateOrderStatisticsChart = updateOrderStatisticsChart;
  window.updateGrowthChart = updateGrowthChart;
  window.loadGrowthData = loadGrowthData;
  window.refreshCharts = refreshCharts;
  window.destroyCharts = destroyCharts;
  window.initializeAllCharts = initializeAllCharts;
})();