document.addEventListener("DOMContentLoaded", () => {
  // Initialize systems
  initTabSystem()
  initFileUploads()
  initUI()
  showMessage("Ready to upload your thesis chapters!", "success")
})

function initTabSystem() {
  const navItems = document.querySelectorAll(".nav-item[data-tab]")
  const currentPage = window.location.pathname.split("/").pop()

  navItems.forEach((item) => {
    item.addEventListener("click", function (e) {
      const tabId = this.getAttribute("data-tab")
      const targetContent = document.getElementById(tabId)

      if (!targetContent) {
        return // Allow default navigation
      }

      e.preventDefault()

      // Remove active classes from all
      document.querySelectorAll(".nav-item").forEach((nav) => nav.classList.remove("active"))
      document.querySelectorAll(".tab-content").forEach((tab) => tab.classList.remove("active"))

      // Add active class to clicked tab
      this.classList.add("active")
      targetContent.classList.add("active")
    })

    // Set initial active tab
    if (item.getAttribute("href").includes(currentPage)) {
      item.classList.add("active")
      const tabId = item.getAttribute("data-tab")
      if (tabId) {
        document.getElementById(tabId)?.classList.add("active")
      }
    }
  })
}

function initFileUploads() {
  const uploadAreas = document.querySelectorAll(".upload-area")

  uploadAreas.forEach((area) => {
    // Click handler
    area.addEventListener("click", function (e) {
      if (!e.target.matches('input[type="file"]')) {
        this.querySelector('input[type="file"]')?.click()
      }
    })

    // Drag and drop handlers
    ;["dragover", "dragleave", "drop"].forEach((event) => {
      area.addEventListener(event, function (e) {
        e.preventDefault()
        this.classList.toggle("dragover", event === "dragover")

        if (event === "drop" && e.dataTransfer.files.length) {
          handleFileUpload(e.dataTransfer.files[0], this)
        }
      })
    })

    // File input change handler
    const fileInput = area.querySelector('input[type="file"]')
    if (fileInput) {
      fileInput.addEventListener("change", (e) => {
        if (e.target.files.length) {
          handleFileUpload(e.target.files[0], area)
        }
      })
    }
  })
}

function handleFileUpload(file, uploadArea) {
  // Validate file type
  const allowedTypes = [
    "application/pdf",
    "application/msword",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  ]
  const allowedExtensions = [".pdf", ".doc", ".docx"]
  const fileExtension = "." + file.name.split(".").pop().toLowerCase()

  if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
    showMessage("Error: Only PDF and Word documents (.pdf, .doc, .docx) are allowed.", "error")
    return
  }

  // Check file size (10MB limit)
  const maxSize = 10 * 1024 * 1024 // 10MB in bytes
  if (file.size > maxSize) {
    showMessage("Error: File size must be less than 10MB.", "error")
    return
  }

  // Get chapter number from the upload area
  const chapterInput = uploadArea.querySelector('input[type="file"]')
  let chapterNumber = chapterInput ? chapterInput.getAttribute("data-chapter") : null

  // Fallback: extract from input ID if data-chapter is missing
  if (!chapterNumber && chapterInput && chapterInput.id) {
    const match = chapterInput.id.match(/chapter(\d+)/)
    chapterNumber = match ? match[1] : null
  }

  // Additional fallback: extract from closest chapter card
  if (!chapterNumber) {
    const chapterCard = uploadArea.closest(".chapter-card")
    if (chapterCard) {
      const titleElement = chapterCard.querySelector(".chapter-title")
      if (titleElement) {
        const titleMatch = titleElement.textContent.match(/Chapter (\d+)/)
        chapterNumber = titleMatch ? titleMatch[1] : null
      }
    }
  }

  if (!chapterNumber) {
    showMessage("Error: Could not determine chapter number.", "error")
    console.error("Chapter number extraction failed. Input element:", chapterInput)
    return
  }

  showUploadModal(file, chapterNumber, uploadArea)
}

function showUploadModal(file, chapterNumber, uploadArea) {
  // Create modal if it doesn't exist
  let modal = document.getElementById("uploadModal")
  if (!modal) {
    modal = createUploadModal()
    document.body.appendChild(modal)
  }

  // Get chapter name
  const chapterNames = {
    1: "Introduction",
    2: "Review of Related Literature",
    3: "Methodology",
    4: "Results and Discussion",
    5: "Summary, Conclusion, and Recommendation",
  }
  const chapterName = chapterNames[chapterNumber] || `Chapter ${chapterNumber}`

  // Update modal content
  const modalTitle = modal.querySelector(".modal-title")
  const fileName = modal.querySelector(".file-name")
  const fileSize = modal.querySelector(".file-size")
  const chapterInfo = modal.querySelector(".chapter-info")

  modalTitle.textContent = "Upload Chapter File"
  fileName.textContent = file.name
  fileSize.textContent = `${(file.size / 1024).toFixed(2)} KB`
  chapterInfo.textContent = chapterName

  // Reset modal state
  resetModalState(modal)

  // Show modal
  modal.classList.add("show")

  // Set up event listeners
  const uploadBtn = modal.querySelector(".btn-upload")
  const cancelBtn = modal.querySelector(".btn-cancel")
  const closeBtn = modal.querySelector(".close-modal")
  const doneBtn = modal.querySelector(".btn-done")

  const handleUpload = () => performUpload(file, chapterNumber, uploadArea, modal)
  const handleClose = () => closeModal(modal)

  uploadBtn.onclick = handleUpload
  cancelBtn.onclick = handleClose
  closeBtn.onclick = handleClose
  doneBtn.onclick = handleClose

  // Close on outside click
  modal.onclick = (e) => {
    if (e.target === modal) handleClose()
  }
}

function createUploadModal() {
  const modal = document.createElement("div")
  modal.id = "uploadModal"
  modal.className = "upload-modal"
  modal.innerHTML = `
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Upload Chapter File</h3>
        <button class="close-modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="file-info">
          <h4>File Details</h4>
          <div class="file-details">
            <div><strong>File:</strong> <span class="file-name"></span></div>
            <div><strong>Size:</strong> <span class="file-size"></span></div>
            <div><strong>Chapter:</strong> <span class="chapter-info"></span></div>
          </div>
        </div>
        <div class="upload-progress">
          <div class="progress-bar">
            <div class="progress-fill"></div>
          </div>
          <div class="progress-text">Uploading... 0%</div>
        </div>
        <div class="upload-success">
          <div class="success-icon">✓</div>
          <h4>Upload Successful!</h4>
          <p>Your chapter has been uploaded and added to your history.</p>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn-modal btn-cancel">Cancel</button>
        <button class="btn-modal btn-upload">
          <i class="fas fa-upload"></i> Upload
        </button>
        <button class="btn-modal btn-done" style="display: none;">Done</button>
      </div>
    </div>
  `
  return modal
}

function resetModalState(modal) {
  modal.querySelector(".upload-progress").style.display = "none"
  modal.querySelector(".upload-success").style.display = "none"
  modal.querySelector(".modal-actions").style.display = "flex"
  modal.querySelector(".progress-fill").style.width = "0%"
  modal.querySelector(".progress-text").textContent = "Uploading... 0%"
  modal.querySelector(".btn-upload").disabled = false
  modal.querySelector(".btn-upload").style.display = "inline-block"
  modal.querySelector(".btn-cancel").style.display = "inline-block"
  modal.querySelector(".btn-done").style.display = "none"
}

function performUpload(file, chapterNumber, uploadArea, modal) {
  const uploadBtn = modal.querySelector(".btn-upload")
  const cancelBtn = modal.querySelector(".btn-cancel")
  const doneBtn = modal.querySelector(".btn-done")
  const progressSection = modal.querySelector(".upload-progress")
  const progressFill = modal.querySelector(".progress-fill")
  const progressText = modal.querySelector(".progress-text")
  const successSection = modal.querySelector(".upload-success")
  const modalActions = modal.querySelector(".modal-actions")

  // Show progress and disable buttons
  progressSection.style.display = "block"
  uploadBtn.disabled = true
  cancelBtn.disabled = true

  // Create FormData for upload
  const formData = new FormData()
  formData.append("file", file)
  formData.append("chapter_number", chapterNumber)

  // Upload file with progress tracking
  const xhr = new XMLHttpRequest()

  xhr.upload.addEventListener("progress", (e) => {
    if (e.lengthComputable) {
      const percentComplete = (e.loaded / e.total) * 100
      progressFill.style.width = `${percentComplete}%`
      progressText.textContent = `Uploading... ${Math.round(percentComplete)}%`
    }
  })

  xhr.addEventListener("load", () => {
    try {
      const response = JSON.parse(xhr.responseText)

      if (xhr.status === 200 && response.success) {
        progressSection.style.display = "none"
        successSection.style.display = "block"

        // Hide upload and cancel buttons, show centered done button
        uploadBtn.style.display = "none"
        cancelBtn.style.display = "none"
        doneBtn.style.display = "block"
        modalActions.style.justifyContent = "center"

        // Update original upload area
        updateUploadAreaSuccess(uploadArea, file, chapterNumber)

        showMessage(`"${response.filename || file.name}" uploaded successfully`, "success")
        
        // Reload page to update history
        setTimeout(() => {
          window.location.reload()
        }, 1500)
      } else {
        handleUploadError(modal, response.message || "Unknown error")
      }
    } catch (e) {
      handleUploadError(modal, "Invalid response from server")
    }
  })

  xhr.addEventListener("error", () => {
    handleUploadError(modal, "Network error occurred during upload")
  })

  xhr.addEventListener("timeout", () => {
    handleUploadError(modal, "Upload timed out. Please try again.")
  })

  xhr.timeout = 60000
  xhr.open("POST", "../Student/upload_handler.php")
  xhr.send(formData)
}

function updateUploadAreaSuccess(uploadArea, file, chapterNumber) {
  const fileNameDisplay = uploadArea.querySelector("p")
  const uploadIcon = uploadArea.querySelector(".upload-icon i")

  if (fileNameDisplay) {
    fileNameDisplay.textContent = file.name
    fileNameDisplay.title = file.name
  }

  if (uploadIcon) {
    uploadIcon.className = "fas fa-file-alt"
  }

  const statusElement = uploadArea.closest(".chapter-card")?.querySelector(".chapter-status")
  if (statusElement) {
    statusElement.textContent = "Uploaded"
    statusElement.className = "chapter-status status uploaded"
  }
}

function handleUploadError(modal, errorMessage) {
  showMessage(`Error: ${errorMessage}`, "error")
  closeModal(modal)
}

function closeModal(modal) {
  modal.classList.remove("show")
}

function viewValidationReport(chapterId) {
  showMessage("Validation report functionality will be implemented soon.", "info")
}

function initUI() {
  // User dropdown functionality
  const userAvatar = document.getElementById("userAvatar")
  const userDropdown = document.getElementById("userDropdown")

  if (userAvatar && userDropdown) {
    userAvatar.addEventListener("click", (e) => {
      e.stopPropagation()
      userDropdown.style.display = userDropdown.style.display === "block" ? "none" : "block"
    })

    document.addEventListener("click", (e) => {
      if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.style.display = "none"
      }
    })
  }

  // Logout functionality
  const logoutBtn = document.getElementById("logoutBtn")
  const logoutLink = document.getElementById("logoutLink")
  const logoutModal = document.getElementById("logoutModal")
  const confirmLogout = document.getElementById("confirmLogout")
  const cancelLogout = document.getElementById("cancelLogout")

  if (logoutModal) {
    const showModal = (e) => {
      if (e) e.preventDefault()
      logoutModal.style.display = "flex"
    }

    const hideModal = () => (logoutModal.style.display = "none")

    if (logoutBtn) logoutBtn.addEventListener("click", showModal)
    if (logoutLink) logoutLink.addEventListener("click", showModal)
    if (cancelLogout) cancelLogout.addEventListener("click", hideModal)
    if (confirmLogout)
      confirmLogout.addEventListener("click", () => {
        window.location.href = "../logout.php"
      })

    logoutModal.addEventListener("click", (e) => {
      if (e.target === logoutModal) {
        hideModal()
      }
    })
  }
}

function showMessage(text, type = "info") {
  document.querySelectorAll(".message").forEach((msg) => msg.remove())

  const message = document.createElement("div")
  message.className = `message ${type}`
  message.textContent = text

  const mainContent = document.querySelector(".main-content")
  if (mainContent) {
    const firstCard = mainContent.querySelector(".card")
    if (firstCard) {
      firstCard.insertAdjacentElement("beforebegin", message)
    } else {
      mainContent.prepend(message)
    }
  }

  setTimeout(() => {
    if (message.parentNode) {
      message.remove()
    }
  }, 5000)
}

// Prevent default drag and drop on window
window.addEventListener("dragover", (e) => {
  e.preventDefault()
})

window.addEventListener("drop", (e) => {
  e.preventDefault()
})
