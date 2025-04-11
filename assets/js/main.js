/**
 * Main JavaScript file for the Hospital Management Platform
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize sidebar toggle
    initializeSidebar();
    
    // Initialize chatbot
    initializeChatbot();
    
    // Initialize appointment booking form if it exists
    if (document.getElementById('appointmentForm')) {
        initializeAppointmentForm();
    }
    
    // Initialize hospital selection if it exists
    if (document.getElementById('hospitalSelect')) {
        initializeHospitalSelection();
    }
    
    // Initialize payment form if it exists
    if (document.getElementById('paymentForm')) {
        initializePaymentForm();
    }
    
    // Initialize charts if they exist
    if (document.getElementById('revenueChart')) {
        initializeRevenueChart();
    }
    
    if (document.getElementById('appointmentsChart')) {
        initializeAppointmentsChart();
    }
});

/**
 * Initialize tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize sidebar toggle functionality
 */
function initializeSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const contentWrapper = document.querySelector('.content-wrapper');
    
    if (sidebarToggle && sidebar && contentWrapper) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            
            // Adjust content wrapper margin on larger screens
            if (window.innerWidth > 768) {
                if (sidebar.classList.contains('show')) {
                    contentWrapper.style.marginLeft = '250px';
                } else {
                    contentWrapper.style.marginLeft = '0';
                }
            }
        });
        
        // Hide sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !sidebarToggle.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
        
        // Adjust on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                if (sidebar.classList.contains('show')) {
                    contentWrapper.style.marginLeft = '250px';
                } else {
                    contentWrapper.style.marginLeft = '0';
                }
            } else {
                contentWrapper.style.marginLeft = '0';
            }
        });
    }
}

/**
 * Initialize chatbot functionality
 */
function initializeChatbot() {
    const chatbotToggle = document.getElementById('chatbotToggle');
    const chatbotContainer = document.querySelector('.chatbot-container');
    const chatbotSend = document.querySelector('.chatbot-send');
    const chatbotInput = document.querySelector('.chatbot-input');
    const chatbotBody = document.querySelector('.chatbot-body');
    
    if (chatbotToggle && chatbotContainer) {
        chatbotToggle.addEventListener('click', function() {
            chatbotContainer.classList.toggle('open');
        });
        
        // Also toggle when clicking the header
        const chatbotHeader = document.querySelector('.chatbot-header');
        if (chatbotHeader) {
            chatbotHeader.addEventListener('click', function() {
                chatbotContainer.classList.toggle('open');
            });
        }
    }
    
    if (chatbotSend && chatbotInput && chatbotBody) {
        // Send message when clicking send button
        chatbotSend.addEventListener('click', function() {
            sendChatbotMessage();
        });
        
        // Send message when pressing Enter
        chatbotInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendChatbotMessage();
            }
        });
    }
    
    /**
     * Send a message to the chatbot
     */
    function sendChatbotMessage() {
        const message = chatbotInput.value.trim();
        
        if (message !== '') {
            // Add user message to chat
            addChatMessage(message, 'user');
            
            // Clear input
            chatbotInput.value = '';
            
            // Simulate bot response (in a real app, this would be an API call)
            setTimeout(function() {
                // Show typing indicator
                const typingIndicator = document.createElement('div');
                typingIndicator.className = 'chat-message bot';
                typingIndicator.innerHTML = '<div class="chat-bubble">Typing...</div>';
                typingIndicator.id = 'typing-indicator';
                chatbotBody.appendChild(typingIndicator);
                
                // Scroll to bottom
                chatbotBody.scrollTop = chatbotBody.scrollHeight;
                
                // Remove typing indicator and add response after a delay
                setTimeout(function() {
                    document.getElementById('typing-indicator').remove();
                    
                    // Simple response logic (would be replaced with actual chatbot API)
                    let botResponse = '';
                    
                    if (message.toLowerCase().includes('appointment')) {
                        botResponse = 'To book an appointment, please go to the Appointments section and click on "Book New Appointment". You can select your preferred hospital, department, and doctor there.';
                    } else if (message.toLowerCase().includes('doctor')) {
                        botResponse = 'You can view all available doctors by going to the Doctors section. You can filter them by hospital and department.';
                    } else if (message.toLowerCase().includes('hospital')) {
                        botResponse = 'We have multiple hospitals in our network. You can view all hospitals in the Hospitals section and select one for your appointment.';
                    } else if (message.toLowerCase().includes('payment')) {
                        botResponse = 'We accept various payment methods including UPI, credit/debit cards, and net banking. Your payment information is securely processed.';
                    } else if (message.toLowerCase().includes('hello') || message.toLowerCase().includes('hi')) {
                        botResponse = 'Hello! How can I assist you today with our hospital management platform?';
                    } else {
                        botResponse = 'Thank you for your message. How else can I assist you with our hospital services?';
                    }
                    
                    addChatMessage(botResponse, 'bot');
                }, 1000);
            }, 500);
        }
    }
    
    /**
     * Add a message to the chat
     */
    function addChatMessage(message, sender) {
        const messageElement = document.createElement('div');
        messageElement.className = `chat-message ${sender}`;
        
        const now = new Date();
        const timeString = now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes();
        
        messageElement.innerHTML = `
            <div class="chat-bubble">${message}</div>
            <div class="chat-time">${timeString}</div>
        `;
        
        chatbotBody.appendChild(messageElement);
        
        // Scroll to bottom
        chatbotBody.scrollTop = chatbotBody.scrollHeight;
    }
}

/**
 * Initialize appointment booking form
 */
function initializeAppointmentForm() {
    const hospitalSelect = document.getElementById('hospital');
    const departmentSelect = document.getElementById('department');
    const doctorSelect = document.getElementById('doctor');
    const dateInput = document.getElementById('appointmentDate');
    const timeSelect = document.getElementById('appointmentTime');
    
    // Set min date to today
    if (dateInput) {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        dateInput.min = `${yyyy}-${mm}-${dd}`;
    }
    
    // Update departments when hospital changes
    if (hospitalSelect) {
        hospitalSelect.addEventListener('change', function() {
            const hospitalId = this.value;
            
            if (hospitalId) {
                // In a real app, this would be an API call
                // For demo, we'll simulate it
                simulateGetDepartments(hospitalId);
            } else {
                // Clear departments and doctors
                clearSelect(departmentSelect, 'Select Department');
                clearSelect(doctorSelect, 'Select Doctor');
            }
        });
    }
    
    // Update doctors when department changes
    if (departmentSelect) {
        departmentSelect.addEventListener('change', function() {
            const departmentId = this.value;
            const hospitalId = hospitalSelect.value;
            
            if (departmentId && hospitalId) {
                // In a real app, this would be an API call
                // For demo, we'll simulate it
                simulateGetDoctors(hospitalId, departmentId);
            } else {
                // Clear doctors
                clearSelect(doctorSelect, 'Select Doctor');
            }
        });
    }
    
    // Update available times when doctor and date change
    if (doctorSelect && dateInput) {
        doctorSelect.addEventListener('change', updateAvailableTimes);
        dateInput.addEventListener('change', updateAvailableTimes);
    }
    
    function updateAvailableTimes() {
        const doctorId = doctorSelect.value;
        const date = dateInput.value;
        
        if (doctorId && date) {
            // In a real app, this would be an API call
            // For demo, we'll simulate it
            simulateGetAvailableTimes(doctorId, date);
        } else {
            // Clear times
            clearSelect(timeSelect, 'Select Time');
        }
    }
    
    // Simulate getting departments from API
    function simulateGetDepartments(hospitalId) {
        // Clear current options
        clearSelect(departmentSelect, 'Select Department');
        clearSelect(doctorSelect, 'Select Doctor');
        
        // Simulate API delay
        setTimeout(function() {
            // Sample departments data (would come from API)
            const departments = {
                '1': [ // City General Hospital
                    {id: '1', name: 'Cardiology'},
                    {id: '2', name: 'Neurology'},
                    {id: '3', name: 'Orthopedics'},
                    {id: '4', name: 'Pediatrics'}
                ],
                '2': [ // Memorial Medical Center
                    {id: '5', name: 'Oncology'},
                    {id: '6', name: 'Gynecology'},
                    {id: '7', name: 'Dermatology'},
                    {id: '8', name: 'Psychiatry'}
                ],
                '3': [ // Sunshine Healthcare
                    {id: '9', name: 'Ophthalmology'},
                    {id: '10', name: 'ENT'},
                    {id: '11', name: 'Urology'},
                    {id: '12', name: 'Gastroenterology'}
                ]
            };
            
            // Add options
            if (departments[hospitalId]) {
                departments[hospitalId].forEach(function(dept) {
                    const option = document.createElement('option');
                    option.value = dept.id;
                    option.textContent = dept.name;
                    departmentSelect.appendChild(option);
                });
            }
        }, 300);
    }
    
    // Simulate getting doctors from API
    function simulateGetDoctors(hospitalId, departmentId) {
        // Clear current options
        clearSelect(doctorSelect, 'Select Doctor');
        
        // Simulate API delay
        setTimeout(function() {
            // Sample doctors data (would come from API)
            const doctors = {
                '1': { // City General Hospital
                    '1': [{id: '1', name: 'Dr. John Smith'}], // Cardiology
                    '3': [{id: '2', name: 'Dr. Emily Jones'}]  // Orthopedics
                },
                '2': { // Memorial Medical Center
                    '5': [{id: '3', name: 'Dr. Michael Williams'}], // Oncology
                    '7': [{id: '4', name: 'Dr. Sarah Brown'}]      // Dermatology
                },
                '3': { // Sunshine Healthcare
                    '9': [{id: '5', name: 'Dr. Robert Davis'}],  // Ophthalmology
                    '11': [{id: '6', name: 'Dr. Jennifer Miller'}] // Urology
                }
            };
            
            // Add options
            if (doctors[hospitalId] && doctors[hospitalId][departmentId]) {
                doctors[hospitalId][departmentId].forEach(function(doc) {
                    const option = document.createElement('option');
                    option.value = doc.id;
                    option.textContent = doc.name;
                    doctorSelect.appendChild(option);
                });
            }
        }, 300);
    }
    
    // Simulate getting available times from API
    function simulateGetAvailableTimes(doctorId, date) {
        // Clear current options
        clearSelect(timeSelect, 'Select Time');
        
        // Simulate API delay
        setTimeout(function() {
            // Sample times (would come from API based on doctor availability)
            const times = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];
            
            // Add options
            times.forEach(function(time) {
                const option = document.createElement('option');
                option.value = time;
                option.textContent = time;
                timeSelect.appendChild(option);
            });
        }, 300);
    }
    
    // Helper to clear select options
    function clearSelect(selectElement, placeholderText) {
        if (selectElement) {
            selectElement.innerHTML = '';
            
            // Add placeholder
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = placeholderText;
            placeholder.disabled = true;
            placeholder.selected = true;
            selectElement.appendChild(placeholder);
        }
    }
}

/**
 * Initialize hospital selection
 */
function initializeHospitalSelection() {
    const hospitalCards = document.querySelectorAll('.hospital-card');
    
    if (hospitalCards.length > 0) {
        hospitalCards.forEach(function(card) {
            card.addEventListener('click', function() {
                const hospitalId = this.dataset.hospitalId;
                
                // Store selected hospital in session storage
                sessionStorage.setItem('selectedHospital', hospitalId);
                
                // Redirect to departments page
                window.location.href = 'departments.php?hospital_id=' + hospitalId;
            });
        });
    }
}

/**
 * Initialize payment form
 */
function initializePaymentForm() {
    const paymentForm = document.getElementById('paymentForm');
    const paymentMethodRadios = document.querySelectorAll('input[name="paymentMethod"]');
    const cardDetails = document.getElementById('cardDetails');
    const upiDetails = document.getElementById('upiDetails');
    const netBankingDetails = document.getElementById('netBankingDetails');
    
    if (paymentMethodRadios.length > 0) {
        paymentMethodRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                // Hide all payment details sections
                cardDetails.classList.add('d-none');
                upiDetails.classList.add('d-none');
                netBankingDetails.classList.add('d-none');
                
                // Show the selected payment method details
                const selectedMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
                
                if (selectedMethod === 'card') {
                    cardDetails.classList.remove('d-none');
                } else if (selectedMethod === 'upi') {
                    upiDetails.classList.remove('d-none');
                } else if (selectedMethod === 'net_banking') {
                    netBankingDetails.classList.remove('d-none');
                }
            });
        });
    }
    
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show processing message
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Simulate payment processing
            setTimeout(function() {
                // Show success message
                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success mt-3';
                successAlert.innerHTML = '<i class="fas fa-check-circle"></i> Payment successful! Redirecting to confirmation page...';
                paymentForm.appendChild(successAlert);
                
                // Redirect after a delay
                setTimeout(function() {
                    window.location.href = 'confirmation.php';
                }, 2000);
            }, 2000);
        });
    }
}

/**
 * Initialize revenue chart
 */
function initializeRevenueChart() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Sample data (would come from API)
    const labels = ['January', 'February', 'March', 'April', 'May', 'June'];
    const data = {
        labels: labels,
        datasets: [{
            label: 'Revenue',
            backgroundColor: 'rgba(52, 152, 219, 0.2)',
            borderColor: 'rgba(52, 152, 219, 1)',
            borderWidth: 2,
            data: [12500, 19200, 15700, 16800, 21500, 18300],
        }]
    };
    
    const config = {
        type: 'bar',
        data: data,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Monthly Revenue'
                }
            }
        },
    };
    
    new Chart(ctx, config);
}

/**
 * Initialize appointments chart
 */
function initializeAppointmentsChart() {
    const ctx = document.getElementById('appointmentsChart').getContext('2d');
    
    // Sample data (would come from API)
    const data = {
        labels: ['Scheduled', 'Completed', 'Cancelled', 'Missed'],
        datasets: [{
            label: 'Appointments',
            backgroundColor: [
                'rgba(52, 152, 219, 0.7)',
                'rgba(46, 204, 113, 0.7)',
                'rgba(231, 76, 60, 0.7)',
                'rgba(243, 156, 18, 0.7)'
            ],
            borderColor: [
                'rgba(52, 152, 219, 1)',
                'rgba(46, 204, 113, 1)',
                'rgba(231, 76, 60, 1)',
                'rgba(243, 156, 18, 1)'
            ],
            borderWidth: 1,
            data: [65, 42, 15, 8],
        }]
    };
    
    const config = {
        type: 'doughnut',
        data: data,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Appointment Status'
                }
            }
        },
    };
    
    new Chart(ctx, config);
}