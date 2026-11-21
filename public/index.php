<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ScholarDesk | Student & Task Management</title><link rel="icon" href="assets/icon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css"><script src="assets/app.js" defer></script></head>
<body>
<aside class="sidebar">
 <a class="brand" href="#dashboard"><span class="brand-icon">S</span><span>ScholarDesk<small>ACADEMIC WORKSPACE</small></span></a>
 <p class="nav-label">WORKSPACE</p>
 <nav aria-label="Main navigation">
  <a href="#dashboard" data-page="dashboard"><span aria-hidden="true">◫</span> Overview</a>
  <a href="#students" data-page="students"><span aria-hidden="true">♙</span> Students <b id="nav-students">0</b></a>
  <a href="#tasks" data-page="tasks"><span aria-hidden="true">▤</span> Academic tasks</a>
  <a href="#submissions" data-page="submissions"><span aria-hidden="true">✓</span> Submissions</a>
 </nav>
 <div class="sidebar-note"><span class="online-dot"></span> Running on your laptop<p>Your records stay in this local database.</p></div>
 <div class="sidebar-footer"><span class="avatar">CS</span><div>CS0053 Final Project<small>Student & Task Management</small></div></div>
</aside>
<main>
 <header class="topbar"><span>Workspace <span class="slash">/</span> <strong id="breadcrumb">Overview</strong></span><span class="local-badge">LOCAL EDITION</span></header>
 <div class="page-content">
  <div class="page-heading"><div><div class="eyebrow">A LITTLE STRUCTURE. MORE PROGRESS.</div><h1 id="page-title">Your academic overview</h1><p id="page-description">A clear view of your students, tasks, and what comes next.</p></div><button id="primary-action" class="button primary">+ Add student</button></div>
  <div id="notice" class="notice" role="status" hidden></div>
  <section id="view" aria-label="Workspace content"><p>Loading your workspace…</p></section>
  <footer class="page-footer"><span>ScholarDesk · Built for a clearer school day.</span><span id="today"></span></footer>
 </div>
</main>
<dialog id="editor"><form id="record-form"><div class="dialog-heading"><div><div class="eyebrow">YOUR WORKSPACE</div><h2 id="editor-title">Add student</h2></div><button type="button" id="close-editor" class="icon-button" aria-label="Close dialog">×</button></div><div id="form-fields"></div><p id="form-error" class="form-error" role="alert"></p><div class="dialog-actions"><button type="button" id="cancel-editor" class="button secondary">Cancel</button><button type="submit" class="button primary" id="save-record">Save record</button></div></form></dialog>
<dialog id="confirmation"><div class="dialog-heading"><h2 id="confirm-title">Delete record?</h2></div><p id="confirm-message"></p><div class="dialog-actions"><button id="confirm-cancel" class="button secondary">Cancel</button><button id="confirm-yes" class="button danger">Delete record</button></div></dialog>
<noscript>Please enable JavaScript to use the student management interface.</noscript>
</body></html>
