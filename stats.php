<?php
include 'header.php';
require_once 'server.php';
 
// 🔢 סוגים
$count_series     = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE type='series'")->fetch_assoc()['c'];
$count_movies     = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE type='movie'")->fetch_assoc()['c'];
$count_miniseries = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE type='miniseries'")->fetch_assoc()['c'];
$count_shorts     = $conn->query("SELECT COUNT(*) AS c FROM posters WHERE type='short'")->fetch_assoc()['c'];

// ❤️ אהדה
$count_likes    = $conn->query("SELECT COUNT(*) AS c FROM poster_votes WHERE vote_type='like'")->fetch_assoc()['c'];
$count_dislikes = $conn->query("SELECT COUNT(*) AS c FROM poster_votes WHERE vote_type='dislike'")->fetch_assoc()['c'];
$total_votes = $count_likes + $count_dislikes;
$percent_likes = $total_votes ? round(($count_likes / $total_votes) * 100) : 0;
$percent_dislikes = $total_votes ? round(($count_dislikes / $total_votes) * 100) : 0;

// 🔝 אהובים
$top_posters = $conn->query("
  SELECT p.id, p.title_en,
    SUM(CASE WHEN pv.vote_type = 'like' THEN 1 ELSE 0 END) AS likes,
    SUM(CASE WHEN pv.vote_type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
  FROM posters p
  LEFT JOIN poster_votes pv ON pv.poster_id = p.id
  GROUP BY p.id
  ORDER BY likes DESC
  LIMIT 10
");

// 🌐 שפה + שנה
$languages = $conn->query("SELECT lang_code, COUNT(*) AS total FROM poster_languages GROUP BY lang_code ORDER BY total DESC");
$years = $conn->query("SELECT year, COUNT(*) AS total FROM posters WHERE year IS NOT NULL GROUP BY year ORDER BY year ASC");
$year_data = []; while ($row = $years->fetch_assoc()) $year_data[] = $row;

// 📦 אוספים
$count_collections = $conn->query("SELECT COUNT(*) AS c FROM collections")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>📊 סטטיסטיקות כלליות</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: Arial; background:#f4f4f4; padding:40px; text-align:center; direction:rtl; max-width:1000px; margin:auto; }
    h1, h2, h3 { margin-bottom:20px; }
    .box {
      background:#fff; padding:30px; border-radius:10px;
      box-shadow:0 0 8px rgba(0,0,0,0.1); margin:30px 0;
    }
    table { width:100%; border-collapse:collapse; background:white; margin-top:20px; }
    th, td { padding:12px; border-bottom:1px solid #ccc; text-align:right; }
    th { background:#eee; }
    canvas { max-width:500px; margin:20px auto; }
    .bar { height:20px; background:#ddd; border-radius:10px; overflow:hidden; margin:10px 0; }
    .bar-inner { height:100%; text-align:right; color:#fff; padding-right:10px; line-height:20px; font-size:13px; }
    .like-bar { background:#28a745; width:<?= $percent_likes ?>%; }
    .dislike-bar { background:#dc3545; width:<?= $percent_dislikes ?>%; }
    a { color:#007bff; text-decoration:none; }
    a:hover { text-decoration:underline; }
    
  </style>
</head>
<body>

<h1>📊 סטטיסטיקות כלליות</h1>

<div class="box">
  <h2>🔢 לפי סוג</h2>
  <p>📺 סדרות: <strong><?= $count_series ?></strong></p>
  <p>🎬 סרטים: <strong><?= $count_movies ?></strong></p>
  <p>📺 מיני־סדרות: <strong><?= $count_miniseries ?></strong></p>
  <p>🎞️ סרטים קצרים: <strong><?= $count_shorts ?></strong></p>
  <p>📦 אוספים קיימים: <strong><?= $count_collections ?></strong></p>
</div>

<div class="box">
  <h2>❤️ אהדה כללית</h2>
  <canvas id="voteChart"></canvas>
  <p>❤️ אהבתי: <?= $count_likes ?> (<?= $percent_likes ?>%)</p>
  <div class="bar"><div class="bar-inner like-bar"><?= $percent_likes ?>%</div></div>
  <p>💔 לא אהבתי: <?= $count_dislikes ?> (<?= $percent_dislikes ?>%)</p>
  <div class="bar"><div class="bar-inner dislike-bar"><?= $percent_dislikes ?>%</div></div>
  <p>סה"כ הצבעות: <strong><?= $total_votes ?></strong></p>
</div>
<div class="box">
  <h2>🔥 עשרת הפוסטרים הכי אהובים</h2>
  <table>
    <tr><th>שם פוסטר</th><th>❤️ אהבתי</th><th>💔 לא אהבתי</th></tr>
    <?php while ($row = $top_posters->fetch_assoc()): ?>
      <tr>
        <td><a href="poster.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['title_en']) ?></a></td>
        <td><?= $row['likes'] ?></td>
        <td><?= $row['dislikes'] ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<div class="box">
  <h2>🌐 פילוח לפי שפה</h2>
  <table>
    <tr><th>שפה</th><th>מספר פוסטרים</th></tr>
    <?php while ($row = $languages->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['lang_code']) ?></td>
        <td><?= $row['total'] ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<div class="box">
  <h2>📅 פילוח לפי שנה</h2>
  <table>
    <tr><th>שנה</th><th>מספר פוסטרים</th></tr>
    <?php foreach ($year_data as $row): ?>
      <tr>
        <td><?= $row['year'] ?></td>
        <td><?= $row['total'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <canvas id="yearChart"></canvas>
</div>

<div class="box">
  <h2>📊 גרף התפלגות לפי סוג</h2>
  <canvas id="typeChart"></canvas>
</div>

<p><a href="index.php">⬅ חזרה לדף הבית</a></p>

<script>
  // גרף אהדה
  new Chart(document.getElementById('voteChart').getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: ['אהבתי', 'לא אהבתי'],
      datasets: [{
        data: [<?= $count_likes ?>, <?= $count_dislikes ?>],
        backgroundColor: ['#28a745', '#dc3545'],
        borderWidth: 1
      }]
    },
    options: {
      plugins: { legend: { position: 'bottom' } }
    }
  });

  // גרף לפי שנה
  const yearLabels = <?= json_encode(array_column($year_data, 'year')) ?>;
  const yearCounts = <?= json_encode(array_column($year_data, 'total')) ?>;
  new Chart(document.getElementById('yearChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: yearLabels,
      datasets: [{
        label: 'פוסטרים לפי שנה',
        data: yearCounts,
        backgroundColor: '#FF9800'
      }]
    },
    options: {
      scales: { y: { beginAtZero: true } },
      plugins: { legend: { display: false } }
    }
  });

  // גרף לפי סוג
  new Chart(document.getElementById('typeChart').getContext('2d'), {
    type: 'pie',
    data: {
      labels: ['🎬 סרטים', '📺 סדרות', '📺 מיני־סדרות', '🎞️ סרטים קצרים'],
      datasets: [{
        data: [<?= $count_movies ?>, <?= $count_series ?>, <?= $count_miniseries ?>, <?= $count_shorts ?>],
        backgroundColor: ['#4CAF50', '#2196F3', '#9C27B0', '#FF9800']
      }]
    },
    options: {
      plugins: { legend: { position: 'bottom' } }
    }
  });
</script>

<?php include 'footer.php'; ?>
</body>
</html>
