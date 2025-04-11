<?php
// Chatbot implementation for the Hospital Management Platform

class Chatbot {
    private $intents = [
        'greeting' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'],
        'hospital_selection' => ['which hospital', 'select hospital', 'best hospital', 'hospital near', 'hospital location'],
        'symptoms' => ['symptom', 'feeling', 'pain', 'discomfort', 'problem'],
        'department_selection' => ['which department', 'select department', 'specialist', 'specialization'],
        'appointment' => ['book appointment', 'schedule appointment', 'make appointment', 'book consultation'],
        'payment' => ['payment', 'pay', 'cost', 'fee', 'charges'],
        'emergency' => ['emergency', 'urgent', 'critical', 'serious']
    ];
    
    private $responses = [
        'greeting' => [
            'Hello! How can I assist you with our hospital services today?',
            'Hi there! Welcome to our hospital management platform. How may I help you?',
            'Greetings! What brings you to our healthcare platform today?'
        ],
        'hospital_selection' => [
            'I can help you choose the right hospital. Please tell me your location or preferred area.',
            'We have multiple hospitals in our network. Would you like to see hospitals based on location or specialization?',
            'To recommend the best hospital, I need to know: 1) Your location 2) Type of medical care needed'
        ],
        'symptoms' => [
            'I understand you\'re not feeling well. Could you describe your symptoms in detail?',
            'To better assist you, please tell me: 1) When did the symptoms start? 2) What symptoms are you experiencing?',
            'I\'ll help you identify the right department based on your symptoms. Please describe what you\'re experiencing.'
        ],
        'department_selection' => [
            'Based on your needs, I can recommend the appropriate department. What type of medical care are you looking for?',
            'We have various specialized departments. Could you tell me about your medical concern?',
            'To suggest the right department, please describe your medical condition or the type of care you need.'
        ],
        'appointment' => [
            'I\'ll help you book an appointment. Do you have a specific doctor or department in mind?',
            'To schedule an appointment, we\'ll need: 1) Preferred hospital 2) Department 3) Convenient date and time',
            'Would you like to see available appointment slots for a specific doctor or department?'
        ],
        'payment' => [
            'We accept various payment methods including UPI, credit/debit cards, and net banking.',
            'The consultation fee varies by doctor. Would you like to know the fees for a specific doctor?',
            'All payments are processed securely. You can pay online or at the hospital.'
        ],
        'emergency' => [
            'If this is a medical emergency, please call emergency services immediately at 102.',
            'For emergencies, please visit the nearest hospital emergency room or call 102.',
            'Medical emergencies require immediate attention. Please call 102 or visit the nearest emergency room.'
        ],
        'default' => [
            'I\'m here to help. Could you please provide more details about your query?',
            'I want to assist you better. Could you elaborate on your question?',
            'Feel free to ask about our hospitals, doctors, appointments, or any other services.'
        ]
    ];
    
    /**
     * Process user message and return appropriate response
     */
    public function getResponse($message) {
        $message = strtolower(trim($message));
        $intent = $this->detectIntent($message);
        
        if ($intent) {
            $responses = $this->responses[$intent];
        } else {
            $responses = $this->responses['default'];
        }
        
        return $responses[array_rand($responses)];
    }
    
    /**
     * Detect intent from user message
     */
    private function detectIntent($message) {
        foreach ($this->intents as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($message, $pattern) !== false) {
                    return $intent;
                }
            }
        }
        return null;
    }
    
    /**
     * Get department suggestions based on symptoms
     */
    public function suggestDepartment($symptoms) {
        $symptoms = strtolower($symptoms);
        
        $departmentMappings = [
            'cardiology' => ['chest pain', 'heart', 'palpitations', 'shortness of breath'],
            'orthopedics' => ['bone', 'joint', 'fracture', 'sprain', 'back pain'],
            'neurology' => ['headache', 'migraine', 'seizure', 'numbness'],
            'gastroenterology' => ['stomach', 'digestion', 'nausea', 'vomiting'],
            'dermatology' => ['skin', 'rash', 'acne', 'itching'],
            'ent' => ['ear', 'nose', 'throat', 'hearing', 'sinus'],
            'ophthalmology' => ['eye', 'vision', 'glasses', 'blurry'],
            'pediatrics' => ['child', 'baby', 'infant', 'vaccination'],
            'gynecology' => ['pregnancy', 'menstrual', 'women\'s health'],
            'psychiatry' => ['anxiety', 'depression', 'stress', 'sleep']
        ];
        
        $suggestions = [];
        foreach ($departmentMappings as $department => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($symptoms, $keyword) !== false) {
                    $suggestions[] = $department;
                    break;
                }
            }
        }
        
        return array_unique($suggestions);
    }
}