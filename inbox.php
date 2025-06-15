<?php
session_start();
$selfUser = $_SESSION['user']['username'] ?? '';
if (!$selfUser) die("You must be logged in.");

$selfFile = __DIR__ . "/users/$selfUser/$selfUser.txt";
if (!file_exists($selfFile)) die("User profile missing.");
$selfData = json_decode(file_get_contents($selfFile), true);

$friends = $selfData['friends'] ?? [];

$msgDir = __DIR__ . "/messages";
$conversations = [];
if (is_dir($msgDir)) {
    foreach (scandir($msgDir) as $file) {
        if (strpos($file, $selfUser . "__") === 0 || strpos($file, "__" . $selfUser . ".txt") !== false) {
            $parts = explode("__", basename($file, ".txt"));
            if (count($parts) !== 2) continue;
            $friend = $parts[0] === $selfUser ? $parts[1] : $parts[0];
            $messages = json_decode(file_get_contents("$msgDir/$file"), true) ?: [];
            $lastMsg = end($messages) ?: null;
            $lastVisitFile = __DIR__ . "/users/$selfUser/inbox_$friend.txt";
            $lastVisit = file_exists($lastVisitFile) ? (int)file_get_contents($lastVisitFile) : 0;
            $unread = 0;
            foreach ($messages as $m) {
                if ($m['to'] === $selfUser && $m['time'] > $lastVisit) $unread++;
            }
            $conversations[$friend] = [
                "lastMsg" => $lastMsg,
                "unread" => $unread,
            ];
        }
    }
}
ksort($conversations);

function getUserFullName($username) {
    $file = __DIR__ . "/users/$username/$username.txt";
    if (file_exists($file)) {
        $u = json_decode(file_get_contents($file), true);
        if ($u) return htmlspecialchars($u['first_name'] . ' ' . $u['last_name']);
    }
    return htmlspecialchars($username);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inbox</title>
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
        .inbox-box { background: #fff; border:1px solid #ccc; padding:22px; width:500px; margin:40px auto; border-radius: 8px; }
        .conv-list { list-style:none; padding:0; }
        .conv-list li { padding:12px 0; border-bottom:1px solid #eee; }
        .friend-link { font-weight:bold; text-decoration:none; color:#246; }
        .preview { color:#555; font-size:0.95em; }
        .unread-dot { display:inline-block; background:red; border-radius:50%; width:10px; height:10px; margin-left:7px; }
        .actions { float: right; }
        .actions a, .actions button { font-size:0.9em; margin-left:10px; }
        #userSearchWrapper { margin-bottom: 20px; }
        #userSearchResults { border:1px solid #bbb; background:#fafafa; max-height:150px; overflow-y:auto; margin-top:4px; }
        .search-user-row { padding:6px 10px; cursor:pointer; border-bottom: 1px solid #eee; }
        .search-user-row:last-child { border-bottom: none; }
        .search-user-row:hover { background:#e6f3ff; }
        .search-user-nickname { color: #888; font-size: 0.95em; }
        .delete-thread-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
        }
        .delete-thread-btn:hover {
            background-color: #d32f2f;
        }
    </style>
    <script>
    function searchUsersInbox() {
        var q = document.getElementById('userSearchInput').value.trim();
        var resultsDiv = document.getElementById('userSearchResults');
        if (!q) { resultsDiv.innerHTML = ''; return; }
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "/php/search_users.php?q=" + encodeURIComponent(q));
        xhr.onload = function () {
            resultsDiv.innerHTML = '';
            if (xhr.status == 200) {
                var arr = JSON.parse(xhr.responseText);
                if(arr.length === 0) {
                    resultsDiv.innerHTML = "<div class='search-user-row'>No users found.</div>";
                } else {
                    arr.forEach(function(u){
                        var label = "<b>"+u.first_name+" "+u.last_name+"</b> <span class='search-user-nickname'>("+u.username+")</span>";
                        var row = document.createElement('div');
                        row.className = 'search-user-row';
                        row.innerHTML = label;
                        row.onclick = function() {
                            window.location = "/messages/message.php?user=" + encodeURIComponent(u.username);
                        }
                        resultsDiv.appendChild(row);
                    });
                }
            } else {
                resultsDiv.innerHTML = "<div class='search-user-row'>Search error.</div>";
            }
        };
        xhr.send();
    }
    function deleteThread(buttonEl) {
        const friendUser = buttonEl.getAttribute('data-friend');
        if (!confirm('Are you sure you want to permanently delete your entire conversation with ' + friendUser + '? This cannot be undone.')) {
            return;
        }
        const formData = new FormData();
        formData.append('friend_user', friendUser);
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "/php/delete_thread.php", true);
        xhr.onload = function() {
            if (xhr.status == 200) {
                const convoLi = buttonEl.closest('li');
                convoLi.style.transition = 'opacity 0.5s';
                convoLi.style.opacity = '0';
                setTimeout(() => convoLi.remove(), 500);
            } else {
                alert("Error: Could not delete conversation. " + (xhr.responseText || ''));
            }
        };
        xhr.send(new URLSearchParams(formData));
    }
    </script>
</head>
<body>

<nav class="main-nav">
    <a href="/index.php">Home</a>
    <a href="profile.php?user=<?=urlencode($selfUser)?>">My Profile</a>
</nav>

<div class="inbox-box">
    <h2>Inbox</h2>
    <div id="userSearchWrapper">
        <input id="userSearchInput" type="text" placeholder="Search users to message..." onkeyup="searchUsersInbox()" autocomplete="off" />
        <div id="userSearchResults"></div>
    </div>
    <?php if (empty($conversations)): ?>
        <p>No active conversations yet.</p>
    <?php else: ?>
        <ul class="conv-list">
        <?php foreach ($conversations as $friend => $info): ?>
            <li>
                <div class="actions">
                    <a href="/messages/message.php?user=<?=urlencode($friend)?>">Message</a>
                    <button class="delete-thread-btn" title="Delete Conversation" data-friend="<?=htmlspecialchars($friend)?>" onclick="deleteThread(this)">Delete</button>
                </div>
                <a class="friend-link" href="/messages/message.php?user=<?=urlencode($friend)?>">
                    <?=getUserFullName($friend)?> (<?=htmlspecialchars($friend)?>)
                    <?php if ($info['unread']): ?>
                        <span class="unread-dot" title="<?= $info['unread'] ?> unread"></span>
                    <?php endif; ?>
                </a>
                <div class="preview">
                    <?php if ($info['lastMsg']): ?>
                        <span><?=htmlspecialchars($info['lastMsg']['from'])?>:
                        <?=htmlspecialchars(mb_strimwidth($info['lastMsg']['body'],0,50,'…'))?>
                        </span>
                        <span style="float:right; color:#aaa;">
                            <?=date('Y-m-d H:i', $info['lastMsg']['time'])?>
                        </span>
                    <?php else: ?>
                        <span>No messages yet.</span>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</div>
</body>
</html>