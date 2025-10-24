// Minimal Student Modal logic for roll-number autofill on Students page
// Ensures correct field IDs and year-level mapping

(function () {
  'use strict';

  // Program Code Utility Functions
  window.ProgramCodeUtils = {
    /**
     * Get program code for specific shift
     * @param {string} baseCode - Base program code (e.g., "SWT", "CIT")
     * @param {string} shift - Shift ("Morning" or "Evening")
     * @returns {string} - Computed program code
     */
    getProgramCodeForShift: function(baseCode, shift) {
      if (shift === 'Evening') {
        return 'E' + baseCode;
      }
      return baseCode;
    },

    /**
     * Extract base program code from display code
     * @param {string} displayCode - Display program code (e.g., "ESWT", "SWT")
     * @returns {string} - Base program code
     */
    getBaseProgramCode: function(displayCode) {
      if (displayCode && displayCode.startsWith('E')) {
        return displayCode.substring(1);
      }
      return displayCode;
    },

    /**
     * Check if a program code is for evening shift
     * @param {string} code - Program code to check
     * @returns {boolean} - True if evening code
     */
    isEveningCode: function(code) {
      return code && code.startsWith('E');
    }
  };

  function mapYearNumericToLabel(n) {
    if (n === 1) return '1st';
    if (n === 2) return '2nd';
    if (n === 3) return '3rd';
    if (n >= 4) return 'Completed'; // For completed students
    return '';
  }

  function clearAutoFilledFields() {
    const programSelect = document.getElementById('student-program');
    const shiftSelect = document.getElementById('student-shift');
    const yearLevelSelect = document.getElementById('student-year');
    const admissionYearInput = document.getElementById('student-admission-year');
    const sectionSelect = document.getElementById('student-section');
    
    // Clear selections and reset styling
    if (programSelect) {
      programSelect.selectedIndex = 0;
      programSelect.style.backgroundColor = '';
      programSelect.style.borderColor = '';
    }
    if (shiftSelect) {
      shiftSelect.selectedIndex = 0;
      shiftSelect.style.backgroundColor = '';
      shiftSelect.style.borderColor = '';
    }
    if (yearLevelSelect) {
      yearLevelSelect.selectedIndex = 0;
      yearLevelSelect.style.backgroundColor = '';
      yearLevelSelect.style.borderColor = '';
    }
    if (admissionYearInput) {
      admissionYearInput.value = '';
      admissionYearInput.style.backgroundColor = '';
      admissionYearInput.style.borderColor = '';
    }
    if (sectionSelect) {
      sectionSelect.innerHTML = '<option value="">Select Section</option>';
    }
  }

  async function parseRollNumber(rollNumber) {
    if (!rollNumber || rollNumber.length < 5) return;

    // Clear previous auto-filled values to ensure clean state
    clearAutoFilledFields();

    try {
      const res = await fetch(`../api/roll_parser_simple.php?action=parse_roll&roll_number=${encodeURIComponent(rollNumber)}`);
      const result = await res.json();

      const statusDiv = document.getElementById('roll-number-status');
      const programSelect = document.getElementById('student-program');
      const shiftSelect = document.getElementById('student-shift');
      const yearLevelSelect = document.getElementById('student-year');
      const admissionYearInput = document.getElementById('student-admission-year');
      const sectionSelect = document.getElementById('student-section');

      if (!result.success) {
        // Fallback: client-side parse and compute year level
        clientSideFillFromRoll(rollNumber);
        if (statusDiv) {
          statusDiv.className = 'text-warning';
          statusDiv.style.display = 'block';
          statusDiv.textContent = (result.error || 'Parsed locally') + ' (fallback)';
        }
        return;
      }

      const data = result.data || {};

      // Admission year
      if (admissionYearInput && data.admission_year) {
        admissionYearInput.value = data.admission_year;
        admissionYearInput.style.backgroundColor = '#e8f5e8';
        admissionYearInput.style.borderColor = '#4caf50';
      }

      // Program
      if (programSelect && data.program_code) {
        for (let opt of programSelect.options) {
          if (opt.value === data.program_code) {
            opt.selected = true;
            programSelect.style.backgroundColor = '#e8f5e8';
            programSelect.style.borderColor = '#4caf50';
            break;
          }
        }
      }

      // Shift
      if (shiftSelect && data.shift) {
        for (let opt of shiftSelect.options) {
          if (opt.value === data.shift) {
            opt.selected = true;
            shiftSelect.style.backgroundColor = '#e8f5e8';
            shiftSelect.style.borderColor = '#4caf50';
            break;
          }
        }
      }

      // Year Level (accept string or numeric) with fallback
      if (yearLevelSelect) {
        let desired = data.year_level || mapYearNumericToLabel(Number(data.year_level_numeric));
        if (!desired) {
          desired = mapYearNumericToLabel(computeYearFromRoll(rollNumber));
        }
        
        console.log('Year level update:', {
          rollNumber: rollNumber,
          dataYearLevel: data.year_level,
          dataYearLevelNumeric: data.year_level_numeric,
          computedDesired: desired,
          availableOptions: Array.from(yearLevelSelect.options).map(o => o.value)
        });
        
        if (desired) {
          let found = false;
          for (let opt of yearLevelSelect.options) {
            if (opt.value === desired) {
              opt.selected = true;
              yearLevelSelect.style.backgroundColor = '#e8f5e8';
              yearLevelSelect.style.borderColor = '#4caf50';
              console.log('Year level selected:', opt.value, opt.text);
              found = true;
              break;
            }
          }
          if (!found) {
            console.warn('Year level option not found:', desired);
          }
        } else {
          console.warn('No year level determined for roll number:', rollNumber);
        }
      }

      // Sections (optional)
      if (sectionSelect && Array.isArray(data.available_sections)) {
        sectionSelect.innerHTML = '<option value="">Select Section</option>';
        data.available_sections.forEach(sec => {
          const o = document.createElement('option');
          o.value = sec.id;
          o.textContent = `${sec.section_name} (${sec.current_students}/${sec.capacity})`;
          sectionSelect.appendChild(o);
        });
      }

      if (statusDiv) {
        statusDiv.className = 'text-success';
        statusDiv.style.display = 'block';
        statusDiv.textContent = `Auto-filled: ${data.program_name || data.program_code || ''} - ${data.shift || ''} - ${data.year_level || mapYearNumericToLabel(Number(data.year_level_numeric))}`;
      }

    } catch (e) {
      // Hard fallback on network or server error
      clientSideFillFromRoll(rollNumber);
      const statusDiv = document.getElementById('roll-number-status');
      if (statusDiv) {
        statusDiv.className = 'text-warning';
        statusDiv.style.display = 'block';
        statusDiv.textContent = 'Parsed locally (server unavailable)';
      }
    }
  }

  function computeYearFromRoll(roll) {
    // Expect YY-...-NN or YY-E...-NN (allow 2-3 digit serial numbers)
    const m = /^(\d{2})-E?[A-Za-z]{2,10}-(\d{2,3})$/.exec(roll.trim());
    if (!m) return 0;
    
    const yy = parseInt(m[1], 10);
    const admissionYear = 2000 + yy;
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1; // 1-12
    
    // Calculate years difference from admission year
    const yearsDifference = currentYear - admissionYear;
    
    // Academic year progression logic:
    // - If current month is September or later, students progress to next year
    // - If current month is before September, they're still in the same academic year
    
    let yearLevel;
    if (currentMonth >= 9) {
      // After September: students have progressed to the next academic year
      yearLevel = yearsDifference + 1;
    } else {
      // Before September: students are still in the same academic year
      yearLevel = yearsDifference;
    }
    
    // For completed students (3+ years), return 4 to indicate completion
    if (yearsDifference >= 3) {
      return 4; // This will be mapped to 'Completed' by mapYearNumericToLabel
    }
    
    // Ensure year level is within valid range (1-3) for active students
    yearLevel = Math.max(1, Math.min(yearLevel, 3));
    
    return yearLevel;
  }

  function clientSideFillFromRoll(roll) {
    const programSelect = document.getElementById('student-program');
    const shiftSelect = document.getElementById('student-shift');
    const yearLevelSelect = document.getElementById('student-year');
    const admissionYearInput = document.getElementById('student-admission-year');
    const m = /^(\d{2})-(E)?([A-Za-z]{2,10})-(\d{2,3})$/.exec(roll.trim());
    if (!m) return;
    const yy = parseInt(m[1], 10);
    const isE = !!m[2];
    const programCode = m[3].toUpperCase();
    const admissionYear = 2000 + yy;
    const shift = isE ? 'Evening' : 'Morning';

    if (admissionYearInput) {
      admissionYearInput.value = admissionYear;
      admissionYearInput.style.backgroundColor = '#e8f5e8';
      admissionYearInput.style.borderColor = '#4caf50';
    }
    if (programSelect) {
      for (let opt of programSelect.options) {
        if (opt.value === programCode) {
          opt.selected = true;
          programSelect.style.backgroundColor = '#e8f5e8';
          programSelect.style.borderColor = '#4caf50';
          break;
        }
      }
    }
    if (shiftSelect) {
      for (let opt of shiftSelect.options) {
        if (opt.value === shift) {
          opt.selected = true;
          shiftSelect.style.backgroundColor = '#e8f5e8';
          shiftSelect.style.borderColor = '#4caf50';
          break;
        }
      }
    }
    if (yearLevelSelect) {
      const desired = mapYearNumericToLabel(computeYearFromRoll(roll));
      for (let opt of yearLevelSelect.options) {
        if (opt.value === desired) {
          opt.selected = true;
          yearLevelSelect.style.backgroundColor = '#e8f5e8';
          yearLevelSelect.style.borderColor = '#4caf50';
          break;
        }
      }
    }
  }

  function setupRollNumberAutoFill() {
    const rollInput = document.getElementById('student-roll');
    if (!rollInput) return;

    let debounceId;
    rollInput.addEventListener('input', function () {
      clearTimeout(debounceId);
      const val = this.value.trim();
      if (!val) return;
      // Basic pattern check before calling API:  YY-PROG-NN or YY-EPROG-NN
      const pat = /^(\d{2})-E?[A-Za-z]{2,10}-(\d{2})$/;
      debounceId = setTimeout(() => {
        if (pat.test(val)) parseRollNumber(val);
      }, 400);
    });

    rollInput.addEventListener('blur', function () {
      const val = this.value.trim();
      if (val) parseRollNumber(val);
    });

    rollInput.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') {
        const val = this.value.trim();
        if (val) parseRollNumber(val);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', setupRollNumberAutoFill);
})();


