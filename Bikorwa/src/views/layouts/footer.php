    </div> <!-- End main-content -->

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Add session token to all AJAX requests if available
        $(function(){
            var token = sessionStorage.getItem('sessionId');
            if(token){
                $.ajaxSetup({
                    beforeSend: function(xhr){
                        xhr.setRequestHeader('X-Session-Id', token);
                    }
                });
            }
        });
    </script>
    
    <!-- Sidebar Toggle and Charts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Toggle for mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.querySelector('.sidebar');
                if (sidebar && sidebar.classList.contains('show') && !sidebar.contains(event.target) && event.target !== sidebarToggle) {
                    sidebar.classList.remove('show');
                }
            });
            
            // Initialize all dropdown toggles in the sidebar
            const dropdownToggles = document.querySelectorAll('.sidebar-item[data-bs-toggle="collapse"]');
            dropdownToggles.forEach(function(toggle) {
                toggle.addEventListener('click', function(event) {
                    event.preventDefault();
                    const targetId = this.getAttribute('data-bs-target');
                    const targetCollapse = document.querySelector(targetId);
                    
                    // Create a Bootstrap collapse instance and toggle it
                    const bsCollapse = new bootstrap.Collapse(targetCollapse, {
                        toggle: true
                    });
                });
            });
        });
    </script>
</body>
</html>
