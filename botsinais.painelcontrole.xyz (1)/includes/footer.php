    <footer class="footer mt-5">
        <div class="container-fluid">
            <div class="text-muted text-center py-3">
                <small>&copy; <?php echo date('Y'); ?> <?php echo $siteName; ?> - Todos os direitos reservados.</small>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('topNav').classList.toggle('expanded');
            document.querySelector('main').classList.toggle('expanded');
        });
    </script>
</body>
</html>