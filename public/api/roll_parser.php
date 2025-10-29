<?php
/**
 * Roll Number Parser for College Attendance System
 * Parses roll number format: YY-[E]PROGRAM-NN
 * Extracts: admission year (YY), shift (E=Evening, none=Morning), program code, sequence number
 */

require_once __DIR__ . '/../includes/academic.php';

class RollParser {
    
    /**
     * Parse roll number format: YY-[E]PROGRAM-NN
     * 
     * @param string $roll_number Roll number to parse (e.g., "25-SWT-01", "24-ECSE-02")
     * @return array Parsed roll number information
     */
    public static function parseRollNumber($roll_number) {
        if (empty($roll_number) || !is_string($roll_number)) {
            return [
                'valid' => false,
                'error' => 'Invalid roll number format'
            ];
        }
        
        // Clean the roll number
        $roll_number = strtoupper(trim($roll_number));
        // Normalize unicode dashes to '-'
        $roll_number = preg_replace('/[\x{2012}-\x{2015}]/u', '-', $roll_number);
        
        // Pattern: XX-XXX-XX..XXXXXX (2–6 digit serial)
        // XX: 2-digit year
        // PROGRAM: 3-4 character program code
        // Serial: 2 to 6 digits
        $pattern = '/^(\d{2})-([A-Za-z]{3,4})-([0-9]{2,6})$/';
        
        if (!preg_match($pattern, $roll_number, $matches)) {
            return [
                'valid' => false,
                'error' => 'Invalid roll number format. Expected: XX-XXX-XX to XX-XXXX-XXXXXX (2–6 digit serial)'
            ];
        }
        
        $year_str = $matches[1];
        $program = $matches[2];
        $sequence = $matches[3];
        
        // Parse year (convert to full year)
        $admission_year = intval($year_str);
        if ($admission_year < 20) {
            $admission_year += 2000;
        } else {
            $admission_year += 2000;
        }
        
        // Shift not encoded in new format; default empty
        $shift = '';
        
        // Parse sequence number
        $sequence_num = intval($sequence);
        
        return [
            'valid' => true,
            'roll_number' => $roll_number,
            'admission_year' => $admission_year,
            'shift' => $shift,
            'program' => $program,
            'sequence_number' => $sequence_num,
            'is_evening' => false
        ];
    }
    
    /**
     * Calculate current academic year based on admission year and current date
     * 
     * @param int $admission_year Year of admission
     * @param DateTime|null $current_date Current date. Defaults to now
     * @return int Current academic year (1-4)
     */
    public static function getStudentYear($admission_year, $current_date = null) {
        if ($current_date === null) {
            $current_date = new DateTime();
        }
        
        return compute_year_of_study($admission_year, $current_date);
    }
    
    /**
     * Extract shift from roll number
     * 
     * @param string $roll_number Roll number to parse
     * @return string 'Morning' or 'Evening'
     */
    public static function getShift($roll_number) {
        $parsed = self::parseRollNumber($roll_number);
        if (!$parsed['valid']) {
            return ''; // Unknown
        }
        
        return $parsed['shift'];
    }
    
    /**
     * Extract program code from roll number
     * 
     * @param string $roll_number Roll number to parse
     * @return string Program code
     */
    public static function getProgram($roll_number) {
        $parsed = self::parseRollNumber($roll_number);
        if (!$parsed['valid']) {
            return 'UNKNOWN';
        }
        
        return $parsed['program'];
    }
    
    /**
     * Validate roll number format
     * 
     * @param string $roll_number Roll number to validate
     * @return bool True if valid, False otherwise
     */
    public static function validateRollNumber($roll_number) {
        $parsed = self::parseRollNumber($roll_number);
        return $parsed['valid'];
    }
    
    /**
     * Get comprehensive academic year information for a roll number
     * 
     * @param string $roll_number Roll number to analyze
     * @param DateTime|null $current_date Current date. Defaults to now
     * @return array Academic year information
     */
    public static function getAcademicYearInfo($roll_number, $current_date = null) {
        $parsed = self::parseRollNumber($roll_number);
        
        if (!$parsed['valid']) {
            return [
                'valid' => false,
                'error' => $parsed['error']
            ];
        }
        
        $current_year = self::getStudentYear($parsed['admission_year'], $current_date);
        
        return [
            'valid' => true,
            'roll_number' => $roll_number,
            'admission_year' => $parsed['admission_year'],
            'current_year' => $current_year,
            'shift' => $parsed['shift'],
            'program' => $parsed['program'],
            'sequence_number' => $parsed['sequence_number'],
            'is_evening' => $parsed['is_evening'],
            'is_graduated' => $current_year > 4,
            'years_remaining' => max(0, 4 - $current_year)
        ];
    }
    
    /**
     * Get batch information for a given admission year
     * 
     * @param int $admission_year Admission year
     * @return array Batch information
     */
    public static function getBatchInfo($admission_year) {
        $current_date = new DateTime();
        $current_year = self::getStudentYear($admission_year, $current_date);
        
        return [
            'admission_year' => $admission_year,
            'current_year' => $current_year,
            'batch_name' => "Batch {$admission_year}",
            'is_graduated' => $current_year > 4,
            'years_remaining' => max(0, 4 - $current_year)
        ];
    }
    
    /**
     * Get program information based on program code
     * 
     * @param string $program_code Program code (e.g., 'SWT', 'ECSE', 'CS')
     * @return array Program information
     */
    public static function getProgramInfo($program_code) {
        $program_mapping = [
            'SWT' => ['name' => 'Software Technology', 'duration' => 4, 'type' => 'Bachelor'],
            'ECSE' => ['name' => 'Electrical & Computer Systems Engineering', 'duration' => 4, 'type' => 'Bachelor'],
            'CS' => ['name' => 'Computer Science', 'duration' => 4, 'type' => 'Bachelor'],
            'IT' => ['name' => 'Information Technology', 'duration' => 4, 'type' => 'Bachelor'],
            'SE' => ['name' => 'Software Engineering', 'duration' => 4, 'type' => 'Bachelor'],
            'CE' => ['name' => 'Computer Engineering', 'duration' => 4, 'type' => 'Bachelor'],
            'EE' => ['name' => 'Electrical Engineering', 'duration' => 4, 'type' => 'Bachelor'],
            'ME' => ['name' => 'Mechanical Engineering', 'duration' => 4, 'type' => 'Bachelor'],
            'CIVIL' => ['name' => 'Civil Engineering', 'duration' => 4, 'type' => 'Bachelor'],
            'MATH' => ['name' => 'Mathematics', 'duration' => 4, 'type' => 'Bachelor'],
            'PHYS' => ['name' => 'Physics', 'duration' => 4, 'type' => 'Bachelor'],
            'CHEM' => ['name' => 'Chemistry', 'duration' => 4, 'type' => 'Bachelor'],
            'BIO' => ['name' => 'Biology', 'duration' => 4, 'type' => 'Bachelor'],
            'BUS' => ['name' => 'Business Administration', 'duration' => 4, 'type' => 'Bachelor'],
            'ECON' => ['name' => 'Economics', 'duration' => 4, 'type' => 'Bachelor'],
            'PSY' => ['name' => 'Psychology', 'duration' => 4, 'type' => 'Bachelor'],
            'ENG' => ['name' => 'English', 'duration' => 4, 'type' => 'Bachelor'],
            'HIST' => ['name' => 'History', 'duration' => 4, 'type' => 'Bachelor'],
            'ART' => ['name' => 'Art', 'duration' => 4, 'type' => 'Bachelor'],
            'MUS' => ['name' => 'Music', 'duration' => 4, 'type' => 'Bachelor']
        ];
        
        $program_code = strtoupper($program_code);
        $program_info = isset($program_mapping[$program_code]) 
            ? $program_mapping[$program_code] 
            : [
                'name' => "Program {$program_code}",
                'duration' => 4,
                'type' => 'Bachelor'
            ];
        
        return [
            'code' => $program_code,
            'name' => $program_info['name'],
            'duration' => $program_info['duration'],
            'type' => $program_info['type']
        ];
    }
    
    /**
     * Parse multiple roll numbers and return summary statistics
     * 
     * @param array $roll_numbers List of roll numbers to parse
     * @return array Summary statistics
     */
    public static function parseMultipleRollNumbers($roll_numbers) {
        $valid_rolls = [];
        $invalid_rolls = [];
        
        foreach ($roll_numbers as $roll) {
            $parsed = self::parseRollNumber($roll);
            if ($parsed['valid']) {
                $valid_rolls[] = $parsed;
            } else {
                $invalid_rolls[] = [
                    'roll_number' => $roll,
                    'error' => $parsed['error']
                ];
            }
        }
        
        // Analyze valid rolls
        $programs = [];
        $shifts = ['Morning' => 0, 'Evening' => 0];
        $years = [];
        
        foreach ($valid_rolls as $roll) {
            // Count programs
            $program = $roll['program'];
            $programs[$program] = isset($programs[$program]) ? $programs[$program] + 1 : 1;
            
            // Count shifts
            $shifts[$roll['shift']]++;
            
            // Count years
            $year = $roll['admission_year'];
            $years[$year] = isset($years[$year]) ? $years[$year] + 1 : 1;
        }
        
        return [
            'total_rolls' => count($roll_numbers),
            'valid_rolls' => count($valid_rolls),
            'invalid_rolls' => count($invalid_rolls),
            'valid_roll_data' => $valid_rolls,
            'invalid_roll_data' => $invalid_rolls,
            'programs' => $programs,
            'shifts' => $shifts,
            'years' => $years
        ];
    }
}

// Example usage and testing
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    // Test cases
    $test_rolls = [
        "25-SWT-01",      // Valid morning shift
        "24-ECSE-02",     // Valid morning shift
        "23-ECS-03",      // Valid morning shift
        "25-ESWT-04",     // Valid evening shift
        "26-CS-05",       // Valid morning shift
        "invalid-roll",   // Invalid
        "25-SWT",         // Invalid (missing sequence)
        "25-SWT-1",       // Invalid (single digit sequence)
        "5-SWT-01",       // Valid (2005)
        "99-SWT-01",      // Valid (2099)
    ];
    
    echo "Roll Number Parser Test Results\n";
    echo str_repeat("=", 50) . "\n";
    
    foreach ($test_rolls as $roll) {
        $result = RollParser::parseRollNumber($roll);
        echo "Roll: {$roll}\n";
        if ($result['valid']) {
            echo "  Valid: Yes\n";
            echo "  Admission Year: {$result['admission_year']}\n";
            echo "  Shift: {$result['shift']}\n";
            echo "  Program: {$result['program']}\n";
            echo "  Sequence: {$result['sequence_number']}\n";
            
            // Get academic year info
            $academic_info = RollParser::getAcademicYearInfo($roll);
            if ($academic_info['valid']) {
                echo "  Current Year: {$academic_info['current_year']}\n";
                echo "  Graduated: " . ($academic_info['is_graduated'] ? 'Yes' : 'No') . "\n";
            }
        } else {
            echo "  Valid: No - {$result['error']}\n";
        }
        echo "\n";
    }
    
    // Test batch analysis
    echo "Batch Analysis\n";
    echo str_repeat("=", 30) . "\n";
    $batch_info = RollParser::getBatchInfo(2024);
    echo "Batch 2024: Year {$batch_info['current_year']}, Graduated: " . ($batch_info['is_graduated'] ? 'Yes' : 'No') . "\n";
    
    // Test program info
    echo "\nProgram Information\n";
    echo str_repeat("=", 30) . "\n";
    $program_info = RollParser::getProgramInfo('SWT');
    echo "SWT: {$program_info['name']} ({$program_info['type']})\n";
    
    // Test multiple roll parsing
    echo "\nMultiple Roll Analysis\n";
    echo str_repeat("=", 30) . "\n";
    $analysis = RollParser::parseMultipleRollNumbers(array_slice($test_rolls, 0, 5)); // Only valid ones
    echo "Total: {$analysis['total_rolls']}, Valid: {$analysis['valid_rolls']}, Invalid: {$analysis['invalid_rolls']}\n";
    echo "Programs: " . json_encode($analysis['programs']) . "\n";
    echo "Shifts: " . json_encode($analysis['shifts']) . "\n";
    echo "Years: " . json_encode($analysis['years']) . "\n";
}
?>
