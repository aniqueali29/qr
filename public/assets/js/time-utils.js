/**
 * Time Format Conversion Utilities
 * Handles conversion between 24-hour and 12-hour time formats
 */

/**
 * Convert 24-hour time to 12-hour format with AM/PM
 * @param {string} time24 - Time in 24-hour format (HH:MM:SS or HH:MM)
 * @returns {string} Time in 12-hour format (H:MM AM/PM)
 */
function convert24to12(time24) {
    if (!time24) return '';
    
    // Handle different input formats
    let timeStr = time24.toString().trim();
    
    // If already in 12-hour format, return as is
    if (timeStr.includes('AM') || timeStr.includes('PM')) {
        return timeStr;
    }
    
    // Extract hours and minutes
    const timeParts = timeStr.split(':');
    if (timeParts.length < 2) return time24;
    
    let hours = parseInt(timeParts[0], 10);
    const minutes = timeParts[1];
    const seconds = timeParts[2] || '00';
    
    // Determine AM/PM
    const period = hours >= 12 ? 'PM' : 'AM';
    
    // Convert to 12-hour format
    if (hours === 0) {
        hours = 12; // Midnight
    } else if (hours > 12) {
        hours = hours - 12;
    }
    
    // Don't pad hours for display in input fields (we want "9:00" not "09:00")
    const formattedHours = hours.toString();
    
    return `${formattedHours}:${minutes} ${period}`;
}

/**
 * Convert 12-hour time to 24-hour format
 * @param {string} time12 - Time in 12-hour format (H:MM AM/PM)
 * @returns {string} Time in 24-hour format (HH:MM:SS)
 */
function convert12to24(time12) {
    if (!time12) return '';
    
    const timeStr = time12.toString().trim();
    
    // If already in 24-hour format, return as is
    if (!timeStr.includes('AM') && !timeStr.includes('PM')) {
        return timeStr.includes(':') ? timeStr : timeStr + ':00';
    }
    
    // Parse 12-hour format
    const match = timeStr.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    if (!match) return time12;
    
    let hours = parseInt(match[1], 10);
    const minutes = match[2];
    const period = match[3].toUpperCase();
    
    // Convert to 24-hour format
    if (period === 'AM') {
        if (hours === 12) hours = 0; // Midnight
    } else { // PM
        if (hours !== 12) hours = hours + 12;
    }
    
    // Format with leading zero
    const formattedHours = hours.toString().padStart(2, '0');
    
    return `${formattedHours}:${minutes}:00`;
}

/**
 * Format time for HTML time input (24-hour format)
 * @param {string} time - Time in any format
 * @returns {string} Time in HH:MM format for HTML input
 */
function formatTimeInput(time) {
    if (!time) return '';
    
    const time24 = convert12to24(time);
    return time24.substring(0, 5); // Return HH:MM format
}

/**
 * Parse various time formats and return 24-hour format
 * @param {string} timeString - Time in various formats
 * @returns {string} Time in 24-hour format (HH:MM:SS)
 */
function parseTimeWithAMPM(timeString) {
    if (!timeString) return '';
    
    const timeStr = timeString.toString().trim();
    
    // If already 24-hour format
    if (!timeStr.includes('AM') && !timeStr.includes('PM')) {
        return timeStr.includes(':') ? timeStr : timeStr + ':00';
    }
    
    return convert12to24(timeStr);
}

/**
 * Format time for display in 12-hour format
 * @param {string} time - Time in any format
 * @returns {string} Time in 12-hour format for display
 */
function formatTimeDisplay(time) {
    if (!time) return '';
    return convert24to12(time);
}

/**
 * Create time input HTML with AM/PM dropdown
 * @param {string} id - Input ID
 * @param {string} value - Current value
 * @param {string} placeholder - Placeholder text
 * @returns {string} HTML for time input with AM/PM
 */
function createTimeInputHTML(id, value = '', placeholder = '') {
    const time12 = convert24to12(value);
    const timeParts = time12.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    
    let hours = '9';
    let minutes = '00';
    let period = 'AM';
    
    if (timeParts) {
        hours = timeParts[1]; // Don't pad - keep as 1-12
        minutes = timeParts[2];
        period = timeParts[3].toUpperCase();
    }
    
    return `
        <div class="input-group">
            <input type="text" class="form-control time-input" id="${id}" 
                   value="${hours}:${minutes}" placeholder="${placeholder}"
                   pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5">
            <select class="form-select time-period" id="${id}_period">
                <option value="AM" ${period === 'AM' ? 'selected' : ''}>AM</option>
                <option value="PM" ${period === 'PM' ? 'selected' : ''}>PM</option>
            </select>
        </div>
    `;
}

/**
 * Get combined time value from time input and period select
 * @param {string} inputId - Time input ID
 * @returns {string} Combined time in 12-hour format
 */
function getTimeInputValue(inputId) {
    const timeInput = document.getElementById(inputId);
    const periodSelect = document.getElementById(inputId + '_period');
    
    if (!timeInput || !periodSelect) return '';
    
    const time = timeInput.value.trim();
    const period = periodSelect.value;
    
    if (!time) return '';
    
    return `${time} ${period}`;
}

/**
 * Set time input value from 24-hour format
 * @param {string} inputId - Time input ID
 * @param {string} time24 - Time in 24-hour format
 */
function setTimeInputValue(inputId, time24) {
    const time12 = convert24to12(time24);
    const timeParts = time12.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    
    if (timeParts) {
        const timeInput = document.getElementById(inputId);
        const periodSelect = document.getElementById(inputId + '_period');
        
        if (timeInput) {
            timeInput.value = `${timeParts[1]}:${timeParts[2]}`;
        }
        if (periodSelect) {
            periodSelect.value = timeParts[3].toUpperCase();
        }
    }
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        convert24to12,
        convert12to24,
        formatTimeInput,
        parseTimeWithAMPM,
        formatTimeDisplay,
        createTimeInputHTML,
        getTimeInputValue,
        setTimeInputValue
    };
}
