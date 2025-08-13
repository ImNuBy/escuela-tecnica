// Función para toggle del sidebar en móvil
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
}

// Cerrar sidebar al hacer click fuera (móvil)
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.querySelector('.mobile-menu-toggle');
    
    if (sidebar && toggleButton && window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggleButton.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    }
});

// Función para confirmar eliminación
function confirmarEliminacion(mensaje = '¿Está seguro que desea eliminar este elemento?') {
    return confirm(mensaje);
}

// Función para mostrar/ocultar contraseña
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        const button = input.nextElementSibling;
        
        if (input.type === 'password') {
            input.type = 'text';
            if (button) button.textContent = '🙈';
        } else {
            input.type = 'password';
            if (button) button.textContent = '👁️';
        }
    }
}

// Función para filtrar tablas
function filtrarTabla(inputId, tablaId) {
    const input = document.getElementById(inputId);
    const tabla = document.getElementById(tablaId);
    
    if (input && tabla) {
        const filas = tabla.getElementsByTagName('tr');
        
        input.addEventListener('keyup', function() {
            const filtro = this.value.toLowerCase();
            
            for (let i = 1; i < filas.length; i++) {
                const fila = filas[i];
                const texto = fila.textContent.toLowerCase();
                
                if (texto.includes(filtro)) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            }
        });
    }
}

// Función para validar formularios
function validarFormulario(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let valido = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('error');
            valido = false;
        } else {
            input.classList.remove('error');
        }
    });
    
    return valido;
}

// Función para mostrar mensajes toast
function mostrarToast(mensaje, tipo = 'info') {
    // Crear elemento toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    toast.textContent = mensaje;
    
    // Estilos del toast
    Object.assign(toast.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '12px 24px',
        borderRadius: '8px',
        color: '#fff',
        fontWeight: '500',
        zIndex: '9999',
        opacity: '0',
        transform: 'translateY(-20px)',
        transition: 'all 0.3s ease',
        maxWidth: '300px',
        wordWrap: 'break-word'
    });
    
    // Colores según tipo
    const colores = {
        'success': '#059669',
        'error': '#dc2626',
        'warning': '#d97706',
        'info': '#2563eb'
    };
    
    toast.style.backgroundColor = colores[tipo] || colores.info;
    
    document.body.appendChild(toast);
    
    // Mostrar toast
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    }, 100);
    
    // Ocultar toast
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Función para cargar contenido con AJAX
function cargarContenido(url, contenedorId) {
    const contenedor = document.getElementById(contenedorId);
    if (!contenedor) return;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.text();
        })
        .then(data => {
            contenedor.innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarToast('Error al cargar el contenido', 'error');
        });
}

// Función para formatear fecha - CORREGIDA
function formatearFecha(fecha) {
    if (!fecha || fecha === 'N/A' || fecha === null) return 'N/A';
    
    try {
        // Si ya está en formato dd/mm/yyyy, devolverlo
        if (/^\d{2}\/\d{2}\/\d{4}$/.test(fecha)) {
            return fecha;
        }
        
        // Crear objeto Date
        let fechaObj;
        
        // Si es formato YYYY-MM-DD
        if (/^\d{4}-\d{2}-\d{2}/.test(fecha)) {
            fechaObj = new Date(fecha + 'T00:00:00');
        } else {
            fechaObj = new Date(fecha);
        }
        
        // Verificar si la fecha es válida
        if (isNaN(fechaObj.getTime())) {
            return 'Fecha inválida';
        }
        
        // Formatear a DD/MM/YYYY
        const dia = fechaObj.getDate().toString().padStart(2, '0');
        const mes = (fechaObj.getMonth() + 1).toString().padStart(2, '0');
        const año = fechaObj.getFullYear();
        
        return `${dia}/${mes}/${año}`;
    } catch (error) {
        console.error('Error al formatear fecha:', error);
        return 'Error en fecha';
    }
}

// Función para formatear hora - CORREGIDA
function formatearHora(hora) {
    if (!hora || hora === 'N/A' || hora === null) return 'N/A';
    
    try {
        // Si ya está en formato HH:MM, devolverlo
        if (/^\d{2}:\d{2}$/.test(hora)) {
            return hora;
        }
        
        // Si es formato HH:MM:SS, extraer HH:MM
        if (/^\d{2}:\d{2}:\d{2}$/.test(hora)) {
            return hora.substring(0, 5);
        }
        
        // Si es un timestamp o fecha completa
        const horaObj = new Date(hora);
        if (isNaN(horaObj.getTime())) {
            return 'Hora inválida';
        }
        
        // Formatear a HH:MM
        const horas = horaObj.getHours().toString().padStart(2, '0');
        const minutos = horaObj.getMinutes().toString().padStart(2, '0');
        
        return `${horas}:${minutos}`;
    } catch (error) {
        console.error('Error al formatear hora:', error);
        return 'Error en hora';
    }
}

// Inicialización cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    // Marcar el elemento activo en el sidebar
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.classList.remove('active');
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        }
    });
    
    // Configurar tooltips si existen
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            
            Object.assign(tooltip.style, {
                position: 'absolute',
                backgroundColor: '#1f2937',
                color: '#fff',
                padding: '8px 12px',
                borderRadius: '6px',
                fontSize: '12px',
                zIndex: '1000',
                pointerEvents: 'none',
                whiteSpace: 'nowrap'
            });
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = document.querySelector('.tooltip');
            if (tooltip) {
                document.body.removeChild(tooltip);
            }
        });
    });
    
    // Auto-resize para textareas
    const textareas = document.querySelectorAll('textarea[data-auto-resize]');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
    
    // Manejar formularios con validación automática
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validarFormulario(this.id)) {
                e.preventDefault();
                mostrarToast('Por favor complete todos los campos requeridos', 'error');
            }
        });
    });
});

// Función para auto-guardar formularios
function autoGuardar(formId, url) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            const formData = new FormData(form);
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarToast('Guardado automáticamente', 'success');
                }
            })
            .catch(error => {
                console.error('Error en auto-guardado:', error);
            });
        });
    });
}

// Función para exportar datos a Excel/CSV
function exportarExcel(tablaId, nombreArchivo) {
    const tabla = document.getElementById(tablaId);
    if (!tabla) {
        mostrarToast('Tabla no encontrada', 'error');
        return;
    }
    
    let csv = [];
    const filas = tabla.querySelectorAll('tr');
    
    filas.forEach(fila => {
        const cols = fila.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            // Limpiar el texto y escapar comillas
            let texto = col.textContent.trim().replace(/"/g, '""');
            rowData.push(`"${texto}"`);
        });
        csv.push(rowData.join(','));
    });
    
    const csvString = '\ufeff' + csv.join('\n'); // BOM para UTF-8
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = (nombreArchivo || 'export') + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    mostrarToast('Archivo exportado exitosamente', 'success');
}

// Función para confirmar acciones
function confirmarAccion(mensaje, callback) {
    if (confirm(mensaje)) {
        callback();
    }
}

// Función para validar email
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// Función para validar DNI
function validarDNI(dni) {
    const regex = /^\d{7,8}$/;
    return regex.test(dni);
}

// Función para loading state en botones
function toggleLoadingButton(buttonId, loading = true) {
    const button = document.getElementById(buttonId);
    if (!button) return;
    
    if (loading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Cargando...';
        button.style.opacity = '0.7';
    } else {
        button.disabled = false;
        button.textContent = button.dataset.originalText || button.textContent;
        button.style.opacity = '1';
    }
}

// Manejo de errores globales
window.addEventListener('error', function(event) {
    console.error('Error global:', event.error);
    mostrarToast('Ocurrió un error inesperado', 'error');
});

// Función para scroll suave
function scrollSuave(elementoId) {
    const elemento = document.getElementById(elementoId);
    if (elemento) {
        elemento.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Detectar modo oscuro del sistema
function detectarModoOscuro() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

// Función para copiar al portapapeles
function copiarAlPortapapeles(texto) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(texto).then(() => {
            mostrarToast('Copiado al portapapeles', 'success');
        }).catch(() => {
            mostrarToast('Error al copiar', 'error');
        });
    } else {
        // Fallback para navegadores más antiguos
        const textArea = document.createElement('textarea');
        textArea.value = texto;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            mostrarToast('Copiado al portapapeles', 'success');
        } catch {
            mostrarToast('Error al copiar', 'error');
        }
        document.body.removeChild(textArea);
    }
}

// Función para formatear números
function formatearNumero(numero, decimales = 0) {
    if (isNaN(numero)) return 'N/A';
    return new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales
    }).format(numero);
}

// Función para debounce (evitar múltiples llamadas)
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

// Función para throttle (limitar frecuencia de llamadas)
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Función para verificar conectividad
function verificarConectividad() {
    return navigator.onLine;
}

// Event listeners para conectividad
window.addEventListener('online', function() {
    mostrarToast('Conexión restaurada', 'success');
});

window.addEventListener('offline', function() {
    mostrarToast('Sin conexión a internet', 'warning');
});

// Función para realizar peticiones AJAX con manejo de errores
async function peticionAjax(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const contentType = response.headers.get('Content-Type');
        
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        } else {
            return await response.text();
        }
    } catch (error) {
        console.error('Error en petición AJAX:', error);
        mostrarToast('Error de conexión', 'error');
        throw error;
    }
}

// Función para confirmar navegación si hay cambios sin guardar
function confirmarSalida() {
    const formularios = document.querySelectorAll('form[data-confirm-exit]');
    let haycambios = false;
    
    formularios.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.dataset.originalValue !== input.value) {
                hayChangios = true;
            }
        });
    });
    
    if (hayChangios) {
        return 'Hay cambios sin guardar. ¿Está seguro que desea salir?';
    }
}

// Configurar confirmación de salida
window.addEventListener('beforeunload', confirmarSalida);

// Función para manejo de errores de formularios
function manejarErrorFormulario(form, errores) {
    // Limpiar errores anteriores
    form.querySelectorAll('.error-message').forEach(error => error.remove());
    form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
    
    // Mostrar nuevos errores
    Object.keys(errores).forEach(campo => {
        const input = form.querySelector(`[name="${campo}"]`);
        if (input) {
            input.classList.add('error');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = errores[campo];
            errorDiv.style.color = '#dc2626';
            errorDiv.style.fontSize = '0.875rem';
            errorDiv.style.marginTop = '0.25rem';
            
            input.parentNode.appendChild(errorDiv);
        }
    });
}

// Función para limpiar errores de formulario
function limpiarErroresFormulario(form) {
    form.querySelectorAll('.error-message').forEach(error => error.remove());
    form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
}

// Exportar funciones para uso global
window.toggleSidebar = toggleSidebar;
window.confirmarEliminacion = confirmarEliminacion;
window.togglePassword = togglePassword;
window.filtrarTabla = filtrarTabla;
window.validarFormulario = validarFormulario;
window.mostrarToast = mostrarToast;
window.cargarContenido = cargarContenido;
window.formatearFecha = formatearFecha;
window.formatearHora = formatearHora;
window.exportarExcel = exportarExcel;
window.confirmarAccion = confirmarAccion;
window.validarEmail = validarEmail;
window.validarDNI = validarDNI;
window.copiarAlPortapapeles = copiarAlPortapapeles;
window.formatearNumero = formatearNumero;
window.peticionAjax = peticionAjax;