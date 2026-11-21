# ScholarDesk

Student Information and Task Management System for CS0053. A local PHP application using XAMPP PHP, PDO SQLite, and a plain JavaScript interface. No Composer, Node, internet connection, Apache service, or MySQL service is required for the default launcher.

## Run on this laptop

1. Open this project folder in File Explorer.
2. Double-click **Start ScholarDesk.cmd**.
3. Microsoft Edge opens (or your default browser if Edge is unavailable) http://127.0.0.1:8053/.
4. On a fresh database, use **Load demo records** to load fictional examples, or add your own records.
5. Use **Stop ScholarDesk.cmd** when finished. Closing the browser alone does not stop the server.

The launcher uses `C:\xampp\php\php.exe`. If XAMPP is elsewhere, edit `$phpPath` in `tools/serve.ps1`. PHP 8.2+ with PDO SQLite and mbstring is required. These extensions were verified on this laptop. The launcher runs PowerShell with RemoteSigned for that process only; it does not change the saved Windows execution policy.

## Features

- Create, view, edit, delete, and search student records.
- Create, view, edit, delete, and search academic tasks and deadlines.
- Automatically track every registered student against every task.
- Mark submissions submitted or pending; past pending deadlines display as overdue.
- View student, task, submitted, pending, overdue, and completion totals.
- JSON API with GET, POST, PUT, DELETE, validation, and session CSRF protection.
- Local persistent database, responsive interface, keyboard focus, and confirmation dialogs.

All tasks apply to all students. Adding a new student also makes existing tasks pending for that student. Pending totals include overdue work. Deadlines use Manila calendar dates and end at 11:59 PM. Submission monitoring records status and timestamp; file attachments are outside this project's scope.

## Database and backup

Records are saved in `data/scholardesk.sqlite`, outside the public web folder. Stop the application before copying that file as a backup. Keep the whole project folder when moving the app to another laptop. A source-only checkout starts with an empty database. Session files and databases are excluded from Git.

## Tests

Double-click **Run Tests.cmd** for PHP unit and integration checks. In a terminal opened in this project folder:

```powershell
& 'C:\xampp\php\php.exe' tests/unit.php
& 'C:\xampp\php\php.exe' tests/integration.php
python tests/http_test.py
```

The HTTP tests need Python 3.10+ (already installed on this laptop), start a temporary PHP server on a free loopback port, and use their own test database. They do not change the application database. Unit tests: 11 passed. Integration tests: 23 passed. HTTP checks: 27 passed. Real run logs are in `docs/evidence/`. See `docs/testing.html` for coverage and limitations.

Postman desktop also passed 15 assertions across requests 01–09 in local mode. The actual screenshot is in `docs/evidence/postman-run.jpg`. The cleanup DELETE requests were verified through direct HTTP checks; the group should run and capture the Postman DELETE requests for submission. A v3 local collection is available in `postman/collections/ScholarDesk - Local API Tests`; the JSON export in `docs` supports older imports.

## Source layout

```text
public/                 Web root: interface, static assets, API adapter
src/Domain/Rules.php     Student/task validation and status policy
src/Application/        Use cases: records, submission tracking, dashboard
src/Infrastructure/     PDO SQLite connection and schema
src/bootstrap.php       Namespace autoloader and Manila timezone
 tests/                 Unit, database integration, and HTTP checks
 tools/                 Local start/stop scripts
 docs/                  User manual, technical/API/test docs, demo guide
 data/                  Private local database and session files (ignored)
```

## Manual PHP / Apache option

From the project directory, run `C:\xampp\php\php.exe -S 127.0.0.1:8053 -t public`. Alternatively, configure an Apache virtual host whose **DocumentRoot is this project's public folder**, with PHP enabled. Use the host's base address in the Postman collection. Keep `data`, `src`, `tests`, and `.git` outside the web root. The default launcher already does this. Do not put the entire project under a broadly served htdocs folder without configuring the public-only web root.

## Documentation

Open `START HERE.html` for the documentation index. Includes User Manual, Technical Documentation, API Documentation, Test Documentation, and a demonstration / handover checklist. Print HTML documents from your browser if PDF copies are needed.

## Git and GitHub

This folder is its own Git repository, separate from other files on the laptop. The initial commits preserve tests written before the selected domain functions, their implementation, and later application development. The recorded history is actual assisted development; it does not represent five group members making those commits.

No GitHub remote has been created or uploaded. To publish after reviewing the files, create an empty repository in your own GitHub account, then run from this folder:

```text
git remote add origin YOUR_REPOSITORY_URL
git push -u origin codex/scholardesk
```

Replace `YOUR_REPOSITORY_URL` with the real URL. Do not include the local database or session files. After future changes, stage specific source files and make meaningful commits. Have each member commit their actual contribution from their own account. Enter the real GitHub link and member contributions before submission.

## Scope

Built for a local classroom demonstration on one laptop, using fictional records during testing. There is no login, role separation, grade computation, file upload, per-course enrollment, or internet deployment. The PHP development server listens only on 127.0.0.1. Add authentication, authorization, auditing, pagination, and deployment hardening before wider use. No production or load testing is claimed.
