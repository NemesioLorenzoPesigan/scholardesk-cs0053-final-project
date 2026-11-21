<?php
declare(strict_types=1);
namespace ScholarDesk\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class Rules
{
    private static function text(array $input, string $key, int $max, bool $required = true): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value)) throw new InvalidArgumentException("$key must be text.");
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $max) {
            throw new InvalidArgumentException("$key is required and must be at most $max characters.");
        }
        return $value;
    }

    public static function student(array $input): array
    {
        $data = [];
        foreach (['student_number'=>30,'full_name'=>100,'email'=>150,'course'=>80,'section'=>30] as $key=>$max) {
            $data[$key] = self::text($input, $key, $max);
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
        $year = filter_var($input['year_level'] ?? null, FILTER_VALIDATE_INT);
        if ($year === false || $year < 1 || $year > 6) throw new InvalidArgumentException('Year level must be a whole number from 1 to 6.');
        $data['year_level'] = $year;
        return $data;
    }

    public static function task(array $input): array
    {
        $data = ['title'=>self::text($input,'title',120), 'subject'=>self::text($input,'subject',80),
            'due_date'=>self::text($input,'due_date',10), 'description'=>self::text($input,'description',2000,false)];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['due_date']);
        if (!$date || $date->format('Y-m-d') !== $data['due_date']) throw new InvalidArgumentException('Enter a real deadline in YYYY-MM-DD format.');
        return $data;
    }

    public static function status(string $dueDate, ?string $submittedAt, string $today): string
    {
        if ($submittedAt !== null) return 'submitted';
        return $dueDate < $today ? 'overdue' : 'pending';
    }
}
