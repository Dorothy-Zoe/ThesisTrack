<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/LoginService.php';

class LoginServiceTest extends TestCase {
    private $pdo;
    private $service;

    protected function setUp(): void {
        $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=thesis_track", "root", "root");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->service = new LoginService($this->pdo);

        // Seed test users
        $hashed = password_hash("password123", PASSWORD_DEFAULT);

        $this->pdo->exec("DELETE FROM advisors");
        $this->pdo->exec("DELETE FROM students");
        $this->pdo->exec("DELETE FROM coordinators");

        $this->pdo->exec("INSERT INTO advisors (id, first_name, last_name, email, password, requires_password_change) 
                          VALUES (1, 'John', 'Doe', 'advisor@test.com', '$hashed', 0)");

        $this->pdo->exec("INSERT INTO students (id, student_id, first_name, last_name, email, password, course, section, year_level, requires_password_change) 
                          VALUES (1, 'S123', 'Jane', 'Smith', 'student@test.com', '$hashed', 'BSCS', 'A', 3, 0)");

        $this->pdo->exec("INSERT INTO coordinators (id, coordinator_id, first_name, last_name, email, password) 
                          VALUES (1, 'C001', 'Alice', 'Brown', 'coordinator@test.com', '$hashed')");
    }

    public function testAdvisorLoginSuccess() {
        $result = $this->service->loginAdvisor("advisor@test.com", "password123");
        $this->assertIsArray($result);
        $this->assertEquals("advisor", $result["role"]);
    }

    public function testAdvisorLoginWrongPassword() {
        $result = $this->service->loginAdvisor("advisor@test.com", "wrongpass");
        $this->assertEquals("Incorrect password", $result);
    }

    public function testStudentLoginSuccess() {
        $result = $this->service->loginStudent("student@test.com", "password123");
        $this->assertIsArray($result);
        $this->assertEquals("student", $result["role"]);
    }

    public function testCoordinatorLoginSuccess() {
        $result = $this->service->loginCoordinator("coordinator@test.com", "password123");
        $this->assertIsArray($result);
        $this->assertEquals("coordinator", $result["role"]);
    }
}
