<?php
declare(strict_types=1);
namespace ScholarDesk\Infrastructure;
use PDO;

final class Database
{
    public static function connect(?string $path = null): PDO
    {
        $path ??= getenv('SCHOLARDESK_DB') ?: dirname(__DIR__, 2) . '/data/scholardesk.sqlite';
        $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        $db->exec('CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_number TEXT NOT NULL COLLATE NOCASE UNIQUE,
            full_name TEXT NOT NULL, email TEXT NOT NULL COLLATE NOCASE UNIQUE,
            course TEXT NOT NULL, year_level INTEGER NOT NULL CHECK(year_level BETWEEN 1 AND 6), section TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, subject TEXT NOT NULL,
            due_date TEXT NOT NULL, description TEXT NOT NULL DEFAULT \'\', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS submissions (
            student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
            task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            submitted_at TEXT NOT NULL, PRIMARY KEY(student_id,task_id)
        );');
        return $db;
    }
}
