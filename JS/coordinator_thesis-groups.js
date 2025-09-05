// =========================== coordinator_thesis-groups.js - Fixed version 6 update ==================================
document.addEventListener("DOMContentLoaded", function() {
    // Initialize components
    initializeTabs();
    initUserDropdown();
    initLogout();
    initGroupFilters();
    
    // Show welcome message
    showMessage("Welcome to Thesis Groups management!", "info");
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

function initGroupFilters() {
    // Initialize any filter-related functionality
    // This would be called when the page loads to set up filter handlers
}

function filterGroups() {
    const programFilter = document.getElementById("programFilter").value;
    const sectionFilter = document.getElementById("sectionFilter").value;
    const advisorFilter = document.getElementById("advisorFilter").value;
    const progressFilter = document.getElementById("progressFilter").value;

    const rows = document.querySelectorAll("#groupsTableBody tr");

    rows.forEach(row => {
        let show = true;

        if (programFilter && row.dataset.program !== programFilter) {
            show = false;
        }

        if (sectionFilter && row.dataset.section !== sectionFilter) {
            show = false;
        }

        if (advisorFilter && row.dataset.advisor !== advisorFilter) {
            show = false;
        }

        if (progressFilter) {
            const progress = parseInt(row.dataset.progress);
            const [min, max] = progressFilter.split('-').map(Number);
            if (progress < min || progress > max) {
                show = false;
            }
        }

        row.style.display = show ? '' : 'none';
    });

    showMessage(`Filters applied to thesis groups`, "info");
}

function viewGroupDetails(groupId) {
    showMessage(`Loading details for ${groupId}...`, "info");
    // In a real implementation, this would load group details
    setTimeout(() => {
        showMessage(`${groupId} details loaded successfully!`, "success");
    }, 1000);
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

// Handle window resize for sidebar (if you have a toggle button)
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove("open");
    }
});

$(document).ready(function() {
    // Initialize the table without DataTables features
    var table = $('#groupsTable');
    var tbody = table.find('tbody');
    var rows = tbody.find('tr');
    
    // Create no data row
    var noDataRow = $('<tr class="no-data-row"><td colspan="9" class="text-center">No data available in table</td></tr>');
    tbody.append(noDataRow);
    noDataRow.hide(); // Hide initially
    
    // Filter function for the dropdowns
    function filterGroups() {
        var program = $('#programFilter').val().toLowerCase();
        var section = $('#sectionFilter').val().toLowerCase();
        var advisor = $('#advisorFilter').val().toLowerCase();
        var status = $('#statusFilter').val().toLowerCase();
        
        var visibleRows = 0;
        
        rows.each(function() {
            var row = $(this);
            var rowProgram = row.data('program').toString().toLowerCase();
            var rowSection = row.data('section').toString().toLowerCase();
            var rowAdvisor = row.data('advisor').toString().toLowerCase();
            var rowStatus = row.data('status').toString().toLowerCase();
            
            var showRow = true;
            
            if (program && !rowProgram.includes(program)) {
                showRow = false;
            }
            if (section && rowSection !== section) {
                showRow = false;
            }
            if (advisor && rowAdvisor !== advisor) {
                showRow = false;
            }
            if (status && rowStatus !== status) {
                showRow = false;
            }
            
            row.toggle(showRow);
            if (showRow) visibleRows++;
        });
        
        // Show/hide no data message
        if (visibleRows === 0) {
            noDataRow.show();
        } else {
            noDataRow.hide();
        }
    }
    
    // Apply filters when dropdowns change
    $('#programFilter, #sectionFilter, #advisorFilter, #statusFilter').on('change', filterGroups);
    
    // Initial filter check
    filterGroups();
    
    // Make viewGroupDetails function available globally
    window.viewGroupDetails = function(groupId) {
        console.log("Viewing details for group ID: " + groupId);
        window.location.href = 'coordinator_group-details.php?id=' + groupId;
    };
});

// =============== End of version 6 update =============== 


// =============== Start of version 7 update =============== 

document.querySelector('.search-input').addEventListener('keyup', function(e) {
  if (e.key === 'Enter') {
    // Submit search
    this.form.submit();
  }
  
});

// =============== End of version 7 update =============== 