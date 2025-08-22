<?php
// src/views/header.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyTube</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body class="text-sm">
    <div class="header-box py-2">
        <div class="container-main flex justify-between items-center px-4">
            <div class="flex items-center space-x-6">
                <a href="/" class="text-2xl font-bold text-red-600">My<span class="text-gray-800">Tube</span></a>
                <form action="/search" method="GET" class="hidden md:flex items-center">
                    <input type="text" name="q" placeholder="Search for videos..." class="input-classic w-64">
                    <button type="submit" class="btn-classic ml-2">Search</button>
                </form>
            </div>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <span class="font-bold">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="/upload" class="link-classic">Upload</a>
                    <a href="/account" class="link-classic">My Account</a>
                    <a href="/logout" class="link-classic">Log Out</a>
                <?php else : ?>
                    <a href="/signup" class="link-classic">Sign Up</a>
                    <a href="/login" class="link-classic">Log In</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <main class="container-main mt-4 px-4 pb-8">
        <?php
        if (isset($_SESSION['flash_message'])) {
            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
            unset($_SESSION['flash_message']);
        }
        ?>
        <?php if (!empty($errors)) : ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php foreach ($errors as $error) : ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success_message) : ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <p><?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>
