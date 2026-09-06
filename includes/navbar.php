<nav class="navbar navbar-expand-lg navbar-dark sticky-top nav-glass">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= url() ?>">
            <i class="bi bi-compass-fill me-2"></i>Myanmar Horizons
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                
                <?php 
                // Get the current user's role
                $role = logged_in() ? current_user()['role'] : 'guest';
                
                // ONLY hide these links if the role is 'hotel'
                // Admins, Customers, and Guests will still see them
                if ($role !== 'hotel'): 
                ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('destinations.php') ?>">Destinations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('packages.php') ?>">Packages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('about.php') ?>">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('contact.php') ?>">Contact</a>
                    </li>
                <?php endif; ?>

                <?php if (logged_in()): ?>
                    <?php if (current_user()['role'] === 'admin'): ?>
                        <!-- Admin Manage Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-gear me-1"></i>Manage
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= url('admin/destinations.php') ?>"><i class="bi bi-geo-alt me-2"></i>Destinations</a></li>
                                <li><a class="dropdown-item" href="<?= url('admin/packages.php') ?>"><i class="bi bi-map me-2"></i>Packages</a></li>
                                <li><a class="dropdown-item" href="<?= url('admin/hotels.php') ?>"><i class="bi bi-building me-2"></i>Hotels</a></li>
                                <li><a class="dropdown-item" href="<?= url('admin/rooms.php') ?>"><i class="bi bi-door-open me-2"></i>Rooms</a></li>
                                <li><a class="dropdown-item" href="<?= url('admin/drivers.php') ?>"><i class="bi bi-car-front me-2"></i>Drivers</a></li>
                                <li><a class="dropdown-item" href="<?= url('admin/customers.php') ?>"><i class="bi bi-people me-2"></i>Customers</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= url('admin/payments.php') ?>"><i class="bi bi-cash-stack me-2"></i>Payments</a></li>
                                <li><a class="dropdown-item" href="<?= url('admin/messages.php') ?>"><i class="bi bi-envelope me-2"></i>Messages</a></li>
                            </ul>
                        </li>
                    <?php elseif (current_user()['role'] === 'hotel'): ?>
                        <!-- Hotel Manage Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="hotelMenu" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-gear me-1"></i>Manage
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= url('hotel/rooms.php') ?>"><i class="bi bi-door-open me-2"></i>Manage Rooms</a></li>
                                <li><a class="dropdown-item" href="<?= url('hotel/bookings.php') ?>"><i class="bi bi-calendar-check me-2"></i>Reservations</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= url('hotel/profile.php') ?>"><i class="bi bi-building me-2"></i>Hotel Profile</a></li>
                            </ul>
                        </li>
                    <?php endif; ?> 

                    <li class="nav-item">
                        <a class="nav-link" href="<?= url(dashboard_path(current_user()['role'])) ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm ms-lg-2" href="<?= url('logout.php') ?>">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('login.php') ?>">Login</a></li>
                    <li class="nav-item"><a class="btn btn-warning btn-sm ms-lg-2" href="<?= url('register.php') ?>">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>