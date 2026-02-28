<?php
/**
 * MyTube — AJAX Request Handler
 *
 * Included by index.php when an XMLHttpRequest is detected.
 * $db and session must already be initialised by the caller.
 */

header('Content-Type: application/json');

$action   = $_POST['action'] ?? '';
$response = ['success' => false];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'You must be logged in to perform this action.';
    echo json_encode($response);
    $db->close();
    exit();
}

$user_id = (int) $_SESSION['user_id'];

switch ($action) {

    // ------------------------------------------------------------------
    case 'post_comment':
        $comment_text      = trim($_POST['comment'] ?? '');
        $video_internal_id = (int) ($_POST['video_id'] ?? 0);

        if ($comment_text === '' || $video_internal_id <= 0) {
            $response['message'] = 'Comment cannot be empty.';
            break;
        }

        $stmt = $db->prepare("INSERT INTO comments (video_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $video_internal_id, $user_id, $comment_text);

        if ($stmt->execute()) {
            $response['success'] = true;

            $su = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $su->bind_param("i", $user_id);
            $su->execute();
            $ur = $su->get_result()->fetch_assoc();
            $su->close();

            $response['comment'] = [
                'username'     => htmlspecialchars($_SESSION['username']),
                'comment_text' => nl2br(htmlspecialchars($comment_text)),
                'comment_date' => date('M d, Y, g:i A'),
                'avatar_url'   => get_avatar_url($ur['profile_picture'] ?? null),
            ];
        } else {
            $response['message'] = 'Failed to save comment.';
        }
        $stmt->close();
        break;

    // ------------------------------------------------------------------
    case 'like_video':
        $video_internal_id = (int) ($_POST['video_id'] ?? 0);
        $like_type         = (int) ($_POST['like_type'] ?? 0);

        if ($video_internal_id <= 0 || !in_array($like_type, [1, -1], true)) {
            $response['message'] = 'Invalid video or like type.';
            break;
        }

        $sc = $db->prepare("SELECT id, like_type FROM likes WHERE video_id = ? AND user_id = ?");
        $sc->bind_param("ii", $video_internal_id, $user_id);
        $sc->execute();
        $vote = $sc->get_result()->fetch_assoc();
        $sc->close();

        if ($vote) {
            if ($vote['like_type'] === $like_type) {
                $sd = $db->prepare("DELETE FROM likes WHERE id = ?");
                $sd->bind_param("i", $vote['id']);
                $sd->execute();
                $sd->close();
            } else {
                $su2 = $db->prepare("UPDATE likes SET like_type = ? WHERE id = ?");
                $su2->bind_param("ii", $like_type, $vote['id']);
                $su2->execute();
                $su2->close();
            }
        } else {
            $si = $db->prepare("INSERT INTO likes (video_id, user_id, like_type) VALUES (?, ?, ?)");
            $si->bind_param("iii", $video_internal_id, $user_id, $like_type);
            $si->execute();
            $si->close();
        }

        $sc2 = $db->prepare(
            "SELECT
                SUM(CASE WHEN like_type =  1 THEN 1 ELSE 0 END) AS likes,
                SUM(CASE WHEN like_type = -1 THEN 1 ELSE 0 END) AS dislikes
             FROM likes WHERE video_id = ?"
        );
        $sc2->bind_param("i", $video_internal_id);
        $sc2->execute();
        $counts = $sc2->get_result()->fetch_assoc();
        $sc2->close();

        $response['success']  = true;
        $response['likes']    = (int) ($counts['likes']    ?? 0);
        $response['dislikes'] = (int) ($counts['dislikes'] ?? 0);
        break;

    // ------------------------------------------------------------------
    default:
        $response['message'] = 'Invalid action specified.';
}

echo json_encode($response);
$db->close();
exit();
