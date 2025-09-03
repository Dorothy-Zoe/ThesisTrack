// student_kanban-progress.js 
document.addEventListener("DOMContentLoaded", function() {
    // Initialize tab system
    initTabSystem();
    
    // Initialize user interface components
    initUI();
    
    // Show welcome message
    showMessage("Viewing your chapter progress", "info");
});

function initTabSystem() {
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    const currentPage = window.location.pathname.split('/').pop();
    
    navItems.forEach(item => {
        item.addEventListener("click", function(e) {
            const tabId = this.getAttribute("data-tab");
            const targetContent = document.getElementById(tabId);
            
            // If this is a link to another page
            if (!targetContent) {
                return; // Allow default navigation
            }
            
            // If this is a tab on current page
            e.preventDefault();
            
            // Remove active classes from all
            document.querySelectorAll(".nav-item").forEach(nav => nav.classList.remove("active"));
            document.querySelectorAll(".tab-content").forEach(tab => tab.classList.remove("active"));
            
            // Add active class to clicked tab
            this.classList.add("active");
            targetContent.classList.add("active");
        });
        
        // Set initial active tab based on current page
        if (item.getAttribute("href").includes(currentPage)) {
            item.classList.add("active");
            const tabId = item.getAttribute("data-tab");
            if (tabId) {
                document.getElementById(tabId)?.classList.add("active");
            }
        }
    });
}

function initUI() {
    // User dropdown toggle
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

    // Make kanban cards draggable (optional)
    initKanbanDragDrop();
}

function initKanbanDragDrop() {
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');
    
    cards.forEach(card => {
        card.setAttribute('draggable', 'true');
        
        card.addEventListener('dragstart', () => {
            card.classList.add('dragging');
        });
        
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
        });
    });
    
    columns.forEach(column => {
        column.addEventListener('dragover', e => {
            e.preventDefault();
            const draggingCard = document.querySelector('.dragging');
            if (draggingCard) {
                column.querySelector('.kanban-cards').appendChild(draggingCard);
            }
        });
    });
}

function showMessage(text, type = "info") {
    // Remove existing messages
    document.querySelectorAll(".message").forEach(msg => msg.remove());
    
    // Create new message
    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;
    
    // Insert message
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

function toggleSidebar() {
    document.querySelector(".sidebar")?.classList.toggle("open");
}

// Responsive sidebar handling
window.addEventListener("resize", function() {
    const sidebar = document.querySelector(".sidebar");
    if (sidebar && window.innerWidth > 768) {
        sidebar.classList.remove("open");
    }
});
