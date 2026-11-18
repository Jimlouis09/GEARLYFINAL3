<?php

require_once __DIR__ . '/auth.php';

$base = '/GEARLY';

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        <?= h($pageTitle ?? 'GEARLY') ?> | GEARLY
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="<?= $base ?>/assets/css/style.css"
    >

</head>

<body>

    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">

        <div class="container">

            <a
                class="navbar-brand fw-bold text-primary fs-3"
                href="<?= $base ?>/index.php"
            >
                <img
                    src="<?= $base ?>/assets/css/Images/gearly-logo.jpg"
                    alt="GEARLY"
                >
            </a>

            <button
                class="navbar-toggler"
                data-bs-toggle="collapse"
                data-bs-target="#nav"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="nav">

                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">

                    <?php if (!empty($_SESSION['user_id'])): ?>

                        <span class="nav-link">
                            Hi, <?= h($_SESSION['full_name']) ?>
                        </span>

                        <?php if ($_SESSION['role'] === 'admin'): ?>

                            <a class="nav-link" href="<?= $base ?>/admin/dashboard.php">
                                Admin
                            </a>

                            <a class="nav-link" href="<?= $base ?>/admin/equipment.php">
                                Equipment
                            </a>

                            <a class="nav-link" href="<?= $base ?>/admin/bookings.php">
                                Bookings
                            </a>

                        <?php else: ?>

                            <a class="nav-link" href="<?= $base ?>/customer/dashboard.php">
                                My Rentals
                            </a>

                        <?php endif; ?>

                        <a
                            class="btn btn-outline-primary btn-sm"
                            href="<?= $base ?>/logout.php"
                        >
                            Logout
                        </a>

                    <?php else: ?>

                        <a class="nav-link" href="<?= $base ?>/login.php">
                            Login
                        </a>

                        <a
                            class="btn btn-primary btn-sm"
                            href="<?= $base ?>/register.php"
                        >
                            Create account
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </nav>

    <main class="container py-4">

        <?php showFlash(); ?>