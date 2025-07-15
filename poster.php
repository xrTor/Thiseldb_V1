<?php
include 'header.php';

function extractImdbId($input) {
  if (preg_match('/tt\d{7,8}/', $input, $matches)) return $matches[0];
  return '';
}
function extractLocalId($input) {
  if (preg_match('/poster\.php\?id=(\d+)/', $input, $matches)) return (int)$matches[1];
  return 0;
}
function extractYoutubeId($url) {
  if (preg_match('/(?:v=|\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) return $matches[1];
  return '';
}

$conn = new mysqli('localhost', 'root', '123456', 'media');
if ($conn->connect_error) die("Connection failed");

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$result = $conn->query("SELECT * FROM posters WHERE id = $id");
if ($result->num_rows == 0) { echo "<p style='text-align:center;'>❌ פוסטר לא נמצא</p>"; exit; }
$row = $result->fetch_assoc();
$video_id = extractYoutubeId($row['youtube_trailer'] ?? '');
$message = '';

session_start();
$visitor_token = session_id();

$vote_row = $conn->query("
  SELECT vote_type FROM poster_votes 
  WHERE poster_id = $id AND visitor_token = '$visitor_token'
");
$user_vote = $vote_row->num_rows ? $vote_row->fetch_assoc()['vote_type'] : '';

if (isset($_POST['vote'])) {
  $vote = $_POST['vote'];

  if ($vote === 'remove') {
    $conn->query("DELETE FROM poster_votes WHERE poster_id=$id AND visitor_token='$visitor_token'");
    $user_vote = '';
  } elseif (in_array($vote, ['like','dislike'])) {
    if ($user_vote === '') {
      $stmt = $conn->prepare("INSERT INTO poster_votes (poster_id, visitor_token, vote_type) VALUES (?, ?, ?)");
      $stmt->bind_param("iss", $id, $visitor_token, $vote); $stmt->execute(); $stmt->close();
    } else {
      $stmt = $conn->prepare("UPDATE poster_votes SET vote_type=? WHERE poster_id=? AND visitor_token=?");
      $stmt->bind_param("sis", $vote, $id, $visitor_token); $stmt->execute(); $stmt->close();
    }
    $user_vote = $vote;
  }
}

$likes = $conn->query("
  SELECT COUNT(*) as c FROM poster_votes 
  WHERE poster_id=$id AND vote_type='like'
")->fetch_assoc()['c'];

$dislikes = $conn->query("
  SELECT COUNT(*) as c FROM poster_votes 
  WHERE poster_id=$id AND vote_type='dislike'
")->fetch_assoc()['c'];


// פעולות: תגיות, סרטים דומים
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (isset($_POST['add_user_tags'])) {
    $g = trim($_POST['user_tags'] ?? '');
    if ($g !== '') {
      $stmt = $conn->prepare("INSERT INTO user_tags (poster_id, genre) VALUES (?, ?)");
      $stmt->bind_param("is", $id, $g); $stmt->execute(); $stmt->close();
      $message = "✅ תגית נוספה";
    }
  }

  if (isset($_POST['remove_user_tags'])) {
    $gid = (int)$_POST['remove_user_tags'];
    $conn->query("DELETE FROM user_tags WHERE id=$gid AND poster_id=$id");
    $message = "🗑️ תגית נמחקה";
  }

  if (isset($_POST['add_similar'])) {
    $input = trim($_POST['similar_input'] ?? '');
    $target_id = 0;
    if (is_numeric($input)) $target_id = (int)$input;
    elseif ($local = extractLocalId($input)) $target_id = $local;
    elseif ($imdb = extractImdbId($input)) {
      $stmt = $conn->prepare("SELECT id FROM posters WHERE imdb_id = ?");
      $stmt->bind_param("s", $imdb); $stmt->execute();
      $res = $stmt->get_result();
      if ($r = $res->fetch_assoc()) $target_id = $r['id'];
      $stmt->close();
    }
    if ($target_id > 0 && $target_id != $id) {
      $check = $conn->prepare("SELECT 1 FROM poster_similar WHERE poster_id=? AND similar_id=?");
      $check->bind_param("ii", $id, $target_id); $check->execute(); $check->store_result();
      if ($check->num_rows == 0) {
        $conn->query("INSERT INTO poster_similar (poster_id, similar_id) VALUES ($id, $target_id)");
        $conn->query("INSERT INTO poster_similar (poster_id, similar_id) VALUES ($target_id, $id)");
        $message = "✅ סרט דומה נוסף";
      } else $message = "⚠️ הקשר כבר קיים";
      $check->close();
    } else $message = "❌ הסרט לא נמצא";
  }

  if (isset($_POST['remove_similar'])) {
    $sid = (int)$_POST['remove_similar'];
    $conn->query("DELETE FROM poster_similar WHERE poster_id=$id AND similar_id=$sid");
    $conn->query("DELETE FROM poster_similar WHERE poster_id=$sid AND similar_id=$id");
    $message = "🗑️ הקשר נמחק";
  }
}

$similar = [];
$res = $conn->query("SELECT p.* FROM poster_similar ps JOIN posters p ON p.id=ps.similar_id WHERE ps.poster_id=$id");
while ($r = $res->fetch_assoc()) $similar[] = $r;
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($row['title_en']) ?></title>
  <link rel="stylesheet" href="style.css">
  <style>
    body.rtl { direction: rtl; font-family: Arial; background:#f1f1f1; padding:40px; }
    .poster-page {
      max-width:800px; margin:auto;
      background:#fff; padding:20px;
      border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.1);
      
    }
    .poster-image {
      width:200px; float:right; margin-left:20px;
      border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.08);
      
    }
    .poster-details { overflow:hidden; }
    .tag {
      background:#eee; padding:6px 12px; margin:4px;
      display:inline-block; border-radius:12px;
      font-size:13px; text-decoration:none; color:#333;
    }
    button.like-button {
  cursor: pointer;
}

  </style>
</head>
<body class="rtl">

<div class="poster-page">

  <!-- 🚨 דיווח על תקלה -->
  <div style="text-align:left; margin-bottom:10px;">
    <a href="report.php?poster_id=<?= $row['id'] ?>" style="background:#ffdddd; color:#a00; padding:6px 12px; border-radius:6px; font-weight:bold; text-decoration:none;">🚨 דווח על תקלה בפוסטר</a>
  </div>

  <form method="post" style="margin-top:30px;">
  <button type="submit" name="vote" class="like-button" value="like"
    style="background:<?= $user_vote === 'like' ? '#28a745' : '#ccc' ?>; color:white; padding:10px 16px; border:none; border-radius:6px;">
    ❤️ אהבתי (<?= $likes ?>)
  </button>

  <button type="submit" name="vote" class="like-button" value="dislike"
    style="background:<?= $user_vote === 'dislike' ? '#dc3545' : '#ccc' ?>; color:white; padding:10px 16px; border:none; border-radius:6px; margin-right:10px;">
    💔 לא אהבתי (<?= $dislikes ?>)
  </button>

  <?php if ($user_vote): ?>
    <button type="submit" name="vote" class="like-button" value="remove"
      style="background:#666; color:white; padding:10px 16px; border:none; border-radius:6px; margin-right:10px;">
      ❌ בטל הצבעה
    </button>
  <?php endif; ?>
</form>

  <?php if ($message): ?>
    <p style="background:#ffe; border:1px solid #cc9; padding:10px; border-radius:6px; color:#444; font-weight:bold;">
      <?= $message ?>
    </p>
  <?php endif; ?>

  <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="Poster" class="poster-image">
  <div class="poster-details">
    <h2>
      <?= htmlspecialchars($row['title_en']) ?>
      <?php if (!empty($row['title_he'])): ?><br><?= htmlspecialchars($row['title_he']) ?><?php endif; ?>
    </h2>

    <?php if ($row['is_dubbed'] || $row['has_subtitles']): ?>
      <p>
        <?php if ($row['is_dubbed']): ?>🎙️ מדובב<br><?php endif; ?>
        <?php if ($row['has_subtitles']): ?>📝 כולל כתוביות<?php endif; ?>
      </p>
    <?php endif; ?>

 <?php
switch ($row['type'] ?? '') {
  case 'series':
    $type_label = '📺 סדרה';
    break;
  case 'movie':
    $type_label = '🎬 סרט';
    break;
  case 'short':
    $type_label = '🎞️ סרט קצר';
    break;
  case 'miniseries':
    $type_label = '📺 מיני-סדרה';
    break;
  default:
    $type_label = '❓ לא ידוע';
}
?>

<p><strong>🗓️ שנה:</strong> <?= htmlspecialchars($row['year']) ?></p>
<p><strong>🎞️ סוג:</strong> <?= $type_label ?></p>
<p><strong>⭐ IMDb:</strong> <?= $row['imdb_rating'] ? htmlspecialchars($row['imdb_rating']) . ' / 10' : 'לא זמין' ?></p>

    <p><strong>🔤 IMDb ID:</strong> <?= htmlspecialchars($row['imdb_id']) ?></p>

    <?php if (!empty($row['tvdb_id'])): ?>
      <p><strong>🛰️ TVDB:</strong>
        <a href="https://thetvdb.com/?id=<?= htmlspecialchars($row['tvdb_id']) ?>" target="_blank" class="tag">
          <?= htmlspecialchars($row['tvdb_id']) ?>
        </a>
      </p>
    <?php endif; ?>

    <?php if (!empty($row['user_rating'])): ?>
      <p><strong>🧑‍⚖️ דירוג קהילתי:</strong> <?= htmlspecialchars($row['user_rating']) ?>/10</p>
    <?php endif; ?>

    <?php if (!empty($row['plot'])): ?>
      <p><strong>📝 תקציר:</strong><br><?= nl2br(htmlspecialchars($row['plot'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($row['collection_name'])): ?>
  <p><strong>📦 אוסף:</strong>
    <a href="collection.php?name=<?= urlencode($row['collection_name']) ?>" class="tag">
      <?= htmlspecialchars($row['collection_name']) ?>
    </a>
  </p>
<?php endif; ?>

    <?php if (!empty($row['collection_name'])): ?>
      <p><strong>📦 אוסף:</strong>
        <a href="collection.php?name=<?= urlencode($row['collection_name']) ?>" class="tag">
          <?= htmlspecialchars($row['collection_name']) ?>
        </a>
      </p>
    <?php endif; ?>

    <p><strong>🌐 שפת מקור:</strong><br>
      <?php
      $lang_result = $conn->query("SELECT lang_code FROM poster_languages WHERE poster_id = $id");
      if ($lang_result->num_rows > 0):
        while ($l = $lang_result->fetch_assoc()):
          $lang_code = $l['lang_code'];
          echo "<a href='language.php?lang_code=" . urlencode($lang_code) . "' class='tag'>" . htmlspecialchars($lang_code) . "</a> ";
        endwhile;
      else:
        echo "<span style='color:#999;'>אין שפות נוספות</span>";
      endif;
      ?>
    </p>

    <?php if (!empty($row['genre'])):
      $genres = explode(',', $row['genre']);
      echo "<p><strong>🎭 ז׳אנר:</strong><br>";
      foreach ($genres as $g):
        $g_clean = trim($g); ?>
        <a href="genre.php?name=<?= urlencode($g_clean) ?>" class="tag"><?= htmlspecialchars($g_clean) ?></a>
      <?php endforeach;
      echo "</p>";
    endif; ?>

    <?php if (!empty($row['actors'])):
      echo "<p><strong>👥 שחקנים:</strong><br>";
      foreach (explode(',', $row['actors']) as $a):
        $a_clean = trim($a); ?>
        <a href="actor.php?name=<?= urlencode($a_clean) ?>" class="tag"><?= htmlspecialchars($a_clean) ?></a>
      <?php endforeach;
      echo "</p>";
    endif; ?>
    <!-- 🔗 קישורים חיצוניים -->
    <?php if (!empty($row['imdb_link'])): ?>
      <p><strong>🔗 IMDb:</strong>
        <a href="<?= htmlspecialchars($row['imdb_link']) ?>" target="_blank" class="tag">מעבר לקישור</a>
      </p>
    <?php endif; ?>
    <?php if (!empty($row['rt_score'])): ?>
      <p><strong>🍅 Rotten Tomatoes:</strong> <?= htmlspecialchars($row['rt_score']) ?></p>
    <?php endif; ?>
    <?php if (!empty($row['rt_link'])): ?>
      <p><strong>🔗 RT:</strong>
        <a href="<?= htmlspecialchars($row['rt_link']) ?>" target="_blank" class="tag">צפייה באתר</a>
      </p>
    <?php endif; ?>
    <?php if (!empty($row['metacritic_score'])): ?>
      <p><strong>📊 Metacritic:</strong> <?= htmlspecialchars($row['metacritic_score']) ?></p>
    <?php endif; ?>
    <?php if (!empty($row['metacritic_link'])): ?>
      <p><strong>🔗 Metacritic:</strong>
        <a href="<?= htmlspecialchars($row['metacritic_link']) ?>" target="_blank" class="tag">צפייה באתר</a>
      </p>
    <?php endif; ?>

    <!-- 🎞️ טריילר מוטמע -->
    <?php if ($video_id): ?>
      <div style="margin-top:30px; text-align:center;">
        <h3>🎞️ טריילר</h3>
        <iframe width="100%" height="315"
          src="https://www.youtube.com/embed/<?= htmlspecialchars($video_id) ?>"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen loading="lazy"></iframe>
      </div>
    <?php else: ?>
      <div style="margin-top:30px; text-align:center; color:#888;">
        <h3>🎞️ טריילר</h3>
        <p>אין טריילר זמין כרגע 😢</p>
      </div>
    <?php endif; ?>

    <!-- 📝 תגיות קהילתיות -->
    <?php
    $res_user = $conn->query("SELECT id, genre FROM user_tags WHERE poster_id = $id");
    if ($res_user->num_rows > 0): ?>
      <p><strong>📝 תגיות משתמשים:</strong><br>
        <?php while ($g = $res_user->fetch_assoc()):
          $g_clean = trim($g['genre']); ?>
          <form method="post" style="display:inline;">
            <a href="user_tags.php?name=<?= urlencode($g_clean) ?>" class="tag"><?= htmlspecialchars($g_clean) ?></a>
            <button type="submit" name="remove_user_tags" value="<?= $g['id'] ?>"
              style="border:none; background:none; color:#900; cursor:pointer;">🗑️</button>
          </form>
        <?php endwhile; ?>
      </p>
    <?php endif; ?>

    <!-- ➕ טופס להוספת תגית -->
    <form method="post" style="margin-bottom:20px;">
      <input type="text" name="user_tags" placeholder="הוסף תגית" required>
      <button type="submit" name="add_user_tags">➕ הוסף</button>
    </form>

    <!-- 🎬 סרטים דומים -->
    <hr>
    <h3>🎬 סרטים דומים:</h3>
    <?php if ($similar): ?>
      <div style="display:flex; flex-wrap:wrap; gap:16px;">
        <?php foreach ($similar as $sim): ?>
          <div style="width:100px; text-align:center;">
            <form method="post">
              <a href="poster.php?id=<?= $sim['id'] ?>">
                <img src="<?= htmlspecialchars($sim['image_url']) ?>" style="width:100px; border-radius:6px;"><br>
                <small><?= htmlspecialchars($sim['title_en']) ?></small>
              </a><br>
              <button type="submit" name="remove_similar" value="<?= $sim['id'] ?>">🗑️</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color:#888;">אין סרטים דומים כרגע</p>
    <?php endif; ?>

    <!-- ➕ טופס סרט דומה -->
    <h3>➕ הוסף סרט דומה</h3>
    <form method="post">
      <input type="text" name="similar_input" placeholder="מזהה פנימי, tt1234567 או קישור" required>
      <button type="submit" name="add_similar">📥 קישור</button>
    </form>

    <!-- 🎛 פעולות מערכת -->
    <div class="actions" style="margin-top:20px;">
      <a href="edit.php?id=<?= $row['id'] ?>">✏️ ערוך</a> |
      <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('למחוק את הפוסטר?')">🗑️ מחק</a> |
      <a href="index.php">⬅ חזרה</a>
    </div>

  </div> <!-- .poster-details -->
</div> <!-- .poster-page -->

</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>
