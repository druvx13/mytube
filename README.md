# MyTube - A Retro Video Sharing Website

MyTube is a simple, retro-style video sharing website inspired by the YouTube of 2006. It's built with PHP and MySQL and features a clean, minimalist interface that focuses on the content and the community.

## Features

*   **Video Sharing:** Upload and share your videos with the world.
*   **User Accounts:** Sign up for an account to upload videos, comment, and like/dislike videos.
*   **Commenting System:** Engage in discussions with other users on video pages.
*   **Like/Dislike System:** Rate videos to let the creator know what you think.
*   **Channels:** View all the videos uploaded by a specific user on their channel page.
*   **Search:** Find videos by searching for keywords in the title or description.
*   **Admin Panel:** Manage users, videos, comments, and messages through a secure admin panel.

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/your-username/mytube.git
    ```

2.  **Create a database:**
    - Create a new MySQL database for the project.
    - Import the `database.sql` file (not yet created, I will create it in a later step) to set up the necessary tables.

3.  **Configure the database connection:**
    - Open `src/db-config.php` and update the database credentials with your own.

4.  **Configure the admin user:**
    - Open `src/admin-config.php` and change the `ADMIN_PASS_HASH` to a secure password hash. You can generate a hash using PHP's `password_hash()` function.

5.  **Set up the web server:**
    - Point your web server's document root to the `public` directory.
    - Ensure that URL rewriting is enabled on your server. For Apache, you can use the provided `.htaccess` file.

6.  **Set file permissions:**
    - Make sure the `uploads` directory is writable by the web server.

## Usage

*   **Home Page:** Browse the latest featured videos.
*   **Watch Page:** Watch videos, view comments, and see related videos.
*   **Sign Up/Log In:** Create an account or log in to access all features.
*   **Upload:** Upload your own videos to share with the community.
*   **My Account:** Manage your profile picture and view your uploaded videos.
*   **Admin Panel:** Access the admin panel by navigating to `/admin`.

## Contributing

Contributions are welcome! If you'd like to contribute to the project, please follow these steps:

1.  Fork the repository.
2.  Create a new branch for your feature or bug fix.
3.  Make your changes and commit them with a descriptive message.
4.  Push your changes to your fork.
5.  Create a pull request to the main repository.

## License

This project is licensed under the MIT License. See the `LICENSE` file for more details.
