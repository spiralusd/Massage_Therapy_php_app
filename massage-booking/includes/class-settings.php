<?php
// includes/class-settings.php

class Massage_Booking_Settings {
    
    private $options_prefix = 'massage_booking_';
    
    public function set_defaults() {
        // Set default working days (Mon-Fri)
        if (!get_option($this->options_prefix . 'working_days')) {
            update_option($this->options_prefix . 'working_days', ['1', '2', '3', '4', '5']);
        }
        
        // Set default break time (15 minutes)
        if (!get_option($this->options_prefix . 'break_time')) {
            update_option($this->options_prefix . 'break_time', 15);
        }
        
        // Set default time slot interval (30 minutes)
        if (!get_option($this->options_prefix . 'time_slot_interval')) {
            update_option($this->options_prefix . 'time_slot_interval', 30);
        }
        
        // Set default service durations
        if (!get_option($this->options_prefix . 'durations')) {
            update_option($this->options_prefix . 'durations', ['60', '90', '120']);
        }
        
        // Set default prices
        if (!get_option($this->options_prefix . 'prices')) {
            update_option($this->options_prefix . 'prices', [
                '60' => 95,
                '90' => 125,
                '120' => 165
            ]);
        }
        
        // Set default schedule
        if (!get_option($this->options_prefix . 'schedule')) {
            $default_schedule = [
                'monday' => [['from' => '09:00', 'to' => '18:00']],
                'tuesday' => [['from' => '09:00', 'to' => '18:00']],
                'wednesday' => [['from' => '09:00', 'to' => '18:00']],
                'thursday' => [['from' => '09:00', 'to' => '18:00']],
                'friday' => [['from' => '09:00', 'to' => '18:00']],
                'saturday' => [],
                'sunday' => []
            ];
            
            update_option($this->options_prefix . 'schedule', $default_schedule);
        }
    }
    
    public function get_setting($key, $default = null) {
        $value = get_option($this->options_prefix . $key, $default);
        error_log("Getting setting {$key}: " . json_encode($value));
        return $value;
    }
    
    public function update_setting($key, $value) {
        return update_option($this->options_prefix . $key, $value);
    }
    
    public function get_all_settings() {
        return [
            'working_days' => $this->get_setting('working_days', ['1', '2', '3', '4', '5']),
            'break_time' => $this->get_setting('break_time', 15),
            'time_slot_interval' => $this->get_setting('time_slot_interval', 30),
            'durations' => $this->get_setting('durations', ['60', '90', '120']),
            'prices' => $this->get_setting('prices', [
                '60' => 95,
                '90' => 125,
                '120' => 165
            ]),
            'schedule' => $this->get_setting('schedule', []),
            'business_name' => $this->get_setting('business_name', 'Massage Therapy Practice'),
            'business_email' => $this->get_setting('business_email', get_option('admin_email'))
        ];
    }
    
    public function get_public_settings() {
        // Return only settings needed for the public booking form
        return [
            'working_days' => $this->get_setting('working_days', ['1', '2', '3', '4', '5']),
            'break_time' => $this->get_setting('break_time', 15),
            'durations' => $this->get_setting('durations', ['60', '90', '120']),
            'prices' => $this->get_setting('prices', [
                '60' => 95,
                '90' => 125,
                '120' => 165
            ]),
            'business_name' => $this->get_setting('business_name', 'Massage Therapy Practice')
        ];
    }
    
}