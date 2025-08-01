// Main JavaScript file for College Club Management System

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', function() {
            navLinks.classList.toggle('show');
        });
    }
    
    // Close alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const closeBtn = alert.querySelector('.close-alert');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                alert.style.display = 'none';
            });
        }
    });
    
    // Modal functionality
    const modalTriggers = document.querySelectorAll('[data-modal-target]');
    const modalCloseButtons = document.querySelectorAll('[data-modal-close]');
    const modalOverlays = document.querySelectorAll('.modal-overlay');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal-target');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.parentElement.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });
    
    modalCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    modalOverlays.forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    const formGroup = field.closest('.form-group');
                    if (formGroup) {
                        const errorElement = formGroup.querySelector('.form-error');
                        if (errorElement) {
                            errorElement.textContent = 'This field is required';
                        } else {
                            const error = document.createElement('div');
                            error.className = 'form-error';
                            error.textContent = 'This field is required';
                            formGroup.appendChild(error);
                        }
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
    
    // Club search and filter
    const clubSearch = document.getElementById('club-search');
    const clubFilter = document.getElementById('club-filter');
    const clubCards = document.querySelectorAll('.club-card');
    
    if (clubSearch) {
        clubSearch.addEventListener('input', filterClubs);
    }
    
    if (clubFilter) {
        clubFilter.addEventListener('change', filterClubs);
    }
    
    function filterClubs() {
        const searchTerm = clubSearch ? clubSearch.value.toLowerCase() : '';
        const filterValue = clubFilter ? clubFilter.value : 'all';
        
        clubCards.forEach(card => {
            const clubName = card.querySelector('h3').textContent.toLowerCase();
            const clubCategory = card.querySelector('.club-category').textContent.toLowerCase();
            
            const matchesSearch = clubName.includes(searchTerm);
            const matchesFilter = filterValue === 'all' || clubCategory === filterValue.toLowerCase();
            
            if (matchesSearch && matchesFilter) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // Event search and filter
    const eventSearch = document.getElementById('event-search');
    const eventFilter = document.getElementById('event-filter');
    const eventCards = document.querySelectorAll('.event-card');
    
    if (eventSearch) {
        eventSearch.addEventListener('input', filterEvents);
    }
    
    if (eventFilter) {
        eventFilter.addEventListener('change', filterEvents);
    }
    
    function filterEvents() {
        const searchTerm = eventSearch ? eventSearch.value.toLowerCase() : '';
        const filterValue = eventFilter ? eventFilter.value : 'all';
        
        eventCards.forEach(card => {
            const eventTitle = card.querySelector('h3').textContent.toLowerCase();
            const eventClub = card.querySelector('.event-club').textContent.toLowerCase();
            
            const matchesSearch = eventTitle.includes(searchTerm) || eventClub.includes(searchTerm);
            const matchesFilter = filterValue === 'all' || (
                (filterValue === 'upcoming' && !card.classList.contains('past')) ||
                (filterValue === 'past' && card.classList.contains('past'))
            );
            
            if (matchesSearch && matchesFilter) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // File upload preview
    const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const previewId = this.getAttribute('data-preview');
            const preview = document.getElementById(previewId);
            
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        preview.style.backgroundImage = `url(${e.target.result})`;
                    }
                    preview.style.display = '';
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    
    // Tabs functionality
    const tabContainers = document.querySelectorAll('.tabs-container');
    tabContainers.forEach(container => {
        const tabs = container.querySelectorAll('.tab');
        const tabContents = container.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and tab contents
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to current tab and tab content
                this.classList.add('active');
                const activeContent = container.querySelector(`.tab-content[data-tab="${tabId}"]`);
                if (activeContent) {
                    activeContent.classList.add('active');
                }
            });
        });
    });
    
    // AJAX form submission
    const ajaxForms = document.querySelectorAll('form[data-ajax]');
    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const url = this.getAttribute('action');
            const method = this.getAttribute('method') || 'POST';
            const responseContainer = document.querySelector(this.getAttribute('data-response')) || null;
            
            fetch(url, {
                method: method,
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (responseContainer) {
                    responseContainer.innerHTML = '';
                    
                    const alert = document.createElement('div');
                    alert.className = `alert ${data.success ? 'alert-success' : 'alert-danger'}`;
                    alert.textContent = data.message;
                    
                    responseContainer.appendChild(alert);
                }
                
                if (data.success && data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                }
                
                if (data.success && this.getAttribute('data-reset') === 'true') {
                    this.reset();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (responseContainer) {
                    responseContainer.innerHTML = '';
                    
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-danger';
                    alert.textContent = 'An error occurred. Please try again.';
                    
                    responseContainer.appendChild(alert);
                }
            });
        });
    });
    
    // Rating system
    const ratingInputs = document.querySelectorAll('.rating-input');
    ratingInputs.forEach(input => {
        const stars = input.querySelectorAll('.star');
        const ratingValue = input.querySelector('input[type="hidden"]');
        
        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = index + 1;
                ratingValue.value = rating;
                
                // Update UI
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            
            star.addEventListener('mouseover', function() {
                const rating = index + 1;
                
                // Update UI on hover
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.classList.add('hover');
                    } else {
                        s.classList.remove('hover');
                    }
                });
            });
            
            star.addEventListener('mouseout', function() {
                stars.forEach(s => s.classList.remove('hover'));
            });
        });
    });
});
