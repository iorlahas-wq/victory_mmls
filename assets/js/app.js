document.addEventListener('DOMContentLoaded', function () {

    /*
     * ========================================================
     * FLASH MESSAGES
     * ========================================================
     */

    const alerts = document.querySelectorAll('.alert');

    alerts.forEach(function (alert) {

        setTimeout(function () {

            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';

            setTimeout(function () {
                alert.remove();
            }, 400);

        }, 4000);

    });


    /*
     * ========================================================
     * PASSWORD SHOW / HIDE
     * ========================================================
     */

    const passwordInput =
        document.getElementById('password');

    const passwordToggle =
        document.getElementById('passwordToggle');


    if (passwordInput && passwordToggle) {

        passwordToggle.addEventListener(
            'click',
            function () {

                if (passwordInput.type === 'password') {

                    passwordInput.type = 'text';

                    passwordToggle.textContent = 'Hide';

                    passwordToggle.setAttribute(
                        'aria-label',
                        'Hide password'
                    );

                } else {

                    passwordInput.type = 'password';

                    passwordToggle.textContent = 'Show';

                    passwordToggle.setAttribute(
                        'aria-label',
                        'Show password'
                    );
                }

            }
        );

    }

});