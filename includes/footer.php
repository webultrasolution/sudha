    </div><!-- End Main Content -->
    <script src="<?php echo BASE_URL; ?>assets/js/main.js?v=1.0.7"></script>
    <script>
    // Global Bootstrap-like Validation Logic
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByTagName('form');
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                        
                        // Scroll to first invalid field
                        const firstInvalid = form.querySelector(':invalid');
                        if (firstInvalid) {
                            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();
    </script>
</body>
</html>
