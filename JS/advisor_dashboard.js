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


// ==================== Start of V7 UPDATE ====================

 // Global variables
        let selectedFile = null;
        
        // Open modal function
        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        // Close modal function
        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            resetUploadForm();
        }
        
        // Preview image function
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const uploadBtn = document.getElementById('uploadBtn');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                if (!file.type.match('image.*')) {
                    showFlashMessage('Please select an image file (JPEG, PNG, etc.)', 'error');
                    resetUploadForm();
                    return;
                }
                
                // Validate file size (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    showFlashMessage('Image must be less than 2MB', 'error');
                    resetUploadForm();
                    return;
                }
                
                selectedFile = file;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    uploadBtn.disabled = false;
                    
                    // Update the upload area text
                    const uploadArea = document.querySelector('.upload-area p');
                    uploadArea.textContent = 'Selected: ' + file.name;
                }
                reader.readAsDataURL(file);
            }
        }
        
  function uploadProfilePicture() {
    if (!selectedFile) {
        showFlashMessage('Please select an image first', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('profileImage', selectedFile);
    
    const uploadBtn = document.getElementById('uploadBtn');
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';
    
    fetch('advisor_upload_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // First check if the response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error('Server returned non-JSON response: ' + text);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Update all avatar images on the page
            const newSrc = '../' + data.filePath + '?t=' + new Date().getTime();
            
            // Update sidebar avatar
            const sidebarAvatar = document.querySelector('.image-sidebar-avatar');
            if (sidebarAvatar) sidebarAvatar.src = newSrc;
            
            // Update header avatar
            const headerAvatar = document.querySelector('.user-avatar');
            if (headerAvatar) headerAvatar.src = newSrc;
            
            showFlashMessage(data.message, 'success');
            closeUploadModal();
        } else {
            throw new Error(data.message || 'Upload failed');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        let errorMessage = error.message;
        
        // Handle common error cases
        if (errorMessage.includes('<br />') || errorMessage.includes('<!DOCTYPE')) {
            errorMessage = 'Server error occurred. Please check the console for details.';
        }
        
        showFlashMessage(errorMessage, 'error');
    })
    .finally(() => {
        uploadBtn.textContent = 'Upload';
        uploadBtn.disabled = false;
    });
}
        
        // Reset upload form function
        function resetUploadForm() {
            document.getElementById('fileInput').value = '';
            document.getElementById('imagePreview').src = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.querySelector('.upload-area p').textContent = 'Click to select an image';
            document.getElementById('uploadBtn').disabled = true;
            selectedFile = null;
        }
        
        // Show flash message function
        function showFlashMessage(message, type) {
            // Remove any existing flash messages
            const existingMessages = document.querySelectorAll('.flash-message');
            existingMessages.forEach(msg => msg.remove());
            
            // Create new message element
            const flashDiv = document.createElement('div');
            flashDiv.className = `flash-message ${type}`;
            flashDiv.textContent = message;
            document.body.appendChild(flashDiv);
            
            // Remove message after 3 seconds
            setTimeout(() => {
                flashDiv.remove();
            }, 3000);
        }
        
        // Close modal when clicking outside
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });
        
        // Add click event to your avatar to open the modal
        document.querySelector('.sidebar-user img').addEventListener('click', function() {
            openUploadModal();
        });


        // ==================== END OF V7 UPDATE ====================