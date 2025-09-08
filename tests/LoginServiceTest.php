<?php
use PHPUnit\Framework\TestCase;

// Load your database connection
require_once __DIR__ . '/../db/db.php';

class LoginServiceTest extends TestCase
{
    protected $pdo;

    protected function setUp(): void
    {
        // Use the PDO connection from db.php
        global $pdo;
        $this->pdo = $pdo;

        // Clean up tables before each test
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $this->pdo->exec("TRUNCATE TABLE advisors");
        $this->pdo->exec("TRUNCATE TABLE students");
        $this->pdo->exec("TRUNCATE TABLE coordinators");
        $this->pdo->exec("TRUNCATE TABLE advisor_sections");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        // Insert sample Advisor
        $stmt = $this->pdo->prepare("INSERT INTO advisors (first_name, last_name, email, password, requires_password_change) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['John', 'Doe', 'advisor@test.com', password_hash('advisor123', PASSWORD_DEFAULT), 0]);

        // Insert sample Student
        $stmt = $this->pdo->prepare("INSERT INTO students (first_name, last_name, email, password, course, section, year_level, requires_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Jane', 'Smith', 'student@test.com', password_hash('student123', PASSWORD_DEFAULT), 'BSCS', 'A', 3, 0]);

        // Insert sample Coordinator
        $stmt = $this->pdo->prepare("INSERT INTO coordinators (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Alice', 'Johnson', 'coordinator@test.com', password_hash('coord123', PASSWORD_DEFAULT)]);
    }

    public function testAdvisorLoginSuccess()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM advisors WHERE email = ?");
        $stmt->execute(['advisor@test.com']);
        $advisor = $stmt->fetch();

        $this->assertNotNull($advisor);
        $this->assertTrue(password_verify('advisor123', $advisor['password']));
    }

    public function testAdvisorLoginWrongPassword()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM advisors WHERE email = ?");
        $stmt->execute(['advisor@test.com']);
        $advisor = $stmt->fetch();

        $this->assertNotNull($advisor);
        $this->assertFalse(password_verify('wrongpass', $advisor['password']));
    }

    public function testStudentLoginSuccess()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE email = ?");
        $stmt->execute(['student@test.com']);
        $student = $stmt->fetch();

        $this->assertNotNull($student);
        $this->assertTrue(password_verify('student123', $student['password']));
    }

    public function testCoordinatorLoginSuccess()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM coordinators WHERE email = ?");
        $stmt->execute(['coordinator@test.com']);
        $coordinator = $stmt->fetch();

        $this->assertNotNull($coordinator);
        $this->assertTrue(password_verify('coord123', $coordinator['password']));
    }

    protected function tearDown(): void
    {
        // Clean up database after each test
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $this->pdo->exec("TRUNCATE TABLE advisors");
        $this->pdo->exec("TRUNCATE TABLE students");
        $this->pdo->exec("TRUNCATE TABLE coordinators");
        $this->pdo->exec("TRUNCATE TABLE advisor_sections");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    }
}
