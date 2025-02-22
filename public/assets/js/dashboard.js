document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard components
    initializeDashboard();
    
    // Load initial data
    loadDashboardData();
    
    // Set up refresh interval (every 5 minutes)
    setInterval(loadDashboardData, 300000);
});

/**
 * Initialize dashboard components
 */
function initializeDashboard() {
    // Sidebar toggle functionality
    initializeSidebar();
    
    // Initialize notifications
    initializeNotifications();
    
    // Initialize responsive table
    initializeResponsiveTable();
    
    // Initialize tooltips and popovers
    initializeBootstrapComponents();
}

/**
 * Initialize sidebar functionality
 */
function initializeSidebar() {
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const dashboard = document.querySelector('.dashboard-container');
    
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            dashboard.classList.toggle('sidebar-collapsed');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 992 && dashboard.classList.contains('sidebar-collapsed')) {
            if (!e.target.closest('.dashboard-sidebar') && !e.target.closest('#mobileSidebarToggle')) {
                dashboard.classList.remove('sidebar-collapsed');
            }
        }
    });
}

/**
 * Initialize notifications
 */
function initializeNotifications() {
    const notificationBell = document.querySelector('.notification-badge');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    
    if (notificationBell && notificationDropdown) {
        // Load initial notifications
        loadNotifications();
        
        // Refresh notifications every minute
        setInterval(loadNotifications, 60000);
    }
}

/**
 * Load notifications from server
 */
async function loadNotifications() {
    try {
        const response = await fetch(`${BASE_PATH}/app/controllers/NotificationController.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ action: 'getNotifications' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateNotificationBadge(data.notifications);
            updateNotificationDropdown(data.notifications);
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

/**
 * Update notification badge count
 */
function updateNotificationBadge(notifications) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        const unreadCount = notifications.filter(n => !n.read).length;
        badge.textContent = unreadCount;
        badge.style.display = unreadCount > 0 ? 'block' : 'none';
    }
}

/**
 * Update notification dropdown content
 */
function updateNotificationDropdown(notifications) {
    const container = document.querySelector('.notification-dropdown');
    if (!container) return;
    
    const content = notifications.map(notification => `
        <a class="dropdown-item" href="#" data-id="${notification.id}">
            <div class="notification-item">
                <div class="icon text-${notification.type}">
                    <i class="bi bi-${getNotificationIcon(notification.type)}"></i>
                </div>
                <div class="content">
                    <div class="title">${notification.title}</div>
                    <div class="text">${notification.message}</div>
                    <div class="time">${formatTimeAgo(notification.created_at)}</div>
                </div>
            </div>
        </a>
    `).join('');
    
    container.innerHTML = `
        <h6 class="dropdown-header">Notifications</h6>
        ${content}
        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-center" href="${BASE_PATH}/public/notifications.php">
            View All Notifications
        </a>
    `;
}

/**
 * Load dashboard data via AJAX
 */
async function loadDashboardData() {
    try {
        const formData = new FormData();
        formData.append('action', 'getDashboardData');
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

        const response = await fetch(`${BASE_PATH}/app/controllers/DashboardController.php`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        if (data.success) {
            updateDashboardStats(data.data.stats);
            updateVehicleTable(data.data.cars);
            updateUpcomingServices(data.data.upcomingBookings);
            updateServiceHistory(data.data.recentServices);
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showAlert('error', 'Failed to load dashboard data. Please refresh the page.');
    }
}

/**
 * Update dashboard statistics
 */
function updateDashboardStats(stats) {
    Object.keys(stats).forEach(key => {
        const element = document.getElementById(key);
        if (element) {
            if (key.includes('Cost')) {
                element.textContent = formatCurrency(stats[key]);
            } else {
                element.textContent = stats[key];
            }
        }
    });
}

/**
 * Update vehicle table content
 */
function updateVehicleTable(vehicles) {
    const tableBody = document.querySelector('.table tbody');
    if (!tableBody) return;
    
    if (vehicles.length === 0) {
        showEmptyState('vehicles');
        return;
    }
    
    const content = vehicles.map(vehicle => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="vehicle-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"></path>
                            <circle cx="6.5" cy="16.5" r="2.5"></circle>
                            <circle cx="16.5" cy="16.5" r="2.5"></circle>
                        </svg>
                    </div>
                    <div>
                        <div class="fw-medium">${vehicle.make} ${vehicle.model}</div>
                        <small class="text-muted">${vehicle.year}</small>
                    </div>
                </div>
            </td>
            <td>${vehicle.reg_number}</td>
            <td>${formatDate(vehicle.last_service_date) || 'No service history'}</td>
            <td>
                <span class="badge bg-${vehicle.maintenanceSchedule?.needs_service ? 'danger' : 'success'}-subtle text-${vehicle.maintenanceSchedule?.needs_service ? 'danger' : 'success'}">
                    ${vehicle.maintenanceSchedule?.needs_service ? 'Service Due' : 'Good'}
                </span>
            </td>
            <td>
                <a href="${BASE_PATH}/public/book-service.php?car=${vehicle.id}" class="btn btn-sm btn-primary">
                    Book Service
                </a>
            </td>
        </tr>
    `).join('');
    
    tableBody.innerHTML = content;
}

/**
 * Update upcoming services section
 */
function updateUpcomingServices(services) {
    const container = document.querySelector('.upcoming-services');
    if (!container) return;
    
    if (services.length === 0) {
        showEmptyState('services');
        return;
    }
    
    const content = services.map(service => `
        <div class="upcoming-service">
            <div class="service-date">
                <div class="date">${formatDate(service.booking_date, 'DD')}</div>
                <div class="month">${formatDate(service.booking_date, 'MMM')}</div>
            </div>
            <div class="service-info">
                <h6 class="mb-1">${service.service_name}</h6>
                <p class="text-muted mb-0">${service.make} ${service.model} - ${service.reg_number}</p>
                <small class="text-muted">${formatTime(service.booking_time)}</small>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = content;
}

/**
 * Update service history timeline
 */
function updateServiceHistory(history) {
    const container = document.querySelector('.timeline');
    if (!container) return;
    
    if (history.length === 0) {
        showEmptyState('history');
        return;
    }
    
    const content = history.map(service => `
        <div class="timeline-item">
            <div class="timeline-marker bg-success">
                <i class="bi bi-check"></i>
            </div>
            <div class="timeline-content">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-1">${service.service_name}</h6>
                    <small class="text-muted">${formatDate(service.service_date, 'MMMM D, YYYY')}</small>
                </div>
                <p class="mb-0">${service.make} ${service.model} - ${service.reg_number}</p>
                <small class="text-muted">
                    ${service.description}
                    Cost: ${formatCurrency(service.cost)}
                </small>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = content;
}

/**
 * Show empty state for different sections
 */
function showEmptyState(section) {
    const emptyStates = {
        vehicles: {
            icon: 'car-front',
            title: 'No vehicles registered yet',
            message: 'Start by adding your first vehicle',
            action: 'Add Vehicle',
            link: 'vehicles.php'
        },
        services: {
            icon: 'calendar-check',
            title: 'No upcoming services',
            message: 'Schedule your next service',
            action: 'Book Service',
            link: 'bookings.php'
        },
        history: {
            icon: 'clock-history',
            title: 'No service history yet',
            message: 'Your service history will appear here after your first service'
        }
    };
    
    const state = emptyStates[section];
    const container = document.querySelector(`.${section}-container`);
    
    if (container && state) {
        container.innerHTML = `
            <div class="text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-${state.icon} display-4 text-muted"></i>
                </div>
                <h6 class="text-muted">${state.title}</h6>
                <p class="text-muted mb-3">${state.message}</p>
                ${state.action ? `
                    <a href="${BASE_PATH}/public/${state.link}" class="btn btn-primary">
                        <i class="bi bi-plus"></i> ${state.action}
                    </a>
                ` : ''}
            </div>
        `;
    }
}

/**
 * Initialize responsive data tables
 */
function initializeResponsiveTable() {
    const tables = document.querySelectorAll('.table-responsive');
    tables.forEach(table => {
        new bootstrap.Table(table, {
            responsive: true
        });
    });
}

/**
 * Initialize Bootstrap components
 */
function initializeBootstrapComponents() {
    // Initialize all tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
    
    // Initialize all popovers
    const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
    popovers.forEach(popover => new bootstrap.Popover(popover));
}

/**
 * Helper function to format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

/**
 * Helper function to format dates
 */
function formatDate(date, format = 'MMM D, YYYY') {
    if (!date) return '';
    return moment(date).format(format);
}

/**
 * Helper function to format time
 */
function formatTime(time) {
    if (!time) return '';
    return moment(time, 'HH:mm:ss').format('h:mm A');
}

/**
 * Helper function to format time ago
 */
function formatTimeAgo(datetime) {
    return moment(datetime).fromNow();
}

/**
 * Helper function to get notification icon
 */
function getNotificationIcon(type) {
    const icons = {
        primary: 'info-circle',
        success: 'check-circle',
        warning: 'exclamation-triangle',
        danger: 'exclamation-circle'
    };
    return icons[type] || 'bell';
}

/**
 * Show alert message
 */
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    alertContainer.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}