<?php
session_start();
$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Homepage</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .container { max-width: 400px; margin: auto; }
        input, select, textarea { width: 100%; margin-bottom: 10px; padding: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 8px; }
        .error { color: red; }
        .success { color: green; }
        .search-results { border: 1px solid #ccc; margin-bottom: 10px; }
        .search-person { padding: 8px; border-bottom: 1px solid #eee; }
        .search-person:last-child { border-bottom: none; }
        .nav-links { margin-bottom: 14px; }
        .nav-links a, .nav-links form { display: inline-block; margin-right: 10px; }
        .nav-links form { margin: 0; }
    </style>
    <script>
    function ajax(url, data, cb, method="POST") {
        var xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.onload = function () { cb(xhr); };
        if (method === "POST") xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(method === "POST" ? data : null);
    }
    function showPassword(id) {
        var pw = document.getElementById(id);
        pw.type = pw.type === 'password' ? 'text' : 'password';
    }
    function register(e) {
        e.preventDefault();
        var f = document.getElementById('registerForm');
        var data = Array.from(new FormData(f)).map(pair => encodeURIComponent(pair[0]) + "=" + encodeURIComponent(pair[1])).join("&");
        ajax('/php/register.php', data, function(xhr) {
            if (xhr.status == 201) {
                document.getElementById('registerMsg').innerHTML = "<span class='success'>Registration successful, you can now log in.</span>";
                f.reset();
            } else if (xhr.status == 409) {
                document.getElementById('registerMsg').innerHTML = "<span class='error'>User already exists or passwords don't match.</span>";
            } else {
                document.getElementById('registerMsg').innerHTML = "<span class='error'>Registration failed. " + xhr.responseText + "</span>";
            }
        });
    }
    function login(e) {
        e.preventDefault();
        var f = document.getElementById('loginForm');
        var data = Array.from(new FormData(f)).map(pair => encodeURIComponent(pair[0]) + "=" + encodeURIComponent(pair[1])).join("&");
        ajax('/php/login.php', data, function(xhr) {
            if (xhr.status == 200) {
                location.reload();
            } else {
                document.getElementById('loginMsg').innerHTML = "<span class='error'>Login failed.</span>";
            }
        });
    }
    function updateProfile(e) {
        e.preventDefault();
        var f = document.getElementById('profileForm');
        var data = Array.from(new FormData(f)).map(pair => encodeURIComponent(pair[0]) + "=" + encodeURIComponent(pair[1])).join("&");
        ajax('/php/profile_update.php', data, function(xhr) {
            if (xhr.status == 200) {
                document.getElementById('profileMsg').innerHTML = "<span class='success'>Profile updated!</span>";
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                document.getElementById('profileMsg').innerHTML = "<span class='error'>Update failed.</span>";
            }
        });
    }
    function searchUsers(e) {
        var q = document.getElementById('searchInput').value.trim();
        if (!q) { document.getElementById('searchResults').innerHTML = ''; return; }
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "/php/search_users.php?q=" + encodeURIComponent(q));
        xhr.onload = function () {
            var out = '';
            if (xhr.status == 200) {
                var arr = JSON.parse(xhr.responseText);
                if(arr.length === 0) out = "<div class='search-person'>No users found.</div>";
                arr.forEach(function(u){
                    var profileUrl = "/profile.php?user=" + encodeURIComponent(u.username);
                    out += "<div class='search-person'><b>" + u.first_name + " " + u.last_name +
                        "</b> (" + u.email + ") <a href='"+profileUrl+"' target='_blank'>View</a> " +
                        "<button onclick=\"friendUser('" + u.username + "', this)\">Add Friend</button></div>";
                });
            }
            document.getElementById('searchResults').innerHTML = out;
        };
        xhr.send();
    }
    function friendUser(username, btn) {
        ajax('/php/friend.php', "username=" + encodeURIComponent(username), function(xhr){
            if (xhr.status == 200) {
                btn.disabled = true;
                btn.textContent = "Friended!";
            } else {
                btn.textContent = "Error";
            }
        });
    }
    </script>
</head>
<body>
<div class="container">
<?php if ($user): ?>
    <div class="nav-links">
        <a href="profile.php?user=<?= urlencode($user['username']) ?>">View Profile</a>
        <a href="inbox.php">Inbox</a>
        <form method="post" action="/php/logout.php" style="display:inline;">
            <button type="submit">Logout</button>
        </form>
    </div>
    <h2>Welcome, <?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?>!</h2>
    <p>Email: <?= htmlspecialchars($user['email']) ?></p>
    <form id="profileForm" onsubmit="updateProfile(event)">
        <input name="age" type="number" required placeholder="Age" value="<?= htmlspecialchars($user['age']) ?>"><br>
        <select name="gender" required>
            <option value="">Gender</option>
            <option <?= ($user['gender'] ?? '') =='Male'?'selected':'' ?>>Male</option>
            <option <?= ($user['gender'] ?? '') =='Female'?'selected':'' ?>>Female</option>
            <option <?= ($user['gender'] ?? '') =='Other'?'selected':'' ?>>Other</option>
        </select><br>
        <button type="submit">Update Profile</button>
        <div id="profileMsg"></div>
    </form>
    <h3>Search for people</h3>
    <input id="searchInput" type="text" placeholder="Search by name or email" onkeyup="searchUsers(event)">
    <div id="searchResults" class="search-results"></div>

    <?php if (isset($user['friends']) && is_array($user['friends']) && count($user['friends'])): ?>
        <h3>Your Friends</h3>
        <ul>
        <?php foreach ($user['friends'] as $f): ?>
            <li>
                <a href="profile.php?user=<?= urlencode($f) ?>">
                    <?= htmlspecialchars($f) ?>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php else: ?>
    <h2>Login</h2>
    <form id="loginForm" onsubmit="login(event)">
        <input name="email" type="email" required placeholder="Email"><br>
        <input name="password" type="password" id="loginpw" required placeholder="Password">
        <input type="checkbox" onclick="showPassword('loginpw')"> Show Password<br>
        <button type="submit">Login</button>
        <div id="loginMsg" class="error"></div>
    </form>
    <h2>Register</h2>
    <form id="registerForm" onsubmit="register(event)">
        <input name="email" type="email" required placeholder="Email"><br>
        <input name="first_name" required placeholder="First Name"><br>
        <input name="last_name" required placeholder="Last Name"><br>
        <input name="username" required placeholder="Username" pattern="[a-zA-Z0-9_]+"><br>
        <input name="password" type="password" id="pw" required placeholder="Password">
        <input type="checkbox" onclick="showPassword('pw')"> Show Password<br>
        <input name="confirm_password" type="password" required placeholder="Confirm Password"><br>
        <input name="age" type="number" required placeholder="Age"><br>
        <select name="gender" required>
            <option value="">Gender</option>
            <option>Male</option>
            <option>Female</option>
            <option>Other</option>
        </select><br>
        <button type="submit">Register</button>
        <div id="registerMsg"></div>
    </form>
<?php endif; ?>
</div> </body>
</html>
