<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
use ScholarDesk\Domain\Rules;

$failed = 0; $passed = 0;
function check(string $name, callable $test): void {
    global $failed, $passed;
    try { $test(); echo "PASS | $name\n"; $passed++; }
    catch (Throwable $e) { echo "FAIL | $name | {$e->getMessage()}\n"; $failed++; }
}
function same(mixed $expected, mixed $actual): void {
    if ($expected !== $actual) throw new RuntimeException('Expected ' . json_encode($expected) . ', got ' . json_encode($actual));
}
function rejects(callable $action): void {
    try { $action(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected validation error');
}
$student = ['student_number'=>' 2026-0001 ', 'full_name'=>' Alex Santos ', 'email'=>'alex@example.test', 'course'=>'BSCS', 'year_level'=>2, 'section'=>'SE31'];
check('Student fields are trimmed', fn()=>same('Alex Santos', Rules::student($student)['full_name']));
check('Student number is required', fn()=>rejects(fn()=>Rules::student([...$student,'student_number'=>' '])));
check('Invalid email is rejected', fn()=>rejects(fn()=>Rules::student([...$student,'email'=>'invalid'])));
check('Year must be 1 through 6', fn()=>rejects(fn()=>Rules::student([...$student,'year_level'=>7])));
check('Fractional year is rejected', fn()=>rejects(fn()=>Rules::student([...$student,'year_level'=>1.5])));
check('Required fields reject arrays', fn()=>rejects(fn()=>Rules::student([...$student,'full_name'=>['x']])));
check('Task date must be a real date', fn()=>rejects(fn()=>Rules::task(['title'=>'Quiz','subject'=>'CS0053','due_date'=>'2026-02-30'])));
check('Task accepts a valid leap day', fn()=>same('2028-02-29', Rules::task(['title'=>'Quiz','subject'=>'CS0053','due_date'=>'2028-02-29'])['due_date']));
check('Pending is overdue after deadline', fn()=>same('overdue', Rules::status('2026-11-20', null, '2026-11-21')));
check('Due today remains pending', fn()=>same('pending', Rules::status('2026-11-21', null, '2026-11-21')));
check('Submitted work is submitted after deadline', fn()=>same('submitted', Rules::status('2026-11-20', '2026-11-21 09:00:00', '2026-11-21')));
echo "\n$passed passed; $failed failed\n";
exit($failed ? 1 : 0);
