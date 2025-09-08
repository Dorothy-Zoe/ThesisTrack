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


// ==================== V7 UPDATE ====================

// Profile Picture Modal Functions
function openProfilePictureModal() {
    document.getElementById('profilePictureModal').style.display = 'flex';
}

function closeProfilePictureModal() {
    document.getElementById('profilePictureModal').style.display = 'none';
    document.getElementById('profilePicturePreview').style.display = 'none';
    document.getElementById('profilePictureInput').value = '';
    document.getElementById('profilePictureUploadBtn').disabled = true;
}

// Preview selected image
function previewProfilePicture(input) {
    const profilePicturePreview = document.getElementById('profilePicturePreview');
    const profilePictureUploadBtn = document.getElementById('profilePictureUploadBtn');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            profilePicturePreview.src = e.target.result;
            profilePicturePreview.style.display = 'block';
            profilePictureUploadBtn.disabled = false;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Upload profile picture
async function uploadProfilePicture() {
    const fileInput = document.getElementById('fileInput');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (!fileInput.files[0]) {
        showMessage('Please select an image first', 'error');
        return;
    }

    const file = fileInput.files[0];
    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
    const maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!validTypes.includes(file.type)) {
        showMessage('Only JPG, PNG, or GIF files are allowed', 'error');
        return;
    }
    
    if (file.size > maxSize) {
        showMessage('File size must be less than 2MB', 'error');
        return;
    }

    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

    try {
        const formData = new FormData();
        formData.append('profile_picture', file);
        
        // Use absolute path to avoid 404 errors
        const response = await fetch('coordinator_upload_profile.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Upload failed');
        }

 document.querySelectorAll('.sidebar-avatar, .user-avatar').forEach(img => {
    img.src = data.filePath + '?t=' + Date.now(); 
});
        closeUploadModal();
        showMessage('Profile picture updated successfully!', 'success');
        
    } catch (error) {
        console.error('Upload error:', error);
        showMessage('Error: ' + error.message, 'error');
    } finally {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('profilePictureModal');
    if (event.target == modal) {
        closeProfilePictureModal();
    }
}

// Notification function
function showNotification(message, type) {
    // Implement your notification system here
    alert(message); // Simple fallback
}


// Ensure DOM is fully loaded before attaching events
document.addEventListener('DOMContentLoaded', function() {
    // Get elements safely
    const uploadBtn = document.getElementById('uploadBtn');
    const fileInput = document.getElementById('fileInput');
    
    if (!uploadBtn || !fileInput) {
        console.error('Required elements not found! Check your HTML IDs.');
        return;
    }
    
    // Alternative way to attach event listener (better than onclick in HTML)
    uploadBtn.addEventListener('click', uploadProfilePicture);
});

async function uploadProfilePicture() {
    const fileInput = document.getElementById('fileInput');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        showFlashMessage('Please select an image first', 'error');
        return;
    }

    const file = fileInput.files[0];
    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
    const maxSize = 2 * 1024 * 1024; // 2MB

    if (!validTypes.includes(file.type)) {
        showFlashMessage('Only JPG, PNG or GIF images are allowed', 'error');
        return;
    }

    if (file.size > maxSize) {
        showFlashMessage('Image must be less than 2MB', 'error');
        return;
    }

    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

    try {
        const formData = new FormData();
        formData.append('profile_picture', file);

        const response = await fetch('../Coordinator/coordinator_upload_profile.php', {
            method: 'POST',
            body: formData
        });

        // More flexible content type checking
        const contentType = response.headers.get('content-type') || '';
        let data;
        
        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            // Try to parse as JSON anyway
            try {
                data = await response.json();
            } catch (e) {
                const text = await response.text();
                throw new Error('Server returned non-JSON response: ' + text);
            }
        }
        
        if (!data.success) {
            throw new Error(data.message || 'Upload failed');
        }

        // Update profile pictures with cache buster
        const newSrc = '../' + data.filePath + '?t=' + Date.now();
        
        // Update sidebar avatar
        const sidebarAvatar = document.getElementById('currentProfilePicture');
        if (sidebarAvatar) sidebarAvatar.src = newSrc;
        
        // Update header avatar
        const headerAvatar = document.getElementById('userAvatar');
        if (headerAvatar) headerAvatar.src = newSrc;
        
        showFlashMessage('Profile picture updated successfully!', 'success');
        closeUploadModal();
        
    } catch (error) {
        console.error('Upload error:', error);
        showFlashMessage(error.message || 'An error occurred. Please try again.', 'error');
    } finally {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload';
    }
}

// Helper function to create square-cropped image
function createSquareImage(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const size = Math.min(img.width, img.height);
                canvas.width = size;
                canvas.height = size;
                
                const ctx = canvas.getContext('2d');
                // Center the crop
                const offsetX = (img.width - size) / 2;
                const offsetY = (img.height - size) / 2;
                
                ctx.drawImage(img, offsetX, offsetY, size, size, 0, 0, size, size);
                
                canvas.toBlob((blob) => {
                    resolve(new File([blob], file.name, {
                        type: file.type,
                        lastModified: Date.now()
                    }));
                }, file.type);
            };
        };
        reader.readAsDataURL(file);
    });
}

// Modal functions
function openUploadModal() {
    document.getElementById('uploadModal').style.display = 'flex';
}

function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    const preview = document.getElementById('imagePreview');
    modal.style.display = 'none';
    preview.style.display = 'none';
    preview.src = '';
    document.getElementById('fileInput').value = '';
}

// Image preview
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function showErrorMessage(message) {
    // Remove any existing error messages
    const existing = document.querySelector('.error-message');
    if (existing) existing.remove();
    
    // Create new error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    
    // Add to document
    document.body.appendChild(errorDiv);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        errorDiv.remove();
    }, 3000);
}

function showMessage(message, type = 'info') {
    const messageContainer = document.getElementById('messageContainer') || createMessageContainer();
    const messageEl = document.createElement('div');
    messageEl.className = `message ${type}`;
    messageEl.textContent = message;
    messageContainer.appendChild(messageEl);
    
    setTimeout(() => messageEl.remove(), 5000);
}

function createMessageContainer() {
    const container = document.createElement('div');
    container.id = 'messageContainer';
    container.style.position = 'fixed';
    container.style.top = '20px';
    container.style.right = '20px';
    container.style.zIndex = '1000';
    document.body.appendChild(container);
    return container;
}

function showFlashMessage(message, type = 'success', duration = 3000) {
    // Remove any existing messages first
    const existing = document.querySelector('.flash-message');
    if (existing) existing.remove();
    
    // Create message element
    const messageEl = document.createElement('div');
    messageEl.className = `flash-message ${type}`;
    messageEl.textContent = message;
    
    // Add to document
    document.body.appendChild(messageEl);
    
    // Auto-remove after duration
    setTimeout(() => {
        messageEl.remove();
    }, duration);
}

// ==================== END OF V7 UPDATE ====================