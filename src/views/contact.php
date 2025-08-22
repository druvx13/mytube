<?php
// src/views/contact.php
require_once 'header.php';
?>

<script>
    document.title = "Contact Us - MyTube";
</script>
<div class="max-w-lg mx-auto box p-6">
    <h1 class="text-2xl font-bold mb-4">Contact Us</h1>
    <p class="mb-4 text-gray-600">Have a question or a copyright concern? Fill out the form below to get in touch.</p>
    <form method="POST">
        <div class="mb-4"><label for="name" class="block font-bold mb-1">Your Name</label><input type="text" id="name" name="name" class="w-full input-classic" required></div>
        <div class="mb-4"><label for="email" class="block font-bold mb-1">Your Email</label><input type="email" id="email" name="email" class="w-full input-classic" required></div>
        <div class="mb-4"><label for="subject" class="block font-bold mb-1">Subject</label><input type="text" id="subject" name="subject" class="w-full input-classic" required></div>
        <div class="mb-4"><label for="message" class="block font-bold mb-1">Message</label><textarea id="message" name="message" class="w-full input-classic" rows="6" required></textarea></div>
        <button type="submit" class="btn-classic">Send Message</button>
    </form>
</div>

<?php
require_once 'footer.php';
?>
