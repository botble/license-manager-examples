/**
 * License Manager Admin Scripts
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Toggle password visibility
        var toggleButtons = document.querySelectorAll('.lm-toggle-password');

        toggleButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var targetId = this.getAttribute('data-target');
                var input = document.getElementById(targetId);

                if (!input) {
                    return;
                }

                if (input.type === 'password') {
                    input.type = 'text';
                    this.textContent = 'Hide';
                } else {
                    input.type = 'password';
                    this.textContent = 'Show';
                }
            });
        });
    });
})();
