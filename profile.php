<?php
session_start();
$userParam = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['user'] ?? '');
if (!$userParam) die("No user specified.");
$userFile = __DIR__ . "/users/$userParam/$userParam.txt";
if (!file_exists($userFile)) die("User not found.");
$user = json_decode(file_get_contents($userFile), true);
if (!$user) die("Corrupt user data.");

$selfUser = $_SESSION['user']['username'] ?? '';
$isOwner = ($userParam === $selfUser);

// Handle show/hide friends toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['toggle_friends'])) {
    $user['show_friends'] = !($user['show_friends'] ?? true);
    file_put_contents($userFile, json_encode($user));
    header("Location: profile.php?user=" . urlencode($userParam));
    exit;
}

$showFriends = $user['show_friends'] ?? true;

function get_friend_details($friendUsername) {
    $friendFile = __DIR__ . "/users/$friendUsername/$friendUsername.txt";
    if (file_exists($friendFile)) {
        $data = json_decode(file_get_contents($friendFile), true);
        if ($data) {
            return [
                'display' => htmlspecialchars($data['first_name'] . ' ' . $data['last_name']),
                'nickname' => htmlspecialchars($data['username']),
            ];
        }
    }
    return [
        'display' => htmlspecialchars($friendUsername),
        'nickname' => htmlspecialchars($friendUsername),
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?=htmlspecialchars($user['first_name'] . ' ' . $user['last_name'])?>'s Profile</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f6f6; margin: 0; }
        .main-nav {
            background-color: #333;
            padding: 12px 20px;
            margin-bottom: 20px;
        }
        .main-nav a {
            color: white;
            text-decoration: none;
            margin-right: 15px;
            font-weight: bold;
        }
        .main-nav a:hover {
            text-decoration: underline;
        }
        .profile { background: #fff; border: 1px solid #ccc; padding: 20px; width: 420px; margin: 40px auto; border-radius: 8px; }
        .friends-list { margin-top: 25px; }
        .friends-list ul { padding-left: 20px; }
        .friend-link { margin-right: 6px; }
        .msg-btn { font-size: 0.9em; margin-left: 7px; }
        .toggle-form { margin-bottom: 10px; }
    </style>

    </head>
<body>

<nav class="main-nav">
    <a href="/index.php">Home</a>
    <?php if ($selfUser): ?>
        <a href="inbox.php">Inbox</a>
    <?php endif; ?>
</nav>

<div class="profile">
    <h2><?=htmlspecialchars($user['first_name'] . ' ' . $user['last_name'])?></h2>
    <p>Email: <?=htmlspecialchars($user['email'])?></p>
    <p>Age: <?=htmlspecialchars($user['age'] ?? '')?></p>
    <p>Gender: <?=htmlspecialchars($user['gender'] ?? '')?></p>

    <?php if ($isOwner): ?>
        <form method="POST" class="toggle-form">
            <button name="toggle_friends" type="submit">
                <?= $showFriends ? 'Hide Friends List' : 'Show Friends List' ?>
            </button>
        </form>
    <?php endif; ?>

    <?php if ($showFriends || $isOwner): ?>
    <div class="friends-list">
        <h3>Friends</h3>
        <?php if (!empty($user['friends'])): ?>
        <ul>
            <?php foreach ($user['friends'] as $friend):
                $friendDetails = get_friend_details($friend);
            ?>
            <li>
                <a class="friend-link" href="profile.php?user=<?=urlencode($friend)?>">
                    <?= $friendDetails['display']; ?> (<?= $friendDetails['nickname']; ?>)
                </a>
                <?php if ($selfUser && $selfUser !== $friend): ?>
                    <a class="msg-btn" href="messages/message.php?user=<?=urlencode($friend)?>">Message</a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
            <p>No friends yet.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div> </body>
</html>