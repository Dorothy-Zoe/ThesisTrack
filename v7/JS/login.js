// ✅ Fill demo credentials (used only for login form)
function fillCredentials(email, password) {
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    if (emailInput && passwordInput) {
        emailInput.value = email;
        passwordInput.value = password;
    }
}

// ✅ Toggle password visibility for login password
const loginToggle = document.getElementById('togglePassword');
const loginPassword = document.getElementById('password');

if (loginToggle && loginPassword) {
    loginToggle.addEventListener('click', function () {
        const isPassword = loginPassword.type === 'password';
        loginPassword.type = isPassword ? 'text' : 'password';

        this.classList.toggle('fa-eye-slash');
        this.classList.toggle('fa-eye');
    });
}

// ======================= Start of version 6 update =======================
// ✅ Toggle visibility for new password (in password change form)
const newPassInput = document.querySelector('input[name="new_password"]');
const confirmPassInput = document.querySelector('input[name="confirm_password"]');


function addToggleVisibility(input) {
    if (!input) return;

    const wrapper = document.createElement('div');
    wrapper.classList.add('password-wrapper');

    const toggle = document.createElement('i');
    toggle.className = 'fas fa-eye-slash toggle-password';
    toggle.style.cursor = 'pointer';
    toggle.style.marginLeft = '10px';

    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    wrapper.appendChild(toggle);

    toggle.addEventListener('click', () => {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        toggle.classList.toggle('fa-eye');
        toggle.classList.toggle('fa-eye-slash');
    });
}

addToggleVisibility(newPassInput);
addToggleVisibility(confirmPassInput);

// ======================= End of version 6 update =======================