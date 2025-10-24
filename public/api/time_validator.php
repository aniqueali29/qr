<?php
/**
 * Time Validator for College Attendance System
 * 
 * DEPRECATED: This file is deprecated and should not be used.
 * All timing validation now uses time_validator_api.php for dynamic settings.
 * 
 * @deprecated Use time_validator_api.php instead
 */

class TimeValidator {
    
    private $timezone;
    
    public function __construct($timezone = 'Asia/Karachi') {
        $this->timezone = new DateTimeZone($timezone);
    }
    
    /**
     * Get timing information for a specific shift
     * 
     * @deprecated Use time_validator_api.php instead
     */
    public function getShiftTimings($shift) {
        throw new Exception("This method is deprecated. Use time_validator_api.php for dynamic timing validation.");
    }
    
    /**
     * Check if current time is within the allowed check-in window for the shift
     * 
     * @deprecated Use time_validator_api.php instead
     */
    public function isWithinCheckinWindow($current_time, $shift) {
        throw new Exception("This method is deprecated. Use time_validator_api.php for dynamic timing validation.");
    }
    
    /**
     * Check if current time is within the allowed check-out window for the shift
     * 
     * @deprecated Use time_validator_api.php instead
     */
    public function isWithinCheckoutWindow($current_time, $shift) {
        throw new Exception("This method is deprecated. Use time_validator_api.php for dynamic timing validation.");
    }
    
    /**
     * Validate if a student can check in at the current time
     * 
     * @deprecated Use time_validator_api.php instead
     */
    public function validateCheckinTime($student_id, $current_time = null, $shift = null) {
        throw new Exception("This method is deprecated. Use time_validator_api.php for dynamic timing validation.");
    }
    
    /**
     * Validate if a student can check out at the current time
     * 
     * @deprecated Use time_validator_api.php instead
     */
    public function validateCheckoutTime($student_id, $current_time = null, $shift = null) {
        throw new Exception("This method is deprecated. Use time_validator_api.php for dynamic timing validation.");
    }
    
    /**
     * Get current shift based on time
     * 
     * @deprecated Use time_validator_api.php instead
     */
    public function getCurrentShift($current_time = null) {
        throw new Exception("This method is deprecated. Use time_validator_api.php for dynamic timing validation.");
    }
    
    /**
     * Check if current time is within any shift's check-in window
     * 
     * @deprecated Use time_validator_api.php instead
     */
    public function isWithinAnyCheckinWindow($current_time = null) {
        throw new Exception("This method is deprecated. Use time_validator_api.php for dynamic timing validation.");
    }
}