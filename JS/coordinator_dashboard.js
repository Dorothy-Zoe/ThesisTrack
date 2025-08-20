let currentAdvisorId = null;

document.addEventListener("DOMContentLoaded", () => {
  // Initialize all components
  initializeTabs();
  initUserDropdown();
  initLogout();
  initializeDropdowns();
  
  // Show welcome message (with icon support from first JS)
  showMessage("Welcome to your CICT coordinator dashboard!", "info");

  // Set default active tab if needed (from first JS)
  // showTab("overview"); // Uncomment if needed
});

// ==================== TAB MANAGEMENT ====================
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


// ==================== USER DROPDOWN ====================
function initUserDropdown() {
  const userAvatar = document.getElementById("userAvatar");
  const userDropdown = document.getElementById("userDropdown");

  if (userAvatar && userDropdown) {
    // Improved from both versions
    userAvatar.addEventListener("click", (e) => {
      e.stopPropagation();
      userDropdown.style.display = userDropdown.style.display === "block" ? "none" : "block";
    });

    // Better click-outside handling from first JS
    document.addEventListener("click", (e) => {
      if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.style.display = "none";
      }
    });
  }
}

// ==================== LOGOUT MANAGEMENT ====================
function initLogout() {
  const logoutBtn = document.getElementById("logoutBtn");
  const logoutLink = document.getElementById("logoutLink"); 
  const logoutModal = document.getElementById("logoutModal");
  const confirmLogout = document.getElementById("confirmLogout");
  const cancelLogout = document.getElementById("cancelLogout");

  if (!logoutModal) return;

  // Combined best of both approaches
  const showModal = (e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    
    // Hide all other modals first (from first JS)
    document.querySelectorAll('.modal').forEach(m => {
      if (m !== logoutModal) {
        m.style.display = 'none';
        m.classList.remove("show");
      }
    });
    
    logoutModal.style.display = "flex";
    logoutModal.classList.add("show");
    document.body.style.overflow = "hidden";
  };

  const hideModal = () => {
    logoutModal.style.display = "none";
    logoutModal.classList.remove("show");
    document.body.style.overflow = "auto";
  };

  if (logoutBtn) logoutBtn.addEventListener("click", showModal);
  if (logoutLink) logoutLink.addEventListener("click", showModal);

  if (cancelLogout) cancelLogout.addEventListener("click", hideModal);

  if (confirmLogout) {
    confirmLogout.addEventListener("click", () => {
      window.location.href = "../logout.php";
    });
  }

  // Enhanced modal closing (from first JS)
  logoutModal.addEventListener("click", (e) => {
    if (e.target === logoutModal) {
      hideModal();
    }
  });

  // Escape key support (from first JS)
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && logoutModal.classList.contains("show")) {
      hideModal();
    }
  });
}

// 

// ==================== SIDEBAR MANAGEMENT ====================
function toggleSidebar() {
  const sidebar = document.querySelector(".sidebar");
  if (sidebar) {
    sidebar.classList.toggle("open");
  }
}

// Handle window resize for sidebar (optimized from both)
window.addEventListener("resize", () => {
  const sidebar = document.querySelector(".sidebar");
  if (window.innerWidth > 768 && sidebar) {
    sidebar.classList.remove("open");
  }
});