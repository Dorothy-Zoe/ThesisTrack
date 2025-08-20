// advisor_dashboard.js
document.addEventListener("DOMContentLoaded", () => {
    // Activate the current tab immediately on page load
    activateCurrentTab();
    
    // Initialize navigation
    initNavigation();
    
    // Show welcome message
    showMessage("Welcome to your dashboard!", "info");
    
    // Initialize UI components
    initUI();
});

function activateCurrentTab() {
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    
    navItems.forEach(item => {
        if (item.getAttribute("href").includes(currentPage)) {
            // Activate the tab immediately
            item.classList.add("active");
            const tabId = item.getAttribute("data-tab");
            if (tabId) {
                const tabContent = document.getElementById(tabId);
                if (tabContent) tabContent.classList.add("active");
            }
        }
    });
}

function initNavigation() {
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    
    navItems.forEach(item => {
        item.addEventListener("click", function(e) {
            const tabId = this.getAttribute("data-tab");
            const tabContent = document.getElementById(tabId);
            const href = this.getAttribute("href");
            
            // If it's a tab on current page
            if (tabContent) {
                e.preventDefault();
                
                // Update active states
                document.querySelectorAll(".nav-item").forEach(nav => nav.classList.remove("active"));
                document.querySelectorAll(".tab-content").forEach(tab => tab.classList.remove("active"));
                
                this.classList.add("active");
                tabContent.classList.add("active");
            }
            // If it's a link to another page, allow default behavior
        });
    });
}

function initUI() {
    // User dropdown
    const userAvatar = document.getElementById('userAvatar');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userAvatar && userDropdown) {
        userAvatar.addEventListener('click', () => {
            userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
        });
        
        document.addEventListener('click', (e) => {
            if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.style.display = 'none';
            }
        });
    }
    
    // Logout functionality
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink'); 
    const logoutModal = document.getElementById('logoutModal');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');
    
    if (logoutModal) {
        const showModal = (e) => {
            if (e) e.preventDefault();
            logoutModal.style.display = 'flex';
        };
        
        const hideModal = () => logoutModal.style.display = 'none';
        
        if (logoutBtn) logoutBtn.addEventListener('click', showModal);
        if (logoutLink) logoutLink.addEventListener('click', showModal);
        if (cancelLogout) cancelLogout.addEventListener('click', hideModal);
        if (confirmLogout) confirmLogout.addEventListener('click', () => {
            window.location.href = '../logout.php';
        });
    }
}

function showMessage(text, type = "info") {
    const existingMessages = document.querySelectorAll(".message");
    existingMessages.forEach(msg => msg.remove());
    
    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;
    
    const mainContent = document.querySelector(".main-content");
    if (mainContent) {
        const header = mainContent.querySelector(".main-header");
        if (header) {
            header.insertAdjacentElement("afterend", message);
        } else {
            mainContent.prepend(message);
        }
    }
    
    setTimeout(() => message.remove(), 5000);
}

function toggleSidebar() {
    document.querySelector(".sidebar")?.classList.toggle("open");
}

// Responsive sidebar
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (sidebar && window.innerWidth > 768) {
        sidebar.classList.remove("open");
    }
});
