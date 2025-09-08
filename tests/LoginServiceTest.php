<?php
use PHPUnit\Framework\TestCase;

// Load the database connection
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../src/LoginService.php';

class LoginServiceTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        // Use the global PDO from db.php
        global $pdo;
        $this->pdo = $pdo;

        // Clean up tables to avoid foreign key conflicts
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->pdo->exec("TRUNCATE TABLE advisor_sections");
        $this->pdo->exec("TRUNCATE TABLE advisors");
        $this->pdo->exec("TRUNCATE TABLE students");
        $this->pdo->exec("TRUNCATE TABLE coordinators");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Insert test users
        $passwordHash = password_hash('password123', PASSWORD_DEFAULT);

        $this->pdo->exec("
            INSERT INTO advisors (id, first_name, last_name, email, password, requires_password_change)
            VALUES (1, 'John', 'Doe', 'advisor@test.com', '$passwordHash', 0)
        ");

        $this->pdo->exec("
            INSERT INTO students (id, first_name, last_name, student_id, email, password, course, section, year_level, requires_password_change)
            VALUES (1, 'Jane', 'Smith', 'S123', 'student@test.com', '$passwordHash', 'CS', 'A1', 3, 0)
        ");

        $this->pdo->exec("
            INSERT INTO coordinators (id, first_name, last_name, coordinator_id, email, password)
            VALUES (1, 'Alice', 'Admin', 'C001', 'coordinator@test.com', '$passwordHash')
        ");
    }

    public function testAdvisorLoginSuccess()
    {
        $loginService = new LoginService($this->pdo);
        $result = $loginService->login('advisor@test.com', 'password123', 'advisor');
        $this->assertTrue($result['success']);
    }

    public function testAdvisorLoginWrongPassword()
    {
        $loginService = new LoginService($this->pdo);
        $result = $loginService->login('advisor@test.com', 'wrongpass', 'advisor');
        $this->assertFalse($result['success']);
        $this->assertEquals('Incorrect password.', $result['message']);
    }

    public function testStudentLoginSuccess()
    {
        $loginService = new LoginService($this->pdo);
        $result = $loginService->login('student@test.com', 'password123', 'student');
        $this->assertTrue($result['success']);
    }

    public function testCoordinatorLoginSuccess()
    {
        $loginService = new LoginService($this->pdo);
        $result = $loginService->login('coordinator@test.com', 'password123', 'coordinator');
        $this->assertTrue($result['success']);
    }
}
