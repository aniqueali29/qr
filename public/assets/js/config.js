/**
 * Template customizer and configuration
 */
(function() {
    'use strict';

    // Colors & Variables
    const colors = {
        primary: '#696cff',
        secondary: '#8592a3',
        success: '#71dd37',
        info: '#03c3ec',
        warning: '#ffab00',
        danger: '#ff3e1d',
        dark: '#233446',
        light: '#fcfdfd',
        muted: '#a1acb8'
    };

    // Chart Colors
    const chartColors = {
        column: {
            series1: '#826af9',
            series2: '#d2b0ff',
            bg: '#f8d3ff'
        },
        donut: {
            series1: '#ffeaa7',
            series2: '#fab1a0',
            series3: '#fd79a8',
            series4: '#a29bfe',
            series5: '#6c5ce7'
        },
        area: {
            series1: '#a29bfe',
            series2: '#74b9ff',
            series3: '#00cec9',
            series4: '#00b894',
            series5: '#fdcb6e'
        }
    };

    // Core Components
    const coreComponents = {
        colors: colors,
        chartColors: chartColors
    };

    // Make it globally available
    window.coreComponents = coreComponents;
})();