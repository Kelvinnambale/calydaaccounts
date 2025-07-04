//Sidebar JavaScript

// System uptime tracking
let systemStartTime = new Date();
if (localStorage.getItem('systemStartTime')) {
    systemStartTime = new Date(localStorage.getItem('systemStartTime'));
} else {
    localStorage.setItem('systemStartTime', systemStartTime.toISOString());
}

// Update system uptime display
function updateSystemUptime() {
    const now = new Date();
    const uptime = now - systemStartTime;
    
    const days = Math.floor(uptime / (1000 * 60 * 60 * 24));
    const hours = Math.floor((uptime % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((uptime % (1000 * 60 * 60)) / (1000 * 60));
    
    let uptimeString = '';
    if (days > 0) {
        uptimeString += `${days}d `;
    }
    if (hours > 0) {
        uptimeString += `${hours}h `;
    }
    uptimeString += `${minutes}m`;
    
    const uptimeElement = document.getElementById('systemUptime');
    if (uptimeElement) {
        uptimeElement.textContent = uptimeString;
    }
}

// Enhanced sidebar toggle function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        
        // Prevent body scroll when sidebar is open on mobile
        if (window.innerWidth <= 768) {
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : 'auto';
        }
    }
}

// Close sidebar function
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

// Handle window resize
function handleResize() {
    if (window.innerWidth > 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar && overlay) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Update uptime immediately and then every minute
    updateSystemUptime();
    setInterval(updateSystemUptime, 60000);
    
    // Add resize event listener
    window.addEventListener('resize', handleResize);
    
    // Add click event to navigation links for mobile
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });
    
    // Add escape key listener to close sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth <= 768) {
            closeSidebar();
        }
    });
    
    // Smooth scroll for navigation
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Add loading state
            this.style.opacity = '0.7';
            setTimeout(() => {
                this.style.opacity = '1';
            }, 300);
        });
    });
});

// Add touch gesture support for mobile
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
}, false);

document.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleGesture();
}, false);

function handleGesture() {
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        const swipeThreshold = 50;
        
        // Swipe right to open sidebar
        if (touchEndX - touchStartX > swipeThreshold && touchStartX < 50) {
            if (sidebar && !sidebar.classList.contains('show')) {
                toggleSidebar();
            }
        }
        
        // Swipe left to close sidebar
        if (touchStartX - touchEndX > swipeThreshold && sidebar && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    }
}

// System status check (optional - you can implement server-side status check)
function checkSystemStatus() {
    // This could be enhanced to check actual server status
    const statusIndicator = document.querySelector('.status-indicator');
    if (statusIndicator) {
        // Simulate status check
        fetch('/api/system-status')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'online') {
                    statusIndicator.style.background = '#4ade80';
                } else {
                    statusIndicator.style.background = '#f87171';
                }
            })
            .catch(() => {
                // If API is not available, assume system is online
                statusIndicator.style.background = '#4ade80';
            });
    }
}

// Check system status every 5 minutes
setInterval(checkSystemStatus, 300000);

// Performance optimization: Debounce resize events
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

// Use debounced resize handler
window.addEventListener('resize', debounce(handleResize, 250));

// Functions for Edit Client Modal

function generatePasswordEdit() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    let password = '';
    for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const passwordInput = document.getElementById('editClientPassword');
    if (passwordInput) {
        passwordInput.value = password;
    }
}

function closeEditModal() {
    const modal = document.getElementById('editClientModal');
    if (modal) {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
    }
}

window.updateClient = function() {
    const form = document.getElementById('editClientForm');
    if (!form) {
        alert('Edit client form not found.');
        return false;
    }
    const formData = new FormData(form);
    
    const updateBtn = form.querySelector('.btn-primary');
    if (updateBtn) {
        updateBtn.disabled = true;
        updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    }
    
    fetch('ajax/client_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (window.showToast) {
                window.showToast('Client updated successfully!', 'success');
            } else {
                alert('Client updated successfully!');
            }
            closeEditModal();
            setTimeout
        } else {
            if (window.showToast) {
                window.showToast(data.message || 'Failed to update client', 'error');
            } else {
                alert(data.message || 'Failed to update client');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (window.showToast) {
            window.showToast('An error occurred. Please try again.', 'error');
        } else {
            alert('An error occurred. Please try again.');
        }
    })
    .finally(() => {
        if (updateBtn) {
            updateBtn.disabled = false;
            updateBtn.innerHTML = 'Update Client';
        }
    });
}
