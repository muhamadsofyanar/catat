</div> <!-- End Container -->

<footer class="mt-5 py-4 bg-white border-top text-center text-muted small">
    <div class="container">
        <p class="mb-1">&copy; <?= date('Y') ?> LegaVoice Project Manager. All rights reserved.</p>
        <p class="mb-0">Designed for Efficiency & Clarity.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>
</body>
</html>