// advisor_reviews.js 
document.addEventListener("DOMContentLoaded", function() {
    // Initialize components
    initializeTabs();
    initUserDropdown();
    initLogout();
    initModals();
    
    // Show welcome message
    showMessage("Viewing your pendings chapter that needed to review.", "info");
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
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink'); 
    const logoutModal = document.getElementById('logoutModal'); // Specific modal
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    // Show only the logout modal
    const showLogoutModal = (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // First hide all other modals
        document.querySelectorAll('.modal').forEach(m => {
            if (m !== logoutModal) m.style.display = 'none';
        });
        
        // Then show the logout modal
        if (logoutModal) {
            logoutModal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
    };

    const hideLogoutModal = () => {
        if (logoutModal) {
            logoutModal.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        }
    };

    // Attach event listeners
    if (logoutBtn) logoutBtn.addEventListener('click', showLogoutModal);
    if (logoutLink) logoutLink.addEventListener('click', showLogoutModal);

    if (cancelLogout) {
        cancelLogout.addEventListener('click', hideLogoutModal);
    }

    if (confirmLogout) {
        confirmLogout.addEventListener('click', () => {
            window.location.href = '../logout.php';
        });
    }

    // Close when clicking outside modal
    if (logoutModal) {
        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                hideLogoutModal();
            }
        });
    }

    // Close with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && logoutModal.style.display === 'flex') {
            hideLogoutModal();
        }
    });
}

function initModals() {
    // Review modal handling
    const reviewModal = document.getElementById('reviewModal');
    const closeBtn = document.querySelector('.close');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    window.addEventListener('click', (e) => {
        if (reviewModal && e.target === reviewModal) {
            closeModal();
        }
    });
}

function reviewChapter(groupId, chapterId) {
    const modal = document.getElementById("reviewModal");
    const title = document.getElementById("reviewTitle");

    if (modal && title) {
        title.textContent = `Review ${groupId.charAt(0).toUpperCase() + groupId.slice(1)} - ${chapterId.charAt(0).toUpperCase() + chapterId.slice(1)}`;
        modal.setAttribute("data-group", groupId);
        modal.setAttribute("data-chapter", chapterId);
        modal.style.display = 'flex';
    }
}

function submitReview() {
    const modal = document.getElementById("reviewModal");
    const score = document.getElementById("scoreInput").value;
    const status = document.getElementById("statusSelect").value;
    const feedback = document.getElementById("feedbackText").value;

    if (!modal) return;

    const group = modal.getAttribute("data-group");
    const chapter = modal.getAttribute("data-chapter");

    if (!score || !feedback) {
        showMessage("Please fill in all required fields.", "error");
        return;
    }

    if (score < 0 || score > 100) {
        showMessage("Score must be between 0 and 100", "error");
        return;
    }

    // In a real app, you would send this data to the server here
    showMessage(`Review submitted for ${group}'s ${chapter}. Score: ${score}/100`, "success");
    closeModal();
    clearReviewForm();
}

function clearReviewForm() {
    document.getElementById("scoreInput").value = "";
    document.getElementById("statusSelect").value = "approved";
    document.getElementById("feedbackText").value = "";
}

function viewChapterFile(groupId, chapterId) {
    showMessage(`Opening ${chapterId} file for ${groupId}...`, "info");
    // Simulate file opening delay
    setTimeout(() => {
        showMessage(`${chapterId} file opened successfully!`, "success");
    }, 1000);
}

function closeModal() {
    const modals = document.querySelectorAll(".modal");
    modals.forEach(modal => modal.style.display = 'none');
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

// Responsive sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    if (sidebar) sidebar.classList.toggle("open");
}

// Handle window resize for sidebar
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove("open");
    }
});
