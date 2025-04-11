<?php
// Enhanced Chatbot implementation with advanced medical assistance capabilities

require_once __DIR__ . '/chatbot_brain.php';

class Chatbot {
    private $brain;
    private $userId;
    
    public function __construct() {
        $this->brain = new ChatbotBrain();
        $this->userId = session_id() ?: uniqid('user_');
    }
    
    /**
     * Process user message and return appropriate response
     */
    public function getResponse($message) {
        $message = trim($message);
        $response = $this->brain->processInput($message, $this->userId);
        
        // Format the response for the frontend
        $formattedResponse = $this->formatResponse($response);
        
        // If there are department suggestions from symptoms, add them
        if (isset($response['action']) && $response['action'] === 'suggest_department') {
            $formattedResponse .= "\n\nRecommended departments: " . implode(', ', $response['data']['departments']);
        }
        
        return $formattedResponse;
    }
    
    /**
     * Format the brain's response for display
     */
    private function formatResponse($response) {
        $message = $response['message'];
        
        // Add interactive suggestions if available
        if (!empty($response['suggestions'])) {
            $message .= "\n\nQuick actions:\n- " . implode("\n- ", $response['suggestions']);
        }
        
        return $message;
    }
    
    /**
     * Get suggestions for user input
     */
    public function getSuggestions($context = null) {
        $commonSuggestions = [
            'Book an appointment',
            'Check my symptoms',
            'Find a doctor',
            'View departments',
            'Emergency services'
        ];
        
        return $commonSuggestions;
    }
}