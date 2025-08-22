<?php
// src/views/login.php
require_once 'header.php';
?>

<script>
    document.title = "Log In - MyTube";
</script>
<div class="max-w-md mx-auto box p-6">
    <h1 class="text-2xl font-bold mb-4">Log In to Your Account</h1>
    <form method="POST">
        <div class="mb-4"><label for="username" class="block font-bold mb-1">Username</label><input type="text" id="username" name="username" class="w-full input-classic" required></div>
        <div class="mb-4"><label for="password" class="block font-bold mb-1">Password</label><input type="password" id="password" name="password" class="w-full input-classic" required></div>
        <button type="submit" class="btn-classic">Log In</button>
    </form>
</div>

<?php
require_once 'footer.php';
?>
