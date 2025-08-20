// Global variables
let currentStudentId = null;
let isEditMode = false;
let confirmCallback = null;

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  initializeUI();
  setupEventListeners();
  showMessage("Welcome to CICT Student Management!", "info");
});

function initializeUI() {
  // Initialize user dropdown
  const headerAvatar = document.getElementById('headerAvatar');
  const userDropdown = document.getElementById('userDropdown');
  const headerLogoutLink = document.getElementById('headerLogoutLink');
  
  if (headerAvatar && userDropdown) {
    headerAvatar.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleUserDropdown();
    });
  }
  
  if (headerLogoutLink) {
    headerLogoutLink.addEventListener('click', (e) => {
      e.preventDefault();
      showLogoutModal();
    });
  }
}

function setupEventListeners() {
  // Setup logout handlers
  setupLogoutHandlers();
  
  // Setup confirmation modal
  setupConfirmationModal();
  
  // Close dropdowns when clicking outside
  document.addEventListener("click", (e) => {
    if (!e.target.closest(".action-dropdown")) {
      closeAllActionDropdowns();
    }
    if (!e.target.closest(".user-info")) {
      closeUserDropdown();
    }
  });

  // Form submission
  const studentForm = document.getElementById("studentForm");
  if (studentForm) {
    studentForm.addEventListener("submit", (e) => {
      e.preventDefault();
      saveStudent();
    });
  }

  // Modal close events
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeStudentModal();
      closeCredentialsModal();
      hideLogoutModal();
      closeConfirmModal();
    }
  });
}

function setupConfirmationModal() {
  const confirmBtn = document.getElementById("confirmActionBtn");
  if (confirmBtn) {
    confirmBtn.addEventListener('click', handleConfirmAction);
  }
  
  const modal = document.getElementById("confirmModal");
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeConfirmModal();
      }
    });
  }
}

function showConfirmModal(title, message, callback) {
  const modal = document.getElementById("confirmModal");
  const titleElement = document.getElementById("confirmModalTitle");
  const messageElement = document.getElementById("confirmModalMessage");
  
  if (modal && titleElement && messageElement) {
    titleElement.textContent = title;
    messageElement.textContent = message;
    confirmCallback = callback;
    
    modal.style.display = "flex";
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
      const confirmBtn = document.getElementById("confirmActionBtn");
      if (confirmBtn) confirmBtn.focus();
    }, 100);
  }
}

function closeConfirmModal() {
  const modal = document.getElementById("confirmModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = '';
    confirmCallback = null;
  }
}

function handleConfirmAction() {
  if (typeof confirmCallback === 'function') {
    confirmCallback();
  }
  closeConfirmModal();
}

function setupLogoutHandlers() {
  // Get elements
  const logoutBtn = document.getElementById("logoutBtn");
  const logoutModal = document.getElementById("logoutModal");
  const confirmLogout = document.getElementById("confirmLogout");
  const cancelLogout = document.getElementById("cancelLogout");

  // Show modal function
  const showModal = (e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    logoutModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  };

  // Hide modal function
  const hideModal = () => {
    logoutModal.style.display = 'none';
    document.body.style.overflow = '';
  };

  // Attach event listeners
  if (logoutBtn) {
    logoutBtn.addEventListener('click', showModal);
  }

  if (confirmLogout) {
    confirmLogout.addEventListener('click', () => {
      window.location.href = "../logout.php";
    });
  }

  if (cancelLogout) {
    cancelLogout.addEventListener('click', hideModal);
  }

  // Close modal when clicking outside
  if (logoutModal) {
    logoutModal.addEventListener('click', (e) => {
      if (e.target === logoutModal) {
        hideModal();
      }
    });
  }
}

function toggleUserDropdown() {
  const dropdown = document.getElementById("userDropdown");
  if (dropdown) {
    dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
  }
}

function closeUserDropdown() {
  const dropdown = document.getElementById("userDropdown");
  if (dropdown) {
    dropdown.style.display = "none";
  }
}

function showLogoutModal() {
  const modal = document.getElementById("logoutModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = 'hidden';
  }
}

function hideLogoutModal() {
  const modal = document.getElementById("logoutModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = '';
  }
}

function toggleActionDropdown(studentId) {
  const menu = document.getElementById("actionMenu" + studentId);
  const allMenus = document.querySelectorAll(".action-menu");

  // Close all other menus
  allMenus.forEach((m) => {
    if (m !== menu) {
      m.classList.remove("show");
    }

    // Determine space
    const menuRect = menu.getBoundingClientRect();
    const spaceBelow = window.innerHeight - menuRect.bottom;
    const spaceAbove = menuRect.top;

    if (spaceBelow < 120 && spaceAbove > 120) {
      menu.classList.add("flip-up");
    } else {
      menu.classList.remove("flip-up");
    }
  });

  // Toggle current menu
  if (menu) {
    menu.classList.toggle("show");
  }
}

function closeAllActionDropdowns() {
  const allMenus = document.querySelectorAll(".action-menu");
  allMenus.forEach((menu) => {
    menu.classList.remove("show");
  });
}

function addNewStudent() {
  isEditMode = false;
  currentStudentId = null;

  // Reset form
  document.getElementById("studentForm").reset();
  document.getElementById("studentId").value = "";
  document.getElementById("studentModalTitle").textContent = "Add New Student";

  // Show modal
  showStudentModal();
}

function editStudent(studentId) {
  isEditMode = true;
  currentStudentId = studentId;

  // Find student data from table
  const row = document.querySelector(`button[onclick="toggleActionDropdown(${studentId})"]`).closest("tr");
  const cells = row.querySelectorAll("td");

  const fullName = cells[1].textContent.trim();
  const nameParts = fullName.split(" ");

  const firstName = nameParts[0] || "";
  const lastName = nameParts[nameParts.length - 1] || "";
  let middleName = "";

  if (nameParts.length > 2) {
    middleName = nameParts.slice(1, -1).join(" ");
  }

  // Populate form
  document.getElementById("studentId").value = studentId;
  document.getElementById("firstName").value = firstName;
  document.getElementById("lastName").value = lastName;
  document.getElementById("middleName").value = middleName;
  document.getElementById("studentModalTitle").textContent = "Edit Student";

  // Show modal
  showStudentModal();

  // Close action dropdown
  closeAllActionDropdowns();
}

function deleteStudent(studentId) {
  showConfirmModal(
    "Delete Student", 
    "Are you sure you want to delete this student? This action cannot be undone.",
    () => {
      const formData = new FormData();
      formData.append("action", "delete_student");
      formData.append("student_id", studentId);

      fetch("advisor_student-management.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showMessage(data.message, "success");
            setTimeout(() => {
              location.reload();
            }, 1500);
          } else {
            showMessage(data.message, "error");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showMessage("An error occurred while deleting the student.", "error");
        });
    }
  );

  // Close action dropdown
  closeAllActionDropdowns();
}

function saveStudent() {
  const form = document.getElementById("studentForm");
  const formData = new FormData(form);

  // Add action
  if (isEditMode) {
    formData.append("action", "edit_student");
  } else {
    formData.append("action", "add_student");
  }

  // Disable form during submission
  const submitBtn = document.querySelector(".modal-footer .btn-primary");
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  submitBtn.disabled = true;

  fetch("advisor_student-management.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showMessage(data.message, "success");
        closeStudentModal();

        if (!isEditMode && data.student_data) {
          // Show credentials modal for new students
          showCredentialsModal(data.student_data);
        } else {
          // Reload page for edits
          setTimeout(() => {
            location.reload();
          }, 1500);
        }
      } else {
        showMessage(data.message, "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showMessage("An error occurred while saving the student.", "error");
    })
    .finally(() => {
      // Re-enable form
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    });
}

function showStudentModal() {
  const modal = document.getElementById("studentModal");
  if (modal) {
    modal.classList.add("show");
    // Focus on first input
    setTimeout(() => {
      document.getElementById("firstName").focus();
    }, 100);
  }
}

function closeStudentModal() {
  const modal = document.getElementById("studentModal");
  if (modal) {
    modal.classList.remove("show");
  }
}

function showCredentialsModal(studentData) {
  // Populate credentials
  document.getElementById("credentialName").textContent = studentData.name;
  document.getElementById("credentialStudentId").textContent = studentData.student_id;
  document.getElementById("credentialEmail").textContent = studentData.email;
  document.getElementById("credentialPassword").textContent = studentData.temp_password;

  // Show modal
  const modal = document.getElementById("credentialsModal");
  if (modal) {
    modal.classList.add("show");
  }
}

function closeCredentialsModal() {
  const modal = document.getElementById("credentialsModal");
  if (modal) {
    modal.classList.remove("show");
    // Reload page to show new student
    setTimeout(() => {
      location.reload();
    }, 500);
  }
}

function copyCredentials() {
  const name = document.getElementById("credentialName").textContent;
  const studentId = document.getElementById("credentialStudentId").textContent;
  const email = document.getElementById("credentialEmail").textContent;
  const password = document.getElementById("credentialPassword").textContent;

  const credentialsText = `Student Login Credentials:
Name: ${name}
Student ID: ${studentId}
Email: ${email}
Temporary Password: ${password}

Please change your password on first login.`;

  // Copy to clipboard
  if (navigator.clipboard) {
    navigator.clipboard
      .writeText(credentialsText)
      .then(() => {
        showMessage("Credentials copied to clipboard!", "success");
      })
      .catch(() => {
        fallbackCopyTextToClipboard(credentialsText);
      });
  } else {
    fallbackCopyTextToClipboard(credentialsText);
  }
}

function fallbackCopyTextToClipboard(text) {
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.top = "0";
  textArea.style.left = "0";
  textArea.style.position = "fixed";

  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();

  try {
    const successful = document.execCommand("copy");
    if (successful) {
      showMessage("Credentials copied to clipboard!", "success");
    } else {
      showMessage("Failed to copy credentials.", "error");
    }
  } catch (err) {
    showMessage("Failed to copy credentials.", "error");
  }

  document.body.removeChild(textArea);
}

function showMessage(message, type = "info") {
  const container = document.getElementById("messageContainer");
  if (!container) return;

  const messageDiv = document.createElement("div");
  messageDiv.className = `message ${type}`;
  messageDiv.innerHTML = `
      <i class="fas ${getMessageIcon(type)}"></i>
      ${message}
  `;

  container.appendChild(messageDiv);

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (messageDiv.parentNode) {
      messageDiv.parentNode.removeChild(messageDiv);
    }
  }, 5000);

  // Scroll to top to show message
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function getMessageIcon(type) {
  switch (type) {
    case "success":
      return "fa-check-circle";
    case "error":
      return "fa-exclamation-circle";
    case "warning":
      return "fa-exclamation-triangle";
    default:
      return "fa-info-circle";
  }
}

// Export functions for global access
window.addNewStudent = addNewStudent;
window.editStudent = editStudent;
window.deleteStudent = deleteStudent;
window.saveStudent = saveStudent;
window.closeStudentModal = closeStudentModal;
window.closeCredentialsModal = closeCredentialsModal;
window.copyCredentials = copyCredentials;
window.toggleActionDropdown = toggleActionDropdown;
window.closeConfirmModal = closeConfirmModal;
window.showConfirmModal = showConfirmModal;


// =============== Start of version 6 update =============== 

// ===============  End of version 6 update =============== 