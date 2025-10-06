document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.getElementById('nav-toggle');
    const navMenu = document.getElementById('nav-menu');

    if (navToggle) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }

    const forms = document.querySelectorAll('form[data-ajax="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', handleAjaxForm);
    });
});

async function handleAjaxForm(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const errorDiv = form.querySelector('.error-message');
    const successDiv = form.querySelector('.success-message');

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Loading...';
    }

    if (errorDiv) errorDiv.style.display = 'none';
    if (successDiv) successDiv.style.display = 'none';

    try {
        const response = await fetch(form.action, {
            method: form.method,
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            if (successDiv) {
                successDiv.textContent = data.message || 'Success!';
                successDiv.style.display = 'block';
            }

            if (data.data && data.data.redirect_url) {
                setTimeout(() => {
                    window.location.href = data.data.redirect_url;
                }, 1000);
            }

            form.reset();
        } else {
            if (errorDiv) {
                errorDiv.textContent = data.error || 'An error occurred';
                errorDiv.style.display = 'block';
            }
        }
    } catch (error) {
        if (errorDiv) {
            errorDiv.textContent = 'Network error. Please try again.';
            errorDiv.style.display = 'block';
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = form.dataset.submitText || 'Submit';
        }
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 8px;
        background-color: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        font-weight: 500;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePassword(password) {
    return password.length >= 8 &&
           /[A-Z]/.test(password) &&
           /[a-z]/.test(password) &&
           /[0-9]/.test(password);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('en-US', options);
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

async function uploadFile(file, endpoint) {
    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            return data.data;
        } else {
            throw new Error(data.error || 'Upload failed');
        }
    } catch (error) {
        console.error('Upload error:', error);
        throw error;
    }
}

function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copied to clipboard!', 'success');
        });
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showNotification('Copied to clipboard!', 'success');
    }
}

const CSS_ANIMATIONS = `
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
`;

const style = document.createElement('style');
style.textContent = CSS_ANIMATIONS;
document.head.appendChild(style);

window.showNotification = showNotification;
window.validateEmail = validateEmail;
window.validatePassword = validatePassword;
window.formatDate = formatDate;
window.formatDateTime = formatDateTime;
window.uploadFile = uploadFile;
window.copyToClipboard = copyToClipboard;
window.debounce = debounce;
