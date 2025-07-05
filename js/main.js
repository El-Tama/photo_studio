// ===== CONFIGURACIÓN INICIAL =====
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    initNavigation();
    initAnimations();
    initImageOptimization();
    initFormValidation();
    initSmoothScrolling();
    initBackToTop();
    initPortfolioFilters();
    addMobileDebugInfo();
}

// ===== NAVEGACIÓN =====
function initNavigation() {
    const navbar = document.querySelector('.navbar');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.style.backgroundColor = 'rgba(26, 37, 47, 0.95)';
            navbar.style.backdropFilter = 'blur(10px)';
        } else {
            navbar.style.backgroundColor = 'var(--dark-color)';
            navbar.style.backdropFilter = 'none';
        }
    });
    
    // Active navigation link
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            navLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Mobile menu close on link click
    const navbarCollapse = document.querySelector('.navbar-collapse');
    const navbarToggler = document.querySelector('.navbar-toggler');
    
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (navbarCollapse.classList.contains('show')) {
                navbarToggler.click();
            }
        });
    });
}

// ===== ANIMACIONES =====
function initAnimations() {
    // Intersection Observer para animaciones
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observar elementos para animar
    const elementsToAnimate = document.querySelectorAll('.card, h2, .lead');
    elementsToAnimate.forEach(el => {
        observer.observe(el);
    });
}

// ===== OPTIMIZACIÓN DE IMÁGENES =====
function initImageOptimization() {
    const images = document.querySelectorAll('img');
    
    // Lazy loading para imágenes
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src || img.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => {
        imageObserver.observe(img);
        
        // Error handling para imágenes
        img.addEventListener('error', function() {
            this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjI1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjhmOWZhIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzZjNzU3ZCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlbiBubyBkaXNwb25pYmxlPC90ZXh0Pjwvc3ZnPg==';
            this.alt = 'Imagen no disponible';
        });
    });
}

// ===== VALIDACIÓN DE FORMULARIOS =====
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
        
        // Validación en tiempo real
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });
        });
    });
}

function validateField(field) {
    const isValid = field.checkValidity();
    field.classList.toggle('is-valid', isValid);
    field.classList.toggle('is-invalid', !isValid);
    
    // Mostrar mensajes de error personalizados
    const errorDiv = field.parentNode.querySelector('.invalid-feedback');
    if (errorDiv && !isValid) {
        errorDiv.textContent = getCustomErrorMessage(field);
    }
}

function getCustomErrorMessage(field) {
    if (field.validity.valueMissing) {
        return 'Este campo es obligatorio';
    }
    if (field.validity.typeMismatch) {
        if (field.type === 'email') return 'Ingrese un email válido';
        if (field.type === 'tel') return 'Ingrese un teléfono válido';
    }
    if (field.validity.tooShort) {
        return `Mínimo ${field.minLength} caracteres`;
    }
    return 'Valor inválido';
}

// ===== SCROLL SUAVE =====
function initSmoothScrolling() {
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            
            if (targetSection) {
                const offsetTop = targetSection.offsetTop - 80; // Ajustar por navbar
                
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// ===== BOTÓN VOLVER ARRIBA =====
function initBackToTop() {
    // Crear botón si no existe
    let backToTopBtn = document.querySelector('.back-to-top');
    if (!backToTopBtn) {
        backToTopBtn = document.createElement('button');
        backToTopBtn.className = 'back-to-top';
        backToTopBtn.innerHTML = '↑';
        backToTopBtn.setAttribute('aria-label', 'Volver arriba');
        document.body.appendChild(backToTopBtn);
    }
    
    // Mostrar/ocultar botón basado en scroll
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            backToTopBtn.style.display = 'block';
            setTimeout(() => backToTopBtn.style.opacity = '1', 10);
        } else {
            backToTopBtn.style.opacity = '0';
            setTimeout(() => backToTopBtn.style.display = 'none', 300);
        }
    });
    
    // Funcionalidad del botón
    backToTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// ===== FILTROS DE PORTAFOLIO =====
function initPortfolioFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-item');
    
    if (filterButtons.length === 0 || galleryItems.length === 0) return;
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            
            // Actualizar botones activos
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filtrar elementos con animaciones mejoradas
            galleryItems.forEach((item, index) => {
                const category = item.getAttribute('data-category');
                
                if (filter === 'all' || category === filter) {
                    // Mostrar elemento
                    item.style.display = 'block';
                    
                    // Animación escalonada para entrada
                    setTimeout(() => {
                        item.classList.add('show');
                        item.style.opacity = '1';
                        item.style.transform = 'scale(1)';
                    }, index * 100); // Delay escalonado
                } else {
                    // Ocultar elemento
                    item.classList.remove('show');
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.8)';
                    
                    setTimeout(() => {
                        item.style.display = 'none';
                    }, 300);
                }
            });
            
            // Scroll suave a la galería en móviles
            if (window.innerWidth <= 768) {
                const gallery = document.getElementById('portfolioGallery');
                if (gallery) {
                    gallery.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });
    
    // Inicializar con todos los elementos visibles
    galleryItems.forEach((item, index) => {
        item.style.opacity = '1';
        item.style.transform = 'scale(1)';
        item.style.transition = 'all 0.4s ease';
        
        // Animación inicial escalonada
        setTimeout(() => {
            item.classList.add('show');
        }, index * 50);
    });
    
    // Mejorar accesibilidad - navegación con teclado
    filterButtons.forEach((button, index) => {
        button.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && index > 0) {
                filterButtons[index - 1].focus();
                e.preventDefault();
            } else if (e.key === 'ArrowRight' && index < filterButtons.length - 1) {
                filterButtons[index + 1].focus();
                e.preventDefault();
            }
        });
    });
}

// ===== UTILIDADES =====

// Loading state para botones
function setButtonLoading(button, isLoading = true) {
    if (isLoading) {
        button.dataset.originalText = button.textContent;
        button.textContent = '';
        button.classList.add('loading');
        button.disabled = true;
    } else {
        button.textContent = button.dataset.originalText;
        button.classList.remove('loading');
        button.disabled = false;
    }
}

// Notificaciones toast
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Detectar dispositivo móvil
function isMobile() {
    return window.innerWidth <= 768;
}

// Throttle para eventos de scroll
function throttle(func, wait) {
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

// ===== PERFORMANCE =====

// Preload de imágenes críticas
function preloadImages() {
    const criticalImages = [
        '../img/hero-bg.jpg',
        '../img/bodas.jpg',
        '../img/retratos.jpg',
        '../img/productos.jpg'
    ];
    
    criticalImages.forEach(src => {
        const img = new Image();
        img.src = src;
    });
}

// ===== CSS ADICIONAL PARA JS =====
const additionalCSS = `
.back-to-top {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    background: var(--secondary-color);
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 20px;
    cursor: pointer;
    display: none;
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.back-to-top:hover {
    background: var(--primary-color);
    transform: translateY(-2px);
}

.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    transform: translateX(100%);
    transition: transform 0.3s ease;
    z-index: 1001;
    max-width: 300px;
}

.toast-notification.show {
    transform: translateX(0);
}

.toast-success { border-left: 4px solid #28a745; }
.toast-error { border-left: 4px solid #dc3545; }
.toast-warning { border-left: 4px solid #ffc107; }
.toast-info { border-left: 4px solid #17a2b8; }

.lazy {
    opacity: 0;
    transition: opacity 0.3s;
}

.lazy.loaded {
    opacity: 1;
}

@media (max-width: 768px) {
    .back-to-top {
        width: 45px;
        height: 45px;
        bottom: 15px;
        right: 15px;
        font-size: 18px;
    }
    
    .toast-notification {
        max-width: calc(100% - 40px);
        right: 20px;
        left: 20px;
    }
}
`;

// Agregar CSS adicional
const style = document.createElement('style');
style.textContent = additionalCSS;
document.head.appendChild(style);

// Inicializar preload de imágenes
preloadImages();

// ===== UTILIDADES PARA DEBUGGING EN MÓVILES =====
function addMobileDebugInfo() {
    if (window.innerWidth <= 768) {
        console.log('📱 Modo móvil detectado');
        console.log('Ancho de pantalla:', window.innerWidth);
        console.log('Alto de pantalla:', window.innerHeight);
        console.log('Orientación:', window.innerHeight > window.innerWidth ? 'Portrait' : 'Landscape');
        
        // Mostrar información de viewport en desarrollo
        if (location.hostname === 'localhost' || location.hostname === '127.0.0.1') {
            const debugInfo = document.createElement('div');
            debugInfo.style.cssText = `
                position: fixed;
                top: 0;
                right: 0;
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 5px;
                font-size: 10px;
                z-index: 9999;
                font-family: monospace;
            `;
            debugInfo.innerHTML = `${window.innerWidth}x${window.innerHeight}`;
            document.body.appendChild(debugInfo);
        }
    }
}

// ===== MANEJO DE REDIMENSIONAMIENTO =====
function handleResize() {
    // Reajustar filtros en cambio de orientación
    const filters = document.querySelector('.portfolio-filters');
    if (filters && window.innerWidth <= 768) {
        filters.scrollLeft = 0; // Reset scroll horizontal
    }
}

// Escuchar cambios de orientación y redimensionamiento
window.addEventListener('resize', debounce(handleResize, 250));
window.addEventListener('orientationchange', function() {
    setTimeout(handleResize, 500); // Delay para esperar el cambio completo
});

// Función debounce para optimizar performance
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

// ===== CONTINUAR CON FUNCIONES EXISTENTES =====
