/**
 * JavaScript spécifique à l'interface client
 */

// Initialisation du dashboard client
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les fonctionnalités du dashboard
    initClientDashboard();
    
    // Initialiser les formulaires client
    initClientForms();
    
    // Initialiser les interactions spécifiques
    initClientInteractions();
    
    // Charger les données initiales
    loadDashboardData();
});

/**
 * Initialise le dashboard client
 */
function initClientDashboard() {
    // Gestion des notifications
    initNotifications();
    
    // Gestion des onglets
    initTabs();
    
    // Gestion des modales
    initModals();
    
    // Gestion des filtres
    initFilters();
    
    // Gestion des prévisualisations
    initPreviews();
}

/**
 * Initialise les formulaires client
 */
function initClientForms() {
    // Validation des formulaires
    initFormValidation();
    
    // Upload de fichiers
    initFileUpload();
    
    // Sélecteurs de couleur
    initColorPickers();
    
    // Sélecteurs de date
    initDatePickers();
}

/**
 * Initialise les interactions client
 */
function initClientInteractions() {
    // Tooltips
    initTooltips();
    
    // Confirmations
    initConfirmations();
    
    // Copie dans le presse-papier
    initClipboard();
    
    // Recherche en temps réel
    initLiveSearch();
}

/**
 * Charge les données du dashboard
 */
function loadDashboardData() {
    // Charger les statistiques
    loadStats();
    
    // Charger les cartes récentes
    loadRecentCards();
    
    // Charger l'activité récente
    loadRecentActivity();
}

/**
 * Gestion des notifications
 */
function initNotifications() {
    const notificationBell = document.querySelector('.notifications');
    if (!notificationBell) return;
    
    notificationBell.addEventListener('click', function() {
        toggleNotificationsPanel();
    });
    
    // Fermer les notifications en cliquant à l'extérieur
    document.addEventListener('click', function(event) {
        if (!notificationBell.contains(event.target) && 
            !document.querySelector('.notifications-panel')?.contains(event.target)) {
            closeNotificationsPanel();
        }
    });
}

function toggleNotificationsPanel() {
    let panel = document.querySelector('.notifications-panel');
    
    if (!panel) {
        panel = createNotificationsPanel();
        document.body.appendChild(panel);
    }
    
    panel.classList.toggle('show');
    
    if (panel.classList.contains('show')) {
        loadNotifications();
    }
}

function createNotificationsPanel() {
    const panel = document.createElement('div');
    panel.className = 'notifications-panel';
    panel.innerHTML = `
        <div class="notifications-header">
            <h3>Notifications</h3>
            <button class="mark-all-read">Tout marquer comme lu</button>
        </div>
        <div class="notifications-list">
            <div class="loading">Chargement...</div>
        </div>
        <div class="notifications-footer">
            <a href="notifications.php">Voir toutes les notifications</a>
        </div>
    `;
    
    // Styles
    panel.style.cssText = `
        position: fixed;
        top: 70px;
        right: 20px;
        width: 350px;
        max-width: 90vw;
        background: white;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        z-index: 1000;
        display: none;
        max-height: 80vh;
        overflow: hidden;
    `;
    
    // Ajouter le bouton "marquer comme lu"
    panel.querySelector('.mark-all-read').addEventListener('click', markAllNotificationsAsRead);
    
    return panel;
}

async function loadNotifications() {
    const list = document.querySelector('.notifications-list');
    if (!list) return;
    
    try {
        const response = await fetch('/api/client/get-notifications.php');
        const data = await response.json();
        
        if (data.success) {
            displayNotifications(data.notifications);
        } else {
            list.innerHTML = '<div class="empty">Erreur de chargement</div>';
        }
    } catch (error) {
        list.innerHTML = '<div class="empty">Erreur de connexion</div>';
    }
}

function displayNotifications(notifications) {
    const list = document.querySelector('.notifications-list');
    if (!list) return;
    
    if (notifications.length === 0) {
        list.innerHTML = '<div class="empty">Aucune notification</div>';
        return;
    }
    
    list.innerHTML = notifications.map(notif => `
        <div class="notification-item ${notif.read ? 'read' : 'unread'}">
            <div class="notification-icon">
                <i class="fas fa-${getNotificationIcon(notif.type)}"></i>
            </div>
            <div class="notification-content">
                <p class="notification-text">${notif.message}</p>
                <span class="notification-time">${formatTimeAgo(notif.created_at)}</span>
            </div>
            ${!notif.read ? '<div class="notification-dot"></div>' : ''}
        </div>
    `).join('');
}

function getNotificationIcon(type) {
    const icons = {
        'order': 'shopping-cart',
        'update': 'sync-alt',
        'support': 'headset',
        'system': 'cog',
        'warning': 'exclamation-triangle',
        'success': 'check-circle'
    };
    return icons[type] || 'bell';
}

function markAllNotificationsAsRead() {
    fetch('/api/client/mark-notifications-read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre à jour l'interface
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.add('read');
                item.classList.remove('unread');
                item.querySelector('.notification-dot')?.remove();
            });
            
            // Mettre à jour le compteur
            updateNotificationCount(0);
        }
    });
}

function updateNotificationCount(count) {
    const counter = document.querySelector('.notification-count');
    if (counter) {
        counter.textContent = count;
        counter.style.display = count > 0 ? 'flex' : 'none';
    }
}

function closeNotificationsPanel() {
    const panel = document.querySelector('.notifications-panel');
    if (panel) {
        panel.classList.remove('show');
    }
}

/**
 * Gestion des onglets
 */
function initTabs() {
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            const tabContainer = this.closest('.tabs');
            
            if (!tabContainer) return;
            
            // Mettre à jour les boutons actifs
            tabContainer.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
            
            // Afficher le contenu correspondant
            tabContainer.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            const targetContent = document.getElementById(tabId + '-tab');
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
}

/**
 * Gestion des modales
 */
function initModals() {
    // Ouvrir une modale
    document.querySelectorAll('[data-modal]').forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            openModal(modalId);
        });
    });
    
    // Fermer une modale
    document.querySelectorAll('.modal-close, .modal-overlay').forEach(element => {
        element.addEventListener('click', function() {
            closeModal(this.closest('.modal'));
        });
    });
    
    // Fermer avec la touche Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modal) {
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function closeAllModals() {
    document.querySelectorAll('.modal.active').forEach(modal => {
        closeModal(modal);
    });
}

/**
 * Gestion des filtres
 */
function initFilters() {
    document.querySelectorAll('.filter-btn').forEach(button => {
        button.addEventListener('click', function() {
            const filterValue = this.getAttribute('data-filter');
            
            // Mettre à jour le bouton actif
            this.parentElement.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
            
            // Appliquer le filtre
            applyFilter(filterValue);
        });
    });
}

function applyFilter(filter) {
    const items = document.querySelectorAll('.filterable-item');
    
    items.forEach(item => {
        const categories = item.getAttribute('data-category').split(' ');
        
        if (filter === 'all' || categories.includes(filter)) {
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
}

/**
 * Gestion des prévisualisations
 */
function initPreviews() {
    // Prévisualisation de carte
    document.querySelectorAll('.preview-btn').forEach(button => {
        button.addEventListener('click', function() {
            const cardId = this.getAttribute('data-card-id');
            previewCard(cardId);
        });
    });
    
    // Prévisualisation d'image
    document.querySelectorAll('.image-preview').forEach(image => {
        image.addEventListener('click', function() {
            openImagePreview(this.src);
        });
    });
}

function previewCard(cardId) {
    // Charger les données de la carte
    fetch(`/api/client/get-card.php?id=${cardId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showCardPreview(data.card);
            } else {
                showNotification('Erreur de chargement de la carte', 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur de connexion', 'error');
        });
}

function showCardPreview(card) {
    const modal = document.getElementById('card-preview-modal') || createCardPreviewModal();
    const previewContainer = modal.querySelector('.preview-container');
    
    // Remplir avec les données de la carte
    previewContainer.innerHTML = `
        <div class="card-preview-content">
            <div class="card-header">
                <span class="card-type ${card.category}">${card.category.toUpperCase()}</span>
                <span class="card-status ${card.status}">${card.status}</span>
            </div>
            <div class="card-body">
                <h3>${card.product_name}</h3>
                <p><strong>ID:</strong> #${card.id.toString().padStart(6, '0')}</p>
                <p><strong>Créée le:</strong> ${formatDate(card.created_at)}</p>
                ${card.nfc_data ? '<p><i class="fas fa-wifi"></i> Carte NFC active</p>' : ''}
            </div>
            <div class="card-design-preview">
                <!-- Afficher le design de la carte -->
            </div>
        </div>
    `;
    
    openModal('card-preview-modal');
}

function createCardPreviewModal() {
    const modal = document.createElement('div');
    modal.id = 'card-preview-modal';
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Aperçu de la carte</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="preview-container"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal(this.closest('.modal'))">Fermer</button>
                <a href="update-card.php" class="btn btn-primary">Modifier</a>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    return modal;
}

function openImagePreview(imageUrl) {
    const preview = document.getElementById('image-preview-modal') || createImagePreviewModal();
    const img = preview.querySelector('.preview-image');
    img.src = imageUrl;
    openModal('image-preview-modal');
}

function createImagePreviewModal() {
    const modal = document.createElement('div');
    modal.id = 'image-preview-modal';
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <div class="preview-image-container">
                <img class="preview-image" src="" alt="Preview">
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    return modal;
}

/**
 * Validation des formulaires
 */
function initFormValidation() {
    // Validation en temps réel
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
        
        field.addEventListener('input', function() {
            clearFieldError(this);
        });
    });
    
    // Validation à la soumission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';
    
    // Validation basique
    if (field.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'Ce champ est obligatoire';
    }
    
    // Validation spécifique par type
    if (isValid && value) {
        switch(field.type) {
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Adresse email invalide';
                }
                break;
                
            case 'tel':
                const phoneRegex = /^[0-9\s\-\+\(\)]{10,}$/;
                if (!phoneRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Numéro de téléphone invalide';
                }
                break;
                
            case 'url':
                try {
                    new URL(value);
                } catch {
                    isValid = false;
                    errorMessage = 'URL invalide';
                }
                break;
        }
    }
    
    // Validation personnalisée
    if (isValid && field.dataset.validate) {
        const validationType = field.dataset.validate;
        const validationValue = field.dataset.validateValue;
        
        switch(validationType) {
            case 'minlength':
                if (value.length < parseInt(validationValue)) {
                    isValid = false;
                    errorMessage = `Minimum ${validationValue} caractères`;
                }
                break;
                
            case 'maxlength':
                if (value.length > parseInt(validationValue)) {
                    isValid = false;
                    errorMessage = `Maximum ${validationValue} caractères`;
                }
                break;
                
            case 'pattern':
                const pattern = new RegExp(validationValue);
                if (!pattern.test(value)) {
                    isValid = false;
                    errorMessage = 'Format invalide';
                }
                break;
        }
    }
    
    // Afficher ou masquer l'erreur
    if (!isValid) {
        showFieldError(field, errorMessage);
    } else {
        clearFieldError(field);
    }
    
    return isValid;
}

function validateForm(form) {
    let isValid = true;
    const fields = form.querySelectorAll('input, select, textarea[required]');
    
    fields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    // Validation croisée (ex: confirmation de mot de passe)
    const password = form.querySelector('input[name="password"]');
    const confirmPassword = form.querySelector('input[name="confirm_password"]');
    
    if (password && confirmPassword && password.value !== confirmPassword.value) {
        isValid = false;
        showFieldError(confirmPassword, 'Les mots de passe ne correspondent pas');
    }
    
    if (!isValid) {
        showNotification('Veuillez corriger les erreurs dans le formulaire', 'error');
    }
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    
    field.classList.add('error');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        color: #e74c3c;
        font-size: 0.9rem;
        margin-top: 5px;
    `;
    
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.classList.remove('error');
    
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

/**
 * Upload de fichiers
 */
function initFileUpload() {
    document.querySelectorAll('.upload-area').forEach(area => {
        const input = area.querySelector('input[type="file"]');
        const preview = area.nextElementSibling?.classList.contains('upload-preview') 
            ? area.nextElementSibling 
            : null;
        
        if (!input) return;
        
        // Click sur la zone
        area.addEventListener('click', function() {
            input.click();
        });
        
        // Drag and drop
        area.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        area.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        
        area.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                handleFileUpload(input, preview);
            }
        });
        
        // Changement de fichier
        input.addEventListener('change', function() {
            handleFileUpload(this, preview);
        });
    });
}

function handleFileUpload(input, preview) {
    const file = input.files[0];
    if (!file) return;
    
    // Vérifier le type
    if (!file.type.startsWith('image/')) {
        showNotification('Veuillez sélectionner une image', 'error');
        input.value = '';
        return;
    }
    
    // Vérifier la taille
    if (file.size > 5 * 1024 * 1024) {
        showNotification('L\'image est trop volumineuse (max 5MB)', 'error');
        input.value = '';
        return;
    }
    
    // Afficher la prévisualisation
    const reader = new FileReader();
    reader.onload = function(e) {
        if (preview) {
            const img = preview.querySelector('img');
            if (img) {
                img.src = e.target.result;
            } else {
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            }
            preview.style.display = 'block';
        }
        
        // Mettre à jour le champ caché si présent
        const hiddenInput = input.closest('.upload-area').querySelector('input[type="hidden"]');
        if (hiddenInput) {
            hiddenInput.value = e.target.result;
        }
    };
    reader.readAsDataURL(file);
}

/**
 * Sélecteurs de couleur
 */
function initColorPickers() {
    document.querySelectorAll('.color-picker').forEach(input => {
        input.addEventListener('input', function() {
            updateColorPreview(this);
        });
        
        // Créer un sélecteur de couleur si nécessaire
        if (!this.hasAttribute('list')) {
            createColorPicker(this);
        }
    });
}

function createColorPicker(input) {
    const colorList = [
        '#2c3e50', '#3498db', '#9b59b6', '#1abc9c',
        '#e74c3c', '#f39c12', '#f1c40f', '#2ecc71',
        '#ffffff', '#ecf0f1', '#bdc3c7', '#95a5a6'
    ];
    
    const datalist = document.createElement('datalist');
    datalist.id = `colors-${input.id || Math.random().toString(36).substr(2, 9)}`;
    
    colorList.forEach(color => {
        const option = document.createElement('option');
        option.value = color;
        datalist.appendChild(option);
    });
    
    input.setAttribute('list', datalist.id);
    input.parentNode.appendChild(datalist);
}

function updateColorPreview(input) {
    const preview = input.nextElementSibling?.classList.contains('color-preview') 
        ? input.nextElementSibling 
        : createColorPreview(input);
    
    preview.style.backgroundColor = input.value;
}

function createColorPreview(input) {
    const preview = document.createElement('div');
    preview.className = 'color-preview';
    preview.style.cssText = `
        width: 30px;
        height: 30px;
        border-radius: 5px;
        border: 1px solid #ddd;
        margin-left: 10px;
        display: inline-block;
        vertical-align: middle;
    `;
    
    input.parentNode.appendChild(preview);
    return preview;
}

/**
 * Sélecteurs de date
 */
function initDatePickers() {
    document.querySelectorAll('input[type="date"]').forEach(input => {
        // Définir la date minimale si nécessaire
        if (!input.min) {
            const today = new Date();
            const minDate = new Date(today);
            minDate.setDate(today.getDate() + 1);
            input.min = minDate.toISOString().split('T')[0];
        }
    });
}

/**
 * Tooltips
 */
function initTooltips() {
    document.querySelectorAll('[title]').forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = this.title;
    
    document.body.appendChild(tooltip);
    
    const rect = this.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
    
    this._tooltip = tooltip;
}

function hideTooltip() {
    if (this._tooltip) {
        this._tooltip.remove();
        delete this._tooltip;
    }
}

/**
 * Confirmations
 */
function initConfirmations() {
    document.querySelectorAll('[data-confirm]').forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Êtes-vous sûr ?';
            
            if (!confirm(message)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    });
}

/**
 * Copie dans le presse-papier
 */
function initClipboard() {
    document.querySelectorAll('[data-copy]').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-copy');
            const target = document.getElementById(targetId) || 
                          this.previousElementSibling;
            
            if (target) {
                copyToClipboard(target.value || target.textContent);
                showNotification('Copié dans le presse-papier', 'success');
            }
        });
    });
}

function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}

/**
 * Recherche en temps réel
 */
function initLiveSearch() {
    const searchInputs = document.querySelectorAll('.live-search');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', debounce(function() {
            performLiveSearch(this.value, this.dataset.target);
        }, 300));
    });
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

async function performLiveSearch(query, targetId) {
    if (!query.trim()) {
        clearSearchResults(targetId);
        return;
    }
    
    try {
        const response = await fetch(`/api/client/search.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success) {
            displaySearchResults(data.results, targetId);
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}

function displaySearchResults(results, targetId) {
    const target = document.getElementById(targetId);
    if (!target) return;
    
    const resultsContainer = target.querySelector('.search-results') || 
                            createSearchResultsContainer(target);
    
    if (results.length === 0) {
        resultsContainer.innerHTML = '<div class="no-results">Aucun résultat</div>';
    } else {
        resultsContainer.innerHTML = results.map(result => `
            <div class="search-result" onclick="selectSearchResult(${result.id})">
                <h4>${result.title}</h4>
                <p>${result.description}</p>
            </div>
        `).join('');
    }
    
    resultsContainer.style.display = 'block';
}

function createSearchResultsContainer(target) {
    const container = document.createElement('div');
    container.className = 'search-results';
    container.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 5px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 100;
        display: none;
    `;
    target.appendChild(container);
    return container;
}

function clearSearchResults(targetId) {
    const target = document.getElementById(targetId);
    if (target) {
        const resultsContainer = target.querySelector('.search-results');
        if (resultsContainer) {
            resultsContainer.style.display = 'none';
        }
    }
}

function selectSearchResult(id) {
    // Implémenter la sélection du résultat
    console.log('Selected result:', id);
}

/**
 * Chargement des statistiques
 */
async function loadStats() {
    try {
        const response = await fetch('/api/client/get-stats.php');
        const data = await response.json();
        
        if (data.success) {
            updateStatsDisplay(data.stats);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

function updateStatsDisplay(stats) {
    // Mettre à jour les éléments de statistiques
    document.querySelectorAll('[data-stat]').forEach(element => {
        const statName = element.getAttribute('data-stat');
        if (stats[statName] !== undefined) {
            element.textContent = stats[statName];
        }
    });
}

/**
 * Chargement des cartes récentes
 */
async function loadRecentCards() {
    try {
        const response = await fetch('/api/client/get-recent-cards.php');
        const data = await response.json();
        
        if (data.success) {
            updateRecentCardsDisplay(data.cards);
        }
    } catch (error) {
        console.error('Error loading recent cards:', error);
    }
}

function updateRecentCardsDisplay(cards) {
    const container = document.querySelector('.recent-cards .cards-grid');
    if (!container) return;
    
    if (cards.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-id-card"></i>
                <h3>Vous n'avez pas encore de cartes</h3>
                <p>Créez votre première carte de visite pour commencer</p>
                <a href="my-cards.php?new=true" class="btn btn-primary">Créer une carte</a>
            </div>
        `;
    } else {
        container.innerHTML = cards.map(card => `
            <div class="card-item">
                <div class="card-header">
                    <span class="card-type ${card.category}">${card.category.toUpperCase()}</span>
                    <span class="card-status ${card.status}">${card.status}</span>
                </div>
                <div class="card-body">
                    <h3>${card.product_name}</h3>
                    <p>Créée le: ${formatDate(card.created_at)}</p>
                    ${card.nfc_data ? '<p><i class="fas fa-wifi"></i> Carte NFC active</p>' : ''}
                </div>
                <div class="card-actions">
                    <button class="btn-action" onclick="previewCard(${card.id})">
                        <i class="fas fa-eye"></i> Aperçu
                    </button>
                    <a href="update-card.php?id=${card.id}" class="btn-action">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                </div>
            </div>
        `).join('');
    }
}

/**
 * Chargement de l'activité récente
 */
async function loadRecentActivity() {
    try {
        const response = await fetch('/api/client/get-recent-activity.php');
        const data = await response.json();
        
        if (data.success) {
            updateRecentActivityDisplay(data.activities);
        }
    } catch (error) {
        console.error('Error loading recent activity:', error);
    }
}

function updateRecentActivityDisplay(activities) {
    const container = document.querySelector('.activity-list');
    if (!container) return;
    
    container.innerHTML = activities.map(activity => `
        <div class="activity-item">
            <div class="activity-icon">
                <i class="fas fa-${getActivityIcon(activity.type)}"></i>
            </div>
            <div class="activity-content">
                <p>${activity.description}</p>
                <span class="activity-time">${formatTimeAgo(activity.created_at)}</span>
            </div>
        </div>
    `).join('');
}

function getActivityIcon(type) {
    const icons = {
        'login': 'sign-in-alt',
        'order': 'shopping-cart',
        'update': 'sync-alt',
        'create': 'plus-circle',
        'delete': 'trash',
        'upgrade': 'level-up-alt'
    };
    return icons[type] || 'circle';
}

/**
 * Fonctions utilitaires
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR');
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'À l\'instant';
    if (diffMins < 60) return `Il y a ${diffMins} min`;
    if (diffHours < 24) return `Il y a ${diffHours} h`;
    if (diffDays < 7) return `Il y a ${diffDays} j`;
    
    return formatDate(dateString);
}

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
    
    // Styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
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
    `;
    
    // Bouton de fermeture
    notification.querySelector('.notification-close').addEventListener('click', function() {
        notification.remove();
    });
    
    // Animation d'entrée
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Auto-remove après 5 secondes
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.transform = 'translateX(120%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

function getNotificationIcon(type) {
    switch(type) {
        case 'success': return 'check-circle';
        case 'warning': return 'exclamation-triangle';
        case 'error': return 'times-circle';
        default: return 'info-circle';
    }
}

// Exposer les fonctions globales
window.previewCard = previewCard;
window.selectSearchResult = selectSearchResult;