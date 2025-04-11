/**
 * Chatbot JavaScript functionality
 */

class Chatbot {
    constructor() {
        this.container = document.querySelector('.chatbot-container');
        this.toggle = document.getElementById('chatbotToggle');
        this.close = document.getElementById('chatbotClose');
        this.body = document.getElementById('chatbotBody');
        this.input = document.getElementById('chatbotInput');
        this.send = document.getElementById('chatbotSend');
        this.badge = document.getElementById('chatbotBadge');
        this.suggestions = document.getElementById('chatbotSuggestions');
        
        this.isOpen = false;
        this.messageQueue = [];
        this.processingQueue = false;
        
        this.initializeEventListeners();
    }
    
    initializeEventListeners() {
        // Toggle chatbot
        this.toggle.addEventListener('click', () => this.toggleChatbot());
        
        // Close chatbot
        this.close.addEventListener('click', () => this.toggleChatbot(false));
        
        // Send message on button click
        this.send.addEventListener('click', () => this.sendMessage());
        
        // Send message on Enter key
        this.input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });
        
        // Handle suggestion clicks
        this.suggestions.addEventListener('click', (e) => {
            if (e.target.classList.contains('suggestion-btn')) {
                const message = e.target.dataset.message;
                this.input.value = message;
                this.sendMessage();
            }
        });
        
        // Hide badge when chatbot is opened
        this.toggle.addEventListener('click', () => {
            this.badge.style.display = 'none';
        });
    }
    
    toggleChatbot(force = null) {
        this.isOpen = force !== null ? force : !this.isOpen;
        this.container.classList.toggle('open', this.isOpen);
        
        // Add entry animation when opening
        if (this.isOpen) {
            this.container.style.animation = 'scaleIn 0.3s ease-out';
        }
    }
    
    async sendMessage() {
        const message = this.input.value.trim();
        if (!message) return;
        
        // Clear input
        this.input.value = '';
        
        // Add message to queue
        this.messageQueue.push(message);
        
        // Process queue if not already processing
        if (!this.processingQueue) {
            this.processMessageQueue();
        }
    }
    
    async processMessageQueue() {
        if (this.messageQueue.length === 0) {
            this.processingQueue = false;
            return;
        }
        
        this.processingQueue = true;
        const message = this.messageQueue.shift();
        
        // Add user message to chat
        this.addMessage(message, 'user');
        
        // Show typing indicator
        this.showTypingIndicator();
        
        try {
            // Send message to backend
            const response = await this.sendToBackend(message);
            
            // Remove typing indicator
            this.removeTypingIndicator();
            
            // Add bot response to chat
            this.addMessage(response.response, 'bot');
            
            // Process next message in queue
            setTimeout(() => this.processMessageQueue(), 1000);
        } catch (error) {
            console.error('Error sending message:', error);
            this.removeTypingIndicator();
            this.addMessage('Sorry, I\'m having trouble connecting. Please try again.', 'bot error');
            this.processingQueue = false;
        }
    }
    
    async sendToBackend(message) {
        const response = await fetch('/api/chatbot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message })
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        return response.json();
    }
    
    addMessage(message, sender) {
        const messageElement = document.createElement('div');
        messageElement.className = `chat-message ${sender}`;
        
        const now = new Date();
        const timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                          now.getMinutes().toString().padStart(2, '0');
        
        // Split message into main content and suggestions
        const parts = message.split('\n\n');
        const mainContent = parts[0];
        const suggestions = parts.length > 1 ? parts.slice(1) : [];
        
        let messageHtml = `<div class="chat-bubble">${this.escapeHtml(mainContent)}</div>`;
        
        // Add suggestions if present
        if (suggestions.length > 0) {
            messageHtml += '<div class="chat-suggestions">';
            suggestions.forEach(suggestion => {
                if (suggestion.startsWith('Quick actions:')) {
                    const actions = suggestion.replace('Quick actions:', '').trim().split('\n- ');
                    actions.forEach(action => {
                        if (action.trim()) {
                            messageHtml += `<button class="suggestion-btn" data-message="${this.escapeHtml(action.trim())}">${this.escapeHtml(action.trim())}</button>`;
                        }
                    });
                } else if (suggestion.startsWith('Recommended departments:')) {
                    const depts = suggestion.replace('Recommended departments:', '').trim().split(',');
                    depts.forEach(dept => {
                        if (dept.trim()) {
                            messageHtml += `<button class="suggestion-btn department-btn" data-message="Book appointment with ${this.escapeHtml(dept.trim())}">${this.escapeHtml(dept.trim())}</button>`;
                        }
                    });
                }
            });
            messageHtml += '</div>';
        }
        
        messageHtml += `<div class="chat-time">${timeString}</div>`;
        messageElement.innerHTML = messageHtml;
        
        this.body.appendChild(messageElement);
        this.scrollToBottom();
    }
    
    showTypingIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'chat-message bot typing';
        indicator.innerHTML = `
            <div class="chat-bubble">
                <div class="typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        indicator.id = 'typing-indicator';
        this.body.appendChild(indicator);
        this.scrollToBottom();
    }
    
    removeTypingIndicator() {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    scrollToBottom() {
        this.body.scrollTop = this.body.scrollHeight;
    }
    
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}

// Initialize chatbot when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new Chatbot();
});

// Add additional styles for typing indicator
const style = document.createElement('style');
style.textContent = `
    .typing-indicator {
        display: flex;
        gap: 4px;
    }
    
    .typing-indicator span {
        width: 8px;
        height: 8px;
        background: var(--gray-400);
        border-radius: 50%;
        animation: typing 1s infinite ease-in-out;
    }
    
    .typing-indicator span:nth-child(1) { animation-delay: 0.1s; }
    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.3s; }
    
    @keyframes typing {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    @keyframes scaleIn {
        from {
            transform: scale(0);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);