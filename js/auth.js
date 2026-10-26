document.querySelectorAll('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        var input = document.getElementById(button.getAttribute('data-target'));
        if (!input) return;

        var showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        button.textContent = showPassword ? 'Hide' : 'Show';
        button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
    });
});

document.querySelectorAll('[data-auth-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        var password = form.querySelector('[name="password"]');
        var confirmation = form.querySelector('[name="confirm_password"]');
        if (password && confirmation) {
            confirmation.setCustomValidity(
                password.value === confirmation.value ? '' : 'The passwords do not match.'
            );
            if (!form.reportValidity()) {
                event.preventDefault();
                return;
            }
        }

        var button = form.querySelector('.auth-button');
        if (button) {
            button.disabled = true;
            button.classList.add('is-loading');
        }
    });
});
