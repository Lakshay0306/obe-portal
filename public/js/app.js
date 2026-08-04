// app.js

// Global Configuration
// Using relative path so it automatically works on any domain (localhost, Render, Vercel etc.)
const API_BASE_URL = '/api';

async function apiCall(endpoint, options = {}) {
    try {
        const token = localStorage.getItem('token');
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const response = await fetch(`${API_BASE_URL}/${endpoint}`, {
            credentials: 'omit',
            ...options,
            headers
        });
        
        if (response.status === 401 && !endpoint.includes('auth.php')) {
            logout();
            return { success: false, message: 'Session expired' };
        }
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error: ' + error.message };
    }
}

// Authentication Check
async function checkAuth() {
    const isLoginPage = window.location.pathname.includes('login.html');
    const token = localStorage.getItem('token');
    
    // Fast check: If no token and not on login page, redirect immediately
    if (!token && !isLoginPage) {
        window.location.href = 'login.html';
        return;
    }
    
    // Fast check: If token exists and on login page, go to dashboard
    if (token && isLoginPage) {
        window.location.href = 'index.html';
        return;
    }
    
    // Update user display without hitting the DB to save 300ms latency
    const userDisplay = document.getElementById('userNameDisplay');
    if (userDisplay && token) {
        userDisplay.textContent = localStorage.getItem('userName') || 'Admin';
    }
}

// Logout
async function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('userName');
    window.location.href = 'login.html';
}

// Run auth check on page load
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
});

function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    const icon = type === 'success' ? 'check-circle' : 'alert-circle';
    toast.innerHTML = `<i data-lucide="${icon}" class="toast-icon"></i> <span>${message}</span>`;
    container.appendChild(toast);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
