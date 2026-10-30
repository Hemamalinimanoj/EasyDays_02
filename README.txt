EasyDays Survey - HTML + PHP + MySQL
====================================
Pages
  index.html   - survey page (give this link to people who fill the survey)
  admin.php    - CLIENT page: login, see all responses, summary charts, download CSV

Files
  style.css, admin.css  - design
  survey.js             - survey questions, navigation, and submission logic
  config.php            - database + client login settings (EDIT THE PASSWORD)
  db.php                - connects to MySQL, creates database + table automatically
  save.php              - receives the answers and saves them in MySQL
  join.php              - saves name/email/phone of people who say Yes in the "join us" popup (table "contacts", created automatically; see admin.php > Interested people)
  questions.php         - list of questions (used by admin page and CSV)

RUN ON XAMPP (your laptop)
1. Copy this project folder to C:\xampp\htdocs\easydays_survey\
2. Open XAMPP Control Panel > Start "Apache" and Start "MySQL".
3. Survey page :  http://localhost/easydays_survey/
4. Client page :  http://localhost/easydays_survey/admin.php
     Username: admin     Password: EasyDays@2026   (change it in config.php)
5. Database "easydays" and table "responses" are created automatically
   on the first submit. No need to create anything in phpMyAdmin.
   (You can view the data in phpMyAdmin > easydays > responses.)

IMPORTANT
- Open the survey through the URL above. Do NOT double-click index.html or use VS Code Live Server; PHP must run through Apache.
- Use the exact project folder above. If you have another folder named "easydays", do not overwrite it.
- If MySQL uses port 3307, change DB_PORT in config.php.
- If MySQL has a root password, put it in DB_PASS in config.php.

SHARE WITH CLIENT (online)
- Upload the folder to any PHP + MySQL hosting (Hostinger, GoDaddy, etc.).
- Create a database in the hosting panel and put its host/user/password/name
  in config.php (DB_HOST, DB_USER, DB_PASS, DB_NAME).
- Change ADMIN_PASS in config.php, then give the client the admin.php link.

DEPLOY ON RENDER (GitHub -> Render, database also on Render)
1. Push all files in this folder to a GitHub repo (files at the repo root, Dockerfile included).
2. Render > New > Postgres  -> create it. Copy its "Internal Database URL".
3. Render > New > Web Service -> pick the repo -> Language: Docker.
4. Environment variables:
     DATABASE_URL = (Internal Database URL from step 2)
     ADMIN_PASS   = (strong password for the client)
5. Create Web Service. Survey: https://<service>.onrender.com/   Admin: https://<service>.onrender.com/admin.php
   The table "responses" is created automatically on the first submit / first admin login.
On XAMPP nothing changes (DATABASE_URL is empty, so MySQL is used).
