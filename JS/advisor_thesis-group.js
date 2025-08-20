// Global variables
let currentGroupId = null;
let currentStudentGroupId = null;

// Show create group modal
function showCreateGroupModal() {
    document.getElementById('createGroupModal').style.display = 'flex';
}

// Close create group modal
function closeCreateGroupModal() {
    document.getElementById('createGroupModal').style.display = 'none';
    document.getElementById('createGroupForm').reset();
    
    // Reset all role selectors to disabled
    const roleSelectors = document.querySelectorAll('.role-selector');
    roleSelectors.forEach(selector => {
        selector.disabled = true;
        selector.value = 'member'; // Reset to default value
    });
}

// Create new group with validation
function createGroup() {
    const form = document.getElementById('createGroupForm');
    const groupName = form.group_name.value.trim();
    const thesisTitle = form.thesis_title.value.trim();
    const section = form.section.value;
    const studentCheckboxes = form.querySelectorAll('.student-checkbox:checked');
    
    // Validate inputs
    if (!groupName) {
        showMessage('Group name is required', 'error');
        return;
    }
    
    if (!thesisTitle) {
        showMessage('Thesis title is required', 'error');
        return;
    }
    
    // Validate member count
    if (studentCheckboxes.length === 0) {
        showMessage('Please select at least one student', 'error');
        return;
    }
    
    if (studentCheckboxes.length > 4) {
        showMessage('A group can have maximum 4 members', 'error');
        return;
    }
    
    // Validate exactly 1 leader
    let leaderCount = 0;
    const selectedRoles = [];
    
    studentCheckboxes.forEach(checkbox => {
        const role = checkbox.closest('.student-select-item').querySelector('.role-selector').value;
        selectedRoles.push(role);
        if (role === 'leader') leaderCount++;
    });
    
    if (leaderCount !== 1) {
        showMessage('Each group must have exactly 1 Leader', 'error');
        return;
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'create_group');
    formData.append('group_name', groupName);
    formData.append('thesis_title', thesisTitle);
    formData.append('section', section);
    
    studentCheckboxes.forEach((checkbox, index) => {
        formData.append('student_ids[]', checkbox.value);
        formData.append('student_roles[]', selectedRoles[index]);
    });
    
    // Make the request
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(data.message || 'Operation failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred: ' + error.message, 'error');
    });
}

// Edit group - fetches actual data from server
function editGroup(groupId, studentGroupId) {
    currentGroupId = groupId;
    currentStudentGroupId = studentGroupId;
    
    const formData = new FormData();
    formData.append('action', 'get_group_data');
    formData.append('group_id', groupId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Build the edit form HTML
            const editFormHTML = `
                <form id="editGroupForm">
                    <input type="hidden" name="group_id" value="${groupId}">
                    <input type="hidden" name="student_group_id" value="${data.data.student_group_id}">
                    <div class="form-group">
                        <label for="editGroupName">Group Name *</label>
                        <input type="text" id="editGroupName" name="group_name" value="${escapeHtml(data.data.group_name)}" required>
                    </div>
                    <div class="form-group">
                        <label for="editThesisTitle">Thesis Title *</label>
                        <input type="text" id="editThesisTitle" name="thesis_title" value="${escapeHtml(data.data.thesis_title)}" required>
                    </div>
                    <div class="form-group">
                        <label>Current Members</label>
                        <div id="currentMembersList">
                            ${data.members.map(member => `
                                <div class="student-select-item">
                                    <input type="checkbox" name="student_ids[]" value="${member.id}" 
                                           id="member_${member.id}" class="student-checkbox" checked>
                                    <label for="member_${member.id}">
                                        ${escapeHtml(member.name)}
                                    </label>
                                    <select name="student_roles[]" class="role-selector">
                                        <option value="member" ${member.role === 'member' ? 'selected' : ''}>Member</option>
                                        <option value="leader" ${member.role === 'leader' ? 'selected' : ''}>Leader</option>
                                    </select>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Add More Members</label>
                        <div class="student-selector" id="editStudentSelector">
                            ${data.available_students.length > 0 ? 
                                data.available_students.map(student => `
                                    <div class="student-select-item">
                                        <input type="checkbox" name="student_ids[]" value="${student.id}" 
                                               id="new_student_${student.id}" class="student-checkbox">
                                        <label for="new_student_${student.id}">
                                            ${escapeHtml(student.name)} (${escapeHtml(student.section)})
                                        </label>
                                        <select name="student_roles[]" class="role-selector" disabled>
                                            <option value="member">Member</option>
                                            <option value="leader">Leader</option>
                                        </select>
                                    </div>
                                `).join('') : 
                                '<p>No ungrouped students available.</p>'}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-primary" onclick="updateGroup()">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeEditGroupModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            `;
            
            document.getElementById('editGroupModalBody').innerHTML = editFormHTML;
            document.getElementById('editGroupModal').style.display = 'flex';
            
            // Enable role selectors for checked members
            document.querySelectorAll('#currentMembersList .student-checkbox').forEach(checkbox => {
                const roleSelector = checkbox.closest('.student-select-item').querySelector('.role-selector');
                roleSelector.disabled = !checkbox.checked;
                
                checkbox.addEventListener('change', function() {
                    roleSelector.disabled = !this.checked;
                });
            });
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        showMessage('An error occurred', 'error');
        console.error(error);
    });
}

// Update group with validation
function updateGroup() {
    const form = document.getElementById('editGroupForm');
    const groupName = form.group_name.value.trim();
    const thesisTitle = form.thesis_title.value.trim();
    const studentCheckboxes = form.querySelectorAll('.student-checkbox:checked');
    
    // Validate inputs
    if (!groupName) {
        showMessage('Group name is required', 'error');
        return;
    }
    
    if (!thesisTitle) {
        showMessage('Thesis title is required', 'error');
        return;
    }
    
    // Validate member count
    if (studentCheckboxes.length === 0) {
        showMessage('Please select at least one student', 'error');
        return;
    }
    
    if (studentCheckboxes.length > 4) {
        showMessage('A group can have maximum 4 members', 'error');
        return;
    }
    
    // Validate exactly 1 leader
    let leaderCount = 0;
    const selectedRoles = [];
    
    studentCheckboxes.forEach(checkbox => {
        const role = checkbox.closest('.student-select-item').querySelector('.role-selector').value;
        selectedRoles.push(role);
        if (role === 'leader') leaderCount++;
    });
    
    if (leaderCount !== 1) {
        showMessage('Each group must have exactly 1 Leader', 'error');
        return;
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'update_group');
    formData.append('group_id', currentGroupId);
    formData.append('student_group_id', currentStudentGroupId);
    formData.append('group_name', groupName);
    formData.append('thesis_title', thesisTitle);
    
    studentCheckboxes.forEach((checkbox, index) => {
        formData.append('student_ids[]', checkbox.value);
        formData.append('student_roles[]', selectedRoles[index]);
    });
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        showMessage('An error occurred', 'error');
        console.error(error);
    });
}

// Close edit group modal
function closeEditGroupModal() {
    document.getElementById('editGroupModal').style.display = 'none';
    currentGroupId = null;
    currentStudentGroupId = null;
}

// Delete group confirmation
function deleteGroup(groupId, studentGroupId) {
    currentGroupId = groupId;
    currentStudentGroupId = studentGroupId;
    document.getElementById('confirmModalTitle').textContent = 'Delete Group';
    document.getElementById('confirmModalMessage').textContent = 'Are you sure you want to delete this group and all its associated data? This action cannot be undone.';
    document.getElementById('confirmActionBtn').onclick = confirmDeleteGroup;
    document.getElementById('confirmModal').style.display = 'flex';
}

// Confirm delete group
function confirmDeleteGroup() {
    const formData = new FormData();
    formData.append('action', 'delete_group');
    formData.append('group_id', currentGroupId);
    formData.append('student_group_id', currentStudentGroupId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
        closeConfirmModal();
    })
    .catch(error => {
        showMessage('An error occurred', 'error');
        console.error(error);
        closeConfirmModal();
    });
}

// Close confirmation modal
function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
    currentGroupId = null;
    currentStudentGroupId = null;
}

// View group details
// function viewGroupDetails(groupId, studentGroupId) {
//     window.location.href = `advisor_group-details.php?group_id=${groupId}&student_group_id=${studentGroupId}`;
// }

// Show message

function showMessage(message, type) {
    const messageContainer = document.getElementById('messageContainer');
    messageContainer.innerHTML = `
        <div class="alert alert-${type}">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        </div>
    `;
    
    setTimeout(() => {
        messageContainer.innerHTML = '';
    }, 5000);
}



// Toggle action dropdown
function toggleActionDropdown(id) {
    const menu = document.getElementById(`actionMenu${id}`);
    const isVisible = menu.style.display === 'block';
    
    // Close all dropdowns
    document.querySelectorAll('.action-menu').forEach(el => el.style.display = 'none');
    
    // Toggle current
    menu.style.display = isVisible ? 'none' : 'block';
}

// Helper function to escape HTML
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Event listeners for member selection and role assignment
document.addEventListener('change', function(e) {
    // Enable/disable role selector when student is selected/deselected
    if (e.target.classList.contains('student-checkbox')) {
        const roleSelector = e.target.closest('.student-select-item').querySelector('.role-selector');
        roleSelector.disabled = !e.target.checked;
        
        // When a leader is selected, unselect other leaders
        if (roleSelector.value === 'leader' && e.target.checked) {
            document.querySelectorAll('.role-selector').forEach(selector => {
                if (selector !== roleSelector && selector.value === 'leader') {
                    selector.value = 'member';
                }
            });
        }
    }
    
    // When a role selector changes to leader, unselect other leaders
    if (e.target.classList.contains('role-selector') && e.target.value === 'leader') {
        document.querySelectorAll('.role-selector').forEach(selector => {
            if (selector !== e.target && selector.value === 'leader') {
                selector.value = 'member';
            }
        });
    }
    
    // Limit total selected members to 4
    const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
    if (selectedCount > 4) {
        e.target.checked = false;
        const roleSelector = e.target.closest('.student-select-item').querySelector('.role-selector');
        roleSelector.disabled = true;
        showMessage('Maximum 4 members allowed per group', 'error');
    }
});

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-menu').forEach(el => el.style.display = 'none');
    }
    
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(el => el.style.display = 'none');
    }
});



// ===== Logout functionality====

// ===== Logout functionality====
document.addEventListener('DOMContentLoaded', function() {
    // Get all logout buttons/links
    const logoutBtn = document.getElementById('logoutBtn');
    const headerLogoutLink = document.getElementById('headerLogoutLink');
    
    // Sidebar logout elements
    const logoutModal = document.getElementById('logoutModal');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');
    
    // Header logout elements
    const headerLogoutModal = document.getElementById('headerLogoutModal');
    const headerConfirmLogout = document.getElementById('headerConfirmLogout');
    const headerCancelLogout = document.getElementById('headerCancelLogout');

    // Show modal when clicking any logout button/link
    function showLogoutModal(modal) {
        modal.style.display = 'flex';
    }

    // Hide modal
    function hideLogoutModal(modal) {
        modal.style.display = 'none';
    }

    // Event listeners for sidebar logout
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showLogoutModal(logoutModal);
        });
    }

    // Event listeners for header logout
    if (headerLogoutLink) {
        headerLogoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            showLogoutModal(headerLogoutModal);
        });
    }

    if (confirmLogout) {
        confirmLogout.addEventListener('click', function() {
            window.location.href = '../logout.php';
        });
    }

    if (headerConfirmLogout) {
        headerConfirmLogout.addEventListener('click', function() {
            window.location.href = '../logout.php';
        });
    }

    if (cancelLogout) {
        cancelLogout.addEventListener('click', function() {
            hideLogoutModal(logoutModal);
        });
    }

    if (headerCancelLogout) {
        headerCancelLogout.addEventListener('click', function() {
            hideLogoutModal(headerLogoutModal);
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === logoutModal) {
            hideLogoutModal(logoutModal);
        }
        if (e.target === headerLogoutModal) {
            hideLogoutModal(headerLogoutModal);
        }
    });
});

// Header dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const headerAvatar = document.getElementById('headerAvatar');
    const userDropdown = document.getElementById('userDropdown');

    if (headerAvatar && userDropdown) {
        // Toggle dropdown on avatar click
        headerAvatar.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = userDropdown.style.display === 'block';
            
            // Close all other dropdowns first
            document.querySelectorAll('.dropdown-menu').forEach(el => {
                el.style.display = 'none';
            });
            
            // Toggle current dropdown
            userDropdown.style.display = isVisible ? 'none' : 'block';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-info')) {
                userDropdown.style.display = 'none';
            }
        });

        // Close dropdown when pressing Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                userDropdown.style.display = 'none';
            }
        });

        // Keyboard navigation for accessibility
        headerAvatar.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const isVisible = userDropdown.style.display === 'block';
                userDropdown.style.display = isVisible ? 'none' : 'block';
            }
        });
    }
});
