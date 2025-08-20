// Student Dashboard JavaScript - Simplified Version
document.addEventListener("DOMContentLoaded", () => {
    // Tab navigation functionality
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    const tabContents = document.querySelectorAll(".tab-content");

    navItems.forEach((item) => {
        item.addEventListener("click", function (e) {
            const tabId = this.getAttribute("data-tab");
            const tabContent = document.getElementById(tabId);
            
            // Only handle as tab if the tab content exists on this page
            if (tabContent) {
                e.preventDefault();
                navItems.forEach((nav) => nav.classList.remove("active"));
                tabContents.forEach((tab) => tab.classList.remove("active"));
                this.classList.add("active");
                tabContent.classList.add("active");
            }
            // Otherwise allow default link behavior
        });
    });

    // Welcome message
    showMessage("Welcome to your CICT thesis dashboard!", "info");

    // Initialize logout functionality
    initLogout();
});

// Show notification messages
function showMessage(text, type = "info") {
    const existingMessages = document.querySelectorAll(".message");
    existingMessages.forEach((msg) => msg.remove());

    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;

    const mainContent = document.querySelector(".main-content");
    const header = document.querySelector(".main-header");
    mainContent.insertBefore(message, header.nextSibling);

    setTimeout(() => {
        message.remove();
    }, 5000);
}

// Toggle sidebar visibility
function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    sidebar.classList.toggle("open");
}

// Handle window resize
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768) {
        sidebar.classList.remove("open");
    }
});

// User dropdown functionality
const userAvatar = document.getElementById('userAvatar');
const userDropdown = document.getElementById('userDropdown');

if (userAvatar && userDropdown) {
    userAvatar.addEventListener('click', () => {
        const isVisible = userDropdown.style.display === 'block';
        userDropdown.style.display = isVisible ? 'none' : 'block';
    });

    document.addEventListener('click', (e) => {
        if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.style.display = 'none';
        }
    });
}

// Initialize logout functionality
function initLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink'); 
    const logoutModal = document.getElementById('logoutModal');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    if (!logoutBtn || !logoutModal) return;

    const showLogoutModal = (e) => {
        if (e) e.preventDefault();
        logoutModal.style.display = 'flex';
    };

    const hideLogoutModal = () => {
        logoutModal.style.display = 'none';
    };

    if (logoutBtn) logoutBtn.addEventListener('click', showLogoutModal);
    if (logoutLink) logoutLink.addEventListener('click', showLogoutModal);
    if (cancelLogout) cancelLogout.addEventListener('click', hideLogoutModal);
    if (confirmLogout) confirmLogout.addEventListener('click', () => {
        window.location.href = '../logout.php';
    });
}
