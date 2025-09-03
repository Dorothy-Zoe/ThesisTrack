// advisor_group.js 
document.addEventListener("DOMContentLoaded", function() {
    // Check if user is logged in
    if (!isUserLoggedIn()) {
        window.location.href = 'login.php';
        return;
    }

    // Initialize all components
    activateCurrentTab();
    initNavigation();
    initUI();
    initModals();
    
    // Show welcome message
    showMessage("Viewing your supervised groups.", "info");
});

function isUserLoggedIn() {
    // In real implementation, this would check with server
    return true;
}

function activateCurrentTab() {
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    
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
}

function initNavigation() {
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    
    navItems.forEach(item => {
        item.addEventListener("click", function(e) {
            const tabId = this.getAttribute("data-tab");
            const tabContent = document.getElementById(tabId);
            const href = this.getAttribute("href");
            
            if (tabContent) {
                e.preventDefault();
                document.querySelectorAll(".nav-item").forEach(nav => nav.classList.remove("active"));
                document.querySelectorAll(".tab-content").forEach(tab => tab.classList.remove("active"));
                this.classList.add("active");
                tabContent.classList.add("active");
            }
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
    
    // Fixed View Details functionality
    document.querySelectorAll('.btn-expand').forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Get group ID from data attribute or onclick
            const groupId = this.getAttribute('data-group-id') || 
                          this.getAttribute('onclick').match(/'([^']+)'/)[1];
            
            // Get the details element
            const details = document.getElementById(`${groupId}-details`);
            
            if (details) {
                // Toggle the expanded class
                const isExpanded = details.classList.toggle('expanded');
                
                // Update button text
                this.textContent = isExpanded ? 'Hide Details' : 'View Details';
                
                // Scroll to show the expanded content if needed
                if (isExpanded) {
                    details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        });
    });
}

function initModals() {
    // Get all modal elements
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink');
    const logoutModal = document.getElementById('logoutModal'); // Changed to single modal
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');
    
    // Review modal elements
    const reviewModal = document.getElementById('reviewModal');
    const closeModalBtn = document.querySelector('.close');

    // Show logout modal function
    const showLogoutModal = (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Hide any other open modals first
        if (reviewModal) reviewModal.style.display = 'none';
        
        // Show logout modal
        if (logoutModal) logoutModal.style.display = 'flex';
    };

    // Hide logout modal function
    const hideLogoutModal = () => {
        if (logoutModal) logoutModal.style.display = 'none';
    };

    // Add event listeners
    if (logoutBtn) logoutBtn.addEventListener('click', showLogoutModal);
    if (logoutLink) logoutLink.addEventListener('click', showLogoutModal);
    if (cancelLogout) cancelLogout.addEventListener('click', hideLogoutModal);
    
    if (confirmLogout) {
        confirmLogout.addEventListener('click', () => {
            window.location.href = '../logout.php';
        });
    }

    // Review modal handlers
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }

    // Close modals when clicking outside
    window.addEventListener('click', (event) => {
        if (logoutModal && event.target === logoutModal) {
            hideLogoutModal();
        }
        if (reviewModal && event.target === reviewModal) {
            closeModal();
        }
    });
}

function showMessage(text, type = "info") {
    const existingMessages = document.querySelectorAll(".message");
    existingMessages.forEach(msg => msg.remove());
    
    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;
    
    const mainContent = document.querySelector(".main-content");
    if (mainContent) {
        const header = mainContent.querySelector("header");
        if (header) {
            header.insertAdjacentElement("afterend", message);
        } else {
            mainContent.prepend(message);
        }
    }
    
    setTimeout(() => message.remove(), 5000);
}

// Modal functions
function openReviewModal(groupId, chapterId) {
    const modal = document.getElementById('reviewModal');
    if (modal) {
        document.getElementById('reviewTitle').textContent = `Review ${chapterId} for ${groupId}`;
        modal.style.display = 'flex';
    }
}

function closeModal() {
    const modal = document.getElementById('reviewModal');
    if (modal) modal.style.display = 'none';
}

function submitReview() {
    const score = document.getElementById('scoreInput').value;
    const status = document.getElementById('statusSelect').value;
    const feedback = document.getElementById('feedbackText').value;
    
    if (!score || score < 0 || score > 100) {
        showMessage('Please enter a valid score between 0 and 100', 'error');
        return;
    }
    
    if (!feedback) {
        showMessage('Please provide feedback', 'error');
        return;
    }
    
    showMessage('Review submitted successfully!', 'success');
    closeModal();
}

// Group management functions
function reviewChapter(groupId, chapterId) {
    openReviewModal(groupId, chapterId);
}

function viewChapterFile(groupId, chapterId) {
    showMessage(`Opening ${chapterId} file for ${groupId}`, "info");
}

function editFeedback(groupId, chapterId) {
    showMessage(`Editing feedback for ${groupId}, ${chapterId}`, "info");
}

// Responsive sidebar
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768) {
        sidebar.classList.remove("open");
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.search-input');
    const table = document.getElementById('groupsTable');
    const rows = table.querySelectorAll('tbody tr');
    
    // Function to perform search
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase();
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let rowMatches = false;
            
            // Skip the first column (Group ID) and last column (Actions)
            for (let i = 1; i < cells.length - 1; i++) {
                const cellText = cells[i].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    rowMatches = true;
                    break;
                }
            }
            
            row.style.display = rowMatches ? '' : 'none';
        });
    }
    
    // Event listener for input changes
    searchInput.addEventListener('input', performSearch);
    
    // If there's an initial search term, filter immediately
    if (searchInput.value) {
        performSearch();
    }
});

