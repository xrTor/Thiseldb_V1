<?php require_once 'server.php';?>
<?php include 'header.php'; ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body class="rtl">

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>אודות האתר</title>
  <link rel="stylesheet" href="style.css"> <!-- אם יש לך קובץ עיצוב קיים -->
  <style>
    .about-wrapper {
      max-width: 900px;
      margin: 50px auto;
      padding: 30px;
      font-family: sans-serif;
      background: #f9f9f9;
      border-radius: 10px;
      box-shadow: 0 0 8px rgba(0,0,0,0.05);
    }
    .about-wrapper h2 {
      text-align: right;
      font-size: 28px;
      margin-bottom: 20px;
      color: #444;
    }
    .about-section {
      margin-bottom: 30px;
    }
    .about-section h3 {
      font-size: 20px;
      margin-bottom: 10px;
      color: #5CABED;
    }
    .about-section p, .about-section li {
      font-size: 15px;
      color: #333;
      line-height: 1.6;
    }
    .about-section ul {
      padding-right: 20px;
    }
    body, div {
  direction: rtl;
  text-align: right;
}
a {color: #2d89ef !important;}
  </style>
</head>
<body>
<div class="about-wrapper">
  <h2>על האתר Thiseldb 🎬</h2>

<img src="images/logo.png" height="240px"><br>
  <div class="about-section">

<h3><b><u>כלים:</u></h3></b>
<a href="https://www.omdbapi.com">פרוייקט API של omdbapi </a><br>
<a href="https://github.com/FabianBeiner/PHP-IMDB-Grabber
">PHP-IMDB-Grabber</a><br>
<br>
<h3><b><u>הצעות לשיפור ודיווח על באגים:</u></h3></b>
אם חשבתם על דרך לשפר תאתר או נתקלתם בשגיאה או באג או נתון לא נכון, בעמודי הפוסטרים השתמשו בכפתור ה'דיווח', לכל פנייה אחרת נא שלחו טופס דרך עמוד '<a href="contact.php">צור קשר</a>'.<br>
תהנו : )
</div>
</div>

</body>
</html>
<?php include 'footer.php'; ?>