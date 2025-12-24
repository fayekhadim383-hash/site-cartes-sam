// ===== MAIN JAVASCRIPT =====

// Document Ready
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Navigation
    initMobileNav();
    
    // Form Validation
    initFormValidation();
    
    // Portfolio Filter
    initPortfolioFilter();
    
    // Smooth Scrolling
    initSmoothScrolling();
    
    // Back to Top Button
    initBackToTop();
    
    // FAQ Accordion
    initFAQAccordion();
});

// ===== MOBILE NAVIGATION =====
function initMobileNav() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');
    
    if (hamburger && navMenu) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
            
            // Prevent body scroll when menu is open
            if (navMenu.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!hamburger.contains(event.target) && !navMenu.contains(event.target)) {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Close menu when clicking a link
        const navLinks = document.querySelectorAll('.nav-menu a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
    }
}

// ===== FORM VALIDATION =====
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
    });
    
    // Password strength meter
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        if (input.name === 'new_password' || input.name === 'password') {
            input.addEventListener('input', function() {
                checkPasswordStrength(this.value, this);
            });
        }
    });
    
    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatPhoneNumber(this);
        });
    });
}

function checkPasswordStrength(password, input) {
    let strength = 0;
    const strengthMeter = input.parentElement?.nextElementSibling?.querySelector('.strength-meter');
    const strengthText = input.parentElement?.nextElementSibling?.querySelector('.strength-text');
    
    if (!strengthMeter || !strengthText) return;
    
    // Criteria
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    // Update UI
    const strengthBar = strengthMeter.querySelector('.strength-bar');
    let color = '#e74c3c';
    let text = 'Faible';
    
    switch(strength) {
        case 1:
            color = '#e74c3c';
            text = 'Faible';
            break;
        case 2:
            color = '#f39c12';
            text = 'Moyen';
            break;
        case 3:
            color = '#3498db';
            text = 'Bon';
            break;
        case 4:
            color = '#2ecc71';
            text = 'Fort';
            break;
    }
    
    if (strengthBar) {
        strengthBar.style.width = (strength * 25) + '%';
        strengthBar.style.backgroundColor = color;
    }
    
    if (strengthText) {
        strengthText.textContent = text;
        strengthText.style.color = color;
    }
}

function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 10) {
        value = value.substring(0, 10);
    }
    
    if (value.length > 2) {
        value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4');
    }
    
    input.value = value;
}

// ===== PORTFOLIO FILTER =====
function initPortfolioFilter() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const portfolioItems = document.querySelectorAll('.portfolio-item');
    
    if (filterButtons.length === 0 || portfolioItems.length === 0) return;
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            const filterValue = this.getAttribute('data-filter');
            
            // Filter items
            portfolioItems.forEach(item => {
                const categories = item.getAttribute('data-category').split(' ');
                
                if (filterValue === 'all' || categories.includes(filterValue)) {
                    item.style.display = 'block';
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'scale(1)';
                    }, 10);
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        item.style.display = 'none';
                    }, 300);
                }
            });
        });
    });
}

// ===== SMOOTH SCROLLING =====
function initSmoothScrolling() {
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                const headerHeight = document.querySelector('.header')?.offsetHeight || 0;
                const targetPosition = targetElement.offsetTop - headerHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// ===== BACK TO TOP BUTTON =====
function initBackToTop() {
    const backToTopButton = document.createElement('button');
    backToTopButton.innerHTML = '↑';
    backToTopButton.className = 'back-to-top';
    document.body.appendChild(backToTopButton);
    
    // Show/hide button
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.classList.add('show');
        } else {
            backToTopButton.classList.remove('show');
        }
    });
    
    // Scroll to top
    backToTopButton.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Add CSS for button
    const style = document.createElement('style');
    style.textContent = `
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background-color: #2980b9;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
    `;
    document.head.appendChild(style);
}

// ===== FAQ ACCORDION =====
function initFAQAccordion() {
    const faqItems = document.querySelectorAll('.faq-item');
    
    if (faqItems.length === 0) return;
    
    faqItems.forEach(item => {
        const question = item.querySelector('h3');
        const answer = item.querySelector('p');
        
        if (question && answer) {
            // Add click handler
            question.addEventListener('click', function() {
                const isActive = item.classList.contains('active');
                
                // Close all other items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                        otherItem.querySelector('p').style.maxHeight = '0';
                    }
                });
                
                // Toggle current item
                if (!isActive) {
                    item.classList.add('active');
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                } else {
                    item.classList.remove('active');
                    answer.style.maxHeight = '0';
                }
            });
            
            // Add CSS for transition
            answer.style.transition = 'max-height 0.3s ease';
            answer.style.overflow = 'hidden';
            answer.style.maxHeight = '0';
        }
    });
}

// ===== CARD PREVIEW =====
function previewCard(cardId) {
    // This function would be implemented to show a preview of the card
    console.log('Previewing card:', cardId);
    // In a real implementation, this would fetch card data and display it
}

// ===== AJAX REQUESTS =====
function makeAjaxRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error(xhr.statusText));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        xhr.send(data ? JSON.stringify(data) : null);
    });
}

// ===== NOTIFICATION SYSTEM =====
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Add close handler
    notification.querySelector('.notification-close').addEventListener('click', function() {
        notification.remove();
    });
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
    
    // Add CSS if not already added
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: white;
                border-radius: 5px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                padding: 15px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                max-width: 400px;
                z-index: 9999;
                transform: translateX(120%);
                transition: transform 0.3s ease;
            }
            
            .notification.show {
                transform: translateX(0);
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .notification i {
                font-size: 1.2rem;
            }
            
            .notification-info i { color: #3498db; }
            .notification-success i { color: #2ecc71; }
            .notification-warning i { color: #f39c12; }
            .notification-error i { color: #e74c3c; }
            
            .notification-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: #7f8c8d;
                line-height: 1;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Show with animation
    setTimeout(() => notification.classList.add('show'), 10);
}

function getNotificationIcon(type) {
    switch(type) {
        case 'success': return 'check-circle';
        case 'warning': return 'exclamation-triangle';
        case 'error': return 'times-circle';
        default: return 'info-circle';
    }
}

// ===== IMAGE UPLOAD PREVIEW =====
function initImageUploadPreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    if (!input || !preview) return;
    
    input.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        if (!file.type.startsWith('image/')) {
            showNotification('Veuillez sélectionner une image', 'error');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            showNotification('L\'image est trop volumineuse (max 5MB)', 'error');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });
}

// ===== FORM DATA SAVE =====
function saveFormData(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, select, textarea');
    const formData = {};
    
    inputs.forEach(input => {
        if (input.name) {
            formData[input.name] = input.value;
        }
    });
    
    localStorage.setItem(`form_${formId}`, JSON.stringify(formData));
}

function loadFormData(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const savedData = localStorage.getItem(`form_${formId}`);
    if (!savedData) return;
    
    const formData = JSON.parse(savedData);
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        if (input.name && formData[input.name] !== undefined) {
            input.value = formData[input.name];
        }
    });
}

// ===== INITIALIZATION =====
// Call this when the page loads
window.addEventListener('load', function() {
    // Load saved form data
    const forms = document.querySelectorAll('form[data-save]');
    forms.forEach(form => {
        const formId = form.id || 'form_' + Math.random().toString(36).substr(2, 9);
        form.id = formId;
        loadFormData(formId);
        
        // Save on input change
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                saveFormData(formId);
            });
        });
    });
});

let slideIndex = 1;
showSlides(slideIndex);

// Contrôles suivant/précédent
function plusSlides(n) {
    showSlides(slideIndex += n);
}

// Contrôles des points
function currentSlide(n) {
    showSlides(slideIndex = n);
}

function showSlides(n) {
    let i;
    let slides = document.getElementsByClassName("mySlides");
    let dots = document.getElementsByClassName("dot");
    
    if (n > slides.length) { slideIndex = 1; }
    if (n < 1) { slideIndex = slides.length; }
    
    for (i = 0; i < slides.length; i++) {
        slides[i].style.display = "none";
    }
    
    for (i = 0; i < dots.length; i++) {
        dots[i].className = dots[i].className.replace(" active", "");
    }
    
    slides[slideIndex - 1].style.display = "block";
    dots[slideIndex - 1].className += " active";
}

// Défilement automatique (optionnel)
let slideInterval = setInterval(() => {
    plusSlides(1);
}, 5000); // Change d'image toutes les 5 secondes

// Arrêter le défilement automatique au survol
document.querySelector('.hero-image').addEventListener('mouseenter', () => {
    clearInterval(slideInterval);
});

// Reprendre le défilement automatique quand la souris quitte
document.querySelector('.hero-image').addEventListener('mouseleave', () => {
    slideInterval = setInterval(() => {
        plusSlides(1);
    }, 5000);
});

