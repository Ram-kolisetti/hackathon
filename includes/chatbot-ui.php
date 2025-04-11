<?php
// Chatbot UI component that will be included in all pages
?>
<div class="chatbot-wrapper">
    <button id="chatbotToggle" class="chatbot-toggle" aria-label="Toggle chatbot">
        <i class="fas fa-comments"></i>
        <span class="chatbot-badge" id="chatbotBadge">1</span>
    </button>
    
    <div class="chatbot-container">
        <div class="chatbot-header">
            <h3><i class="fas fa-robot"></i> Healthcare Assistant</h3>
            <button class="chatbot-close" id="chatbotClose" aria-label="Close chatbot">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="chatbot-body" id="chatbotBody">
            <div class="chat-message bot">
                <div class="chat-bubble">
                    Hello! I'm your AI healthcare assistant. I can help you with:
                    - Checking symptoms
                    - Booking appointments
                    - Finding specialists
                    - Medical information
                    - Emergency guidance
                </div>
                <div class="chat-suggestions">
                    <button class="suggestion-btn" data-message="Check my symptoms">Check Symptoms</button>
                    <button class="suggestion-btn" data-message="Book an appointment">Book Appointment</button>
                    <button class="suggestion-btn" data-message="Find a specialist">Find Specialist</button>
                    <button class="suggestion-btn" data-message="Emergency services">Emergency</button>
                </div>
                <div class="chat-time"><?php echo date('H:i'); ?></div>
            </div>
        </div>
        
        <div class="chatbot-suggestions" id="chatbotSuggestions">
            <button class="suggestion-btn" data-message="How do I book an appointment?">
                Book Appointment
            </button>
            <button class="suggestion-btn" data-message="Which hospital should I choose?">
                Choose Hospital
            </button>
            <button class="suggestion-btn" data-message="I need to describe my symptoms">
                Describe Symptoms
            </button>
            <button class="suggestion-btn" data-message="How do I make a payment?">
                Payment Help
            </button>
        </div>
        
        <div class="chatbot-footer">
            <div class="chatbot-input-wrapper">
                <input type="text" id="chatbotInput" class="chatbot-input" 
                       placeholder="Type your message..." aria-label="Chat message">
                <button id="chatbotSend" class="chatbot-send" aria-label="Send message">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Chatbot Styles */
.chatbot-wrapper {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    z-index: var(--z-index-modal);
}

.chatbot-toggle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: var(--shadow-lg);
    position: relative;
    transition: transform var(--transition-normal);
}

.chatbot-toggle:hover {
    transform: scale(1.1);
}

.chatbot-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--accent-color);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chatbot-container {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 350px;
    height: 500px;
    background: white;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-2xl);
    display: flex;
    flex-direction: column;
    transform: scale(0);
    transform-origin: bottom right;
    transition: transform var(--transition-normal);
}

.chatbot-container.open {
    transform: scale(1);
}

.chatbot-header {
    padding: var(--spacing-md);
    background: var(--primary-color);
    color: white;
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chatbot-header h3 {
    margin: 0;
    color: white;
    font-size: var(--font-size-lg);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.chatbot-close {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: var(--font-size-lg);
    padding: var(--spacing-xs);
    transition: transform var(--transition-normal);
}

.chatbot-close:hover {
    transform: scale(1.2);
}

.chatbot-body {
    flex: 1;
    padding: var(--spacing-md);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.chat-message {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
    max-width: 80%;
    animation: messageSlide var(--transition-normal);
}

.chat-message.user {
    align-self: flex-end;
}

.chat-bubble {
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--border-radius-lg);
    background: var(--gray-100);
    box-shadow: var(--shadow-sm);
}

.chat-message.user .chat-bubble {
    background: var(--primary-color);
    color: white;
}

.chat-time {
    font-size: var(--font-size-xs);
    color: var(--gray-600);
    align-self: flex-end;
}

.chatbot-suggestions {
    padding: var(--spacing-sm);
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
    border-top: 1px solid var(--gray-200);
}

.suggestion-btn {
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--primary-color);
    border-radius: var(--border-radius-full);
    background: white;
    color: var(--primary-color);
    cursor: pointer;
    transition: all var(--transition-normal);
    font-size: var(--font-size-sm);
}

.suggestion-btn:hover {
    background: var(--primary-color);
    color: white;
}

.chatbot-footer {
    padding: var(--spacing-md);
    border-top: 1px solid var(--gray-200);
}

.chatbot-input-wrapper {
    display: flex;
    gap: var(--spacing-sm);
}

.chatbot-input {
    flex: 1;
    padding: var(--spacing-sm);
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius-full);
    font-size: var(--font-size-md);
    transition: border-color var(--transition-normal);
}

.chatbot-input:focus {
    outline: none;
    border-color: var(--primary-color);
}

.chatbot-send {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    border: none;
    cursor: pointer;
    transition: transform var(--transition-normal);
}

.chatbot-send:hover {
    transform: scale(1.1);
}

@keyframes messageSlide {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .chatbot-container {
        width: calc(100vw - 2rem);
        height: calc(100vh - 100px);
        bottom: 70px;
    }
    
    .chatbot-toggle {
        width: 50px;
        height: 50px;
    }
    
    .chatbot-suggestions {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding: var(--spacing-sm) var(--spacing-md);
    }
    
    .suggestion-btn {
        flex: 0 0 auto;
    }
}
</style>