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

// ==================== Start of V7 UPDATE ====================
// Global variables
let selectedFile = null;

// Open modal function
function openUploadModal() {
    document.getElementById('uploadModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    resetUploadForm(); // Reset form when opening
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
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            showFlashMessage('Please select a JPG, PNG, or GIF image', 'error');
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
            uploadArea.style.color = '#333';
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
    formData.append('profile_picture', selectedFile);
    
    const uploadBtn = document.getElementById('uploadBtn');
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';
    
    fetch('student_upload_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add timestamp to prevent caching
            const newSrc = '../' + data.image_path + '?t=' + Date.now();
            
            // Update all profile images on the page
            document.querySelectorAll('.sidebar-avatar, .user-avatar, #sidebarProfileImage, #userAvatar').forEach(img => {
                img.src = newSrc;
            });
            
            showFlashMessage('Profile picture updated!', 'success');
            closeUploadModal();
        } else {
            throw new Error(data.message || 'Upload failed');
        }
    })
    .catch(error => {
        showFlashMessage(error.message, 'error');
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
    const uploadText = document.querySelector('.upload-area p');
    if (uploadText) {
        uploadText.textContent = 'Click to select an image';
        uploadText.style.color = '#777';
    }
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
    
    // Position the message (centered at top)
    flashDiv.style.position = 'fixed';
    flashDiv.style.top = '20px';
    flashDiv.style.left = '50%';
    flashDiv.style.transform = 'translateX(-50%)';
    flashDiv.style.padding = '10px 20px';
    flashDiv.style.borderRadius = '4px';
    flashDiv.style.zIndex = '10000';
    flashDiv.style.animation = 'fadeIn 0.3s ease-in-out';
    
    // Style based on type
    if (type === 'success') {
        flashDiv.style.backgroundColor = '#4CAF50';
        flashDiv.style.color = 'white';
    } else {
        flashDiv.style.backgroundColor = '#f44336';
        flashDiv.style.color = 'white';
    }
    
    // Remove message after 3 seconds
    setTimeout(() => {
        flashDiv.style.animation = 'fadeOut 0.3s ease-in-out';
        setTimeout(() => {
            flashDiv.remove();
        }, 300);
    }, 3000);
}

// Close modal when clicking outside
document.getElementById('uploadModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeUploadModal();
    }
});

// Add click event to your avatar to open the modal (if exists)
document.querySelectorAll('.sidebar-user img, .profile-picture').forEach(el => {
    el.addEventListener('click', function() {
        openUploadModal();
    });
});

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
    }
    @keyframes fadeOut {
        from { opacity: 1; transform: translateX(-50%) translateY(0); }
        to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
    }
`;
document.head.appendChild(style);

// ==================== END OF V7 UPDATE ====================