</main>
<footer class="footer mt-5 py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5">
                <h5>Myanmar Horizons</h5>
                <p class="mb-0 text-white-50">Thoughtful journeys across Myanmar, coordinated with trusted local partners.</p>
            </div>
            <div class="col-md-3">
                <h6>Explore</h6><a href="<?= url('destinations.php') ?>">Destinations</a><a href="<?= url('packages.php') ?>">Packages</a>
            </div>
            <div class="col-md-4">
                <h6>Contact</h6>
                <p class="text-white-50 mb-0"><i class="bi bi-envelope me-2"></i>hello@myanmarhorizons.test<br><i class="bi bi-telephone me-2"></i>+95 9 123 456 789</p>
            </div>
        </div>
        <hr><small class="text-white-50">© <?= date('Y') ?> Myanmar Horizons. All rights reserved.</small>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('/assets/js/script.js') ?>"></script>
</body>

</html>