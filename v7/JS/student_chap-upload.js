// student 
document.addEventListener("DOMContentLoaded", function() {
    // Initialize tab system with single-click functionality
    initTabSystem();
    
    // Initialize file upload system
    initFileUploads();
    
    // Initialize user interface components
    initUI();
    
    // Show welcome message
    showMessage("Ready to upload your thesis chapters!", "success");
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
        
        // Set initial active tab
        if (item.getAttribute("href").includes(currentPage)) {
            item.classList.add("active");
            const tabId = item.getAttribute("data-tab");
            if (tabId) {
                document.getElementById(tabId)?.classList.add("active");
            }
        }
    });
}

function initFileUploads() {
    const uploadAreas = document.querySelectorAll(".upload-area");
    
    uploadAreas.forEach(area => {
        // Click handler
        area.addEventListener("click", function(e) {
            if (!e.target.matches('input[type="file"]')) {
                this.querySelector('input[type="file"]')?.click();
            }
        });
        
        // Drag and drop handlers
        ['dragover', 'dragleave', 'drop'].forEach(event => {
            area.addEventListener(event, function(e) {
                e.preventDefault();
                this.classList.toggle("dragover", event === 'dragover');
                
                if (event === 'drop' && e.dataTransfer.files.length) {
                    handleFileUpload(e.dataTransfer.files[0], this);
                }
            });
        });
        
        // File input change handler
        area.querySelector('input[type="file"]')?.addEventListener("change", function(e) {
            if (e.target.files.length) {
                handleFileUpload(e.target.files[0], area);
            }
        });
    });
}

function handleFileUpload(file, uploadArea) {
    // Validate file type
    const allowedTypes = ['application/pdf', 'application/msword', 
                         'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    const allowedExtensions = ['.pdf', '.doc', '.docx'];
    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();

    if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
        showMessage("Error: Only PDF and Word documents (.pdf, .doc, .docx) are allowed.", "error");
        return;
    }

    // Update UI
    const fileNameDisplay = uploadArea.querySelector('p');
    if (fileNameDisplay) {
        fileNameDisplay.textContent = file.name;
        fileNameDisplay.title = file.name;
    }

    // Show success message
    const chapterTitle = uploadArea.closest(".chapter-card")?.querySelector(".chapter-title")?.textContent || "chapter";
    showMessage(`"${file.name}" ready for ${chapterTitle} upload`, "success");
}

function viewValidationReport(chapterId) {
    // Mock data - replace with actual API call in production
    const reports = {
        chapter1: {
            title: "Chapter 1: Introduction",
            score: 92,
            details: [
                "✅ Clear problem statement",
                "✅ Well-defined objectives",
                "✅ Proper citation format",
                "⚠️ Consider expanding significance section"
            ]
        },
        chapter2: {
            title: "Chapter 2: Literature Review",
            score: 88,
            details: [
                "✅ Comprehensive coverage of topics",
                "✅ Critical analysis present",
                "⚠️ Add 3-5 more recent sources (2022-2024)",
                "⚠️ Consider adding comparison table"
            ]
        },
        chapter3: {
            title: "Chapter 3: Methodology",
            score: 75,
            details: [
                "✅ Research design clearly stated",
                "❌ Missing data collection timeline",
                "❌ Unclear sampling method",
                "⚠️ Add ethical considerations section"
            ]
        },
        chapter4: {
            title: "Chapter 4: Results and Discussion",
            score: null,
            details: ["No report available yet."]
        },
        chapter5: {
            title: "Chapter 5: Summary, Conclusion, and Recommendation",
            score: null,
            details: ["No report available yet."]
        }
    };

    const report = reports[chapterId];
    if (report) {
        alert(`Evaluation Report - ${report.title}\n\nScore: ${report.score ? report.score + '%' : 'N/A'}\n\n${report.details.join('\n')}`);
    }
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
