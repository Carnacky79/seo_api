</main>
</div>

<!-- Footer -->
<footer class="dashboard-footer">
    &copy; <?php echo date('Y'); ?> SEO Metadata API. Tutti i diritti riservati.
</footer>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="assets/js/dashboard.js"></script>

<?php if ($page === 'api-tester'): ?>
    <script src="assets/js/api-tester.js"></script>
<?php endif; ?>
</body>
</html>
