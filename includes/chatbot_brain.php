<?php
// Advanced chatbot brain implementation with medical knowledge and context management

class ChatbotBrain {
    private $medicalKnowledge = [
        'symptoms' => [
            'fever' => ['temperature', 'chills', 'sweating', 'hot', 'cold'],
            'pain' => ['ache', 'hurt', 'sore', 'discomfort', 'burning'],
            'respiratory' => ['cough', 'breathing', 'shortness of breath', 'wheezing'],
            'digestive' => ['nausea', 'vomiting', 'diarrhea', 'stomach', 'appetite'],
            'neurological' => ['headache', 'dizziness', 'confusion', 'fainting'],
            'musculoskeletal' => ['joint', 'muscle', 'back', 'neck', 'weakness'],
            'skin' => ['rash', 'itching', 'swelling', 'bruising', 'wound'],
            'psychological' => ['anxiety', 'depression', 'stress', 'mood', 'sleep']
        ],
        'urgency_levels' => [
            'emergency' => ['severe', 'extreme', 'unbearable', 'critical', 'life-threatening'],
            'urgent' => ['acute', 'serious', 'worsening', 'concerning'],
            'non_urgent' => ['mild', 'minor', 'slight', 'occasional']
        ],
        'medical_conditions' => [
            'chronic' => ['diabetes', 'hypertension', 'asthma', 'arthritis'],
            'acute' => ['infection', 'injury', 'allergy', 'poisoning'],
            'preventive' => ['vaccination', 'screening', 'checkup', 'wellness']
        ]
    ];

    private $contextManager = [];
    private $conversationFlow = [];

    public function processInput($message, $userId = null) {
        $intent = $this->analyzeIntent($message);
        $entities = $this->extractEntities($message);
        $urgency = $this->assessUrgency($message);

        $this->updateContext($userId, [
            'last_intent' => $intent,
            'entities' => $entities,
            'urgency' => $urgency
        ]);

        return $this->generateResponse($intent, $entities, $urgency, $userId);
    }

    private function analyzeIntent($message) {
        $message = strtolower($message);
        $intents = [
            'symptom_check' => ['feel', 'experiencing', 'symptom', 'problem', 'suffering'],
            'appointment' => ['book', 'schedule', 'appointment', 'visit', 'see doctor'],
            'emergency' => ['emergency', 'urgent', 'immediate', 'severe'],
            'information' => ['info', 'about', 'what is', 'tell me', 'explain'],
            'location' => ['where', 'location', 'address', 'directions'],
            'timing' => ['when', 'hours', 'open', 'available', 'timing'],
            'cost' => ['cost', 'price', 'fee', 'charge', 'payment']
        ];

        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    private function extractEntities($message) {
        $entities = [];
        
        // Extract symptoms
        foreach ($this->medicalKnowledge['symptoms'] as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos(strtolower($message), $keyword) !== false) {
                    $entities['symptoms'][] = [
                        'category' => $category,
                        'keyword' => $keyword
                    ];
                }
            }
        }

        // Extract medical conditions
        foreach ($this->medicalKnowledge['medical_conditions'] as $type => $conditions) {
            foreach ($conditions as $condition) {
                if (strpos(strtolower($message), $condition) !== false) {
                    $entities['conditions'][] = [
                        'type' => $type,
                        'condition' => $condition
                    ];
                }
            }
        }

        return $entities;
    }

    private function assessUrgency($message) {
        $message = strtolower($message);
        
        foreach ($this->medicalKnowledge['urgency_levels'] as $level => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    return $level;
                }
            }
        }

        return 'normal';
    }

    private function updateContext($userId, $data) {
        if (!$userId) return;

        if (!isset($this->contextManager[$userId])) {
            $this->contextManager[$userId] = [
                'conversation_history' => [],
                'current_flow' => null,
                'collected_info' => []
            ];
        }

        $this->contextManager[$userId]['conversation_history'][] = $data;
        $this->contextManager[$userId]['current_flow'] = $data['last_intent'];
    }

    private function generateResponse($intent, $entities, $urgency, $userId) {
        if ($urgency === 'emergency') {
            return [
                'message' => 'This sounds like a medical emergency. Please call emergency services immediately at 102 or visit the nearest emergency room.',
                'action' => 'emergency_alert',
                'suggestions' => ['Call Emergency', 'Show Nearest ER', 'View Emergency Guidelines']
            ];
        }

        $response = [];
        switch ($intent) {
            case 'symptom_check':
                $response = $this->handleSymptomCheck($entities, $userId);
                break;
            case 'appointment':
                $response = $this->handleAppointmentRequest($entities, $userId);
                break;
            case 'information':
                $response = $this->handleInformationRequest($entities);
                break;
            default:
                $response = $this->handleGeneralQuery();
        }

        return $response;
    }

    private function handleSymptomCheck($entities, $userId) {
        if (empty($entities['symptoms'])) {
            return [
                'message' => 'Could you please describe your symptoms in more detail? For example, what kind of pain or discomfort are you experiencing?',
                'action' => 'prompt_symptoms',
                'suggestions' => ['Describe Pain', 'List Symptoms', 'Show Symptom Checker']
            ];
        }

        $recommendedDepts = $this->recommendDepartments($entities['symptoms']);
        return [
            'message' => 'Based on your symptoms, I recommend consulting with our ' . implode(' or ', $recommendedDepts) . ' department. Would you like to schedule an appointment?',
            'action' => 'suggest_department',
            'data' => ['departments' => $recommendedDepts],
            'suggestions' => ['Book Appointment', 'More Information', 'View Doctors']
        ];
    }

    private function handleAppointmentRequest($entities, $userId) {
        return [
            'message' => 'I can help you schedule an appointment. Please select your preferred department or doctor.',
            'action' => 'start_booking',
            'suggestions' => ['Select Department', 'Choose Doctor', 'View Available Slots']
        ];
    }

    private function handleInformationRequest($entities) {
        return [
            'message' => 'What specific information would you like to know about our medical services?',
            'action' => 'show_info_options',
            'suggestions' => ['Departments', 'Doctors', 'Services', 'Facilities']
        ];
    }

    private function handleGeneralQuery() {
        return [
            'message' => 'How can I assist you with our healthcare services today?',
            'action' => 'show_menu',
            'suggestions' => ['Book Appointment', 'Check Symptoms', 'Find Doctor', 'View Services']
        ];
    }

    private function recommendDepartments($symptoms) {
        $departments = [];
        $departmentMapping = [
            'fever' => ['Internal Medicine', 'General Medicine'],
            'pain' => ['Pain Management', 'Orthopedics'],
            'respiratory' => ['Pulmonology', 'ENT'],
            'digestive' => ['Gastroenterology'],
            'neurological' => ['Neurology'],
            'musculoskeletal' => ['Orthopedics', 'Physiotherapy'],
            'skin' => ['Dermatology'],
            'psychological' => ['Psychiatry', 'Psychology']
        ];

        foreach ($symptoms as $symptom) {
            if (isset($departmentMapping[$symptom['category']])) {
                $departments = array_merge($departments, $departmentMapping[$symptom['category']]);
            }
        }

        return array_unique($departments);
    }
}