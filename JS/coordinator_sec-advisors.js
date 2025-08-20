// coordinator_sec-advisors.js 
document.addEventListener("DOMContentLoaded", function() {
    // Initialize components
    initializeTabs();
    initUserDropdown();
    initLogout();
    
    // Show welcome message
    showMessage("Welcome to CICT Sections & Advisors management!", "info");

    // Initialize DataTable
    initDataTable();
});

function initializeTabs() {
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    const tabContents = document.querySelectorAll(".tab-content");

    // Activate current tab based on URL
    const currentPage = window.location.pathname.split('/').pop();
    navItems.forEach(item => {
        if (item.getAttribute("href").includes(currentPage)) {
            item.classList.add("active");
            const tabId = item.getAttribute("data-tab");
            if (tabId) {
                const tabContent = document.getElementById(tabId);
                if (tabContent) tabContent.classList.add("active");
            }
        }
    });

    // Add click handlers
    navItems.forEach(item => {
        item.addEventListener("click", function(e) {
            const tabId = this.getAttribute("data-tab");
            const tabContent = document.getElementById(tabId);
            
            if (tabContent) {
                e.preventDefault();
                navItems.forEach(nav => nav.classList.remove("active"));
                tabContents.forEach(tab => tab.classList.remove("active"));
                this.classList.add("active");
                tabContent.classList.add("active");
            }
        });
    });
}

function initUserDropdown() {
    const userAvatar = document.getElementById('userAvatar');
    const userDropdown = document.getElementById('userDropdown');

    if (userAvatar && userDropdown) {
        userAvatar.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = userDropdown.style.display === 'block';
            userDropdown.style.display = isVisible ? 'none' : 'block';
        });

        document.addEventListener('click', () => {
            userDropdown.style.display = 'none';
        });
    }
}

function initLogout() {
    // Get all logout triggers
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink');
    
    // Get all logout modals
    const sidebarLogoutModal = document.getElementById('logoutModal');
    const headerLogoutModal = document.getElementById('LogoutModal');
    
    // Get all confirm and cancel buttons
    const confirmLogoutBtn = document.getElementById('confirmLogout');
    const cancelLogoutBtn = document.getElementById('cancelLogout');
    const headerConfirmLogoutBtn = document.getElementById('headerConfirmLogout');
    const headerCancelLogoutBtn = document.getElementById('headerCancelLogout');

    // Function to show the appropriate modal
    const showModal = (e, modalType) => {
        e.preventDefault();
        if (modalType === 'sidebar' && sidebarLogoutModal) {
            sidebarLogoutModal.style.display = 'flex';
        } else if (modalType === 'header' && headerLogoutModal) {
            headerLogoutModal.style.display = 'flex';
        }
    };

    // Function to hide all modals
    const hideModals = () => {
        if (sidebarLogoutModal) sidebarLogoutModal.style.display = 'none';
        if (headerLogoutModal) headerLogoutModal.style.display = 'none';
    };

    // Add event listeners to logout triggers
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => showModal(e, 'sidebar'));
    }
    
    if (logoutLink) {
        logoutLink.addEventListener('click', (e) => showModal(e, 'header'));
    }

    // Add event listeners to cancel buttons
    if (cancelLogoutBtn) {
        cancelLogoutBtn.addEventListener('click', hideModals);
    }
    
    if (headerCancelLogoutBtn) {
        headerCancelLogoutBtn.addEventListener('click', hideModals);
    }

    // Add event listeners to confirm buttons
    if (confirmLogoutBtn) {
        confirmLogoutBtn.addEventListener('click', () => {
            window.location.href = '../logout.php';
        });
    }
    
    if (headerConfirmLogoutBtn) {
        headerConfirmLogoutBtn.addEventListener('click', () => {
            window.location.href = '../logout.php';
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', (e) => {
        if (sidebarLogoutModal && e.target === sidebarLogoutModal) {
            sidebarLogoutModal.style.display = 'none';
        }
        if (headerLogoutModal && e.target === headerLogoutModal) {
            headerLogoutModal.style.display = 'none';
        }
    });

    // Close modals when pressing Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideModals();
        }
    });
}



function showMessage(text, type = "info") {
    // Remove any existing messages first
    const existingMessages = document.querySelectorAll(".message");
    existingMessages.forEach(msg => msg.remove());

    // Create new message element
    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;

    // Insert message in the appropriate location
    const mainContent = document.querySelector(".main-content");
    if (mainContent) {
        const header = mainContent.querySelector("header");
        if (header) {
            header.insertAdjacentElement("afterend", message);
        } else {
            mainContent.prepend(message);
        }
    }

    // Auto-remove after 5 seconds
    setTimeout(() => message.remove(), 5000);
}

function viewSectionDetails(sectionId) {
    showMessage(`Loading details for ${sectionId}...`, "info");
    // In a real implementation, this would load section details
    setTimeout(() => {
        showMessage(`${sectionId} details loaded successfully!`, "success");
        // Here you would typically redirect or show a modal with the details
        // window.location.href = `section-details.php?id=${sectionId}`;
    }, 1000);
}

// Handle window resize for sidebar (if you have a toggle button)
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove("open");
    }
});

// =============== Start of version 6 update =============== 
    
$(document).ready(function() {
    $('#sectionsTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50],
        dom: '<"top"lf>rt<"bottom"ip>', // Control layout of elements
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search here...",
            lengthMenu: "Show _MENU_ entries",
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            },
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        },
        initComplete: function() {
            // Add custom class to search input
            $('.dataTables_filter input').addClass('datatable-search-input');
            
            // Add custom class to length menu
            $('.dataTables_length select').addClass('datatable-length-select');
            
            // Add custom class to pagination buttons
            $('.dataTables_paginate .paginate_button').addClass('datatable-pagination-btn');
        },
        drawCallback: function() {
            // Reapply classes after each draw (pagination, etc)
            $('.dataTables_paginate .paginate_button').addClass('datatable-pagination-btn');
        }
    });
    
    function viewSectionDetails(sectionId) {
        console.log("Viewing details for section: " + sectionId);
    }
    
    window.viewSectionDetails = viewSectionDetails;
});
 
// =============== End of version 6 update =============== 