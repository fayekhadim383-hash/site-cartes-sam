/**
 * Validation de formulaire avancée
 */

class FormValidator {
    constructor(formId, options = {}) {
        this.form = document.getElementById(formId);
        if (!this.form) return;
        
        this.options = {
            validateOnInput: true,
            validateOnBlur: true,
            showErrors: true,
            ...options
        };
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupCustomValidation();
    }
    
    setupEventListeners() {
        if (this.options.validateOnInput) {
            const inputs = this.form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', () => this.validateField(input));
            });
        }
        
        if (this.options.validateOnBlur) {
            const inputs = this.form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', () => this.validateField(input));
            });
        }
        
        this.form.addEventListener('submit', (e) => this.validateForm(e));
    }
    
    setupCustomValidation() {
        // Email validation
        const emailInputs = this.form.querySelectorAll('input[type="email"]');
        emailInputs.forEach(input => {
            input.addEventListener('input', () => {
                this.validateEmail(input);
            });
        });
        
        // Phone validation
        const phoneInputs = this.form.querySelectorAll('input[type="tel"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', () => {
                this.formatPhoneNumber(input);
                this.validatePhone(input);
            });
        });
        
        // Password confirmation
        const passwordInputs = this.form.querySelectorAll('input[type="password"]');
        passwordInputs.forEach((input, index) => {
            if (input.name.includes('confirm')) {
                const passwordField = this.form.querySelector(`input[name="${input.name.replace('confirm_', '').replace('_confirm', '')}"]`);
                if (passwordField) {
                    input.addEventListener('input', () => {
                        this.validatePasswordConfirmation(passwordField, input);
                    });
                }
            }
        });
    }
    
    validateField(field) {
        const isValid = field.checkValidity();
        
        if (this.options.showErrors) {
            this.showFieldError(field, isValid);
        }
        
        return isValid;
    }
    
    validateForm(e) {
        e.preventDefault();
        
        let isValid = true;
        const fields = this.form.querySelectorAll('input, select, textarea[required]');
        
        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        if (isValid) {
            this.form.submit();
        } else {
            this.showFormError('Veuillez corriger les erreurs dans le formulaire');
        }
        
        return isValid;
    }
    
    validateEmail(input) {
        const email = input.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValid = emailRegex.test(email);
        
        if (email && !isValid) {
            this.setCustomValidity(input, 'Veuillez entrer une adresse email valide');
        } else {
            this.clearCustomValidity(input);
        }
        
        return isValid;
    }
    
    validatePhone(input) {
        const phone = input.value.replace(/\D/g, '');
        const isValid = phone.length >= 10;
        
        if (phone && !isValid) {
            this.setCustomValidity(input, 'Veuillez entrer un numéro de téléphone valide (10 chiffres minimum)');
        } else {
            this.clearCustomValidity(input);
        }
        
        return isValid;
    }
    
    validatePasswordConfirmation(passwordField, confirmField) {
        if (passwordField.value !== confirmField.value) {
            this.setCustomValidity(confirmField, 'Les mots de passe ne correspondent pas');
            return false;
        } else {
            this.clearCustomValidity(confirmField);
            return true;
        }
    }
    
    formatPhoneNumber(input) {
        let value = input.value.replace(/\D/g, '');
        
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        
        if (value.length > 2) {
            value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4');
        }
        
        input.value = value;
    }
    
    showFieldError(field, isValid) {
        const errorContainer = field.parentElement.querySelector('.error-message') || 
                              this.createErrorContainer(field);
        
        if (!isValid) {
            const errorMessage = field.validationMessage || 'Ce champ est invalide';
            errorContainer.textContent = errorMessage;
            errorContainer.style.display = 'block';
            field.classList.add('error');
            field.classList.remove('success');
        } else {
            errorContainer.style.display = 'none';
            field.classList.remove('error');
            field.classList.add('success');
        }
    }
    
    createErrorContainer(field) {
        const container = document.createElement('div');
        container.className = 'error-message';
        container.style.cssText = `
            color: #e74c3c;
            font-size: 0.9rem;
            margin-top: 5px;
            display: none;
        `;
        
        field.parentElement.appendChild(container);
        return container;
    }
    
    setCustomValidity(input, message) {
        input.setCustomValidity(message);
        this.showFieldError(input, false);
    }
    
    clearCustomValidity(input) {
        input.setCustomValidity('');
        this.showFieldError(input, true);
    }
    
    showFormError(message) {
        let errorContainer = this.form.querySelector('.form-error-message');
        
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'form-error-message';
            errorContainer.style.cssText = `
                background-color: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                border: 1px solid #f5c6cb;
            `;
            this.form.prepend(errorContainer);
        }
        
        errorContainer.textContent = message;
        errorContainer.style.display = 'block';
        
        // Scroll to error
        errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Initialize validators for specific forms
document.addEventListener('DOMContentLoaded', function() {
    // Contact form
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        new FormValidator('contactForm', {
            validateOnInput: true,
            validateOnBlur: true
        });
    }
    
    // Register form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        new FormValidator('registerForm', {
            validateOnInput: true,
            validateOnBlur: true
        });
    }
    
    // Login form
    const loginForm = document.querySelector('.auth-form form');
    if (loginForm && !loginForm.id) {
        loginForm.id = 'loginForm';
        new FormValidator('loginForm', {
            validateOnInput: false,
            validateOnBlur: true
        });
    }
});

// Additional validation functions
function validateRequiredFields(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            markFieldAsError(field, 'Ce champ est obligatoire');
            isValid = false;
        } else {
            markFieldAsSuccess(field);
        }
    });
    
    return isValid;
}

function markFieldAsError(field, message) {
    field.classList.add('error');
    field.classList.remove('success');
    
    let errorMessage = field.parentElement.querySelector('.error-message');
    if (!errorMessage) {
        errorMessage = document.createElement('div');
        errorMessage.className = 'error-message';
        errorMessage.style.cssText = `
            color: #e74c3c;
            font-size: 0.9rem;
            margin-top: 5px;
        `;
        field.parentElement.appendChild(errorMessage);
    }
    
    errorMessage.textContent = message;
}

function markFieldAsSuccess(field) {
    field.classList.remove('error');
    field.classList.add('success');
    
    const errorMessage = field.parentElement.querySelector('.error-message');
    if (errorMessage) {
        errorMessage.remove();
    }
}

// Real-time validation for specific fields
document.addEventListener('input', function(e) {
    const target = e.target;
    
    // Email validation
    if (target.type === 'email' && target.value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(target.value)) {
            markFieldAsError(target, 'Veuillez entrer une adresse email valide');
        } else {
            markFieldAsSuccess(target);
        }
    }
    
    // Password strength
    if (target.type === 'password' && (target.name === 'password' || target.name === 'new_password')) {
        checkPasswordStrengthRealTime(target);
    }
    
    // Phone number formatting
    if (target.type === 'tel') {
        formatPhoneNumberRealTime(target);
    }
});

function checkPasswordStrengthRealTime(input) {
    const password = input.value;
    const strengthMeter = input.parentElement?.nextElementSibling?.querySelector('.strength-meter');
    const strengthText = input.parentElement?.nextElementSibling?.querySelector('.strength-text');
    
    if (!strengthMeter || !strengthText) return;
    
    let strength = 0;
    let color = '#e74c3c';
    let text = 'Faible';
    
    // Criteria
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    // Determine strength level
    switch(strength) {
        case 0:
            color = '#e74c3c';
            text = 'Faible';
            break;
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
    
    // Update UI
    const strengthBar = strengthMeter.querySelector('.strength-bar');
    if (strengthBar) {
        strengthBar.style.width = (strength * 25) + '%';
        strengthBar.style.backgroundColor = color;
    }
    
    if (strengthText) {
        strengthText.textContent = text;
        strengthText.style.color = color;
    }
}

function formatPhoneNumberRealTime(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 10) {
        value = value.substring(0, 10);
    }
    
    if (value.length > 6) {
        value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4');
    } else if (value.length > 4) {
        value = value.replace(/(\d{2})(\d{2})(\d+)/, '$1 $2 $3');
    } else if (value.length > 2) {
        value = value.replace(/(\d{2})(\d+)/, '$1 $2');
    }
    
    input.value = value;
}

// File upload validation
function validateFileUpload(input, maxSize = 5, allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
    const file = input.files[0];
    
    if (!file) {
        return { valid: false, message: 'Aucun fichier sélectionné' };
    }
    
    // Check file type
    if (!allowedTypes.includes(file.type)) {
        return { 
            valid: false, 
            message: `Type de fichier non autorisé. Types acceptés: ${allowedTypes.join(', ')}` 
        };
    }
    
    // Check file size (in MB)
    const maxSizeBytes = maxSize * 1024 * 1024;
    if (file.size > maxSizeBytes) {
        return { 
            valid: false, 
            message: `Fichier trop volumineux. Taille maximum: ${maxSize}MB` 
        };
    }
    
    return { valid: true, message: 'Fichier valide' };
}

// Add CSS for validation styles
const validationStyles = `
    .error {
        border-color: #e74c3c !important;
        background-color: #fdf7f7;
    }
    
    .error:focus {
        border-color: #e74c3c !important;
        box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
    }
    
    .success {
        border-color: #2ecc71 !important;
    }
    
    .success:focus {
        border-color: #2ecc71 !important;
        box-shadow: 0 0 0 0.2rem rgba(46, 204, 113, 0.25);
    }
    
    .error-message {
        color: #e74c3c;
        font-size: 0.9rem;
        margin-top: 5px;
    }
    
    .form-error-message {
        background-color: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        border: 1px solid #f5c6cb;
    }
`;

// Add styles to document
if (!document.querySelector('#validation-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'validation-styles';
    styleSheet.textContent = validationStyles;
    document.head.appendChild(styleSheet);
}