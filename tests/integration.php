<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
use ScholarDesk\Application\SchoolService;
use ScholarDesk\Infrastructure\Database;

$count=0;
function verify(string $name, bool $condition): void { global $count; if (!$condition) throw new RuntimeException($name); echo "PASS | $name\n"; $count++; }
function expectError(string $name, callable $action, string $class): void {
    try {$action();} catch(Throwable $e) {verify($name,$e instanceof $class);return;}
    throw new RuntimeException("$name: expected error");
}
try {
    $db=Database::connect(':memory:'); $service=new SchoolService($db);
    $input=['student_number'=>'2026-TEST','full_name'=>'Casey Test','email'=>'casey@example.test','course'=>'BSCS','year_level'=>2,'section'=>'SE31'];
    verify('Empty dashboard has zero completion',$service->dashboard()['completion']===0);
    $student=$service->save('students',$input);
    verify('Create and retrieve student',$service->get('students',(int)$student['id'])['full_name']==='Casey Test');
    verify('Search finds student number',count($service->all('students','2026-TEST'))===1);
    verify('Search treats SQL characters as literal input',count($service->all('students',"' OR 1=1 --"))===0);
    expectError('Duplicate number rejected',fn()=>$service->save('students',[...$input,'email'=>'other@example.test']),InvalidArgumentException::class);
    expectError('Duplicate email rejected',fn()=>$service->save('students',[...$input,'student_number'=>'another']),InvalidArgumentException::class);
    $student=$service->save('students',[...$input,'full_name'=>'Casey Updated'],(int)$student['id']);
    verify('Update persists student changes',$student['full_name']==='Casey Updated');
    $task=$service->save('tasks',['title'=>'Past task','subject'=>'CS0053','due_date'=>date('Y-m-d',strtotime('-1 day'))]);
    verify('Student-task assignment is automatically pending',$service->dashboard()['pending']===1);
    verify('Past pending deadline is overdue',$service->submissions()[0]['status']==='overdue');
    $service->submit((int)$student['id'],(int)$task['id'],'submitted');
    verify('Submit updates completion and clears overdue',$service->dashboard()['completion']===100 && $service->dashboard()['overdue']===0);
    $service->submit((int)$student['id'],(int)$task['id'],'submitted');
    verify('Repeated submit does not duplicate records',$service->dashboard()['submitted']===1);
    verify('Status filter selects submitted work',count($service->submissions('','submitted'))===1);
    $service->submit((int)$student['id'],(int)$task['id'],'pending');
    verify('Mark pending reverses submission',$service->dashboard()['submitted']===0);
    expectError('Missing student cannot submit',fn()=>$service->submit(999,(int)$task['id'],'submitted'),OutOfBoundsException::class);
    expectError('Unknown submission status rejected',fn()=>$service->submit((int)$student['id'],(int)$task['id'],'unknown'),InvalidArgumentException::class);
    $task=$service->save('tasks',['title'=>'Edited task','subject'=>'CS0053','due_date'=>date('Y-m-d')],(int)$task['id']);
    verify('Task update and search work',count($service->all('tasks','Edited task'))===1);
    $service->submit((int)$student['id'],(int)$task['id'],'submitted');
    $service->delete('tasks',(int)$task['id']);
    verify('Deleting a task cascades submissions',$service->dashboard()['submitted']===0);
    $task=$service->save('tasks',['title'=>'Another task','subject'=>'CS0053','due_date'=>date('Y-m-d')]);
    $service->submit((int)$student['id'],(int)$task['id'],'submitted');
    $service->delete('students',(int)$student['id']);
    verify('Deleting a student cascades submissions',$service->dashboard()['submitted']===0);
    expectError('Missing record returns not found',fn()=>$service->get('students',999),OutOfBoundsException::class);
    $db2=Database::connect(':memory:');$demo=new SchoolService($db2);$demo->seed();
    verify('Demo creates three students and three tasks',$demo->dashboard()['expected']===9);
    expectError('Demo refuses to overwrite populated database',fn()=>$demo->seed(),InvalidArgumentException::class);
    verify('Rejected demo leaves database intact',$demo->dashboard()['expected']===9);
    // A second connection verifies that records survive database connection closure.
    $path=tempnam(sys_get_temp_dir(),'sd-test-');
    try {
        $disk=new SchoolService(Database::connect($path));$disk->save('students',$input);unset($disk);
        $reopened=new SchoolService(Database::connect($path));
        verify('Records persist across database reconnections',count($reopened->all('students'))===1);unset($reopened);
    } finally {unlink($path);}
    echo "\n$count passed; 0 failed\n";
} catch(Throwable $e) {echo "FAIL | {$e->getMessage()}\n";exit(1);}
