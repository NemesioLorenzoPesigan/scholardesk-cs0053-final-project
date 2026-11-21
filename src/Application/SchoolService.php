<?php
declare(strict_types=1);
namespace ScholarDesk\Application;
use PDO;
use PDOException;
use InvalidArgumentException;
use OutOfBoundsException;
use ScholarDesk\Domain\Rules;

final class SchoolService
{
    public function __construct(private PDO $db) {}

    private function table(string $type): string
    {
        if (!in_array($type, ['students','tasks'], true)) throw new InvalidArgumentException('Unknown resource.');
        return $type;
    }

    public function all(string $type, string $query = ''): array
    {
        $table = $this->table($type);
        // The table and columns are selected from fixed names; user values use parameters.
        $columns = $type === 'students' ? ['student_number','full_name','email','course','section'] : ['title','subject','description'];
        $where = implode(' OR ', array_map(fn($c)=>"instr(lower($c), lower(?)) > 0", $columns));
        $stmt = $this->db->prepare("SELECT * FROM $table WHERE $where ORDER BY id DESC");
        $stmt->execute(array_fill(0,count($columns),$query));
        return $stmt->fetchAll();
    }

    public function get(string $type, int $id): array
    {
        $table = $this->table($type);
        $stmt = $this->db->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) throw new OutOfBoundsException('Record was not found.');
        return $row;
    }

    public function save(string $type, array $input, ?int $id = null): array
    {
        $table = $this->table($type);
        if ($id !== null) $this->get($type,$id);
        $data = $type === 'students' ? Rules::student($input) : Rules::task($input);
        $columns = array_keys($data);
        $sql = $id === null
            ? "INSERT INTO $table (" . implode(',',$columns) . ') VALUES (' . implode(',',array_fill(0,count($columns),'?')) . ')'
            : "UPDATE $table SET " . implode(',',array_map(fn($c)=>"$c = ?",$columns)) . ' WHERE id = ?';
        $values = array_values($data);
        if ($id !== null) $values[] = $id;
        try { $this->db->prepare($sql)->execute($values); }
        catch (PDOException $e) {
            if (str_contains($e->getMessage(),'UNIQUE constraint failed')) throw new InvalidArgumentException('Student number or email already exists.', 409);
            throw $e;
        }
        return $this->get($type,$id ?? (int)$this->db->lastInsertId());
    }

    public function delete(string $type, int $id): void
    {
        $table = $this->table($type);
        $this->get($type,$id);
        $this->db->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    }

    public function submit(int $studentId, int $taskId, string $status): array
    {
        $this->get('students',$studentId); $this->get('tasks',$taskId);
        if (!in_array($status,['submitted','pending'],true)) throw new InvalidArgumentException('Status must be submitted or pending.');
        if ($status === 'submitted') {
            // Repeated requests preserve the original submission timestamp.
            $this->db->prepare('INSERT OR IGNORE INTO submissions(student_id,task_id,submitted_at) VALUES(?,?,?)')
                ->execute([$studentId,$taskId,date('Y-m-d H:i:s')]);
        } else {
            $this->db->prepare('DELETE FROM submissions WHERE student_id=? AND task_id=?')->execute([$studentId,$taskId]);
        }
        $stmt = $this->db->prepare('SELECT submitted_at FROM submissions WHERE student_id=? AND task_id=?');
        $stmt->execute([$studentId,$taskId]);
        return ['student_id'=>$studentId,'task_id'=>$taskId,'status'=>$status,'submitted_at'=>$stmt->fetchColumn() ?: null];
    }

    public function submissions(string $query = '', string $filter = 'all'): array
    {
        if (!in_array($filter,['all','submitted','pending','overdue'],true)) throw new InvalidArgumentException('Unknown submission filter.');
        $rows = $this->db->query('SELECT s.id student_id,s.student_number,s.full_name,t.id task_id,t.title,t.subject,t.due_date,u.submitted_at
            FROM students s CROSS JOIN tasks t LEFT JOIN submissions u ON s.id=u.student_id AND t.id=u.task_id
            ORDER BY t.due_date,s.full_name')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $row['status'] = Rules::status($row['due_date'],$row['submitted_at'],date('Y-m-d'));
            $matches = $filter === 'all' || $row['status'] === $filter || ($filter === 'pending' && $row['status'] === 'overdue');
            if ($matches && ($query === '' || mb_stripos(implode(' ',array_map(fn($v)=>(string)$v,$row)),$query) !== false)) $result[] = $row;
        }
        return $result;
    }

    public function dashboard(): array
    {
        $students = (int)$this->db->query('SELECT COUNT(*) FROM students')->fetchColumn();
        $tasks = (int)$this->db->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
        $submitted = (int)$this->db->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM students CROSS JOIN tasks t LEFT JOIN submissions u ON students.id=u.student_id AND t.id=u.task_id WHERE t.due_date < ? AND u.student_id IS NULL');
        $stmt->execute([date('Y-m-d')]);
        $expected = $students * $tasks;
        return ['students'=>$students,'tasks'=>$tasks,'expected'=>$expected,'submitted'=>$submitted,'pending'=>$expected-$submitted,
            'overdue'=>(int)$stmt->fetchColumn(),'completion'=>$expected ? (int)round($submitted/$expected*100) : 0];
    }

    public function seed(): void
    {
        $this->db->beginTransaction();
        try {
            if ($this->dashboard()['students'] || $this->dashboard()['tasks']) throw new InvalidArgumentException('Demo data can only be loaded into an empty system.',409);
            $names = [['2026-0001','Alex Santos','alex'],['2026-0002','Jamie Cruz','jamie'],['2026-0003','Morgan Reyes','morgan']];
            foreach ($names as [$number,$name,$email]) $this->save('students',['student_number'=>$number,'full_name'=>$name,'email'=>"$email@example.test",'course'=>'BS Computer Science','year_level'=>2,'section'=>'SE31']);
            foreach ([['Unit testing activity','CS0053',2],['API laboratory report','CS0053',-2],['Design documentation','CS0053',5]] as [$title,$subject,$days]) {
                $this->save('tasks',['title'=>$title,'subject'=>$subject,'due_date'=>date('Y-m-d',strtotime("$days days")),'description'=>'Fictional sample task for the system demonstration.']);
            }
            $students=$this->all('students'); $tasks=$this->all('tasks');
            $this->submit((int)$students[0]['id'],(int)$tasks[0]['id'],'submitted');
            $this->submit((int)$students[1]['id'],(int)$tasks[0]['id'],'submitted');
            $this->submit((int)$students[0]['id'],(int)$tasks[1]['id'],'submitted');
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }
}
