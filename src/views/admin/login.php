<?php
// src/views/admin/login.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>

<body class="flex items-center justify-center h-screen">
    <div class="w-full max-w-xs">
        <form class="box shadow-md rounded px-8 pt-6 pb-8 mb-4" method="POST">
            <h1 class="text-2xl font-bold mb-4 text-center">MyTube Admin</h1>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username</label>
                <input class="input-classic w-full" id="username" name="username" type="text" placeholder="Username" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                <input class="input-classic w-full" id="password" name="password" type="password" placeholder="******************" required>
            </div>
            <?php if ($login_error) : ?>
                <p class="text-red-500 text-xs italic mb-4"><?php echo $login_error; ?></p>
            <?php endif; ?>
            <div class="flex items-center justify-between">
                <button class="btn-classic w-full" type="submit">Sign In</button>
            </div>
        </form>
    </div>
</body>

</html>
