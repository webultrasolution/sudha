function toggleSidebar() {
    if (window.innerWidth > 1024) {
        document.body.classList.toggle('sidebar-collapsed');
    } else {
        document.body.classList.toggle('sidebar-open');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Sudha Creative CRM Loaded');
    
    // Close sidebar on mobile when clicking outside of it
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && document.body.classList.contains('sidebar-open')) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (sidebar && !sidebar.contains(e.target) && menuToggle && !menuToggle.contains(e.target)) {
                document.body.classList.remove('sidebar-open');
            }
        }
    });
});
