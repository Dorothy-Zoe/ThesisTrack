<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../src/LoginService.php';

class LoginServiceTest extends TestCase
{
    private PDO $pdo;
    private LoginService $loginService;

    protected function setUp(): void
    {
        // Get PDO instance directly from db.php
        $pdo = require __DIR__ . '/../db/db.php';
        
        // Check if connection was successful
        if ($pdo === null) {
            $this->markTestSkipped('Database connection failed');
        }
        
        $this->pdo = $pdo;

        // Start a transaction so changes rollback after each test
        $this->pdo->beginTransaction();

        // Pass the PDO instance to LoginService constructor
        $this->loginService = new LoginService($this->pdo);
    }

    protected function tearDown(): void
    {
        // Rollback changes if transaction is active
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testAdvisorLoginSuccess()
    {
        // Skip test if no database connection
        if (!isset($this->pdo)) {
            $this->markTestSkipped('No database connection');
        }

        // Insert test advisor
        $hashed = password_hash('password123', PASSWORD_DEFAULT);
        $this->pdo->exec("INSERT INTO advisors (first_name,last_name,email,password) VALUES ('John','Doe','advisor@test.com','$hashed')");

        $result = $this->loginService->loginAdvisor('advisor@test.com', 'password123');
        $this->assertNotFalse($result);
        $this->assertEquals('John', $result['first_name']);
    }

    public function testAdvisorLoginWrongPassword()
    {
        if (!isset($this->pdo)) {
            $this->markTestSkipped('No database connection');
        }

        $hashed = password_hash('password123', PASSWORD_DEFAULT);
        $this->pdo->exec("INSERT INTO advisors (first_name,last_name,email,password) VALUES ('Jane','Doe','advisor2@test.com','$hashed')");

        $result = $this->loginService->loginAdvisor('advisor2@test.com', 'wrongpass');
        $this->assertFalse($result);
    }

    public function testStudentLoginSuccess()
    {
        if (!isset($this->pdo)) {
            $this->markTestSkipped('No database connection');
        }

        $hashed = password_hash('studentpass', PASSWORD_DEFAULT);
        $this->pdo->exec("INSERT INTO students (first_name,last_name,email,password) VALUES ('Alice','Smith','student@test.com','$hashed')");

        $result = $this->loginService->loginStudent('student@test.com', 'studentpass');
        $this->assertNotFalse($result);
        $this->assertEquals('Alice', $result['first_name']);
    }

    public function testCoordinatorLoginSuccess()
    {
        if (!isset($this->pdo)) {
            $this->markTestSkipped('No database connection');
        }

        $hashed = password_hash('coordpass', PASSWORD_DEFAULT);
        $this->pdo->exec("INSERT INTO coordinators (first_name,last_name,email,password) VALUES ('Bob','Jones','coord@test.com','$hashed')");

        $result = $this->loginService->loginCoordinator('coord@test.com', 'coordpass');
        $this->assertNotFalse($result);
        $this->assertEquals('Bob', $result['first_name']);
    }
}
?>