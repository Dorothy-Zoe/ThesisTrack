<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../db/db.php';

class LoginServiceTest extends TestCase
{
    protected function setUp(): void
    {
        global $pdo;

        // Disable foreign key checks to truncate safely
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        // Clean tables
        $pdo->exec('TRUNCATE TABLE advisor_sections');
        $pdo->exec('TRUNCATE TABLE advisors');
        $pdo->exec('TRUNCATE TABLE students');
        $pdo->exec('TRUNCATE TABLE coordinators');

        // Re-enable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        // Insert test advisor
        $stmt = $pdo->prepare("INSERT INTO advisors (id, first_name, last_name, email, password, requires_password_change) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, 'John', 'Doe', 'john@cict.edu', password_hash('pass123', PASSWORD_DEFAULT), 0]);

        // Insert advisor_sections to avoid foreign key violation
        $stmt = $pdo->prepare("INSERT INTO advisor_sections (advisor_id, section_name) VALUES (?, ?)");
        $stmt->execute([1, 'Section A']);

        // Insert test student
        $stmt = $pdo->prepare("INSERT INTO students (id, student_id, first_name, last_name, email, password, course, section, year_level, requires_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, 'S1001', 'Jane', 'Smith', 'jane@student.com', password_hash('student123', PASSWORD_DEFAULT), 'BSCS', 'A', 3, 0]);

        // Insert test coordinator
        $stmt = $pdo->prepare("INSERT INTO coordinators (id, coordinator_id, first_name, last_name, email, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, 'C1001', 'Alice', 'Cooper', 'alice@cict.edu', password_hash('coord123', PASSWORD_DEFAULT)]);
    }

    protected function tearDown(): void
    {
        global $pdo;

        // Clean up tables
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('TRUNCATE TABLE advisor_sections');
        $pdo->exec('TRUNCATE TABLE advisors');
        $pdo->exec('TRUNCATE TABLE students');
        $pdo->exec('TRUNCATE TABLE coordinators');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    public function testAdvisorLoginSuccess()
    {
        global $pdo;

        // Simulate login
        $stmt = $pdo->prepare("SELECT * FROM advisors WHERE email = ?");
        $stmt->execute(['john@cict.edu']);
        $advisor = $stmt->fetch();

        $this->assertNotFalse($advisor, "Advisor should exist in database");
        $this->assertTrue(password_verify('pass123', $advisor['password']), "Password should match");
    }

    public function testAdvisorLoginWrongPassword()
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM advisors WHERE email = ?");
        $stmt->execute(['john@cict.edu']);
        $advisor = $stmt->fetch();

        $this->assertFalse(password_verify('wrongpass', $advisor['password']), "Wrong password should fail");
    }

    public function testStudentLoginSuccess()
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
        $stmt->execute(['jane@student.com']);
        $student = $stmt->fetch();

        $this->assertNotFalse($student, "Student should exist in database");
        $this->assertTrue(password_verify('student123', $student['password']), "Password should match");
    }

    public function testCoordinatorLoginSuccess()
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM coordinators WHERE email = ?");
        $stmt->execute(['alice@cict.edu']);
        $coordinator = $stmt->fetch();

        $this->assertNotFalse($coordinator, "Coordinator should exist in database");
        $this->assertTrue(password_verify('coord123', $coordinator['password']), "Password should match");
    }
}
