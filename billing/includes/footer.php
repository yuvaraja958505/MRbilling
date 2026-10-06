        </div> <!-- End p-4 flex-grow-1 -->
    </div> <!-- End content -->
</div> <!-- End wrapper -->

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS with Cache Buster -->
<script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('sidebarCollapse');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('active');
            });
        }
    });
</script>
</body>
</html>
